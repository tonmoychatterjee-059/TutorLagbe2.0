-- TutorLagbe core schema (utf8mb4 supports Bangla and other Unicode text)
CREATE DATABASE IF NOT EXISTS tutorlagbe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tutorlagbe;

-- Platform accounts. A user may be a student, tutor, or administrator.
CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  address VARCHAR(150) NULL,
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
  qualification TEXT NULL,
  experience_years TINYINT UNSIGNED NOT NULL DEFAULT 0,
  hourly_rate DECIMAL(10,2) NULL,
  rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  medium ENUM('Bangla','English','Both') NOT NULL DEFAULT 'Both',
  gender ENUM('Male','Female','Other') NULL,
  location VARCHAR(150) NULL,
  cover_photo VARCHAR(255) NULL,
  demo_video VARCHAR(255) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tutors_user_id (user_id),
  KEY idx_tutors_featured (is_featured),
  KEY idx_tutors_location (location),
  CONSTRAINT fk_tutors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO subjects (name, icon) VALUES
('Mathematics', 'bi-calculator'), ('Physics', 'bi-atom'), ('English', 'bi-translate'),
('Bangla', 'bi-book'), ('ICT', 'bi-laptop'), ('Admission Coaching', 'bi-mortarboard');

-- Tutor subject and availability
CREATE TABLE tutor_subjects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tutor_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  class_level VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_tutor_subject_level (tutor_id, subject_id, class_level),
  KEY idx_tutor_subjects_tutor_id (tutor_id),
  KEY idx_tutor_subjects_subject_id (subject_id),
  CONSTRAINT fk_tutor_subjects_tutor FOREIGN KEY (tutor_id) REFERENCES tutors(id) ON DELETE CASCADE,
  CONSTRAINT fk_tutor_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE availability (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tutor_id BIGINT UNSIGNED NOT NULL,
  day_of_week ENUM('Sat','Sun','Mon','Tue','Wed','Thu','Fri') NULL,
  specific_date DATE NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  is_recurring TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_availability_tutor_id (tutor_id),
  KEY idx_availability_specific_date (specific_date),
  CONSTRAINT fk_availability_tutor FOREIGN KEY (tutor_id) REFERENCES tutors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Booking and payment workflow
CREATE TABLE bookings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  tutor_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  booking_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  session_type ENUM('online','offline') NOT NULL,
  address VARCHAR(255) NULL,
  status ENUM('pending','accepted','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
  price DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_bookings_student_id (student_id),
  KEY idx_bookings_tutor_id (tutor_id),
  KEY idx_bookings_subject_id (subject_id),
  KEY idx_bookings_status (status),
  KEY idx_bookings_tutor_schedule (tutor_id, booking_date, start_time, status),
  CONSTRAINT fk_bookings_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_bookings_tutor FOREIGN KEY (tutor_id) REFERENCES tutors(id) ON DELETE CASCADE,
  CONSTRAINT fk_bookings_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('bkash','nagad','rocket','visa','mastercard') NOT NULL,
  transaction_id VARCHAR(100) NULL,
  status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payments_transaction_id (transaction_id),
  KEY idx_payments_booking_id (booking_id),
  KEY idx_payments_student_id (student_id),
  KEY idx_payments_status (status),
  CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Reviews. Application code must recalculate tutors.rating after every review insert, update, or delete.
CREATE TABLE reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  tutor_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reviews_booking_id (booking_id),
  KEY idx_reviews_tutor_id (tutor_id),
  KEY idx_reviews_student_id (student_id),
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_tutor FOREIGN KEY (tutor_id) REFERENCES tutors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Messaging and in-app notifications
CREATE TABLE messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sender_id BIGINT UNSIGNED NOT NULL,
  receiver_id BIGINT UNSIGNED NOT NULL,
  message TEXT NULL,
  file_url VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_messages_receiver_read (receiver_id, is_read, created_at),
  KEY idx_messages_conversation (sender_id, receiver_id, created_at),
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  -- booking_pending lets the tutor know a request awaits an accept/reject decision.
  type ENUM('booking_pending','booking_accepted','booking_rejected','session_reminder','payment_success','new_message') NOT NULL,
  message VARCHAR(500) NOT NULL,
  related_id BIGINT UNSIGNED NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user_read (user_id, is_read, created_at),
  KEY idx_notifications_type (type),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Public contact form messages (production should also add SMTP delivery/moderation).
CREATE TABLE contact_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_contact_messages_created_at (created_at)
) ENGINE=InnoDB;

-- Application settings used for SMTP, payment gateways, and video-call configuration.
CREATE TABLE app_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value LONGTEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Password reset tokens. Store the hash only; the user receives the raw token by email.
CREATE TABLE password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_password_resets_email (email),
  KEY idx_password_resets_token_hash (token_hash),
  KEY idx_password_resets_expires_at (expires_at),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
