<?php
session_start();
include "db.php";

$message = '';
$error = '';
$token_valid = false;
$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$reset_record = null;

if (!empty($token)) {
    // Check if token exists and has not expired
    $stmt = $conn->prepare("SELECT id, email, expires_at FROM password_resets WHERE token = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $reset_record = $res->fetch_assoc();
        $token_valid = true;
    } else {
        $error = "This password reset link is invalid or has expired. Please request a new one.";
    }
} else {
    $error = "No password reset token provided.";
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters in length.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $email = $reset_record['email'];
        $hash = password_hash($new_password, PASSWORD_DEFAULT);

        // Update user's password
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $upd->bind_param("ss", $hash, $email);

        if ($upd->execute()) {
            // Delete used token(s)
            $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $del->bind_param("s", $email);
            $del->execute();

            $message = "Your password has been successfully reset! You can now log in with your new password.";
            $token_valid = false; // Prevent resubmission
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}

$page_title = 'Set New Password - FinCart';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a1a 0%, #2c2c2c 100%);
            padding: 20px;
        }
        .auth-box {
            background: #ffffff;
            border-radius: 16px;
            padding: 40px;
            max-width: 460px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.25);
        }
    </style>
</head>
<body>

<div class="auth-box">
    <div style="text-align: center; margin-bottom: 25px;">
        <h1 style="font-size: 28px; margin-bottom: 6px;">Fin<span style="color: var(--accent);">Cart</span></h1>
        <h2 style="font-size: 20px; color: var(--text-dark);">Choose New Password</h2>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <div style="margin-top: 20px;">
            <a href="login.html" class="btn btn-primary" style="width: 100%;">Proceed to Login</a>
        </div>
    <?php elseif (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php if (!$token_valid): ?>
            <div style="margin-top: 20px; text-align: center;">
                <a href="forgot_password.php" class="btn btn-secondary btn-sm">Request New Reset Link</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($token_valid): ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="form-group">
                <label>New Password (min. 8 characters)</label>
                <input type="password" name="new_password" class="form-control" required minlength="8" autofocus>
            </div>

            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="8">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Update Password</button>
        </form>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; font-size: 14px;">
        <a href="login.html" style="color: var(--primary); text-decoration: none; font-weight: 500;">&larr; Back to Login</a>
    </div>
</div>

</body>
</html>

