<?php
require_once "includes/security.php";
init_secure_session();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    session_destroy();
    header('Location: login.html');
    exit();
}

$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $name = trim($_POST['full_name']);
        $email = trim(strtolower($_POST['email']));
        
        if (empty($name) || empty($email)) {
            $error = 'Name and email are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            // Check if email is already taken by another user
            $check_email_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
            $check_email_stmt = $conn->prepare($check_email_sql);
            $check_email_stmt->bind_param("si", $email, $user_id);
            $check_email_stmt->execute();
            $check_email_result = $check_email_stmt->get_result();
            
            if ($check_email_result->num_rows > 0) {
                $error = 'This email is already registered to another account';
            } else {
                $update_sql = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssi", $name, $email, $user_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $user['full_name'] = $name;
                    $user['email'] = $email;
                    $message = 'Profile updated successfully!';
                } else {
                    $error = 'Failed to update profile. Please try again.';
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect';
        } elseif (empty($new_password)) {
            $error = 'New password is required';
        } elseif (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $pwd_sql = "UPDATE users SET password = ? WHERE id = ?";
            $pwd_stmt = $conn->prepare($pwd_sql);
            $pwd_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($pwd_stmt->execute()) {
                $message = 'Password changed successfully!';
            } else {
                $error = 'Failed to change password';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - FinCart</title>
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
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -1px;
        }

        .back-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: var(--primary);
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            color: var(--primary);
            margin-bottom: 10px;
            letter-spacing: -1px;
        }

        .page-subtitle {
            color: var(--text-light);
            font-size: 16px;
            margin-bottom: 40px;
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
            border: 1px solid var(--success);
            color: var(--success);
        }

        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        .alert-icon {
            font-size: 18px;
        }

        .form-card {
            background: var(--white);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .form-card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--primary);
            margin-bottom: 30px;
            letter-spacing: -1px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 8px;
            font-size: 14px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .input-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn {
            padding: 14px 32px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--accent);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(212, 175, 55, 0.3);
        }

        .btn-secondary {
            background: var(--off-white);
            color: var(--primary);
            border: 2px solid var(--primary);
            margin-left: 10px;
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: var(--white);
            transform: translateY(-2px);
        }

        .form-row {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .help-text {
            font-size: 13px;
            color: var(--text-light);
            margin-top: 6px;
        }

        .divider {
            height: 1px;
            background: rgba(0, 0, 0, 0.1);
            margin: 40px 0;
        }

        @media (max-width: 768px) {
            .input-group {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 32px;
            }

            .form-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .btn-secondary {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <div class="logo">
                <h1>Fincart</h1>
            </div>
            <a href="dashboard.php" class="back-link">←← Back to Dashboard</a>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <h1 class="page-title">Account Settings</h1>
        <p class="page-subtitle">Manage your profile information and security settings</p>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✓</span>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span class="alert-icon">✕</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Profile Information Form -->
        <div class="form-card">
            <h2>Profile Information</h2>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_profile">
                
                <div class="input-group">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="user_id">Account ID</label>
                    <input type="text" id="user_id" value="<?php echo htmlspecialchars($user['id']); ?>" disabled style="background: var(--off-white); cursor: not-allowed;">
                    <p class="help-text">Your unique account identifier</p>
                </div>

                <div class="form-row">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="reset" class="btn btn-secondary">Reset</button>
                </div>
            </form>
        </div>

        <div class="divider"></div>

        <!-- Change Password Form -->
        <div class="form-card">
            <h2>Change Password</h2>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                    <p class="help-text">Enter your current password for verification</p>
                </div>

                <div class="input-group">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <p class="help-text">Minimum 8 characters</p>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>

                <div class="form-row">
                    <button type="submit" class="btn btn-primary">Update Password</button>
                    <button type="reset" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>

        <div class="divider"></div>

        <!-- Account Information -->
        <div class="form-card">
            <h2>Account Information</h2>
            <div style="display: grid; gap: 16px;">
                <div>
                    <p style="color: var(--text-light); font-size: 14px; margin-bottom: 4px;">Member Since</p>
                    <p style="font-weight: 600; color: var(--primary);">
                        <?php 
                        if (isset($user['created_at']) && !empty($user['created_at'])) {
                            echo date('F j, Y', strtotime($user['created_at']));
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </p>
                </div>
                <div>
                    <p style="color: var(--text-light); font-size: 14px; margin-bottom: 4px;">Account Status</p>
                    <p style="font-weight: 600; color: var(--success);">Active ✓</p>
                </div>
                <div>
                    <p style="color: var(--text-light); font-size: 14px; margin-bottom: 4px;">Account Type</p>
                    <p style="font-weight: 600; color: var(--primary);">Personal</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        });
    </script>
</body>
</html>
