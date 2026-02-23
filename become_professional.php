<?php
session_start();
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

    <div class="auth-body">
        <form class="auth-form" method="post" action="professional/become_handler.php">

            <!-- Always ask user details -->
            <div class="form-group">
                <label>Full name</label>
                <input class="form-control" type="text" name="full_name" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input class="form-control" type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input class="form-control" type="password" name="password" required>
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
                <textarea class="form-control" name="bio"></textarea>
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input class="form-control" type="text" name="phone">
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
