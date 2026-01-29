-- Tax Deductions Table
CREATE TABLE IF NOT EXISTS tax_deductions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category VARCHAR(100) NOT NULL,
  description VARCHAR(255),
  amount DECIMAL(12, 2) NOT NULL,
  deduction_date DATE NOT NULL,
  proof_of_expense VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tax Planning Data Table
CREATE TABLE IF NOT EXISTS tax_planning (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  financial_year VARCHAR(20),
  gross_income DECIMAL(12, 2),
  total_deductions DECIMAL(12, 2) DEFAULT 0,
  taxable_income DECIMAL(12, 2),
  tax_slabs_used JSON,
  estimated_tax DECIMAL(12, 2),
  tax_bracket VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ITR Preparation Table
CREATE TABLE IF NOT EXISTS itr_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  financial_year VARCHAR(20),
  gross_income DECIMAL(12, 2),
  total_deductions DECIMAL(12, 2),
  taxable_income DECIMAL(12, 2),
  tax_payable DECIMAL(12, 2),
  surcharge DECIMAL(12, 2),
  health_cess DECIMAL(12, 2),
  total_tax DECIMAL(12, 2),
  status VARCHAR(50) DEFAULT 'draft',
  submission_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
