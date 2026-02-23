<?php
include '../auth_professional.php';
include '../db.php';

$uid = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM professionals WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$prof = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Professional Dashboard</title>
</head>
<body>
    <h1>Professional Dashboard</h1>
    <p>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
    <?php if ($prof): ?>
        <p>Status: <?php echo htmlspecialchars($prof['status']); ?></p>
        <p>Type: <?php echo htmlspecialchars($prof['professional_type']); ?></p>
        <p>Experience: <?php echo htmlspecialchars($prof['experience_years']); ?> years</p>
    <?php else: ?>
        <p>You have not submitted your professional details yet. <a href="apply.php">Apply now</a></p>
    <?php endif; ?>
    <p><a href="../logout.php">Logout</a></p>
</body>
</html>
