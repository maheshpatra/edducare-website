-- Add utr_number to admission_requests
ALTER TABLE admission_requests 
ADD COLUMN utr_number VARCHAR(100) DEFAULT NULL,
ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL,
ADD COLUMN payment_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending';
