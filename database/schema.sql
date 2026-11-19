CREATE DATABASE IF NOT EXISTS conference_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE conference_manager;

CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_users_role CHECK (role IN ('admin','user','reviewer'))
) ENGINE=InnoDB;

CREATE TABLE conferences (
    conference_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255) NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    submission_deadline DATETIME NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_conferences_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    CONSTRAINT chk_conferences_dates CHECK (end_date > start_date),
    CONSTRAINT chk_conferences_status CHECK (status IN ('draft','upcoming','open','closed','completed')),
    INDEX idx_conferences_date_status (start_date, status)
) ENGINE=InnoDB;

CREATE TABLE registrations (
    registration_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    conference_id INT UNSIGNED NOT NULL,
    registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    CONSTRAINT fk_registrations_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_registrations_conference FOREIGN KEY (conference_id) REFERENCES conferences(conference_id) ON DELETE CASCADE,
    CONSTRAINT uq_registration UNIQUE (user_id, conference_id),
    CONSTRAINT chk_registration_status CHECK (status IN ('pending','approved','cancelled')),
    INDEX idx_registrations_status (conference_id, status)
) ENGINE=InnoDB;

CREATE TABLE sessions (
    session_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conference_id INT UNSIGNED NOT NULL,
    session_name VARCHAR(255) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    room VARCHAR(100) NOT NULL,
    CONSTRAINT fk_sessions_conference FOREIGN KEY (conference_id) REFERENCES conferences(conference_id) ON DELETE CASCADE,
    CONSTRAINT chk_sessions_time CHECK (end_time > start_time),
    INDEX idx_sessions_schedule (conference_id, start_time)
) ENGINE=InnoDB;

CREATE TABLE papers (
    paper_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    abstract TEXT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    topic VARCHAR(100) NOT NULL,
    author_id INT UNSIGNED NOT NULL,
    conference_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(30) NOT NULL DEFAULT 'submitted',
    CONSTRAINT fk_papers_author FOREIGN KEY (author_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_papers_conference FOREIGN KEY (conference_id) REFERENCES conferences(conference_id) ON DELETE CASCADE,
    CONSTRAINT fk_papers_session FOREIGN KEY (session_id) REFERENCES sessions(session_id) ON DELETE SET NULL,
    CONSTRAINT chk_papers_status CHECK (status IN ('submitted','under_review','accepted','rejected','withdrawn')),
    INDEX idx_papers_author (author_id, submitted_at),
    INDEX idx_papers_workflow (conference_id, status)
) ENGINE=InnoDB;

CREATE TABLE reviews (
    review_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    paper_id INT UNSIGNED NOT NULL,
    reviewer_id INT UNSIGNED NOT NULL,
    score INT NULL,
    comments TEXT NULL,
    recommendation VARCHAR(30) NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    CONSTRAINT fk_reviews_paper FOREIGN KEY (paper_id) REFERENCES papers(paper_id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    CONSTRAINT uq_review_assignment UNIQUE (paper_id, reviewer_id),
    CONSTRAINT chk_reviews_score CHECK (score IS NULL OR score BETWEEN 1 AND 10),
    CONSTRAINT chk_reviews_recommendation CHECK (recommendation IS NULL OR recommendation IN ('accept','minor_revision','major_revision','reject')),
    INDEX idx_reviews_reviewer (reviewer_id, reviewed_at)
) ENGINE=InnoDB;

CREATE TABLE admin_audit_logs (
    audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    target_type VARCHAR(30) NOT NULL,
    target_id INT UNSIGNED NOT NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    reason VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_audit_actor FOREIGN KEY (actor_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_admin_audit_created (created_at),
    INDEX idx_admin_audit_target (target_type, target_id)
) ENGINE=InnoDB;

-- Bảng bảo mật bổ sung để chống brute-force khi đăng nhập.
CREATE TABLE login_attempts (
    attempt_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_throttle (email, ip_hash, attempted_at)
) ENGINE=InnoDB;

