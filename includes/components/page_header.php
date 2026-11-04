<?php
/**
 * Reusable Page Header Component
 *
 * Usage:
 * render_page_header([
 *     'title'    => 'Financial Transactions',
 *     'subtitle' => 'Track and manage all your historical cash flows & records',
 *     'badge'    => 'Updated just now',
 *     'actions'  => [
 *         ['label' => '+ Add Transaction', 'href' => 'add_transaction.php', 'class' => 'btn-gold'],
 *         ['label' => 'Export CSV', 'href' => 'export_transactions.php', 'class' => 'btn-outline']
 *     ]
 * ]);
 */
function render_page_header($props = []) {
    $title    = htmlspecialchars($props['title'] ?? 'Overview');
    $subtitle = htmlspecialchars($props['subtitle'] ?? '');
    $badge    = htmlspecialchars($props['badge'] ?? '');
    $actions  = $props['actions'] ?? [];
    ?>
    <section class="page-header-luxury">
        <div class="page-header-text">
            <div class="page-header-title-row">
                <h1 class="page-title-luxury"><?php echo $title; ?></h1>
                <?php if (!empty($badge)): ?>
                    <span class="badge-luxury-gold"><?php echo $badge; ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($subtitle)): ?>
                <p class="page-subtitle-luxury"><?php echo $subtitle; ?></p>
            <?php endif; ?>
        </div>
        <?php if (!empty($actions)): ?>
            <div class="page-header-actions">
                <?php foreach ($actions as $action): 
                    $btn_class = htmlspecialchars($action['class'] ?? 'btn-outline');
                    $href = htmlspecialchars($action['href'] ?? '#');
                    $label = $action['label'] ?? '';
                    $attr = $action['attr'] ?? '';
                ?>
                    <a href="<?php echo $href; ?>" class="btn-luxury <?php echo $btn_class; ?>" <?php echo $attr; ?>>
                        <?php echo $label; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
}
