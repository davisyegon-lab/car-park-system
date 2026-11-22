<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

final class RequestError extends RuntimeException {}

function fail(int $status, string $message): void
{
    throw new RequestError($message, $status);
}

function field(array $data, string $key, int $limit, bool $required = true): string
{
    $value = array_key_exists($key, $data) ? $data[$key] : '';
    if (!is_string($value) || preg_match('/\p{Cc}/u', $value) !== 0
        || strlen($value = trim($value)) > $limit || ($required && $value === '')) {
        fail(422, 'Check the ' . str_replace('_', ' ', $key) . ' field.');
    }
    return $value;
}

function integer(array $data, string $key, int $max = 4294967295): int
{
    $value = $data[$key] ?? null;
    $number = is_int($value) || (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value))
        ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]])
        : false;
    if ($number === false) {
        fail(422, 'Choose a valid ' . str_replace('_', ' ', $key) . '.');
    }
    return $number;
}

function flag(array $data, string $key, ?bool $default = null): bool
{
    $value = array_key_exists($key, $data) ? $data[$key] : $default;
    if (!is_bool($value)) {
        fail(422, 'Check the ' . str_replace('_', ' ', $key) . ' field.');
    }
    return $value;
}

function allowFields(array $data, array $allowed): void
{
    if (array_diff(array_keys($data), $allowed)) {
        fail(422, 'This action received unexpected fields.');
    }
}

function writeBody(string $action): array
{
    $mediaType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '', 2)[0]));
    if ($mediaType !== 'application/json') {
        fail(415, 'Send the request as application/json.');
    }
    $body = file_get_contents('php://input', false, null, 0, 16385);
    if ($body === false) {
        fail(400, 'Could not read the request body.');
    }
    if (strlen($body) > 16384) {
        fail(413, 'This request is too large.');
    }
    $decoded = json_decode($body, false, 32);
    if (!$decoded instanceof stdClass) {
        fail(400, 'Send a JSON object in the request body.');
    }
    $data = (array) $decoded;
    if ($action !== 'checkin') {
        allowFields($data, match ($action) {
            'login' => ['email', 'password'],
            'logout' => [],
            'zone_create' => ['name', 'description', 'capacity', 'active'],
            'zone_update' => ['id', 'name', 'description', 'capacity', 'active'],
            'zone_delete' => ['id'],
            'checkout' => ['id', 'expected_zone_id'],
            'correct_checkout' => ['id', 'expected_zone_id', 'reason'],
            'transfer' => ['id', 'expected_zone_id', 'zone_id', 'reason'],
            'account_status' => ['id', 'active'],
            'bay_occupy', 'bay_release' => ['zone_id', 'bay_number', 'current'],
            'bay_manage' => ['zone_id', 'bay_number', 'current', 'status', 'booking_label'],
        });
    }
    return $data;
}

function login(array $data): array
{
    $email = strtolower(field($data, 'email', 150));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail(422, 'Enter a valid email address.');
    }
    $password = $data['password'] ?? null;
    if (!is_string($password) || $password === '' || strlen($password) > 72 || str_contains($password, "\0")) {
        fail(422, 'Check the password field.');
    }
    $key = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $now = time();
    $result = transaction(function () use ($email, $password, $key, $now): array {
        sql('INSERT IGNORE INTO login_attempts (scope_key, window_started) VALUES (?, ?)', [$key, $now]);
        $rate = sql('SELECT attempts, window_started FROM login_attempts WHERE scope_key = ? FOR UPDATE', [$key])->fetch();
        $attempts = $now - (int) $rate['window_started'] < 900 ? (int) $rate['attempts'] : 0;
        if ($attempts >= 8) {
            return ['status' => 429, 'message' => 'Too many failed attempts. Try again in 15 minutes.'];
        }
        $user = parkingAccount($email);
        $fallback = '$2y$10$EP645PfCvUHKb2qdOi7sIuztPloz9g8zDs6FE7p/g0nKY4U3qfulm';
        if (!password_verify($password, $user['password_hash'] ?? $fallback) || !$user) {
            sql('UPDATE login_attempts SET attempts = ?, window_started = ? WHERE scope_key = ?',
                [$attempts + 1, $attempts ? $rate['window_started'] : $now, $key]);
            audit(null, 'login_failed', 'authentication', null);
            return ['status' => 401, 'message' => 'Email or password is incorrect.'];
        }
        if (!parkingRoleAllowed($user['role'])) {
            return ['status' => 403, 'message' => 'Use the public map to try the sensor demo. Admin and security can sign in here.'];
        }
        audit((int) $user['id'], 'login', 'authentication', (int) $user['id']);
        return ['user' => $user];
    });
    if (isset($result['status'])) {
        fail($result['status'], $result['message']);
    }
    $user = $result['user'];
    rotateParkingSession();
    $signedIn = time();
    $_SESSION += ['user_id' => $user['id'], 'role' => $user['role'],
        'authorization_version' => (string) $user['authorization_version'],
        'signed_in_at' => $signedIn, 'last_activity' => $signedIn];
    return ['user' => publicAccount($user), 'csrf' => $_SESSION['csrf']];
}

function zones(bool $includeInactive = false, ?array $viewer = null): array
{
    $rows = sql('SELECT z.id, z.name, z.description, z.capacity, z.active,
        COALESCE(g.total, 0) AS gate_occupied, b.bay_number, b.status, b.student_id, b.booking_label
        FROM parking_zones z
        LEFT JOIN (SELECT zone_id, COUNT(*) AS total FROM parking_sessions WHERE checkout_at IS NULL GROUP BY zone_id) g
            ON g.zone_id = z.id
        LEFT JOIN parking_bays b ON b.zone_id = z.id
        WHERE (? = 1 OR z.active = 1) ORDER BY z.id, b.bay_number', [(int) $includeInactive]);
    $zones = [];
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $zones[$id] ??= array_diff_key($row, array_flip(['bay_number', 'status', 'student_id', 'booking_label'])) + [
            'occupied' => (int) $row['gate_occupied'],
            'available' => (int) $row['capacity'] - (int) $row['gate_occupied'],
            'bays' => [],
        ];
        if ($row['bay_number'] !== null) {
            $zones[$id]['available']--;
            $zones[$id]['occupied'] += (int) ($row['status'] === 'occupied');
            $bay = ['number' => (int) $row['bay_number'], 'status' => $row['status'],
                'mine' => $row['student_id'] !== null && (int) $row['student_id'] === (int) ($viewer['id'] ?? 0)];
            if (($viewer['role'] ?? '') === 'admin' && $row['booking_label'] !== null) {
                $bay['label'] = $row['booking_label'];
            }
            $zones[$id]['bays'][] = $bay;
        }
    }
    return array_values($zones);
}

function dashboard(?array $viewer = null): array
{
    $list = zones(false, $viewer);
    $capacity = array_sum(array_column($list, 'capacity'));
    $occupied = array_sum(array_column($list, 'occupied'));
    return ['zones' => $list, 'capacity' => $capacity, 'occupied' => $occupied,
        'available' => array_sum(array_column($list, 'available')),
        'percentage' => $capacity ? round($occupied * 100 / $capacity) : 0];
}

function lockZone(int $id): array
{
    $zone = sql('SELECT * FROM parking_zones WHERE id = ? FOR UPDATE', [$id])->fetch();
    if (!$zone) {
        fail(404, 'That parking zone no longer exists.');
    }
    return $zone;
}

function occupancy(int $zone): int
{
    return (int) sql('SELECT COUNT(*) FROM parking_sessions WHERE zone_id = ? AND checkout_at IS NULL', [$zone])->fetchColumn();
}

function requireSpace(array $zone): void
{
    $id = (int) $zone['id'];
    $held = (int) sql('SELECT COUNT(*) FROM parking_bays WHERE zone_id = ?', [$id])->fetchColumn();
    if (!$zone['active'] || occupancy($id) + $held >= (int) $zone['capacity']) {
        fail(409, 'The selected zone is full or inactive.');
    }
}

function changeBay(string $action, array $data, int $actor): array
{
    $zoneId = integer($data, 'zone_id');
    $number = integer($data, 'bay_number', 9999);
    $current = field($data, 'current', 16);
    $status = match ($action) {
        'bay_occupy' => 'occupied',
        'bay_release' => 'available',
        'bay_manage' => field($data, 'status', 16),
    };
    $adminStates = ['available', 'booked', 'unavailable'];
    $expected = match ($action) {
        'bay_occupy' => ['available'],
        'bay_release' => ['occupied'],
        'bay_manage' => $adminStates,
    };
    if (!in_array($current, $expected, true) || ($action === 'bay_manage' && !in_array($status, $adminStates, true))) {
        fail(422, 'Choose a valid bay status.');
    }
    $booking = field($data, 'booking_label', 100, $status === 'booked');
    $label = $status === 'booked' ? $booking : null;
    return transaction(function () use ($action, $actor, $zoneId, $number, $current, $status, $label): array {
        $zone = lockZone($zoneId);
        if (!$zone['active'] || $number > (int) $zone['capacity']) {
            fail(409, 'That bay is no longer in an active parking zone.');
        }
        $bay = sql('SELECT status, student_id FROM parking_bays WHERE zone_id = ? AND bay_number = ?', [$zoneId, $number])->fetch();
        $previous = $bay['status'] ?? 'available';
        if (!$bay) {
            // Gate sessions only identify a zone. Map them to the first bays without explicit state.
            $earlier = (int) sql('SELECT COUNT(*) FROM parking_bays WHERE zone_id = ? AND bay_number < ?',
                [$zoneId, $number])->fetchColumn();
            if ($number - $earlier <= occupancy($zoneId)) {
                $previous = 'occupied';
            }
        }
        if ($action === 'bay_release' && (int) ($bay['student_id'] ?? 0) !== $actor) {
            fail(409, 'You can only release the bay you occupied.');
        }
        if ($previous !== $current || ($action === 'bay_occupy' && $previous !== 'available')) {
            fail(409, 'That bay has changed. Refresh and choose again.');
        }
        if ($status === 'available') {
            sql('DELETE FROM parking_bays WHERE zone_id = ? AND bay_number = ?', [$zoneId, $number]);
        } elseif ($bay) {
            sql('UPDATE parking_bays SET status = ?, booking_label = ? WHERE zone_id = ? AND bay_number = ?',
                [$status, $label, $zoneId, $number]);
        } else {
            sql('INSERT INTO parking_bays (zone_id, bay_number, status, student_id, booking_label) VALUES (?,?,?,?,?)',
                [$zoneId, $number, $status, $status === 'occupied' ? $actor : null, $label]);
        }
        audit($actor, $action, 'parking_bay', $zoneId,
            ['bay_number' => $number, 'previous_status' => $previous, 'status' => $status, 'booking_label' => $label]);
        return ['status' => $status];
    });
}

function changeZone(string $action, array $data, int $actor): array
{
    return transaction(function () use ($action, $data, $actor): array {
        $id = $action === 'zone_create' ? null : integer($data, 'id');
        $zone = $id === null ? null : lockZone($id);
        if ($action === 'zone_delete') {
            if (sql('SELECT 1 FROM parking_sessions WHERE zone_id = ? LIMIT 1', [$id])->fetchColumn()) {
                fail(409, 'This zone has parking history. Deactivate it after all vehicles leave.');
            }
            if (sql('SELECT 1 FROM parking_bays WHERE zone_id = ? LIMIT 1', [$id])->fetchColumn()) {
                fail(409, 'Release the occupied, booked and unavailable bays before deleting this zone.');
            }
            sql('DELETE FROM parking_zones WHERE id = ?', [$id]);
            audit($actor, 'zone_deleted', 'zone', $id, ['name' => $zone['name']]);
            return ['id' => $id];
        }
        $name = field($data, 'name', 80);
        $description = field($data, 'description', 250, false);
        $capacity = integer($data, 'capacity', 9999);
        $active = flag($data, 'active', $zone === null || (bool) $zone['active']);
        if ($zone) {
            $occupied = occupancy($id);
            $bays = sql('SELECT COUNT(*) AS total, MAX(bay_number) AS highest FROM parking_bays WHERE zone_id = ?', [$id])->fetch();
            $used = $occupied + (int) $bays['total'];
            if ($capacity < max($used, (int) $bays['highest']) || (!$active && $used > 0)) {
                fail(409, 'Capacity must cover occupied and held bays. Clear all bays before deactivation.');
            }
            sql('UPDATE parking_zones SET name = ?, description = ?, capacity = ?, active = ? WHERE id = ?',
                [$name, $description, $capacity, (int) $active, $id]);
            audit($actor, !$active && $zone['active'] ? 'zone_deactivated' : 'zone_updated', 'zone', $id,
                ['previous_capacity' => (int) $zone['capacity'], 'capacity' => $capacity, 'active' => $active]);
        } else {
            sql('INSERT INTO parking_zones (name, description, capacity, active) VALUES (?, ?, ?, ?)',
                [$name, $description, $capacity, (int) $active]);
            $id = (int) db()->lastInsertId();
            audit($actor, 'zone_created', 'zone', $id, ['name' => $name, 'capacity' => $capacity]);
        }
        return ['id' => $id];
    });
}

function amsUsers(): array
{
    $query = field($_GET, 'q', 80, false);
    return sql("SELECT u.id, m.institutional_id, m.name, m.category
        FROM mock_ams m JOIN users u ON u.ams_id = m.id AND u.role = m.category
        WHERE m.active = 1 AND u.active = 1
        AND (LOCATE(?, m.name) > 0 OR LOCATE(?, m.institutional_id) > 0 OR ? = '')
        ORDER BY m.name LIMIT 30", [$query, $query, $query])->fetchAll();
}

function checkin(array $data, int $actor): array
{
    $zoneId = integer($data, 'zone_id');
    $plate = strtoupper(preg_replace('/\s+/', '', field($data, 'vehicle_plate', 32)));
    if (!preg_match('/^[A-Z0-9][A-Z0-9-]{2,15}$/D', $plate)) {
        fail(422, 'Enter a vehicle registration of 3–16 letters, digits or hyphens.');
    }
    $kind = field($data, 'kind', 20);
    $driverFields = match ($kind) {
        'visitor' => ['name', 'purpose', 'destination', 'id_inspected', 'id_retained'],
        'institutional' => ['user_id'],
        default => fail(422, 'Choose a valid driver type.'),
    };
    allowFields($data, ['kind', 'zone_id', 'vehicle_plate', ...$driverFields]);
    $userId = $kind === 'institutional' ? integer($data, 'user_id') : null;
    $visit = null;
    if ($kind === 'visitor') {
        $inspected = flag($data, 'id_inspected', false);
        $retained = flag($data, 'id_retained', false);
        if ($retained && !$inspected) {
            fail(422, 'Confirm the physical ID was inspected before marking it retained.');
        }
        $visit = [field($data, 'name', 100), field($data, 'purpose', 120),
            field($data, 'destination', 100), (int) $inspected, (int) $retained];
    }
    return transaction(function () use ($actor, $plate, $zoneId, $kind, $userId, $visit): array {
        $zone = lockZone($zoneId);
        if (sql('SELECT 1 FROM parking_sessions WHERE active_plate = ?', [$plate])->fetchColumn()) {
            fail(409, 'That vehicle already has an active parking session. Find it in Active sessions.');
        }
        requireSpace($zone);
        $visitorId = null;
        if ($visit !== null) {
            sql('INSERT INTO visitors (name, purpose, destination, id_inspected, id_retained) VALUES (?,?,?,?,?)', $visit);
            $visitorId = (int) db()->lastInsertId();
        } else {
            if (!sql("SELECT 1 FROM users u JOIN mock_ams m ON m.id = u.ams_id AND m.category = u.role
                WHERE u.id = ? AND u.active = 1 AND m.active = 1", [$userId])->fetchColumn()) {
                fail(422, 'Select an active student or staff member from the simulated AMS.');
            }
        }
        sql('INSERT INTO parking_sessions (user_id, visitor_id, vehicle_plate, zone_id) VALUES (?,?,?,?)',
            [$userId, $visitorId, $plate, $zoneId]);
        $id = (int) db()->lastInsertId();
        audit($actor, 'vehicle_entry', 'parking_session', $id, ['zone_id' => $zoneId, 'driver_type' => $kind]);
        return ['id' => $id, 'zone' => $zone['name']];
    });
}

function changeSession(string $action, array $data, int $actor): array
{
    $id = integer($data, 'id');
    $reason = $action === 'checkout' ? '' : field($data, 'reason', 250);
    $expectedZone = array_key_exists('expected_zone_id', $data) ? integer($data, 'expected_zone_id') : null;
    $target = $action === 'transfer' ? integer($data, 'zone_id') : null;
    return transaction(function () use ($action, $actor, $id, $reason, $expectedZone, $target): array {
        $session = sql('SELECT * FROM parking_sessions WHERE id = ? FOR UPDATE', [$id])->fetch();
        if (!$session || $session['checkout_at'] !== null) {
            fail(409, 'This parking session is no longer active.');
        }
        $source = (int) $session['zone_id'];
        if ($expectedZone !== null && $expectedZone !== $source) {
            fail(409, 'This vehicle moved to another zone. Review the refreshed active session before retrying.');
        }
        if ($target !== null) {
            if ($source === $target) {
                fail(422, 'Choose a different parking zone.');
            }
            $first = lockZone(min($source, $target));
            $second = lockZone(max($source, $target));
            $targetZone = (int) $first['id'] === $target ? $first : $second;
            $sourceZone = (int) $first['id'] === $source ? $first : $second;
            requireSpace($targetZone);
            sql('UPDATE parking_sessions SET zone_id = ? WHERE id = ?', [$target, $id]);
            audit($actor, 'zone_transfer', 'parking_session', $id, ['from_zone_id' => $source, 'to_zone_id' => $target, 'reason' => $reason]);
            return ['id' => $id, 'from_zone' => $sourceZone['name'], 'to_zone' => $targetZone['name']];
        }
        lockZone($source);
        sql('UPDATE parking_sessions SET checkout_at = NOW() WHERE id = ?', [$id]);
        audit($actor, $action === 'checkout' ? 'vehicle_exit' : 'checkout_corrected', 'parking_session', $id,
            $reason === '' ? ['zone_id' => $source] : ['zone_id' => $source, 'reason' => $reason]);
        $retained = $session['visitor_id'] && sql('SELECT id_retained FROM visitors WHERE id = ?', [$session['visitor_id']])->fetchColumn();
        return ['id' => $id, 'return_id' => (bool) $retained];
    });
}

function sessionSearch(string $query, bool $showVisitorPlates): array
{
    if ($query === '') {
        return ['1 = 1', []];
    }
    if (str_starts_with($query, '#')) {
        return ['CAST(s.id AS CHAR) = ?', [substr($query, 1)]];
    }
    $plate = $showVisitorPlates ? 'LOCATE(?, s.vehicle_plate) > 0'
        : '(s.visitor_id IS NULL AND LOCATE(?, s.vehicle_plate) > 0)';
    return ["($plate OR CAST(s.id AS CHAR) = ?)", [$query, $query]];
}

function activeSessions(): array
{
    $query = strtoupper(preg_replace('/\s+/', '', field($_GET, 'q', 32, false)));
    [$search, $params] = sessionSearch($query, true);
    return sql("SELECT s.id, s.vehicle_plate, s.zone_id, s.checkin_at, s.checkout_at, z.name AS zone,
        COALESCE(m.name, v.name) AS driver, COALESCE(m.category, 'visitor') AS driver_type,
        COALESCE(v.id_retained, 0) AS id_retained, v.destination, v.purpose,
        (s.checkin_at < NOW() - INTERVAL 12 HOUR) AS needs_review
        FROM parking_sessions s JOIN parking_zones z ON z.id = s.zone_id
        LEFT JOIN users u ON u.id = s.user_id LEFT JOIN mock_ams m ON m.id = u.ams_id
        LEFT JOIN visitors v ON v.id = s.visitor_id
        WHERE $search AND s.checkout_at IS NULL
        ORDER BY (s.vehicle_plate = ?) DESC, s.id DESC LIMIT 100", [...$params, $query])->fetchAll();
}

function activityDate(string $value, DateTimeZone $timezone): DateTimeImmutable
{
    if (!preg_match('/^([1-9][0-9]{3})-([0-9]{2})-([0-9]{2})$/D', $value, $parts)
        || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) || $value === '9999-12-31') {
        fail(422, 'Enter valid dates in YYYY-MM-DD format.');
    }
    return new DateTimeImmutable($value, $timezone);
}

function activityWindow(bool $defaultToday): ?array
{
    $from = field($_GET, 'from', 10, false);
    $through = field($_GET, 'through', 10, false);
    $timezone = new DateTimeZone('Africa/Nairobi');
    if ($from === '' && $through === '') {
        if (!$defaultToday) {
            return null;
        }
        $from = $through = (new DateTimeImmutable('today', $timezone))->format('Y-m-d');
    }
    if ($from === '' || $through === '') {
        fail(422, 'Choose both a start date and an end date.');
    }
    $start = activityDate($from, $timezone);
    $end = activityDate($through, $timezone);
    if ($start > $end) {
        fail(422, 'The start date must be on or before the end date.');
    }
    return ['from' => $from, 'through' => $through, 'timezone' => 'Africa/Nairobi',
        'start' => $start->format('Y-m-d H:i:s'), 'end' => $end->modify('+1 day')->format('Y-m-d H:i:s')];
}

function auditReport(): array
{
    $window = activityWindow(false);
    $action = field($_GET, 'event', 30, false);
    $session = field($_GET, 'session_id', 10, false);
    $conditions = ['1 = 1'];
    $params = [];
    if ($window !== null) {
        $conditions[] = 'a.created_at >= ? AND a.created_at < ?';
        array_push($params, $window['start'], $window['end']);
    }
    if ($action !== '') {
        $conditions[] = 'a.action = ?';
        $params[] = $action;
    }
    if ($session !== '') {
        $conditions[] = "a.entity_type = 'parking_session' AND a.entity_id = ?";
        $params[] = integer($_GET, 'session_id');
    }
    $where = implode(' AND ', $conditions);
    $logs = sql("SELECT a.id, a.action, a.entity_type, a.entity_id, a.created_at, a.metadata,
        u.name AS actor FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id
        WHERE $where ORDER BY a.created_at DESC, a.id DESC LIMIT 101", $params)->fetchAll();
    $safeFields = array_flip(['zone_id', 'from_zone_id', 'to_zone_id', 'driver_type', 'capacity',
        'previous_capacity', 'active', 'bay_number', 'status', 'previous_status']);
    $visible = array_slice($logs, 0, 100);
    foreach ($visible as &$log) {
        $metadata = (array) json_decode($log['metadata'], true);
        $safe = array_intersect_key($metadata, $safeFields);
        if (isset($metadata['reason']) && $metadata['reason'] !== '') {
            $safe['reason_recorded'] = true;
        }
        $log['metadata'] = json_encode((object) $safe, JSON_THROW_ON_ERROR);
    }
    unset($log);
    return ['logs' => $visible, 'more' => count($logs) > 100, 'window' => $window,
        'events' => sql('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN)];
}

function report(): array
{
    $window = activityWindow(true);
    $bounds = [$window['start'], $window['end']];
    $counts = sql('SELECT
        (SELECT COUNT(*) FROM parking_sessions WHERE checkin_at >= ? AND checkin_at < ?) AS entries,
        (SELECT COUNT(*) FROM parking_sessions WHERE checkout_at >= ? AND checkout_at < ?) AS exits,
        (SELECT COUNT(*) FROM parking_sessions WHERE visitor_id IS NOT NULL AND checkout_at IS NULL) AS active_visitors',
        [...$bounds, ...$bounds])->fetch();
    $query = strtoupper(preg_replace('/\s+/', '', field($_GET, 'q', 32, false)));
    [$search, $params] = sessionSearch($query, false);
    $zone = field($_GET, 'zone_id', 10, false);
    $status = field($_GET, 'status', 10, false);
    $conditions = ['((s.checkin_at >= ? AND s.checkin_at < ?) OR (s.checkout_at >= ? AND s.checkout_at < ?))', $search];
    $params = [...$bounds, ...$bounds, ...$params];
    if ($zone !== '') {
        $conditions[] = 's.zone_id = ?';
        $params[] = integer($_GET, 'zone_id');
    }
    if ($status !== '') {
        $conditions[] = match ($status) {
            'active' => 's.checkout_at IS NULL',
            'closed' => 's.checkout_at IS NOT NULL',
            default => fail(422, 'Choose a valid session status.'),
        };
    }
    $where = implode(' AND ', $conditions);
    $sessions = sql("SELECT s.id, CASE WHEN s.visitor_id IS NULL THEN s.vehicle_plate END AS vehicle_plate,
        s.zone_id, s.checkin_at, s.checkout_at, z.name AS zone,
        CASE WHEN s.visitor_id IS NULL THEN m.name END AS driver,
        CASE WHEN s.visitor_id IS NULL THEN m.category ELSE 'visitor' END AS driver_type
        FROM parking_sessions s JOIN parking_zones z ON z.id = s.zone_id
        LEFT JOIN users u ON u.id = s.user_id LEFT JOIN mock_ams m ON m.id = u.ams_id
        WHERE $where ORDER BY s.checkin_at DESC, s.id DESC LIMIT 101", $params)->fetchAll();
    return $counts + dashboard() + ['sessions' => array_slice($sessions, 0, 100), 'more' => count($sessions) > 100,
        'window' => $window, 'history_zones' => sql('SELECT id, name, active FROM parking_zones ORDER BY name')->fetchAll()];
}

function updateAccount(array $data, int $actor): array
{
    $id = integer($data, 'id');
    $active = flag($data, 'active');
    if ($id === $actor) {
        fail(409, 'You cannot change the status of your own account.');
    }
    return transaction(function () use ($id, $active, $actor): array {
        $user = sql('SELECT id, active FROM users WHERE id = ? FOR UPDATE', [$id])->fetch();
        if (!$user) {
            fail(404, 'That account no longer exists.');
        }
        if ((bool) $user['active'] !== $active) {
            sql('UPDATE users SET active = ? WHERE id = ?', [(int) $active, $id]);
            audit($actor, 'account_status_changed', 'user', $id, ['active' => $active]);
        }
        return ['id' => $id];
    });
}

function actionPolicy(string $action): array
{
    $gate = ['admin', 'security'];
    $admin = ['admin'];
    $authenticated = ['student', 'staff', ...$gate];
    return match ($action) {
        'state', 'availability' => ['GET', []],
        'login' => ['POST', []],
        'dashboard' => ['GET', $authenticated],
        'logout' => ['POST', $authenticated],
        'ams', 'active' => ['GET', $gate],
        'checkin', 'checkout', 'transfer', 'correct_checkout' => ['POST', $gate],
        'zones', 'audit', 'report', 'accounts' => ['GET', $admin],
        'zone_create', 'zone_update', 'zone_delete', 'account_status', 'bay_manage' => ['POST', $admin],
        'bay_occupy', 'bay_release' => ['POST', ['student']],
        default => fail(404, 'Unknown action.'),
    };
}

try {
    $action = field($_GET, 'action', 30);
    [$method, $roles] = actionPolicy($action);
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header("Allow: $method");
        fail(405, "Use $method for this action.");
    }
    if ($action === 'availability') {
        echo json_encode(dashboard(), JSON_THROW_ON_ERROR);
        return;
    }
    startParkingSession();
    $user = currentUser();
    if ($roles && !$user) {
        fail(401, 'Sign in to continue.');
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    // The UI also sends its token on protected reads to catch a changed account in another tab.
    if (($method === 'POST' || ($roles && $token !== null)) && !hash_equals($_SESSION['csrf'], $token ?? '')) {
        fail(403, 'Your session token changed. Refresh and try again.');
    }
    if ($roles && !in_array($user['role'], $roles, true)) {
        fail(403, 'Your role does not have access to this action.');
    }
    $data = $method === 'POST' ? writeBody($action) : [];
    if ($action === 'state') {
        $result = ['user' => $user, 'csrf' => $_SESSION['csrf'], 'demo' => localDemo()];
    } elseif ($action === 'login') {
        $result = login($data);
    } else {
        $poll = $action === 'dashboard' ? field($_GET, 'poll', 1, false) : '';
        if ($poll !== '' && $poll !== '1') {
            fail(422, 'Choose a valid dashboard refresh mode.');
        }
        $actor = (int) $user['id'];
        $result = match ($action) {
            'logout' => (function () use ($actor): array {
                audit($actor, 'logout', 'authentication', $actor);
                rotateParkingSession();
                return ['csrf' => $_SESSION['csrf']];
            })(),
            'dashboard' => dashboard($user),
            'ams' => ['users' => amsUsers()],
            'active' => ['sessions' => activeSessions()],
            'zones' => ['zones' => zones(true)],
            'zone_create', 'zone_update', 'zone_delete' => changeZone($action, $data, $actor),
            'checkin' => checkin($data, $actor),
            'checkout', 'transfer', 'correct_checkout' => changeSession($action, $data, $actor),
            'audit' => auditReport(),
            'report' => report(),
            'accounts' => ['accounts' => sql('SELECT u.id, u.name, u.email, u.role, u.active, m.institutional_id
                FROM users u LEFT JOIN mock_ams m ON m.id = u.ams_id ORDER BY u.id')->fetchAll()],
            'account_status' => updateAccount($data, $actor),
            'bay_occupy', 'bay_release', 'bay_manage' => changeBay($action, $data, $actor),
        };
        if ($action !== 'logout' && $poll === '') {
            $_SESSION['last_activity'] = time();
        }
    }
    session_write_close();
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (RequestError $error) {
    http_response_code($error->getCode());
    echo json_encode(['error' => $error->getMessage()]);
} catch (PDOException $error) {
    $code = (int) ($error->errorInfo[1] ?? 0);
    if ($code === 1062) {
        http_response_code(409);
        echo json_encode(['error' => match ($action ?? '') {
            'checkin' => 'That vehicle already has an active parking session. Find it in Active sessions.',
            'bay_occupy' => 'Release your current bay before occupying another one.',
            default => 'A zone with that name already exists.',
        }]);
    } elseif (in_array($code, [1205, 1213], true)) {
        http_response_code(409);
        echo json_encode(['error' => 'Another operation changed these records. Refresh and try again.']);
    } else {
        error_log($error->getMessage());
        http_response_code(503);
        echo json_encode(['error' => 'The parking database is unavailable. Check the database setup and try again.']);
    }
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'The request could not be completed.']);
}
