-- Create the database
CREATE DATABASE IF NOT EXISTS NoteFlow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create a dedicated user and grant privileges
CREATE USER IF NOT EXISTS 'NoteFlow_User'@'localhost' IDENTIFIED BY 'your_password';

GRANT ALL PRIVILEGES ON NoteFlow.* TO 'NoteFlow_User'@'localhost';

FLUSH PRIVILEGES;

-- Use the database
USE NoteFlow;

-- Create the notes table
CREATE TABLE IF NOT EXISTS notes (
    id VARCHAR(255) NOT NULL PRIMARY KEY,
    content TEXT NOT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



