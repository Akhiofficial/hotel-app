-- Add image field to rooms table
ALTER TABLE rooms 
ADD COLUMN image VARCHAR(255) DEFAULT NULL 
AFTER status;
