<?php
include '../auth_professional.php';
include '../db.php';

// Simple application form for professionals (logged-in users)
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Apply as Professional</title>
</head>
<body>
    <h1>Apply as Professional</h1>
    <form method="post" action="apply_handler.php">
        <label>Type: <input name="professional_type" required></label><br>
        <label>Qualification: <input name="qualification"></label><br>
        <label>Experience Years: <input name="experience_years" type="number"></label><br>
        <label>Specialization: <input name="specialization"></label><br>
        <label>Bio: <textarea name="bio"></textarea></label><br>
        <label>Phone: <input name="phone"></label><br>
        <label>City: <input name="city"></label><br>
        <label>State: <input name="state"></label><br>
        <label>Consultation Fee: <input name="consultation_fee" type="number" step="0.01"></label><br>
        <button type="submit">Submit Application</button>
    </form>
    <p><a href="dashboard.php">← Back</a></p>
</body>
</html>
