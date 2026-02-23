<?php
// Professional login UI - posts to root login handler
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Professional Login - FinCart</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .auth-container { max-width:420px; margin:80px auto; padding:24px; }
        .auth-form label{display:block;margin:10px 0 6px}
        .auth-form input{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px}
        .auth-actions{display:flex;gap:8px;margin-top:12px}
        .auth-actions a{align-self:center}
    </style>
</head>
<body>
<div class="auth-container">
    <h2>Professional Login</h2>
    <p>Use your registered email and password to sign in.</p>
    <form class="auth-form" method="post" action="../login.php">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <div class="auth-actions">
            <button type="submit" class="btn btn-primary">Login</button>
            <a href="../become_professional.php" class="btn btn-secondary">Join as Professional</a>
        </div>
    </form>

    <p style="margin-top:18px;font-size:13px;color:#666">If you already applied and are pending approval, you can still login — your dashboard will show the pending status.</p>
</div>
</body>
</html>
