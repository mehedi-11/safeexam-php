-- SafeExam.AI - MySQL Relational Schema
CREATE DATABASE IF NOT EXISTS `safeexam_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `safeexam_db`;

-- 1. Users Table (Admin, Teacher, Student)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id_str` VARCHAR(50) NULL,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'teacher', 'student') NOT NULL,
  `status` ENUM('pending', 'approved', 'active', 'suspended', 'blocked') DEFAULT 'active',
  `dob` DATE NULL,
  `university` VARCHAR(150) NULL,
  `address` TEXT NULL,
  `years_of_experience` INT DEFAULT 0,
  `is_super_admin` TINYINT(1) DEFAULT 0,
  `joining_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Events Table
CREATE TABLE IF NOT EXISTS `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` LONGTEXT NULL,
  `event_date` DATETIME NOT NULL,
  `image` LONGTEXT NULL,
  `status` ENUM('live', 'closed') DEFAULT 'live',
  `secret_code` VARCHAR(100) NULL,
  `created_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Event Registrations Table
CREATE TABLE IF NOT EXISTS `event_registrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `student_name` VARCHAR(150) NOT NULL,
  `student_email` VARCHAR(150) NOT NULL,
  `student_roll` VARCHAR(50) NULL,
  `registered_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_event_reg_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_reg_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Exams Table
CREATE TABLE IF NOT EXISTS `exams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `event_id` INT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `course_name` VARCHAR(150) NULL,
  `course_code` VARCHAR(50) NULL,
  `university_name` VARCHAR(150) NULL,
  `exam_date` DATETIME NOT NULL,
  `duration_minutes` INT NOT NULL,
  `total_marks` INT NOT NULL,
  `exam_password` VARCHAR(100) NULL,
  `is_live` TINYINT(1) DEFAULT 0,
  `results_published` TINYINT(1) DEFAULT 0,
  `max_attempts` INT DEFAULT 1,
  `must_on_camera` TINYINT(1) DEFAULT 1,
  `must_on_microphone` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_exams_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exams_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Exam Questions Table
CREATE TABLE IF NOT EXISTS `exam_questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `question_text` TEXT NOT NULL,
  `question_type` ENUM('mcq', 'written') DEFAULT 'mcq',
  `option_a` TEXT NULL,
  `option_b` TEXT NULL,
  `option_c` TEXT NULL,
  `option_d` TEXT NULL,
  `correct_option` VARCHAR(50) NULL,
  `marks` INT DEFAULT 1,
  `explanation` TEXT NULL,
  `order_num` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_questions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Student Exams Table (Attempts & Summary)
CREATE TABLE IF NOT EXISTS `student_exams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `exam_id` INT NOT NULL,
  `attempt_number` INT DEFAULT 1,
  `total_score` INT DEFAULT 0,
  `status` ENUM('in_progress', 'completed', 'terminated') DEFAULT 'in_progress',
  `demerit_points` INT DEFAULT 0,
  `submission_reason` TEXT NULL,
  `start_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  CONSTRAINT `fk_student_exams_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_exams_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Student Answers Table
CREATE TABLE IF NOT EXISTS `student_answers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_exam_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `exam_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `selected_option` VARCHAR(50) NULL,
  `written_answer` TEXT NULL,
  `marks_obtained` INT DEFAULT 0,
  `is_correct` TINYINT(1) DEFAULT 0,
  `answered_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_student_answers_attempt` FOREIGN KEY (`student_exam_id`) REFERENCES `student_exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_answers_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_answers_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_answers_question` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Proctoring Logs Table
CREATE TABLE IF NOT EXISTS `proctoring_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `activity_type` VARCHAR(100) NOT NULL,
  `demerit_points` INT DEFAULT 0,
  `details` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_proctoring_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_proctoring_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Admin Notifications Table
CREATE TABLE IF NOT EXISTS `admin_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. One-Time Passwords (OTP) Table
CREATE TABLE IF NOT EXISTS `otps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(150) NOT NULL,
  `otp` VARCHAR(10) NOT NULL,
  `purpose` ENUM('registration', 'reset_password') DEFAULT 'registration',
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email_purpose` (`email`, `purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Seed Users (Password: admin123, teacher123, student123)
INSERT INTO `users` (`student_id_str`, `name`, `email`, `password`, `role`, `status`, `university`, `is_super_admin`)
VALUES
(NULL, 'System Administrator', 'mehedimridul1919@gmail.com', '$2y$10$SXR26LeAdrl1e8SfasmKZuoerXXXC1vh13WfQ3EkPtpGg7PzW86DG', 'admin', 'active', 'SafeExam University', 1),
(NULL, 'Dr. MD Mehedi Hasan', 'teacher@safeexam.ai', '$2y$10$fRcFHTXjoJjSY0p1w.Kh4e.4Z/MSe4PXCEDWZuBMikF2DHoRowHbG', 'teacher', 'approved', 'Primeasia University', 0),
('232004048', 'MST Onamika Jannat Ara', 'student@safeexam.ai', '$2y$10$wC3r3PcBTHEeR3ZkPlEgVerUm7pFzyKkC3anbiAGyyG7KiPbeekFe', 'student', 'approved', 'Primeasia University', 0)
ON DUPLICATE KEY UPDATE `email` = `email`;

-- Sample Exam
INSERT INTO `exams` (`id`, `teacher_id`, `title`, `description`, `course_name`, `course_code`, `university_name`, `exam_date`, `duration_minutes`, `total_marks`, `exam_password`, `is_live`, `results_published`, `max_attempts`)
VALUES
(1, 2, 'Database Management Systems (Demo)', 'Final Term Mid-Review Examination with AI Proctoring.', 'Database Systems', 'CSE-412', 'Primeasia University', NOW(), 15, 10, 'safe123', 1, 1, 2)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Sample Exam Questions
INSERT INTO `exam_questions` (`id`, `exam_id`, `question_text`, `question_type`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `marks`, `order_num`)
VALUES
(1, 1, 'Which SQL command is used to remove a table and all its data completely from database?', 'mcq', 'DELETE', 'DROP', 'REMOVE', 'TRUNCATE', 'B', 2, 1),
(2, 1, 'What is the primary characteristic of a PRIMARY KEY in relational databases?', 'mcq', 'Can contain duplicate values', 'Can be NULL anytime', 'Must be UNIQUE and NOT NULL', 'Can only be an Integer', 'C', 2, 2),
(3, 1, 'Which of the following is NOT an ACID property in Database Management?', 'mcq', 'Atomicity', 'Consistency', 'Integrity', 'Durability', 'C', 2, 3),
(4, 1, 'Which normal form eliminates partial functional dependency?', 'mcq', '1NF', '2NF', '3NF', 'BCNF', 'B', 2, 4),
(5, 1, 'Explain the fundamental difference between Clustered Index and Non-Clustered Index.', 'written', NULL, NULL, NULL, NULL, NULL, 2, 5)
ON DUPLICATE KEY UPDATE `id` = `id`;

