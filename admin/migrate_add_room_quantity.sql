-- Add quantity field to rooms table
ALTER TABLE rooms 
ADD COLUMN quantity INT DEFAULT 1 
AFTER capacity;

-- Update existing rooms to have quantity 1 if NULL
UPDATE rooms SET quantity = 1 WHERE quantity IS NULL;
