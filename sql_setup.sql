CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(255),
    role VARCHAR(50),
    ip_address VARCHAR(45),
    accessed_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
