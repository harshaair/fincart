<?php
session_start();
include '../db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /professionals.php');
    exit();
}

$stmt = $conn->prepare("SELECT * FROM professionals WHERE id = ? AND status = 'active'");
$stmt->bind_param('i', $id);
$stmt->execute();
$prof = $stmt->get_result()->fetch_assoc();
if (!$prof) {
    header('Location: /professionals.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($prof['name']); ?> — Profile</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<header class="site-header">
    <div class="logo"><h1><?php echo htmlspecialchars($prof['name']); ?></h1></div>
    <div class="header-actions">
        <a href="/professionals.php" class="btn btn-secondary">Directory</a>
        <a href="/dashboard.php" class="btn btn-secondary">Dashboard</a>
    </div>
</header>
<div class="container">
    <div class="card">
        <div class="professional-header" style="padding:0 24px 24px; border-bottom:none;">
            <div class="professional-avatar" style="width:96px;height:96px;font-size:40px;"><?php echo strtoupper(substr($prof['name'],0,1)); ?></div>
            <h2 style="margin-top:10px"><?php echo htmlspecialchars($prof['name']); ?></h2>
            <div class="professional-type"><?php echo htmlspecialchars($prof['professional_type']); ?></div>
        </div>
        <div class="professional-content">
            <?php if ($prof['specialization']): ?><div class="specialization"><?php echo htmlspecialchars($prof['specialization']); ?></div><?php endif; ?>
            <?php if ($prof['qualification']): ?><div class="info-item"><span class="info-label">Qualification</span><span class="info-value"><?php echo htmlspecialchars($prof['qualification']); ?></span></div><?php endif; ?>
            <?php if ($prof['experience_years']): ?><div class="info-item"><span class="info-label">Experience</span><span class="info-value"><?php echo $prof['experience_years']; ?> years</span></div><?php endif; ?>
            <?php if ($prof['city']): ?><div class="info-item"><span class="info-label">Location</span><span class="info-value"><?php echo htmlspecialchars($prof['city'] . ', ' . $prof['state']); ?></span></div><?php endif; ?>
            <?php if ($prof['languages']): ?><div class="info-item"><span class="info-label">Languages</span><span class="info-value"><?php echo htmlspecialchars($prof['languages']); ?></span></div><?php endif; ?>
            <?php if ($prof['bio']): ?><div class="bi⭐><?php echo nl2br(htmlspecialchars($prof['bio'])); ?></div><?php endif; ?>
            <?php if ($prof['consultation_fee']): ?><div class="consultation-fee">₹<?php echo number_format($prof['consultation_fee'],0); ?> /session</div><?php endif; ?>

            <div style="margin-top:20px; display:flex; gap:12px; flex-wrap:wrap;">
                <a href="/booking.php?professional_id=<?php echo $prof['id']; ?>" class="btn btn-primary">Book Consultation</a>
                <?php if ($prof['email']): ?><a href="mailto:<?php echo htmlspecialchars($prof['email']); ?>" class="btn btn-secondary">Email</a><?php endif; ?>
                <?php if ($prof['phone']): ?><a href="tel:<?php echo htmlspecialchars($prof['phone']); ?>" class="btn btn-secondary">Call</a><?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>