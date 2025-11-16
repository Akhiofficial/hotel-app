-- install.sql - MySQL schema & sample data
CREATE DATABASE IF NOT EXISTS hotel_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hotel_app;

-- admin config (optional table for flexible admin users)
CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) UNIQUE,
  password_hash VARCHAR(255)
);

-- rooms
CREATE TABLE IF NOT EXISTS rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) UNIQUE,
  title VARCHAR(200),
  description TEXT,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  capacity INT DEFAULT 1,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- services / inventory
CREATE TABLE IF NOT EXISTS services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200),
  price DECIMAL(10,2) DEFAULT 0,
  qty INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- bookings
CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT,
  customer_name VARCHAR(255),
  customer_email VARCHAR(255),
  customer_phone VARCHAR(50),
  checkin DATE,
  checkout DATE,
  nights INT,
  total DECIMAL(12,2),
  gst_rate DECIMAL(5,2) DEFAULT 18.00,
  gst_amount DECIMAL(12,2) DEFAULT 0,
  status ENUM('pending','paid','confirmed','cancelled') DEFAULT 'pending',
  payment_method ENUM('cash','bank_transfer','online') DEFAULT 'cash',
  bank_proof VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
);

-- sample room(s)
INSERT INTO rooms (code,title,description,price,capacity) VALUES
('R101','Standard Single','Simple single room',1200.00,1),
('R201','Deluxe Double','Double bed, AC',2200.00,2),
('R301','Suite','Big suite with seating',4500.00,4);

-- sample services
INSERT INTO services (name,price,qty) VALUES
('Breakfast',150.00,100),
('Airport Pickup',500.00,10);

-- sample admin
INSERT INTO admins (username, password_hash) VALUES
('admin', SHA2('admin123', 256));

