<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all professionals
$sql = "SELECT * FROM professionals WHERE status = 'active' ORDER BY professional_type, name";
$result = $conn->query($sql);
$professionals = [];

while ($row = $result->fetch_assoc()) {
    $professionals[] = $row;
}

// Group by professional type
$grouped = [];
foreach ($professionals as $prof) {
    $type = $prof['professional_type'];
    if (!isset($grouped[$type])) {
        $grouped[$type] = [];
    }
    $grouped[$type][] = $prof;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Directory - FinCart</title>
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
            --primary: #1a1a1a;
            --secondary: #f8f6f3;
            --accent: #d4af37;
            --text-dark: #2c2c2c;
            --text-light: #666;
            --white: #ffffff;
            --off-white: #fafaf9;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--off-white) 0%, var(--secondary) 100%);
            color: var(--text-dark);
            min-height: 100vh;
        }

        header {
            position: sticky;
            top: 0;
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 2px solid var(--accent);
            padding: 15px 0;
            z-index: 1000;
        }

        header nav {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h1 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 24px;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: var(--accent);
        }

        .logout-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .page-header p {
            color: var(--text-light);
            font-size: 16px;
        }

        .category-section {
            margin-bottom: 50px;
        }

        .category-title {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--primary);
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--accent);
        }

        .professionals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }

        .professional-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }

        .professional-card:hover {
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.2);
            transform: translateY(-5px);
        }

        .professional-header {
            background: linear-gradient(135deg, var(--primary) 0%, rgba(26, 26, 26, 0.9) 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .professional-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 15px;
            border: 3px solid white;
        }

        .professional-name {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .professional-type {
            font-size: 13px;
            text-transform: uppercase;
            color: var(--accent);
            letter-spacing: 1px;
            font-weight: 600;
        }

        .professional-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .professional-info {
            margin-bottom: 15px;
        }

        .info-item {
            margin-bottom: 12px;
            font-size: 14px;
        }

        .info-label {
            color: var(--text-light);
            font-weight: 600;
            display: block;
            margin-bottom: 3px;
        }

        .info-value {
            color: var(--text-dark);
        }

        .specialization {
            background: rgba(212, 175, 55, 0.1);
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 15px;
        }

        .bio {
            color: var(--text-light);
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 15px;
            flex-grow: 1;
        }

        .consultation-fee {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 15px;
        }

        .contact-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .btn-small {
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .btn-book {
            background: var(--accent);
            color: var(--primary);
            grid-column: 1/-1;
        }

        .btn-book:hover {
            background: #c99e2e;
        }

        .btn-call {
            background: var(--info);
            color: white;
        }

        .btn-call:hover {
            background: #2980b9;
        }

        .btn-email {
            background: var(--warning);
            color: white;
        }

        .btn-email:hover {
            background: #e67e22;
        }

        .no-professionals {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }

        @media (max-width: 768px) {
            .professionals-grid {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 28px;
            }

            .category-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <h1>🏢 Professional Directory</h1>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="tax_calculator_simple.php">Tax Calculator</a>
                <form method="POST" action="logout.php" style="display: inline;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="page-header">
            <h1>Meet Our Professional Partners</h1>
            <p>Connect with experienced CAs, financial advisors, and tax consultants</p>
        </div>

        <?php if (empty($grouped)): ?>
            <div class="no-professionals">
                <p>No professionals available at the moment. Please check back soon!</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped as $type => $profs): ?>
                <div class="category-section">
                    <h2 class="category-title">
                        <?php 
                        $icons = [
                            'CA' => '📊',
                            'Financial Advisor' => '💰',
                            'Tax Consultant' => '📋'
                        ];
                        echo ($icons[$type] ?? '👤') . ' ' . $type;
                        ?>
                    </h2>
                    <div class="professionals-grid">
                        <?php foreach ($profs as $prof): ?>
                            <div class="professional-card">
                                <div class="professional-header">
                                    <div class="professional-avatar">
                                        <?php echo strtoupper(substr($prof['name'], 0, 1)); ?>
                                    </div>
                                    <div class="professional-name"><?php echo htmlspecialchars($prof['name']); ?></div>
                                    <div class="professional-type"><?php echo htmlspecialchars($prof['professional_type']); ?></div>
                                </div>
                                <div class="professional-content">
                                    <?php if ($prof['specialization']): ?>
                                        <div class="specialization">
                                            <?php echo htmlspecialchars($prof['specialization']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="professional-info">
                                        <?php if ($prof['qualification']): ?>
                                            <div class="info-item">
                                                <span class="info-label">Qualification</span>
                                                <span class="info-value"><?php echo htmlspecialchars($prof['qualification']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($prof['experience_years']): ?>
                                            <div class="info-item">
                                                <span class="info-label">Experience</span>
                                                <span class="info-value"><?php echo $prof['experience_years']; ?> years</span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($prof['city']): ?>
                                            <div class="info-item">
                                                <span class="info-label">Location</span>
                                                <span class="info-value"><?php echo htmlspecialchars($prof['city'] . ', ' . $prof['state']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($prof['languages']): ?>
                                            <div class="info-item">
                                                <span class="info-label">Languages</span>
                                                <span class="info-value"><?php echo htmlspecialchars($prof['languages']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($prof['bio']): ?>
                                        <div class="bio">
                                            <?php echo htmlspecialchars($prof['bio']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($prof['consultation_fee']): ?>
                                        <div class="consultation-fee">
                                            ₹<?php echo number_format($prof['consultation_fee'], 0); ?> /session
                                        </div>
                                    <?php endif; ?>

                                    <div class="contact-buttons">
                                        <a href="booking.php?professional_id=<?php echo $prof['id']; ?>" class="btn-small btn-book">
                                            📅 Book Now
                                        </a>
                                        <?php if ($prof['phone']): ?>
                                            <a href="tel:<?php echo $prof['phone']; ?>" class="btn-small btn-call">
                                                ☎️ Call
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($prof['email']): ?>
                                            <a href="mailto:<?php echo $prof['email']; ?>" class="btn-small btn-email">
                                                ✉️ Email
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
