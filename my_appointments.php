<?php
require_once "includes/security.php";
init_secure_session();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = $_GET['notice'] ?? '';
$error = $_GET['error'] ?? '';

// Handle rating and review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_rating') {
    enforce_csrf();
    $appointment_id = intval($_POST['appointment_id'] ?? 0);
    $professional_id = intval($_POST['professional_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $review_text = trim($_POST['review_text'] ?? '');

    if ($rating < 1 || $rating > 5 || $professional_id <= 0 || $appointment_id <= 0) {
        $error = 'Please provide a valid rating between 1 and 5 stars.';
    } else {
        // Verify appointment belongs to user and is confirmed/completed
        $chk = $conn->prepare("SELECT id, status FROM appointments WHERE id = ? AND user_id = ? AND professional_id = ?");
        $chk->bind_param("iii", $appointment_id, $user_id, $professional_id);
        $chk->execute();
        $chk_row = $chk->get_result()->fetch_assoc();
        if ($chk_row) {
            if (in_array($chk_row['status'], ['cancelled', 'rejected'])) {
                $error = 'You cannot rate or review a cancelled or rejected appointment.';
            } else {
                $r_stmt = $conn->prepare("INSERT INTO professional_ratings (professional_id, user_id, appointment_id, rating, review_text) 
                                          VALUES (?, ?, ?, ?, ?) 
                                          ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text), updated_at = NOW()");
                $r_stmt->bind_param("iiiis", $professional_id, $user_id, $appointment_id, $rating, $review_text);
                if ($r_stmt->execute()) {
                    $message = 'Thank you! Your feedback and star rating have been submitted.';
                } else {
                    $error = 'Failed to submit rating. Please try again.';
                }
            }
        } else {
            $error = 'Invalid appointment verification.';
        }
    }
}

// Fetch user's appointments with ratings if submitted
$stmt = $conn->prepare("
    SELECT a.*, p.name as professional_name, p.professional_type, p.qualification, p.phone as prof_phone,
           r.rating as user_rating, r.review_text as user_review
    FROM appointments a 
    LEFT JOIN professionals p ON a.professional_id = p.id
    LEFT JOIN professional_ratings r ON r.appointment_id = a.id AND r.user_id = a.user_id
    WHERE a.user_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$appointments = $stmt->get_result();

$page_title = 'My Appointments - FinCart';
include "includes/header.php";
include "includes/navbar.php";
?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="font-size: 28px; margin-bottom: 6px;">My Consultation Appointments</h1>
            <p style="color: var(--text-muted);">Manage your expert consultations, check booking status, and review verified professionals.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="professionals.php" class="btn btn-primary">+ Book New Consultation</a>
            <a href="dashboard.php" class="btn btn-secondary">&larr; Dashboard</a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success">&#10003; <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">&#9888; <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($appointments->num_rows > 0): ?>
        <div style="display: grid; gap: 18px;">
            <?php while ($apt = $appointments->fetch_assoc()): ?>
                <div class="card" style="padding: 22px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <h3 style="font-size: 18px; margin-bottom: 4px;"><?php echo htmlspecialchars($apt['professional_name'] ?? 'Archived Advisor (Listing Closed)'); ?></h3>
                            <?php if (!empty($apt['professional_type'])): ?>
                                <span class="badge badge-info"><?php echo htmlspecialchars($apt['professional_type']); ?></span>
                            <?php endif; ?>
                            <span style="color: #666; font-size: 13px; margin-left: 8px;"><?php echo htmlspecialchars($apt['qualification'] ?? ''); ?></span>
                        </div>
                        <div>
                            <?php
                            $st = strtolower($apt['status']);
                            if ($st === 'confirmed') echo '<span class="badge badge-success">&#10003; Confirmed</span>';
                            elseif ($st === 'pending') echo '<span class="badge badge-warning">&#9203; Pending Approval</span>';
                            elseif ($st === 'cancelled') echo '<span class="badge badge-danger">Cancelled</span>';
                            elseif ($st === 'rejected') echo '<span class="badge badge-danger">Declined</span>';
                            else echo '<span class="badge badge-info">' . ucfirst($st) . '</span>';
                            ?>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; background: var(--off-white); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 15px;">
                        <div>
                            <small style="color: var(--text-muted); display: block;">Date & Time</small>
                            <strong><?php echo date('d-M-Y', strtotime($apt['appointment_date'])); ?> at <?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></strong>
                        </div>
                        <div>
                            <small style="color: var(--text-muted); display: block;">Session Type</small>
                            <strong><?php echo htmlspecialchars($apt['consultation_type']); ?></strong>
                        </div>
                        <div>
                            <small style="color: var(--text-muted); display: block;">Consultation Mode</small>
                            <strong><?php echo htmlspecialchars(ucfirst($apt['consultation_mode'] ?? 'online')); ?></strong>
                        </div>
                        <div>
                            <small style="color: var(--text-muted); display: block;">Booked On</small>
                            <span><?php echo date('d-M-Y', strtotime($apt['created_at'])); ?></span>
                        </div>
                    </div>

                    <?php if (!empty($apt['notes'])): ?>
                        <p style="font-size: 13px; color: #555; margin-bottom: 12px;">
                            <strong>Your Notes:</strong> <?php echo htmlspecialchars($apt['notes']); ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($apt['rejection_reason'])): ?>
                        <div class="alert alert-warning" style="margin-bottom: 12px; font-size: 13px;">
                            <strong>Reason:</strong> <?php echo htmlspecialchars($apt['rejection_reason']); ?>
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-light); padding-top: 12px; flex-wrap: wrap; gap: 10px;">
                        <!-- Review & Rating Display or Trigger -->
                        <div>
                            <?php if ($apt['status'] === 'confirmed'): ?>
                                <?php if (!empty($apt['user_rating'])): ?>
                                    <div style="font-size: 14px; color: #f39c12;">
                                        <?php echo str_repeat('★', (int)$apt['user_rating']) . str_repeat('☆', 5 - (int)$apt['user_rating']); ?>
                                        <span style="color: #444; font-size: 13px; margin-left: 6px;">Your Rating (<?php echo (int)$apt['user_rating']; ?>/5)</span>
                                        <?php if (!empty($apt['user_review'])): ?>
                                            <div style="font-size: 12px; color: #666; font-style: italic; margin-top: 3px;">
                                                "<?php echo htmlspecialchars($apt['user_review']); ?>"
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <button 
                                        type="button" 
                                        class="btn btn-accent btn-sm"
                                        onclick="openReviewModal(<?php echo (int)$apt['id']; ?>, <?php echo (int)$apt['professional_id']; ?>, '<?php echo addslashes($apt['professional_name']); ?>')">
                                        ★ Rate & Review Consultation
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Cancel Action -->
                        <div>
                            <?php if (!in_array($apt['status'], ['cancelled', 'rejected'])): ?>
                                <form method="POST" action="cancel_appointment.php" onsubmit="return confirm('Are you sure you want to cancel this appointment?');" style="display: inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="appointment_id" value="<?php echo (int)$apt['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Cancel Appointment</button>
                                </form>
                            <?php else: ?>
                                <a href="booking.php?professional_id=<?php echo (int)$apt['professional_id']; ?>" class="btn btn-secondary btn-sm">Book Again</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 60px 20px;">
            <h3 style="font-size: 20px; margin-bottom: 10px;">No Appointments Booked Yet</h3>
            <p style="color: #888; margin-bottom: 20px;">Book a consultation with verified Chartered Accountants and certified financial planners.</p>
            <a href="professionals.php" class="btn btn-primary">Browse Verified Experts &rarr;</a>
        </div>
    <?php endif; ?>
</div>

<!-- Review & Rating Modal -->
<div id="reviewModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="font-size: 20px;">Rate Consultation</h2>
            <button type="button" onclick="closeReviewModal()" style="background: none; border: none; font-size: 22px; cursor: pointer;">&times;</button>
        </div>
        <p style="color: #666; font-size: 13px; margin-bottom: 20px;">
            Leave feedback for <strong id="modal_prof_name"></strong>. Your review helps other users make informed decisions.
        </p>

        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="submit_rating">
            <input type="hidden" name="appointment_id" id="modal_apt_id">
            <input type="hidden" name="professional_id" id="modal_prof_id">

            <div class="form-group">
                <label>Overall Rating</label>
                <select name="rating" class="form-control" required style="font-size: 16px;">
                    <option value="5">★★★★★ - Excellent (5 stars)</option>
                    <option value="4">★★★★☆ - Very Good (4 stars)</option>
                    <option value="3">★★★☆☆ - Average (3 stars)</option>
                    <option value="2">★★☆☆☆ - Below Average (2 stars)</option>
                    <option value="1">★☆☆☆☆ - Poor (1 star)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Review / Feedback (Optional)</label>
                <textarea name="review_text" class="form-control" rows="3" placeholder="Describe your consultation experience, advisory quality, and communication..."></textarea>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 25px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Submit Rating</button>
                <button type="button" class="btn btn-secondary" onclick="closeReviewModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
window.openReviewModal = function(aptId, profId, profName) {
    const modalApt = document.getElementById('modal_apt_id');
    const modalProf = document.getElementById('modal_prof_id');
    const modalName = document.getElementById('modal_prof_name');
    const modal = document.getElementById('reviewModal');
    if (!modalApt || !modalProf || !modal) return;

    modalApt.value = aptId;
    modalProf.value = profId;
    if (modalName) modalName.textContent = profName;
    modal.style.display = 'flex';
};

window.closeReviewModal = function() {
    const modal = document.getElementById('reviewModal');
    if (modal) modal.style.display = 'none';
};

window.addEventListener('click', function(e) {
    const modal = document.getElementById('reviewModal');
    if (e.target === modal) {
        window.closeReviewModal();
    }
});
</script>

<?php include "includes/footer.php"; ?>
