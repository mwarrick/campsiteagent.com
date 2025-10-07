-- Add 'disable_alerts' to the login_tokens type enum
ALTER TABLE login_tokens MODIFY COLUMN type ENUM('verify','login','disable_alerts') NOT NULL;
