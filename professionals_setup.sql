-- Professionals Table
CREATE TABLE IF NOT EXISTS professionals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  professional_type VARCHAR(50) NOT NULL,
  name VARCHAR(100) NOT NULL,
  qualification VARCHAR(100),
  experience_years INT,
  specialization VARCHAR(100),
  bio TEXT,
  phone VARCHAR(20),
  email VARCHAR(100),
  address TEXT,
  city VARCHAR(50),
  state VARCHAR(50),
  languages VARCHAR(200),
  consultation_fee DECIMAL(10, 2),
  status VARCHAR(20) DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Appointments/Bookings Table
CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  professional_id INT NOT NULL,
  user_id INT NOT NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  consultation_type VARCHAR(50),
  notes TEXT,
  status VARCHAR(20) DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sample Data: Professionals
INSERT INTO professionals (professional_type, name, qualification, experience_years, specialization, bio, phone, email, city, state, consultation_fee, languages) VALUES
('CA', 'Rajesh Kumar', 'CA (ICAI)', 12, 'Income Tax & Investment Planning', 'Experienced CA specializing in personal income tax and investment optimization for high net-worth individuals.', '9876543210', 'rajesh@example.com', 'Mumbai', 'Maharashtra', 500, 'Hindi, English'),
('CA', 'Priya Sharma', 'CA (ICAI)', 8, 'GST & Corporate Tax', 'Expert in GST compliance and corporate tax planning. Helps businesses optimize tax structure.', '9765432109', 'priya@example.com', 'Delhi', 'Delhi', 600, 'Hindi, English'),
('Financial Advisor', 'Amit Patel', 'CFP, MBA Finance', 10, 'Investment & Wealth Management', 'Certified financial planner helping clients build diversified investment portfolios.', '8765432109', 'amit@example.com', 'Bangalore', 'Karnataka', 400, 'Hindi, English, Gujarati'),
('Tax Consultant', 'Neha Singh', 'B.Com, Tax Specialist', 6, 'Personal Tax Planning', 'Specializes in helping individuals and freelancers with tax planning and returns.', '7654321098', 'neha@example.com', 'Pune', 'Maharashtra', 350, 'Hindi, English'),
('Financial Advisor', 'Vikram Reddy', 'CFA, MBA', 15, 'Retirement & Education Planning', 'Expert in long-term financial planning for retirement and education goals.', '6543210987', 'vikram@example.com', 'Hyderabad', 'Telangana', 700, 'Hindi, English, Telugu');
