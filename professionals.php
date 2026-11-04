<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all active professionals with average rating and review counts
$sql = "SELECT p.*, 
               COALESCE(AVG(r.rating), 0) as avg_rating,
               COUNT(r.id) as review_count
        FROM professionals p
        LEFT JOIN professional_ratings r ON r.professional_id = p.id
        WHERE p.status = 'active'
        GROUP BY p.id
        ORDER BY p.professional_type, avg_rating DESC, p.name ASC";
$result = $conn->query($sql);
$professionals = [];

while ($row = $result->fetch_assoc()) {
    $professionals[] = $row;
}

// Group by professional type
$grouped = [];
foreach ($professionals as $prof) {
    $type = $prof['professional_type'];
    if (!isset($grouped[$type])) {
        $grouped[$type] = [];
    }
    $grouped[$type][] = $prof;
}

$page_title = 'Verified Financial & Tax Professionals - FinCart';
include "includes/header.php";
include "includes/navbar.php";
?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="font-size: 28px; margin-bottom: 6px;">Verified Experts & Financial Advisors</h1>
            <p style="color: var(--text-muted);">Connect with certified Chartered Accountants, financial planners, and tax attorneys.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="my_appointments.php" class="btn btn-secondary">My Appointments &rarr;</a>
            <a href="tax_planning.php" class="btn btn-primary">Tax Planning Hub &rarr;</a>
        </div>
    </div>

    <!-- Directory Groups -->
    <?php foreach ($grouped as $type => $profs): ?>
        <div style="margin-bottom: 40px;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; border-bottom: 2px solid var(--border-light); padding-bottom: 10px;">
                <h2 style="font-size: 22px; color: var(--primary);"><?php echo htmlspecialchars($type); ?>s</h2>
                <span class="badge badge-info"><?php echo count($profs); ?> Available</span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                <?php foreach ($profs as $prof): ?>
                    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; margin-bottom: 0;">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                <div>
                                    <h3 style="font-size: 19px; margin-bottom: 4px;"><?php echo htmlspecialchars($prof['name']); ?></h3>
                                    <span style="font-size: 13px; color: var(--accent); font-weight: 600;"><?php echo htmlspecialchars($prof['qualification'] ?? 'Certified Professional'); ?></span>
                                </div>
                                <div style="text-align: right;">
                                    <div style="color: #f39c12; font-size: 14px; font-weight: 700;">
                                        ★ <?php echo number_format((float)$prof['avg_rating'], 1); ?>
                                    </div>
                                    <small style="color: #888; font-size: 12px;">(<?php echo (int)$prof['review_count']; ?> reviews)</small>
                                </div>
                            </div>

                            <div style="background: var(--off-white); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 14px; font-size: 13px;">
                                <strong>Specialization:</strong> <?php echo htmlspecialchars($prof['specialization'] ?? 'Financial Planning'); ?><br>
                                <strong>Experience:</strong> <?php echo (int)($prof['experience_years'] ?? 0); ?> years in practice<br>
                                <?php if (!empty($prof['city']) || !empty($prof['state'])): ?>
                                    <strong>Location:</strong> <?php echo htmlspecialchars(trim(($prof['city'] ?? '') . ', ' . ($prof['state'] ?? ''), ', ')); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($prof['languages'])): ?>
                                    <strong>Languages:</strong> <?php echo htmlspecialchars($prof['languages']); ?>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($prof['bio'])): ?>
                                <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 18px;">
                                    <?php echo htmlspecialchars($prof['bio']); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-top: 12px; border-top: 1px solid var(--border-light);">
                                <span style="font-size: 13px; color: var(--text-muted);">Consultation Fee</span>
                                <strong style="font-size: 18px; color: var(--primary);">₹<?php echo number_format((float)($prof['consultation_fee'] ?? 0), 2); ?></strong>
                            </div>
                            <a href="booking.php?professional_id=<?php echo (int)$prof['id']; ?>" class="btn btn-primary" style="width: 100%; text-align: center;">
                                Book Appointment &rarr;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include "includes/footer.php"; ?>
