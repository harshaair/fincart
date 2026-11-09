<?php
/**
 * Reusable Alert Component
 *
 * Usage:
 * render_alert('Transaction saved successfully!', 'success');
 * render_alert('Session expired. Please log in again.', 'danger', true);
 */
function render_alert($message, $type = 'info', $dismissible = true) {
    if (empty($message)) return;
    $icons = [
        'success' => '✓',
        'danger'  => '✕',
        'warning' => '⚠',
        'info'    => 'ℹ'
    ];
    $icon = $icons[$type] ?? 'ℹ';
    ?>
    <div class="alert-luxury alert-<?php echo htmlspecialchars($type); ?>" role="alert">
        <span class="alert-luxury-icon"><?php echo $icon; ?></span>
        <div class="alert-luxury-content"><?php echo htmlspecialchars($message); ?></div>
        <?php if ($dismissible): ?>
            <button type="button" class="alert-luxury-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
        <?php endif; ?>
    </div>
    <?php
}

