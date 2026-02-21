<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'fleet_hub');

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die(json_encode(['error' => 'DB Connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

function initDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($conn->connect_error) die('Connection failed: ' . $conn->connect_error);

    $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` 
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $conn->select_db(DB_NAME);

    $tables = [

        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('manager','dispatcher','safety_officer','financial_analyst') 
                DEFAULT 'dispatcher',
            reset_token VARCHAR(100) DEFAULT NULL,
            reset_expires DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS vehicles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            model VARCHAR(100),
            type ENUM('Truck','Van','Bike') DEFAULT 'Van',
            license_plate VARCHAR(30) UNIQUE NOT NULL,
            max_capacity DECIMAL(10,2) NOT NULL,
            odometer DECIMAL(10,2) DEFAULT 0,
            status ENUM('Available','On Trip','In Shop','Retired') DEFAULT 'Available',
            region VARCHAR(100),
            acquisition_cost DECIMAL(12,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS drivers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150),
            phone VARCHAR(20),
            license_number VARCHAR(50) UNIQUE NOT NULL,
            license_category VARCHAR(20),
            license_expiry DATE NOT NULL,
            status ENUM('On Duty','Off Duty','Suspended') DEFAULT 'Off Duty',
            safety_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
            trips_completed INT DEFAULT 0,
            trips_total INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS trips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            driver_id INT NOT NULL,
            origin VARCHAR(200) NOT NULL,
            destination VARCHAR(200) NOT NULL,
            cargo_weight DECIMAL(10,2) NOT NULL,
            cargo_description TEXT,
            status ENUM('Draft','Dispatched','Completed','Cancelled') DEFAULT 'Draft',
            distance_km DECIMAL(10,2) DEFAULT 0,
            start_odometer DECIMAL(10,2) DEFAULT 0,
            end_odometer DECIMAL(10,2) DEFAULT 0,
            revenue DECIMAL(12,2) DEFAULT 0,
            dispatched_at DATETIME,
            completed_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
            FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS maintenance_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            service_type VARCHAR(100) NOT NULL,
            description TEXT,
            cost DECIMAL(12,2) DEFAULT 0,
            technician VARCHAR(100),
            service_date DATE NOT NULL,
            completed_date DATE,
            status ENUM('Scheduled','In Progress','Completed') 
                DEFAULT 'In Progress',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS fuel_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            trip_id INT DEFAULT NULL,
            liters DECIMAL(8,2) NOT NULL,
            cost DECIMAL(10,2) NOT NULL,
            odometer_reading DECIMAL(10,2),
            fuel_date DATE NOT NULL,
            station VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
            FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL
        ) ENGINE=InnoDB"
    ];

    foreach ($tables as $sql) {
        if (!$conn->query($sql)) {
            echo "Error creating table: " . $conn->error . "<br>";
        }
    }

    // Seed default admin
    $check = $conn->query("SELECT id FROM users LIMIT 1");
    if ($check->num_rows === 0) {
        $hash = password_hash('Admin@123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (name, email, password, role) 
            VALUES ('System Admin', 'admin@fleethub.com', '$hash', 'manager')");
    }

    $conn->close();
}

initDB();