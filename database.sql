CREATE DATABASE IF NOT EXISTS strathmore_smart_parking
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE strathmore_smart_parking;
SET time_zone = '+03:00';

CREATE TABLE mock_ams (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institutional_id VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  category ENUM('student','staff') NOT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ams_id INT UNSIGNED UNIQUE,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  role ENUM('student','staff','security','admin') NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  FOREIGN KEY (ams_id) REFERENCES mock_ams(id),
  CHECK ((role IN ('student','staff')) = (ams_id IS NOT NULL))
) ENGINE=InnoDB;

CREATE TABLE parking_zones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(250) NOT NULL,
  capacity SMALLINT UNSIGNED NOT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  CHECK (capacity BETWEEN 1 AND 9999)
) ENGINE=InnoDB;

CREATE TABLE visitors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  purpose VARCHAR(120) NOT NULL,
  destination VARCHAR(100) NOT NULL,
  id_inspected BOOLEAN NOT NULL,
  id_retained BOOLEAN NOT NULL,
  CHECK (id_retained <= id_inspected)
) ENGINE=InnoDB;

CREATE TABLE parking_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED,
  visitor_id INT UNSIGNED UNIQUE,
  vehicle_plate VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  zone_id INT UNSIGNED NOT NULL,
  checkin_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  checkout_at DATETIME,
  active_plate VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin
    GENERATED ALWAYS AS (IF(checkout_at IS NULL, vehicle_plate, NULL)) STORED,
  UNIQUE KEY one_active_session_per_vehicle (active_plate),
  KEY zone_occupancy (zone_id, checkout_at),
  KEY session_entry (checkin_at),
  KEY session_exit (checkout_at),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (visitor_id) REFERENCES visitors(id),
  FOREIGN KEY (zone_id) REFERENCES parking_zones(id),
  CHECK ((user_id IS NULL) <> (visitor_id IS NULL)),
  CHECK (checkout_at IS NULL OR checkout_at >= checkin_at)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id INT UNSIGNED,
  action VARCHAR(30) NOT NULL,
  entity_type VARCHAR(30) NOT NULL,
  entity_id INT UNSIGNED,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  metadata JSON NOT NULL,
  FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  scope_key CHAR(64) CHARACTER SET ascii PRIMARY KEY,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  window_started BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB;

INSERT INTO mock_ams (id, institutional_id, name, category) VALUES
  (1, 'STU-DEMO-01', 'Maya Demo', 'student'),
  (2, 'STA-DEMO-01', 'Jordan Demo', 'staff');

SET @demo_hash = '$2y$10$EP645PfCvUHKb2qdOi7sIuztPloz9g8zDs6FE7p/g0nKY4U3qfulm';
INSERT INTO users (id, ams_id, name, email, role, password_hash) VALUES
  (1, 1, 'Maya Demo', 'student@parking.test', 'student', @demo_hash),
  (2, 2, 'Jordan Demo', 'staff@parking.test', 'staff', @demo_hash),
  (3, NULL, 'Sam Demo', 'security@parking.test', 'security', @demo_hash),
  (4, NULL, 'Alex Demo', 'admin@parking.test', 'admin', @demo_hash);

INSERT INTO parking_zones (id, name, description, capacity) VALUES
  (1, 'Main Parking', 'General campus parking', 50),
  (2, 'Staff Parking', 'Parking beside the faculty offices', 24),
  (3, 'Visitor Parking', 'Parking near the reception desk', 12);

INSERT INTO parking_sessions (user_id, vehicle_plate, zone_id, checkin_at) VALUES
  (1, 'DEMO001', 1, NOW() - INTERVAL 40 MINUTE),
  (2, 'DEMO002', 2, NOW() - INTERVAL 20 MINUTE);
