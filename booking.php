<?php
require_once "includes/security.php";
init_secure_session();
include "db.php";
require_once "includes/mailer.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$user_id = $_SESSION['user_id'];
$professional_id = intval($_GET['professional_id'] ?? 0);
$message = '';
$error = '';

// Get professional details
$prof_sql = "SELECT * FROM professionals WHERE id = ? AND status = 'active'";
$prof_stmt = $conn->prepare($prof_sql);
$prof_stmt->bind_param("i", $professional_id);
$prof_stmt->execute();
$prof_result = $prof_stmt->get_result();

if ($prof_result->num_rows === 0) {
    header('Location: professionals.php');
    exit();
}

$professional = $prof_result->fetch_assoc();

// Handle booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();

    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $consultation_type = $_POST['consultation_type'] ?? '';
    $notes = $_POST['notes'] ?? '';

    // Validate date is in future
    if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error = 'Please select a future date';
    } elseif (empty($appointment_time)) {
        $error = 'Please select a time slot';
    } else {
        // Atomic transaction with row-level slot locking
        $conn->begin_transaction();
        try {
            $check_sql = "SELECT id FROM appointments WHERE professional_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled' FOR UPDATE";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("iss", $professional_id, $appointment_date, $appointment_time);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $conn->rollback();
                $error = 'This time slot is already booked. Please select another.';
            } else {
                $book_sql = "INSERT INTO appointments (professional_id, user_id, appointment_date, appointment_time, consultation_type, notes, status) VALUES (?, ?, ?, ?, ?, ?, 'confirmed')";
                $book_stmt = $conn->prepare($book_sql);
                $book_stmt->bind_param("iissss", $professional_id, $user_id, $appointment_date, $appointment_time, $consultation_type, $notes);
                $book_stmt->execute();

                $conn->commit();
                $message = 'Appointment booked successfully! You will receive a confirmation email shortly.';

                // Send email notifications
                $prof_email = $professional['email'] ?? null;
                $admin_email = null;
                $a = $conn->query("SELECT email FROM users WHERE role = 'admin' LIMIT 1");
                if ($a && $a->num_rows) {
                    $admin_row = $a->fetch_assoc();
                    $admin_email = $admin_row['email'];
                }

                $subject = "New appointment booked with " . $professional['name'];
                $body = "Hello,\n\nAn appointment has been booked on " . $appointment_date . " at " . $appointment_time . " by " . htmlspecialchars($_SESSION['user_name']) . ".\n\nDetails:\nProfessional: " . $professional['name'] . "\nUser: " . htmlspecialchars($_SESSION['user_name']) . " (" . htmlspecialchars($user_id) . ")\nNotes: " . htmlspecialchars($notes) . "\n\nPlease login to the portal to view details.\n";
                $headers = 'From: no-reply@fincart.local' . "\r\n" . 'Reply-To: no-reply@fincart.local' . "\r\n";

                if ($prof_email) {
                    @mail($prof_email, $subject, $body, $headers);
                }
                if ($admin_email) {
                    @mail($admin_email, $subject, $body, $headers);
                }
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to book appointment: ' . $e->getMessage();
        }
    }
}

// Get available slots (next 30 days)
$available_dates = [];
for ($i = 1; $i <= 30; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $day = date('l', strtotime($date)); // Day name
    
    // Skip Sundays
    if ($day !== 'Sunday') {
        $available_dates[$date] = $day;
    }
}

// Available time slots
$time_slots = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Consultation - FinCart</title>
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
        }

        .professional-info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 80px;
        }

        .professional-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin: 0 auto 20px;
            border: 3px solid white;
        }

        .professional-name {
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .professional-type {
            text-align: center;
            color: var(--accent);
            font-size: 13px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .info-item {
            margin-bottom: 15px;
            font-size: 13px;
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

        .fee {
            background: rgba(212, 175, 55, 0.1);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-top: 15px;
        }

        .fee-label {
            color: var(--text-light);
            font-size: 12px;
        }

        .fee-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--accent);
        }

        .booking-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .booking-form h2 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--primary);
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .time-slots {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .time-slot {
            position: relative;
        }

        .time-slot input {
            display: none;
        }

        .time-slot label {
            display: block;
            padding: 10px;
            text-align: center;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
            font-weight: 600;
            background: var(--off-white);
        }

        .time-slot input:checked + label {
            border-color: var(--accent);
            background: var(--accent);
            color: var(--primary);
        }

        .btn {
            background: var(--accent);
            color: var(--primary);
            border: none;
            padding: 14px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn:hover {
            background: #c99e2e;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .professional-info-card {
                position: static;
            }

            .time-slots {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <h1>📅 Book Consultation</h1>
            <div class="nav-links">
                <a href="professionals.php">← Back to Directory</a>
                <form method="POST" action="logout.php" style="display: inline;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="content-grid">
            <!-- Professional Info Sidebar -->
            <div class="professional-info-card">
                <div class="professional-avatar">
                    <?php echo strtoupper(substr($professional['name'], 0, 1)); ?>
                </div>
                <div class="professional-name"><?php echo htmlspecialchars($professional['name']); ?></div>
                <div class="professional-type"><?php echo htmlspecialchars($professional['professional_type']); ?></div>

                <div class="info-item">
                    <span class="info-label">Specialization</span>
                    <span class="info-value"><?php echo htmlspecialchars($professional['specialization']); ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label">Qualification</span>
                    <span class="info-value"><?php echo htmlspecialchars($professional['qualification']); ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label">Experience</span>
                    <span class="info-value"><?php echo $professional['experience_years']; ?> years</span>
                </div>

                <div class="info-item">
                    <span class="info-label">Location</span>
                    <span class="info-value"><?php echo htmlspecialchars($professional['city'] . ', ' . $professional['state']); ?></span>
                </div>

                <div class="fee">
                    <div class="fee-label">Consultation Fee</div>
                    <div class="fee-value">₹<?php echo number_format($professional['consultation_fee'], 0); ?></div>
                </div>
            </div>

            <!-- Booking Form -->
            <div class="booking-form">
                <h2>Schedule Your Consultation</h2>

                <?php if ($message): ?>
                    <div class="alert alert-success">✓ <?php echo $message; ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">✕ <?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label>Consultation Date</label>
                        <select name="appointment_date" required>
                            <option value="">Select a date</option>
                            <?php foreach ($available_dates as $date => $day): ?>
                                <option value="<?php echo $date; ?>"><?php echo date('M d, Y', strtotime($date)) . ' (' . $day . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Time Slot</label>
                        <div class="time-slots">
                            <?php foreach ($time_slots as $index => $slot): ?>
                                <div class="time-slot">
                                    <input type="radi⭐ id="slot_<?php echo $index; ?>" name="appointment_time" value="<?php echo $slot; ?>">
                                    <label for="slot_<?php echo $index; ?>"><?php echo date('h:i A', strtotime($slot)); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Type of Consultation</label>
                        <select name="consultation_type" required>
                            <option value="">Select type</option>
                            <option value="Initial Consultation">Initial Consultation (30 min)</option>
                            <option value="Follow-up">Follow-up Session (30 min)</option>
                            <option value="Tax Planning">Tax Planning Session (60 min)</option>
                            <option value="Investment Review">Investment Review (60 min)</option>
                            <option value="Comprehensive Planning">Comprehensive Planning (90 min)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="notes">Additional Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="5" placeholder="Tell the professional about your specific needs or questions..."></textarea>
                    </div>

                    <button type="submit" class="btn">Confirm Booking</button>
                </form>

                <div style="margin-top: 20px; padding: 15px; background: var(--off-white); border-radius: 8px; font-size: 13px; color: var(--text-light);">
                    <strong>📌 What happens next:</strong><br>
                    1. Your booking will be confirmed instantly<br>
                    2. You'll receive a confirmation email with meeting details<br>
                    3. The professional may reach out 24 hours before the session<br>
                    4. Follow the link in email to join the consultation
                </div>
            </div>
        </div>
    </div>
</body>
</html>
