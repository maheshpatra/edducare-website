-- Add Payment and Admission Settings to admission_configs
ALTER TABLE admission_configs 
ADD COLUMN is_admission_fee_enabled TINYINT(1) DEFAULT 0,
ADD COLUMN admission_fee_amount DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN qr_code VARCHAR(255) DEFAULT NULL;

-- Ensure school_id exists if we create a new table for general settings, but admission_configs is already school-specific.
