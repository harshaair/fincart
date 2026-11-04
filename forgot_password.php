<?php
require_once "includes/security.php";
init_secure_session();
include "db.php";
require_once "includes/mailer.php";

$message = '';
$error = '';
$reset_link_debug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();

    $rate = check_rate_limit($conn, 'forgot_password', 3, 900);
    if (!$rate['allowed']) {
        $minutes = ceil($rate['retry_after'] / 60);
        $error = "Too many password reset attempts. Please wait {$minutes} minute(s) before trying again.";
    } else {
        $email = trim(strtolower($_POST['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid registered email address.';
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            // Generate crypto token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store in password_resets table
            $ins = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $email, $token, $expires_at);
            $ins->execute();

            // Construct link
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $reset_url = "{$protocol}://{$host}{$path}/reset_password.php?token=" . urlencode($token);

            $subject = "FinCart - Password Reset Instructions";
            $html_body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;'>
                    <h2 style='color: #1a1a1a;'>Reset Your FinCart Password</h2>
                    <p>Hello " . htmlspecialchars($user['full_name'] ?? 'User') . ",</p>
                    <p>We received a request to reset the password for your FinCart account.</p>
                    <p style='margin: 25px 0;'>
                        <a href='{$reset_url}' style='background: #d4af37; color: #1a1a1a; padding: 12px 24px; text-decoration: none; font-weight: bold; border-radius: 6px; display: inline-block;'>Reset My Password</a>
                    </p>
                    <p style='color: #666; font-size: 13px;'>Or copy and paste this URL into your browser:<br><a href='{$reset_url}'>{$reset_url}</a></p>
                    <p style='color: #999; font-size: 12px;'>This link will expire in 1 hour. If you did not make this request, please ignore this email.</p>
                </div>
            ";

            sendFinCartEmail($email, $subject, $html_body);
            $reset_link_debug = $reset_url; // Surface link directly on screen for testing convenience
        }

        $message = "If that email address is in our system, we have sent instructions to reset your password.";
    }
}

$page_title = 'Forgot Password - FinCart';
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
        <h2 style="font-size: 20px; color: var(--text-dark);">Reset Password</h2>
        <p style="color: var(--text-muted); font-size: 14px; margin-top: 6px;">Enter your email to receive a password recovery link.</p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php if (!empty($reset_link_debug)): ?>
            <div style="background: #f8f9fa; border: 1px dashed #d4af37; padding: 12px; border-radius: 8px; font-size: 13px; word-break: break-all; margin-bottom: 20px;">
                <strong>Local Dev Link:</strong><br>
                <a href="<?php echo htmlspecialchars($reset_link_debug); ?>" style="color: #d4af37;"><?php echo htmlspecialchars($reset_link_debug); ?></a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_field(); ?>
        <div class="form-group">
            <label>Registered Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="name@example.com" required autofocus>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Send Reset Link</button>
    </form>

    <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; font-size: 14px;">
        <a href="login.html" style="color: var(--primary); text-decoration: none; font-weight: 500;">&larr; Back to Login</a>
    </div>
</div>

</body>
</html>

