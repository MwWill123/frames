-- ==========================================
-- FRAMES Database Schema
-- Video Editor Platform
-- ==========================================

-- Create database
CREATE DATABASE IF NOT EXISTS frames_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE frames_db;

-- ==========================================
-- Users Table
-- ==========================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    avatar VARCHAR(255),
    role ENUM('admin', 'editor', 'user') DEFAULT 'user',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- Editors Table
-- ==========================================
CREATE TABLE IF NOT EXISTS editors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    title VARCHAR(150) NOT NULL,
    software VARCHAR(50) NOT NULL,
    format VARCHAR(50) DEFAULT 'general',
    rating INT DEFAULT 0,
    reviews INT DEFAULT 0,
    image VARCHAR(255),
    featured TINYINT(1) DEFAULT 0,
    description TEXT,
    portfolio_url VARCHAR(255),
    user_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_software (software),
    INDEX idx_format (format),
    INDEX idx_featured (featured),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- Projects Table
-- ==========================================
CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    format VARCHAR(50) NOT NULL,
    status ENUM('draft', 'in_progress', 'completed', 'cancelled') DEFAULT 'draft',
    editor_id INT UNSIGNED,
    user_id INT UNSIGNED NOT NULL,
    thumbnail VARCHAR(255),
    video_url VARCHAR(255),
    duration INT, -- in seconds
    price DECIMAL(10, 2),
    deadline DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_format (format),
    INDEX idx_editor (editor_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (editor_id) REFERENCES editors(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- Reviews Table
-- ==========================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    editor_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_editor (editor_id),
    INDEX idx_user (user_id),
    INDEX idx_rating (rating),
    FOREIGN KEY (editor_id) REFERENCES editors(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- Messages Table
-- ==========================================
CREATE TABLE IF NOT EXISTS messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_project (project_id),
    INDEX idx_read (is_read),
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- Portfolio Items Table
-- ==========================================
CREATE TABLE IF NOT EXISTS portfolio_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    editor_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    thumbnail VARCHAR(255),
    video_url VARCHAR(255),
    format VARCHAR(50),
    views INT DEFAULT 0,
    likes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_editor (editor_id),
    INDEX idx_format (format),
    INDEX idx_views (views),
    FOREIGN KEY (editor_id) REFERENCES editors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- Categories Table
-- ==========================================
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- Tags Table
-- ==========================================
CREATE TABLE IF NOT EXISTS tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- Editor Categories Junction Table
-- ==========================================
CREATE TABLE IF NOT EXISTS editor_categories (
    editor_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (editor_id, category_id),
    FOREIGN KEY (editor_id) REFERENCES editors(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- Project Tags Junction Table
-- ==========================================
CREATE TABLE IF NOT EXISTS project_tags (
    project_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (project_id, tag_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- Insert Sample Data
-- ==========================================

-- Sample Users
INSERT INTO users (username, email, password_hash, full_name, role) VALUES
('admin', 'admin@frames.com', '$2y$10$YourHashedPasswordHere', 'Admin User', 'admin'),
('johndoe', 'john@example.com', '$2y$10$YourHashedPasswordHere', 'John Doe', 'user'),
('janeeditor', 'jane@example.com', '$2y$10$YourHashedPasswordHere', 'Jane Editor', 'editor');

-- Sample Categories
INSERT INTO categories (name, slug, description, icon) VALUES
('Reels/TikTok', 'reels-tiktok', 'Vertical videos for social media', 'mobile'),
('YouTube Vlog', 'youtube-vlog', 'Horizontal video content', 'play'),
('Documentário', 'documentario', 'Long-form documentary content', 'film'),
('Comercial', 'comercial', 'Commercial and advertising content', 'megaphone'),
('Gameplay', 'gameplay', 'Gaming and streaming content', 'gamepad');

-- Sample Tags
INSERT INTO tags (name, slug) VALUES
('Premiere Pro', 'premiere-pro'),
('After Effects', 'after-effects'),
('DaVinci Resolve', 'davinci-resolve'),
('Final Cut Pro', 'final-cut-pro'),
('Color Grading', 'color-grading'),
('Motion Graphics', 'motion-graphics'),
('Sound Design', 'sound-design');

-- Sample Editors
INSERT INTO editors (name, title, software, format, rating, reviews, image, featured, description) VALUES
('AURA FILMS', 'Aléx e Bibi Inuê', 'PR SUB', 'reels', 16, 3, 'aura-films.jpg', 1, 'Especialistas em vídeos verticais para redes sociais'),
('PIXEL FLOW', 'Adobe Illustra', 'EL DUVE', 'youtube', 16, 3, 'pixel-flow.jpg', 1, 'Criação de conteúdo profissional para YouTube'),
('PIXEL FLOW', 'Premiere Resolve', 'DI.0 SUB', 'documentario', 16, 3, 'pixel-flow-2.jpg', 0, 'Documentários de alta qualidade'),
('NONA RIDEOKE', 'DaVinci Resolve', 'GATE HOUR', 'comercial', 16, 3, 'nona-rideoke.jpg', 0, 'Vídeos comerciais e publicitários'),
('CREATIVE MINDS', 'Final Cut Pro', 'PR SUB', 'gameplay', 18, 5, 'creative-minds.jpg', 0, 'Edição profissional de gameplays'),
('MOTION STUDIO', 'After Effects', 'AE PRO', 'motion', 20, 7, 'motion-studio.jpg', 1, 'Motion graphics e animações');

-- Sample Projects
INSERT INTO projects (title, description, format, status, editor_id, user_id, price, deadline) VALUES
('Reels de Lançamento', 'Vídeo vertical para lançamento de produto', 'reels', 'in_progress', 1, 2, 500.00, '2026-02-15'),
('Vlog Semanal', 'Edição do vlog semanal de viagens', 'youtube', 'completed', 2, 2, 800.00, '2026-02-01'),
('Documentário Corporativo', 'Documentário sobre a história da empresa', 'documentario', 'draft', 3, 2, 2500.00, '2026-03-01');

-- ==========================================
-- Create Views for Common Queries
-- ==========================================

-- View: Featured Editors with Statistics
CREATE VIEW v_featured_editors AS
SELECT 
    e.*,
    COUNT(DISTINCT p.id) as total_projects,
    AVG(r.rating) as avg_rating,
    COUNT(DISTINCT r.id) as total_reviews
FROM editors e
LEFT JOIN projects p ON e.id = p.editor_id
LEFT JOIN reviews r ON e.id = r.editor_id
WHERE e.featured = 1
GROUP BY e.id
ORDER BY e.rating DESC;

-- View: Active Projects Summary
CREATE VIEW v_active_projects AS
SELECT 
    p.*,
    e.name as editor_name,
    u.username as client_username,
    u.email as client_email
FROM projects p
LEFT JOIN editors e ON p.editor_id = e.id
LEFT JOIN users u ON p.user_id = u.id
WHERE p.status IN ('draft', 'in_progress')
ORDER BY p.created_at DESC;

-- ==========================================
-- Create Stored Procedures
-- ==========================================

DELIMITER //

-- Procedure: Get Editor Statistics
CREATE PROCEDURE sp_get_editor_stats(IN editor_id_param INT)
BEGIN
    SELECT 
        e.id,
        e.name,
        COUNT(DISTINCT p.id) as total_projects,
        COUNT(DISTINCT CASE WHEN p.status = 'completed' THEN p.id END) as completed_projects,
        AVG(r.rating) as avg_rating,
        COUNT(DISTINCT r.id) as total_reviews,
        SUM(pi.views) as total_views,
        SUM(pi.likes) as total_likes
    FROM editors e
    LEFT JOIN projects p ON e.id = p.editor_id
    LEFT JOIN reviews r ON e.id = r.editor_id
    LEFT JOIN portfolio_items pi ON e.id = pi.editor_id
    WHERE e.id = editor_id_param
    GROUP BY e.id, e.name;
END //

DELIMITER ;

-- ==========================================
-- Indexes for Performance Optimization
-- ==========================================

-- Additional composite indexes
CREATE INDEX idx_projects_status_deadline ON projects(status, deadline);
CREATE INDEX idx_reviews_editor_rating ON reviews(editor_id, rating);
CREATE INDEX idx_messages_receiver_read ON messages(receiver_id, is_read);
CREATE INDEX idx_portfolio_editor_views ON portfolio_items(editor_id, views);

-- Full-text search indexes
ALTER TABLE editors ADD FULLTEXT INDEX ft_search (name, title, description);
ALTER TABLE projects ADD FULLTEXT INDEX ft_search (title, description);

-- ==========================================
-- Triggers
-- ==========================================

DELIMITER //

-- Trigger: Update editor rating after new review
CREATE TRIGGER trg_update_editor_rating 
AFTER INSERT ON reviews
FOR EACH ROW
BEGIN
    UPDATE editors 
    SET rating = (
        SELECT ROUND(AVG(rating))
        FROM reviews
        WHERE editor_id = NEW.editor_id
    ),
    reviews = (
        SELECT COUNT(*)
        FROM reviews
        WHERE editor_id = NEW.editor_id
    )
    WHERE id = NEW.editor_id;
END //

DELIMITER ;

-- ==========================================
-- Grant Permissions
-- ==========================================

-- Create application user (change password in production)
-- CREATE USER IF NOT EXISTS 'frames_user'@'localhost' IDENTIFIED BY 'YourStrongPasswordHere';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON frames_db.* TO 'frames_user'@'localhost';
-- FLUSH PRIVILEGES;

-- ==========================================
-- End of Schema
-- ==========================================
