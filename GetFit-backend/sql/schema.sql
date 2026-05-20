-- GetFit Database Schema
-- Import this in phpMyAdmin: http://localhost/phpmyadmin
-- Database > Import > choose this file

CREATE DATABASE IF NOT EXISTS getfit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE getfit;

CREATE TABLE IF NOT EXISTS users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  role         ENUM('admin','trainer','member') NOT NULL,
  username     VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email        VARCHAR(120),
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS members (
  user_id         INT PRIMARY KEY,
  full_name       VARCHAR(120),
  age             INT,
  gender          VARCHAR(20),
  phone           VARCHAR(20),
  height          DECIMAL(5,2),
  weight          DECIMAL(5,2),
  fitness_goal    VARCHAR(80),
  registration_date DATE,
  status          ENUM('active','inactive') DEFAULT 'active',
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS trainers (
  user_id         INT PRIMARY KEY,
  full_name       VARCHAR(120),
  phone           VARCHAR(20),
  specialization  VARCHAR(80),
  experience      VARCHAR(40),
  certification   VARCHAR(120),
  hours_available VARCHAR(80),
  status          ENUM('active','inactive') DEFAULT 'active',
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS memberships (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  member_id  INT NOT NULL,
  plan       ENUM('monthly','quarterly','yearly') NOT NULL,
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL,
  status     ENUM('active','expired') DEFAULT 'active',
  FOREIGN KEY (member_id) REFERENCES members(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS trainer_assignments (
  member_id   INT PRIMARY KEY,
  trainer_id  INT NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id)  REFERENCES members(user_id)  ON DELETE CASCADE,
  FOREIGN KEY (trainer_id) REFERENCES trainers(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS workout_plans (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  member_id    INT NOT NULL,
  trainer_id   INT,
  duration     VARCHAR(40),
  days_per_week VARCHAR(20),
  assigned_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS workout_exercises (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  plan_id    INT NOT NULL,
  exercise_text VARCHAR(255),
  sort_order INT DEFAULT 0,
  FOREIGN KEY (plan_id) REFERENCES workout_plans(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS diet_plans (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  member_id      INT NOT NULL,
  trainer_id     INT,
  daily_calories INT,
  assigned_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS diet_meals (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  plan_id    INT NOT NULL,
  meal_time  VARCHAR(20),
  food       VARCHAR(255),
  calories   INT,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (plan_id) REFERENCES diet_plans(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS progress_entries (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  member_id  INT NOT NULL,
  entry_date DATE,
  weight     DECIMAL(5,2),
  bmi        DECIMAL(5,2),
  notes      TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS settings (
  setting_key   VARCHAR(60) PRIMARY KEY,
  setting_value TEXT
);

-- Default gym settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('gym_name',             'GetFit'),
('gym_address',          '123 Fitness St, New York, NY 10001'),
('gym_email',            'contact@getfit.com'),
('gym_phone',            '+1 555-987-6543'),
('allow_registrations',  '1'),
('max_members',          '500');

CREATE TABLE IF NOT EXISTS trainer_sessions (
  session_id   INT AUTO_INCREMENT PRIMARY KEY,
  trainer_id   INT NOT NULL,
  member_id    INT NOT NULL,
  session_date DATE NOT NULL,
  session_time TIME NOT NULL,
  status       ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
  FOREIGN KEY (trainer_id) REFERENCES trainers(user_id) ON DELETE CASCADE,
  FOREIGN KEY (member_id) REFERENCES members(user_id) ON DELETE CASCADE
);
