-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add notification_preferences column to sellers_info if not exists
ALTER TABLE sellers_info
ADD COLUMN IF NOT EXISTS notification_preferences JSON DEFAULT NULL;

-- Create stored procedure for creating notifications
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS create_notification(
    IN p_user_id INT,
    IN p_type VARCHAR(50),
    IN p_title VARCHAR(255),
    IN p_message TEXT
)
BEGIN
    INSERT INTO notifications (user_id, type, title, message)
    VALUES (p_user_id, p_type, p_title, p_message);
END //
DELIMITER ;

-- Create stored procedure for marking notifications as read
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS mark_notification_read(
    IN p_notification_id INT
)
BEGIN
    UPDATE notifications
    SET is_read = TRUE
    WHERE id = p_notification_id;
END //
DELIMITER ; 