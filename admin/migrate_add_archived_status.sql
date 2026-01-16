-- Add 'archived' to the status ENUM
ALTER TABLE bookings MODIFY COLUMN status ENUM('pending','paid','confirmed','cancelled','archived') DEFAULT 'pending';
