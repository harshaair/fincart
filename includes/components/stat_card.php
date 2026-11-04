<?php
/**
 * Reusable Stat Card Component
 * 
 * Usage:
 * render_stat_card([
 *     'title'      => 'Total Balance',
 *     'value'      => '₹1,24,500',
 *     'icon'       => '💰',
 *     'trend'      => '+12.4%',
 *     'trend_type' => 'positive', // 'positive' | 'negative' | 'neutral'
 *     'subtitle'   => 'vs last month',
 *     'badge'      => 'Live'
 * ]);
 */
function render_stat_card($props = []) {
    $title      = htmlspecialchars($props['title'] ?? 'Metric');
    $value      = htmlspecialchars($props['value'] ?? '0');
    $icon       = $props['icon'] ?? '📊';
    $trend      = htmlspecialchars($props['trend'] ?? '');
    $trend_type = $props['trend_type'] ?? 'neutral';
    $subtitle   = htmlspecialchars($props['subtitle'] ?? '');
    $badge      = htmlspecialchars($props['badge'] ?? '');
    $card_class = $props['class'] ?? '';

    $trend_icon = $trend_type === 'positive' ? '▲ ' : ($trend_type === 'negative' ? '▼ ' : '');
    $trend_class = $trend_type === 'positive' ? 'trend-up' : ($trend_type === 'negative' ? 'trend-down' : 'trend-neutral');
    ?>
    <div class="stat-card <?php echo htmlspecialchars($card_class); ?>">
        <div class="stat-card-header">
            <span class="stat-card-title"><?php echo $title; ?></span>
            <div class="stat-card-icon-wrap"><?php echo $icon; ?></div>
        </div>
        <div class="stat-card-value"><?php echo $value; ?></div>
        <div class="stat-card-footer">
            <?php if (!empty($trend)): ?>
                <span class="stat-card-trend <?php echo $trend_class; ?>">
                    <?php echo $trend_icon . $trend; ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($subtitle)): ?>
                <span class="stat-card-subtitle"><?php echo $subtitle; ?></span>
            <?php endif; ?>
            <?php if (!empty($badge)): ?>
                <span class="badge-luxury ml-auto"><?php echo $badge; ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
