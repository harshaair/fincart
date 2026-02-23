-- Migration: add professional_requests table (stores user->professional messages/requests)

CREATE TABLE IF NOT EXISTS professional_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  professional_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  name VARCHAR(150),
  email VARCHAR(150),
  message TEXT,
  preferred_date DATE DEFAULT NULL,
  status VARCHAR(20) DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
