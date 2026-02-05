-- ==========================================
-- FRAMES Platform - Complete Database Schema
-- PostgreSQL 14+ with UUID support
-- ==========================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ==========================================
-- ENUMS (Type Definitions)
-- ==========================================

CREATE TYPE user_role AS ENUM ('ADMIN', 'EDITOR', 'CLIENT');
CREATE TYPE project_status AS ENUM ('OPEN', 'IN_PROGRESS', 'IN_REVIEW', 'REVISION_REQUESTED', 'COMPLETED', 'CANCELLED', 'DISPUTED');
CREATE TYPE payment_status AS ENUM ('PENDING', 'ESCROWED', 'RELEASED', 'REFUNDED', 'DISPUTED');
CREATE TYPE file_type AS ENUM ('RAW_FOOTAGE', 'DRAFT_VERSION', 'FINAL_DELIVERY', 'REFERENCE', 'THUMBNAIL');
CREATE TYPE software_type AS ENUM ('PREMIERE_PRO', 'DAVINCI_RESOLVE', 'FINAL_CUT_PRO', 'AFTER_EFFECTS', 'AVID');
CREATE TYPE editor_level AS ENUM ('JUNIOR', 'PRO', 'CINEMA_GRADE');
CREATE TYPE video_specialty AS ENUM ('VLOG', 'COMMERCIAL', 'DOCUMENTARY', 'MUSIC_VIDEO', 'REELS_TIKTOK', 'YOUTUBE', 'CORPORATE', 'WEDDING', 'GAMEPLAY');

-- ==========================================
-- 1. USERS TABLE (Master Authentication)
-- ==========================================

CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role user_role NOT NULL DEFAULT 'CLIENT',
    is_verified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified_at TIMESTAMP,
    last_login_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL -- Soft delete
);

-- Indexes for performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_is_active ON users(is_active);

-- ==========================================
-- 2. USER PROFILES (Detailed Information)
-- ==========================================

CREATE TABLE user_profiles (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    display_name VARCHAR(100) NOT NULL,
    avatar_url TEXT,
    bio TEXT,
    phone VARCHAR(20),
    country VARCHAR(2), -- ISO country code
    timezone VARCHAR(50),
    language VARCHAR(5) DEFAULT 'pt-BR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_user_profiles_user_id ON user_profiles(user_id);

-- ==========================================
-- 3. EDITOR PROFILES (Extended for Editors)
-- ==========================================

CREATE TABLE editor_profiles (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    display_name VARCHAR(100) NOT NULL,
    tagline VARCHAR(200), -- "Transforming raw footage into cinematic stories"
    bio TEXT,
    avatar_url TEXT,
    banner_url TEXT,
    primary_software software_type NOT NULL,
    secondary_software software_type[],
    editor_level editor_level DEFAULT 'JUNIOR',
    specialties video_specialty[] NOT NULL,
    hourly_rate DECIMAL(10, 2),
    project_rate_min DECIMAL(10, 2),
    project_rate_max DECIMAL(10, 2),
    years_experience INTEGER,
    total_projects_completed INTEGER DEFAULT 0,
    average_rating DECIMAL(3, 2) DEFAULT 0.00,
    total_reviews INTEGER DEFAULT 0,
    response_time_hours INTEGER, -- Average response time
    is_featured BOOLEAN DEFAULT FALSE, -- Featured on homepage
    is_verified BOOLEAN DEFAULT FALSE, -- KYC verified
    portfolio_video_url TEXT, -- Showreel URL
    available_for_hire BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_editor_profiles_user_id ON editor_profiles(user_id);
CREATE INDEX idx_editor_profiles_featured ON editor_profiles(is_featured);
CREATE INDEX idx_editor_profiles_available ON editor_profiles(available_for_hire);
CREATE INDEX idx_editor_profiles_specialties ON editor_profiles USING GIN(specialties);

-- ==========================================
-- 4. PORTFOLIO ITEMS (Editor's Work Showcase)
-- ==========================================

CREATE TABLE portfolio_items (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    editor_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    
    -- Video URLs (processed for web streaming)
    video_url_processed TEXT NOT NULL, -- Main streaming URL (HLS/DASH)
    video_url_720p TEXT, -- 720p version
    video_url_1080p TEXT, -- 1080p version
    video_url_raw TEXT, -- Original file URL for download
    
    -- Before/After feature
    before_video_url TEXT, -- Source footage URL
    after_video_url TEXT, -- Edited result URL
    
    -- Thumbnails
    thumbnail_static TEXT, -- Static thumbnail
    thumbnail_gif TEXT, -- Animated preview GIF
    
    -- Metadata
    duration_seconds INTEGER,
    video_specialty video_specialty,
    software_used software_type[],
    tags TEXT[],
    
    -- Engagement metrics
    views_count INTEGER DEFAULT 0,
    likes_count INTEGER DEFAULT 0,
    
    -- Status
    is_featured BOOLEAN DEFAULT FALSE,
    is_public BOOLEAN DEFAULT TRUE,
    processing_status VARCHAR(20) DEFAULT 'pending', -- pending, processing, completed, failed
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_portfolio_editor ON portfolio_items(editor_id);
CREATE INDEX idx_portfolio_featured ON portfolio_items(is_featured);
CREATE INDEX idx_portfolio_specialty ON portfolio_items(video_specialty);
CREATE INDEX idx_portfolio_public ON portfolio_items(is_public);

-- ==========================================
-- 5. PROJECTS (Job Postings)
-- ==========================================

CREATE TABLE projects (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    client_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    editor_id UUID REFERENCES users(id) ON DELETE SET NULL,
    
    -- Project details
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    
    -- Specifications
    video_duration_min INTEGER, -- in seconds
    video_duration_max INTEGER,
    video_specialty video_specialty NOT NULL,
    preferred_software software_type,
    aspect_ratio VARCHAR(10), -- 16:9, 9:16, 1:1, etc.
    
    -- Budget
    budget_type VARCHAR(20) DEFAULT 'fixed', -- fixed or hourly
    budget_min DECIMAL(10, 2),
    budget_max DECIMAL(10, 2),
    agreed_price DECIMAL(10, 2), -- Final negotiated price
    
    -- Timeline
    deadline TIMESTAMP,
    estimated_delivery_date TIMESTAMP,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    
    -- Status
    status project_status DEFAULT 'OPEN',
    
    -- Revisions
    max_revisions INTEGER DEFAULT 2,
    current_revision INTEGER DEFAULT 0,
    
    -- Requirements
    requirements_document TEXT, -- Detailed briefing
    reference_urls TEXT[], -- YouTube links, etc.
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_projects_client ON projects(client_id);
CREATE INDEX idx_projects_editor ON projects(editor_id);
CREATE INDEX idx_projects_status ON projects(status);
CREATE INDEX idx_projects_specialty ON projects(video_specialty);
CREATE INDEX idx_projects_deadline ON projects(deadline);

-- ==========================================
-- 6. PROJECT ASSETS (Files Associated with Projects)
-- ==========================================

CREATE TABLE project_assets (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    uploader_id UUID NOT NULL REFERENCES users(id),
    
    -- File information
    file_name VARCHAR(255) NOT NULL,
    file_type file_type NOT NULL,
    file_url TEXT NOT NULL, -- S3/CDN URL
    file_size_bytes BIGINT,
    mime_type VARCHAR(100),
    
    -- Video metadata (if applicable)
    duration_seconds INTEGER,
    resolution VARCHAR(20), -- 1920x1080, etc.
    codec VARCHAR(50),
    bitrate INTEGER,
    
    -- Version control
    version INTEGER DEFAULT 1,
    is_latest BOOLEAN DEFAULT TRUE,
    replaces_asset_id UUID REFERENCES project_assets(id), -- Links to previous version
    
    -- Processing
    processing_status VARCHAR(20) DEFAULT 'uploaded', -- uploaded, processing, ready, failed
    processed_urls JSONB, -- {720p: 'url', 1080p: 'url', thumbnail: 'url'}
    
    -- Metadata
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_assets_project ON project_assets(project_id);
CREATE INDEX idx_assets_uploader ON project_assets(uploader_id);
CREATE INDEX idx_assets_type ON project_assets(file_type);
CREATE INDEX idx_assets_version ON project_assets(version);

-- ==========================================
-- 7. VIDEO COMMENTS (Timestamped Feedback)
-- ==========================================

CREATE TABLE video_comments (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    asset_id UUID NOT NULL REFERENCES project_assets(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id),
    parent_comment_id UUID REFERENCES video_comments(id), -- For threaded replies
    
    -- Comment content
    timestamp_seconds DECIMAL(10, 3) NOT NULL, -- Exact timestamp (supports milliseconds)
    content TEXT NOT NULL,
    
    -- Status
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP,
    resolved_by UUID REFERENCES users(id),
    
    -- Priority (for client feedback)
    priority VARCHAR(10) DEFAULT 'normal', -- low, normal, high, critical
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_comments_asset ON video_comments(asset_id);
CREATE INDEX idx_comments_user ON video_comments(user_id);
CREATE INDEX idx_comments_timestamp ON video_comments(timestamp_seconds);
CREATE INDEX idx_comments_resolved ON video_comments(is_resolved);

-- ==========================================
-- 8. PAYMENTS (Escrow System)
-- ==========================================

CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    client_id UUID NOT NULL REFERENCES users(id),
    editor_id UUID REFERENCES users(id),
    
    -- Payment details
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'BRL',
    platform_fee DECIMAL(10, 2), -- 15% fee
    editor_amount DECIMAL(10, 2), -- Amount editor receives after fee
    
    -- Payment gateway
    payment_method VARCHAR(50), -- credit_card, pix, boleto
    stripe_payment_id VARCHAR(255),
    stripe_transfer_id VARCHAR(255),
    
    -- Status
    status payment_status DEFAULT 'PENDING',
    
    -- Escrow dates
    escrowed_at TIMESTAMP,
    released_at TIMESTAMP,
    refunded_at TIMESTAMP,
    
    -- Dispute
    dispute_reason TEXT,
    dispute_opened_at TIMESTAMP,
    dispute_resolved_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_payments_project ON payments(project_id);
CREATE INDEX idx_payments_client ON payments(client_id);
CREATE INDEX idx_payments_editor ON payments(editor_id);
CREATE INDEX idx_payments_status ON payments(status);

-- ==========================================
-- 9. PROPOSALS (Editor Bids on Projects)
-- ==========================================

CREATE TABLE proposals (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    editor_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    -- Proposal details
    proposed_price DECIMAL(10, 2) NOT NULL,
    estimated_delivery_days INTEGER NOT NULL,
    cover_letter TEXT NOT NULL,
    
    -- Status
    status VARCHAR(20) DEFAULT 'pending', -- pending, accepted, rejected, withdrawn
    
    -- Timestamps
    accepted_at TIMESTAMP,
    rejected_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(project_id, editor_id) -- One proposal per editor per project
);

CREATE INDEX idx_proposals_project ON proposals(project_id);
CREATE INDEX idx_proposals_editor ON proposals(editor_id);
CREATE INDEX idx_proposals_status ON proposals(status);

-- ==========================================
-- 10. REVIEWS (Client Reviews Editor)
-- ==========================================

CREATE TABLE reviews (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    client_id UUID NOT NULL REFERENCES users(id),
    editor_id UUID NOT NULL REFERENCES users(id),
    
    -- Rating (1-5 stars)
    rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
    
    -- Detailed ratings
    communication_rating INTEGER CHECK (communication_rating >= 1 AND communication_rating <= 5),
    quality_rating INTEGER CHECK (quality_rating >= 1 AND quality_rating <= 5),
    timeliness_rating INTEGER CHECK (timeliness_rating >= 1 AND timeliness_rating <= 5),
    
    -- Review content
    review_text TEXT,
    
    -- Editor response
    editor_response TEXT,
    responded_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(project_id) -- One review per project
);

CREATE INDEX idx_reviews_editor ON reviews(editor_id);
CREATE INDEX idx_reviews_client ON reviews(client_id);
CREATE INDEX idx_reviews_rating ON reviews(rating);

-- ==========================================
-- 11. MESSAGES (In-App Chat)
-- ==========================================

CREATE TABLE messages (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    project_id UUID REFERENCES projects(id) ON DELETE CASCADE,
    sender_id UUID NOT NULL REFERENCES users(id),
    receiver_id UUID NOT NULL REFERENCES users(id),
    
    -- Message content
    content TEXT NOT NULL,
    
    -- Attachments
    attachments JSONB, -- [{name: 'file.pdf', url: 'https://...', size: 12345}]
    
    -- Status
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_messages_project ON messages(project_id);
CREATE INDEX idx_messages_sender ON messages(sender_id);
CREATE INDEX idx_messages_receiver ON messages(receiver_id);
CREATE INDEX idx_messages_read ON messages(is_read);
CREATE INDEX idx_messages_created ON messages(created_at DESC);

-- ==========================================
-- 12. NOTIFICATIONS
-- ==========================================

CREATE TABLE notifications (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    -- Notification content
    type VARCHAR(50) NOT NULL, -- new_proposal, project_completed, payment_received, etc.
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    
    -- Related entities
    related_id UUID, -- ID of related project, payment, etc.
    related_type VARCHAR(50), -- project, payment, message, etc.
    action_url TEXT, -- Link to take action
    
    -- Status
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(is_read);
CREATE INDEX idx_notifications_created ON notifications(created_at DESC);

-- ==========================================
-- 13. ADMIN LOGS (Audit Trail)
-- ==========================================

CREATE TABLE admin_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    admin_id UUID NOT NULL REFERENCES users(id),
    
    -- Action details
    action VARCHAR(100) NOT NULL, -- ban_user, verify_editor, feature_portfolio, etc.
    target_type VARCHAR(50), -- user, project, payment, etc.
    target_id UUID,
    
    -- Additional context
    details JSONB,
    ip_address INET,
    user_agent TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_admin_logs_admin ON admin_logs(admin_id);
CREATE INDEX idx_admin_logs_action ON admin_logs(action);
CREATE INDEX idx_admin_logs_created ON admin_logs(created_at DESC);

-- ==========================================
-- 14. FEATURED CONTENT (Homepage Curation)
-- ==========================================

CREATE TABLE featured_content (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    content_type VARCHAR(20) NOT NULL, -- editor, portfolio_item
    content_id UUID NOT NULL,
    
    -- Display details
    title VARCHAR(200),
    subtitle VARCHAR(200),
    display_order INTEGER DEFAULT 0,
    
    -- Active period
    featured_from TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    featured_until TIMESTAMP,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by UUID REFERENCES users(id)
);

CREATE INDEX idx_featured_type ON featured_content(content_type);
CREATE INDEX idx_featured_active ON featured_content(is_active);
CREATE INDEX idx_featured_order ON featured_content(display_order);

-- ==========================================
-- TRIGGERS FOR AUTO-UPDATE
-- ==========================================

-- Auto-update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply trigger to all tables with updated_at
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_user_profiles_updated_at BEFORE UPDATE ON user_profiles
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_editor_profiles_updated_at BEFORE UPDATE ON editor_profiles
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_portfolio_items_updated_at BEFORE UPDATE ON portfolio_items
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_projects_updated_at BEFORE UPDATE ON projects
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_video_comments_updated_at BEFORE UPDATE ON video_comments
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_payments_updated_at BEFORE UPDATE ON payments
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_proposals_updated_at BEFORE UPDATE ON proposals
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_reviews_updated_at BEFORE UPDATE ON reviews
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ==========================================
-- SAMPLE DATA FOR TESTING
-- ==========================================

-- Sample Admin User
INSERT INTO users (email, password_hash, role, is_verified) VALUES
('admin@frames.com', crypt('admin123', gen_salt('bf')), 'ADMIN', TRUE);

-- Sample Editor User
INSERT INTO users (email, password_hash, role, is_verified) VALUES
('editor@frames.com', crypt('editor123', gen_salt('bf')), 'EDITOR', TRUE);

-- Sample Client User
INSERT INTO users (email, password_hash, role, is_verified) VALUES
('client@frames.com', crypt('client123', gen_salt('bf')), 'CLIENT', TRUE);

-- ==========================================
-- VIEWS FOR COMMON QUERIES
-- ==========================================

-- Active projects with client and editor info
CREATE VIEW v_active_projects AS
SELECT 
    p.*,
    c.email as client_email,
    up_c.display_name as client_name,
    e.email as editor_email,
    ep.display_name as editor_name,
    ep.average_rating as editor_rating
FROM projects p
JOIN users c ON p.client_id = c.id
LEFT JOIN user_profiles up_c ON c.id = up_c.user_id
LEFT JOIN users e ON p.editor_id = e.id
LEFT JOIN editor_profiles ep ON e.id = ep.user_id
WHERE p.status IN ('OPEN', 'IN_PROGRESS', 'IN_REVIEW');

-- Editor statistics
CREATE VIEW v_editor_stats AS
SELECT 
    ep.user_id,
    ep.display_name,
    ep.average_rating,
    ep.total_reviews,
    COUNT(DISTINCT p.id) as total_projects,
    COUNT(DISTINCT CASE WHEN p.status = 'COMPLETED' THEN p.id END) as completed_projects,
    COUNT(DISTINCT pi.id) as portfolio_count,
    SUM(pi.views_count) as total_portfolio_views
FROM editor_profiles ep
LEFT JOIN projects p ON ep.user_id = p.editor_id
LEFT JOIN portfolio_items pi ON ep.user_id = pi.editor_id
GROUP BY ep.user_id, ep.display_name, ep.average_rating, ep.total_reviews;

-- ==========================================
-- END OF SCHEMA
-- ==========================================
