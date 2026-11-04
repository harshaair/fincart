<?php
require_once "includes/security.php";
init_secure_session();
include "db.php";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Apply as Professional - FinCart</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<header class="site-header">
    <div class="logo"><h1>Become a Professional</h1></div>
    <div class="header-actions">
        <a href="index.html" class="btn btn-secondary">Home</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="logout.php" class="btn btn-primary">Logout</a>
        <?php else: ?>
            <a href="login.html" class="btn btn-primary">Login</a>
        <?php endif; ?>
    </div>
</header>

<div class="auth-container">
    <div class="auth-header">
        <h2>Apply to join as a professional</h2>
        <p>Please provide your professional details below.</p>
    </div>

    <?php
    // Check if user is already logged in
    if (isset($_SESSION['user_id'])):
    ?>
    <div style="padding:15px;background-color:#fff3cd;border:1px solid #ffc107;border-radius:6px;margin:20px 0;color:#856404;">
        <h3>⚠️ Already Logged In</h3>
        <p>You are currently logged in as <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></p>
        <p>To apply as a professional with a <strong>different email address</strong>, you must log out first.</p>
        <p style="margin-bottom:0;">
            <a href="logout.php" class="btn btn-primary">Logout</a> 
            <span style="margin:0 10px;">or</span>
            <a href="professional/dashboard.php" class="btn btn-secondary">View My Profile</a>
        </p>
        <p style="margin-top:15px; font-size:13px; color:#888;">
            If you want to apply with this current account, you can proceed with the form below.
        </p>
    </div>
    <?php
    endif;
    ?>

    <div class="auth-body">
        <?php
        // Display error message if any
        if (isset($_GET['error'])) {
            echo '<div style="padding:12px;background-color:#fee;border:1px solid #f99;border-radius:6px;margin-bottom:20px;color:#c33;">
                    <strong>Error:</strong> ' . htmlspecialchars(urldecode($_GET['error'])) . '
                  </div>';
        }
        ?>
        <form class="auth-form" method="post" action="professional/become_handler.php" autocomplete="off">
            <?php echo csrf_field(); ?>

            <!-- Always ask user details -->
            <div class="form-group">
                <label>Full name</label>
                <input class="form-control" type="text" name="full_name" required autocomplete="off">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input class="form-control" type="email" name="email" required autocomplete="off">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input class="form-control" type="password" name="password" required autocomplete="new-password">
            </div>

            <div class="form-group">
                <label>Professional Type</label>
                <input class="form-control" type="text" name="professional_type" placeholder="CA / Financial Advisor / Tax Consultant" required>
            </div>

            <div class="form-group">
                <label>Qualification</label>
                <input class="form-control" type="text" name="qualification">
            </div>

            <div class="form-group">
                <label>Experience (years)</label>
                <input class="form-control" type="number" name="experience_years" min="0">
            </div>

            <div class="form-group">
                <label>Specialization</label>
                <input class="form-control" type="text" name="specialization">
            </div>

            <div class="form-group">
                <label>Bio</label>
                <textarea class="form-control" name="bi⭐></textarea>
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input class="form-control" type="text" name="phone" autocomplete="off">
            </div>

            <div class="form-group">
                <label>City</label>
                <input class="form-control" type="text" name="city">
            </div>

            <div class="form-group">
                <label>State</label>
                <input class="form-control" type="text" name="state">
            </div>

            <div class="form-group">
                <label>Consultation fee (INR)</label>
                <input class="form-control" type="number" step="0.01" name="consultation_fee">
            </div>

            <button type="submit" class="btn-submit">Submit Application</button>

        </form>
    </div>
</div>
</body>
</html>
