-- ScholarSeek Complete Database Schema
-- Copy and paste this entire code into phpMyAdmin SQL tab

-- Users table (for admin/staff login)
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  fullname VARCHAR(255),
  role VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Students table
CREATE TABLE students (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  fullname VARCHAR(255),
  contact_number VARCHAR(20),
  address TEXT,
  date_of_birth DATE,
  gpa DECIMAL(3, 2),
  course VARCHAR(255),
  year_level VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Staff table
CREATE TABLE staff (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  fullname VARCHAR(255),
  position VARCHAR(255),
  department VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Scholarships table
CREATE TABLE scholarships (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  amount DECIMAL(10, 2),
  requirements TEXT,
  deadline DATE,
  status VARCHAR(50) DEFAULT 'active',
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES staff(id)
);

-- Applications table
CREATE TABLE applications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  scholarship_id INT NOT NULL,
  status VARCHAR(50) DEFAULT 'pending',
  gpa_requirement DECIMAL(3, 2),
  documents_submitted VARCHAR(255),
  application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_by INT,
  reviewed_date DATETIME,
  rejection_reason TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES staff(id)
);

-- Documents table (for file uploads)
CREATE TABLE documents (
  id INT PRIMARY KEY AUTO_INCREMENT,
  application_id INT NOT NULL,
  document_type VARCHAR(255),
  file_path VARCHAR(255),
  file_name VARCHAR(255),
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- Notifications table
CREATE TABLE notifications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  user_type VARCHAR(50),
  title VARCHAR(255),
  message TEXT,
  type VARCHAR(50),
  is_read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Logs table (for system logging)
CREATE TABLE logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  user_type VARCHAR(50),
  action VARCHAR(255),
  details TEXT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert test data
INSERT INTO users (email, password, fullname, role) 
VALUES ('admin@biliran.edu.ph', '$2y$10$YourHashedPasswordHere', 'Administrator', 'admin');

INSERT INTO staff (email, password, fullname, position, department) 
VALUES ('staff@biliran.edu.ph', '$2y$10$YourHashedPasswordHere', 'Staff Member', 'Scholarship Officer', 'Student Services');

INSERT INTO students (email, password, fullname, contact_number, course, year_level) 
VALUES ('student@biliran.edu.ph', '$2y$10$YourHashedPasswordHere', 'Sample Student', '09123456789', 'Bachelor of Science in Computer Science', '3rd Year');

INSERT INTO scholarships (name, description, amount, requirements, deadline, status, created_by) 
VALUES ('Merit Scholarship', 'For students with excellent academic performance', 50000.00, 'GPA 3.5 or higher', '2025-12-31', 'active', 1);
