-- Payment Gateways Table
CREATE TABLE IF NOT EXISTS school_payment_gateways (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    gateway_name VARCHAR(50) NOT NULL, -- 'razorpay', 'payu', 'upi_qr'
    is_active TINYINT(1) DEFAULT 0,
    config_json TEXT, -- JSON for credentials like api_key, api_secret, salt etc.
    mode ENUM('sandbox', 'live') DEFAULT 'sandbox',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY (school_id, gateway_name)
);
