-- TutorLagbe core schema (utf8mb4 supports Bangla and other Unicode text)
CREATE DATABASE IF NOT EXISTS tutorlagbe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tutorlagbe;

-- Platform accounts. A user may be a student, tutor, or administrator.
CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('student','tutor','admin') NOT NULL DEFAULT 'student',
  google_id VARCHAR(255) NULL,
  profile_picture VARCHAR(255) NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  is_suspended TINYINT(1) NOT NULL DEFAULT 0,
  rejection_reason TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_phone (phone),
  UNIQUE KEY uq_users_google_id (google_id),
  KEY idx_users_role (role),
  KEY idx_users_tutor_approval (role, is_verified, is_suspended)
) ENGINE=InnoDB;

-- Seed administrator. Change this password immediately after importing.
-- Email: admin@tutorlagbe.com | Password: Admin@123
INSERT INTO users (full_name, email, phone, password, role, is_verified)
VALUES ('TutorLagbe Administrator', 'admin@tutorlagbe.com', '01700000000', '$2y$10$6QhclJI361c3rqRqk5/OYuFfExMFoVGQcdX.cZzEy3AugQSELL5vC', 'admin', 1);

-- Subjects shown in discovery and later associated with tutors.
CREATE TABLE subjects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  icon VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_subjects_name (name)
) ENGINE=InnoDB;

-- Extended tutor profile, one record per tutor account.
CREATE TABLE tutors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  bio TEXT NULL,
  education VARCHAR(255) NULL,
  experience_years TINYINT UNSIGNED NOT NULL DEFAULT 0,
  hourly_rate DECIMAL(10,2) NULL,
  rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tutors_user_id (user_id),
  KEY idx_tutors_featured (is_featured),
  CONSTRAINT fk_tutors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO subjects (name, icon) VALUES
('Mathematics', 'bi-calculator'), ('Physics', 'bi-atom'), ('English', 'bi-translate'),
('Bangla', 'bi-book'), ('ICT', 'bi-laptop'), ('Admission Coaching', 'bi-mortarboard');

-- Future tables: bookings, reviews, payments, messages, subject_tutor (many-to-many)
