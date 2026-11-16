<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $host = getenv('PARKING_DB_HOST') ?: '127.0.0.1';
        $port = getenv('PARKING_DB_PORT') ?: '3306';
        $name = getenv('PARKING_DB_NAME') ?: 'strathmore_smart_parking';
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
            getenv('PARKING_DB_USER') ?: 'root', getenv('PARKING_DB_PASSWORD') ?: '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+03:00'",
            ]);
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }
    return $pdo;
}

function sql(string $query, array $params = []): PDOStatement
{
    $statement = db()->prepare($query);
    $statement->execute($params);
    return $statement;
}

function transaction(callable $work): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $work();
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function audit(?int $actor, string $action, string $entity, ?int $id, array $metadata = []): void
{
    sql('INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, metadata) VALUES (?,?,?,?,?)',
        [$actor, $action, $entity, $id, json_encode((object) $metadata, JSON_THROW_ON_ERROR)]);
}

function startParkingSession(): void
{
    ini_set('session.use_strict_mode', '1');
    session_name('strathmore_parking');
    session_set_cookie_params([
        'path' => dirname($_SERVER['SCRIPT_NAME']) === '/' ? '/' : rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
    $timeout = 1800;
    if (getenv('PARKING_TEST_MODE') === '1' && PHP_SAPI === 'cli-server') {
        $timeout = max(1, (int) (getenv('PARKING_TEST_TIMEOUT') ?: $timeout));
    }
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] >= $timeout) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function currentUser(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return sql("SELECT u.id, u.name, u.email, u.role
        FROM users u LEFT JOIN mock_ams m ON m.id = u.ams_id
        WHERE u.id = ? AND u.active = 1 AND (u.ams_id IS NULL OR (m.active = 1 AND m.category = u.role))",
        [$_SESSION['user_id']])->fetch() ?: null;
}

function localDemo(): bool
{
    return getenv('PARKING_LOCAL_DEMO') === '1'
        && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}
