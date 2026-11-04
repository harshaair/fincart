<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle ITR submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_itr') {
    $fy = $_POST['financial_year'];
    $total_income = floatval($_POST['total_income']);
    $total_deductions = floatval($_POST['total_deductions']);
    $tax_paid = floatval($_POST['tax_paid']);
    $notes = $_POST['notes'];

    if (empty($fy) || $total_income <= 0) {
        $error = 'Please fill in all required fields';
    } else {
        $sql = "INSERT INTO itr_submissions (user_id, financial_year, total_income, total_deductions, tax_paid, submission_notes, status, submission_date) VALUES (?, ?, ?, ?, ?, ?, 'Draft', NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isddds", $user_id, $fy, $total_income, $total_deductions, $tax_paid, $notes);
        
        if ($stmt->execute()) {
            $message = 'ITR record saved as draft! Review and finalize before filing.';
        } else {
            $error = 'Failed to save ITR. Please try again.';
        }
    }
}

// Fetch ITR records
$itr_sql = "SELECT * FROM itr_submissions WHERE user_id = ? ORDER BY financial_year DESC";
$itr_stmt = $conn->prepare($itr_sql);
$itr_stmt->bind_param("i", $user_id);
$itr_stmt->execute();
$itr_result = $itr_stmt->get_result();
$itr_records = [];

while ($row = $itr_result->fetch_assoc()) {
    $itr_records[] = $row;
}

// Get current year data
$current_year = date('Y');
$income_sql = "SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'income' AND YEAR(date) = ?";
$income_stmt = $conn->prepare($income_sql);
$income_stmt->bind_param("ii", $user_id, $current_year);
$income_stmt->execute();
$income_result = $income_stmt->get_result();
$income_row = $income_result->fetch_assoc();
$current_income = $income_row['total'] ?? 0;

$deduction_sql = "SELECT SUM(amount) as total FROM tax_deductions WHERE user_id = ? AND YEAR(deduction_date) = ?";
$deduction_stmt = $conn->prepare($deduction_sql);
$deduction_stmt->bind_param("ii", $user_id, $current_year);
$deduction_stmt->execute();
$deduction_result = $deduction_stmt->get_result();
$deduction_row = $deduction_result->fetch_assoc();
$current_deductions = $deduction_row['total'] ?? 0;

// Form 16 simulation data
$form16_details = [
    'salary_gross' => $current_income,
    'allowances' => round($current_income * 0.15, 0),
    'deductions' => round($current_income * 0.10, 0),
    'net_salary' => round($current_income * 0.75, 0)
];

// ITR forms available
$itr_forms = [
    ['form' => 'ITR-1', 'name' => 'SAHAJ', 'for' => 'Individuals with income from salary, one house, and other sources', 'income_limit' => '50 Lakhs'],
    ['form' => 'ITR-2', 'name' => 'SUGAM', 'for' => 'Individuals and HUF with income from capital gains, etc.', 'income_limit' => 'No limit'],
    ['form' => 'ITR-3', 'name' => 'VJAY', 'for' => 'Individuals with business/professional income', 'income_limit' => 'No limit'],
    ['form' => 'ITR-4', 'name' => 'SUGAM', 'for' => 'Individuals with presumptive income from business/profession', 'income_limit' => 'Up to 5 Cr'],
];

// Filing deadlines for different circumstances
$filing_deadlines = [
    ['scenario' => 'Normal Taxpayers', 'deadline' => 'July 31, 2024 (for FY 2023-24)', 'penalty' => 'Late fee up to ₹10,000'],
    ['scenario' => 'With Audit', 'deadline' => 'October 31, 2024', 'penalty' => 'Fine up to ₹1,00,000'],
    ['scenario' => 'Revised Return', 'deadline' => 'December 31 (same FY)', 'penalty' => 'No penalty, but interest may apply'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITR Preparation - FinCart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            —primary: #1a1a1a;
            —secondary: #f8f6f3;
            —accent: #d4af37;
            —text-dark: #2c2c2c;
            —text-light: #666;
            —white: #ffffff;
            —off-white: #fafaf9;
            —success: #27ae60;
            —danger: #e74c3c;
            —warning: #f39c12;
            —info: #3498db;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(—off-white) 0%, var(—secondary) 100%);
            color: var(—text-dark);
            min-height: 100vh;
        }

        header {
            position: sticky;
            top: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            padding: 20px 0;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, var(—primary) 0%, var(—accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -1px;
        }

        .nav-links {
            display: flex;
            gap: 30px;
        }

        .nav-link {
            color: var(—text-dark);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(—accent);
        }

        .logout-btn {
            padding: 8px 20px;
            background: var(—danger);
            color: var(—white);
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            opacity: 0.9;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            color: var(—primary);
            margin-bottom: 10px;
            letter-spacing: -1px;
        }

        .page-header p {
            color: var(—text-light);
            font-size: 16px;
        }

        .disclaimer {
            background: rgba(231, 76, 60, 0.1);
            border: 2px solid var(—danger);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 30px;
            color: var(—text-dark);
            font-size: 13px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid var(—success);
            color: var(—success);
        }

        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid var(—danger);
            color: var(—danger);
        }

        .card {
            background: var(—white);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(—primary);
            margin-bottom: 20px;
        }

        .card h3 {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            color: var(—primary);
            margin: 20px 0 15px 0;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: 600;
            color: var(—primary);
            margin-bottom: 8px;
            font-size: 14px;
        }

        input[type="number"],
        input[type="text"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(—accent);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
        }

        .btn-primary {
            background: var(—primary);
            color: var(—white);
        }

        .btn-primary:hover {
            background: var(—accent);
            color: var(—primary);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(—off-white);
            color: var(—primary);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .btn-secondary:hover {
            background: var(—accent);
            border-color: var(—accent);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .itr-form-card {
            background: var(—off-white);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid var(—accent);
        }

        .itr-form-title {
            font-weight: 700;
            color: var(—primary);
            margin-bottom: 5px;
        }

        .itr-form-desc {
            color: var(—text-light);
            font-size: 13px;
            margin-bottom: 8px;
        }

        .itr-form-limit {
            display: inline-block;
            background: var(—info);
            color: var(—white);
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 12px;
        }

        .deadline-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .deadline-table th {
            background: var(—off-white);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid rgba(0, 0, 0, 0.1);
        }

        .deadline-table td {
            padding: 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .deadline-table tr:hover {
            background: var(—off-white);
        }

        .itr-records {
            display: grid;
            gap: 15px;
            margin-top: 20px;
        }

        .itr-record {
            background: var(—off-white);
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid var(—success);
        }

        .itr-record.draft {
            border-left-color: var(—warning);
        }

        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .record-year {
            font-weight: 700;
            color: var(—primary);
        }

        .record-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-draft {
            background: var(—warning);
            color: var(—white);
        }

        .status-filed {
            background: var(—success);
            color: var(—white);
        }

        .record-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 10px;
        }

        .detail-item {
            font-size: 13px;
        }

        .detail-label {
            color: var(—text-light);
        }

        .detail-value {
            font-weight: 700;
            color: var(—primary);
        }

        .highlight-box {
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(39, 174, 96, 0.1) 100%);
            padding: 20px;
            border-radius: 12px;
            margin: 15px 0;
            border-left: 5px solid var(—accent);
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .record-details {
                grid-template-columns: 1fr;
            }

            .deadline-table {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <!— Header —>
    <header>
        <div class="header-container">
            <div class="log⭐>
                <h1>Fincart</h1>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="transactions.php" class="nav-link">Transactions</a>
                <a href="profile.php" class="nav-link">Settings</a>
                <form method="POST" action="logout.php" style="display: inline;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>
    </header>

    <!— Main Content —>
    <div class="container">
        <div class="page-header">
            <h1>📄 ITR Preparation Assistant</h1>
            <p>Step-by-step guidance for Income Tax Return filing</p>
        </div>

        <!— Disclaimer —>
        <div class="disclaimer">
            ⚠️ <strong>Important Disclaimer:</strong> This ITR Preparation Assistant is for informational purposes only and provides general guidance. It does NOT constitute professional tax or legal advice. The accuracy of ITR forms depends on accurate input and interpretation of your financial data. You MUST:
            <ul style="margin: 10px 0 0 20px;">
                <li>Verify all information before filing</li>
                <li>Consult a qualified Chartered Accountant or tax professional</li>
                <li>Keep all supporting documents and proofs safe</li>
                <li>File on the official Income Tax e-filing portal only</li>
            </ul>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span>✕</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!— ITR Form Selection —>
        <div class="card">
            <h2>Step 1: Select Appropriate ITR Form</h2>
            <p style="color: var(—text-light); margin-bottom: 20px;">
                Choose the correct ITR form based on your income type and amount:
            </p>

            <?php foreach ($itr_forms as $form): ?>
                <div class="itr-form-card">
                    <div class="itr-form-title">
                        <?php echo htmlspecialchars($form['form'] . ' - ' . $form['name']); ?>
                    </div>
                    <div class="itr-form-desc">
                        <?php echo htmlspecialchars($form['for']); ?>
                    </div>
                    <span class="itr-form-limit">Income Limit: <?php echo htmlspecialchars($form['income_limit']); ?></span>
                </div>
            <?php endforeach; ?>

            <div class="highlight-box">
                <strong>💡 Tip:</strong> Most salaried individuals with basic investments file ITR-1 (SAHAJ). Check the IT Department's website or consult your CA if unsure.
            </div>
        </div>

        <!— Filing Deadlines —>
        <div class="card">
            <h2>Step 2: Check Filing Deadlines</h2>
            <p style="color: var(—text-light); margin-bottom: 20px;">
                Filing your ITR on time is important to avoid penalties:
            </p>

            <table class="deadline-table">
                <thead>
                    <tr>
                        <th>Scenario</th>
                        <th>Deadline</th>
                        <th>Penalty for Late Filing</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filing_deadlines as $deadline): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($deadline['scenario']); ?></td>
                            <td><?php echo htmlspecialchars($deadline['deadline']); ?></td>
                            <td><?php echo htmlspecialchars($deadline['penalty']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!— Current Year Data for Reference —>
        <div class="card">
            <h2>Step 3: Verify Your Financial Data</h2>
            <p style="color: var(—text-light); margin-bottom: 20px;">
                Here's a summary of your current financial year data from FinCart:
            </p>

            <div class="form-row">
                <div class="form-group">
                    <label>Total Income (Current FY)</label>
                    <input type="text" value="₹<?php echo number_format($current_income, 0); ?>" readonly style="background: var(—off-white);">
                </div>
                <div class="form-group">
                    <label>Total Deductions (Current FY)</label>
                    <input type="text" value="₹<?php echo number_format($current_deductions, 0); ?>" readonly style="background: var(—off-white);">
                </div>
            </div>

            <div class="highlight-box">
                <strong>⚠️ Important:</strong> These figures should match your official salary statements (Form 16) and tax deduction proofs. Verify before filing your ITR.
            </div>
        </div>

        <!— Create ITR Record —>
        <div class="card">
            <h2>Step 4: Create ITR Record</h2>
            <p style="color: var(—text-light); margin-bottom: 20px;">
                Save your ITR details as a draft before final submission:
            </p>

            <form method="POST">
                <input type="hidden" name="action" value="submit_itr">

                <div class="form-row">
                    <div class="form-group">
                        <label for="financial_year">Financial Year</label>
                        <input type="text" id="financial_year" name="financial_year" placeholder="e.g., 2023-24" required>
                    </div>
                    <div class="form-group">
                        <label for="total_income">Total Income (₹)</label>
                        <input type="number" id="total_income" name="total_income" value="<?php echo $current_income; ?>" step="0.01" min="0" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="total_deductions">Total Deductions (₹)</label>
                        <input type="number" id="total_deductions" name="total_deductions" value="<?php echo $current_deductions; ?>" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="tax_paid">Tax Paid/Deposited (₹)</label>
                        <input type="number" id="tax_paid" name="tax_paid" placeholder="0.00" step="0.01" min="0">
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Additional Notes (ITR Form Selected, etc.)</label>
                    <textarea id="notes" name="notes" placeholder="ITR-1 (SAHAJ) selected" rows="3"></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Save as Draft</button>
            </form>
        </div>

        <!— Saved ITR Records —>
        <?php if (count($itr_records) > 0): ?>
            <div class="card">
                <h2>Step 5: Your ITR Records</h2>
                <p style="color: var(—text-light); margin-bottom: 20px;">
                    Previous ITR filings and drafts:
                </p>

                <div class="itr-records">
                    <?php foreach ($itr_records as $record): ?>
                        <div class="itr-record <?php echo strtolower($record['status']); ?>">
                            <div class="record-header">
                                <span class="record-year">FY <?php echo htmlspecialchars($record['financial_year']); ?></span>
                                <span class="record-status status-<?php echo strtolower($record['status']); ?>">
                                    <?php echo htmlspecialchars($record['status']); ?>
                                </span>
                            </div>
                            <div class="record-details">
                                <div class="detail-item">
                                    <div class="detail-label">Income</div>
                                    <div class="detail-value">₹<?php echo number_format($record['total_income'], 0); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Deductions</div>
                                    <div class="detail-value">₹<?php echo number_format($record['total_deductions'], 0); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Tax Paid</div>
                                    <div class="detail-value">₹<?php echo number_format($record['tax_paid'], 0); ?></div>
                                </div>
                            </div>
                            <div style="margin-top: 10px; color: var(—text-light); font-size: 12px;">
                                Saved: <?php echo date('M d, Y', strtotime($record['submission_date'])); ?>
                                <?php if (!empty($record['submission_notes'])): ?>
                                    • <?php echo htmlspecialchars($record['submission_notes']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!— Required Documents Checklist —>
        <div class="card">
            <h2>Required Documents Checklist</h2>
            <p style="color: var(—text-light); margin-bottom: 20px;">
                Ensure you have all necessary documents before filing:
            </p>

            <h3>Income Proof:</h3>
            <ul style="margin: 15px 0; margin-left: 20px; color: var(—text-light);">
                <li>Form 16 (if salaried)</li>
                <li>Form 16A (if freelance/contractual)</li>
                <li>Business statements/invoices (if self-employed)</li>
                <li>Bank statements showing income deposits</li>
            </ul>

            <h3>Deduction Proof:</h3>
            <ul style="margin: 15px 0; margin-left: 20px; color: var(—text-light);">
                <li>Insurance premium receipts (80C)</li>
                <li>Health insurance policy documents (80D)</li>
                <li>PPF/NPS/ELSS contribution statements</li>
                <li>Education loan interest details (80E)</li>
                <li>Charity/donation receipts (80G)</li>
            </ul>

            <h3>Other Documents:</h3>
            <ul style="margin: 15px 0; margin-left: 20px; color: var(—text-light);">
                <li>PAN card and Aadhaar</li>
                <li>Bank account details for refund</li>
                <li>Capital gains documentation (if applicable)</li>
                <li>Previous year ITR copies (for reference)</li>
            </ul>
        </div>

        <!— Final Steps —>
        <div class="card" style="background: linear-gradient(135deg, rgba(39, 174, 96, 0.1) 0%, rgba(212, 175, 55, 0.1) 100%);">
            <h2>🎯 Final Steps Before Filing</h2>
            
            <ol style="margin: 20px 0; margin-left: 20px; color: var(—text-dark);">
                <li><strong>Verify All Information:</strong> Double-check income, deductions, and personal details</li>
                <li><strong>Consult a Professional:</strong> Show your draft ITR to a CA for review</li>
                <li><strong>Calculate Tax Liability:</strong> Use our tax calculator tool to verify amounts</li>
                <li><strong>Arrange Documents:</strong> Collect all proofs and supporting documents</li>
                <li><strong>File Online:</strong> Go to income-tax.gov.in and file on the official portal</li>
                <li><strong>Verify Your Filing:</strong> Note down your filing reference number</li>
                <li><strong>Keep Records:</strong> Maintain all documents for 7 years</li>
            </ol>

            <div class="highlight-box">
                <strong>🤝 Professional Assistance Available:</strong><br>
                Our team of qualified Chartered Accountants can guide you through the ITR filing process, ensure compliance with all regulations, and help you file with confidence.
                <a href="#" style="color: var(—accent); text-decoration: none; font-weight: 600; display: inline-block; margin-top: 10px;">Connect with a Professional CA →</a>
            </div>
        </div>

        <!— Quick Links —>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 30px;">
            <a href="tax_calculator.php" class="btn btn-primary">📋 Tax Calculator</a>
            <a href="tax_tracker.php" class="btn btn-primary">📝 Deductions Tracker</a>
            <a href="tax_summary.php" class="btn btn-primary">📊 Tax Summary</a>
            <a href="tax_planning.php" class="btn btn-primary">📈 Tax Planning</a>
        </div>
    </div>
</body>
</html>
