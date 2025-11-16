-- Add identity_card column to bookings table
ALTER TABLE bookings 
ADD COLUMN identity_card VARCHAR(255) DEFAULT NULL 
AFTER bank_proof;
