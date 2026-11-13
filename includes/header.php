<?php
// includes/header.php
if (!isset($page_title)) {
    $page_title = 'FinCart - Personal Finance & Tax Management';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo isset($css_path) ? $css_path : '/assets/css/main.css'; ?>">
    <!-- Global Chart.js (Cached Once) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Universal collision-safe Chart mounting helper
        window.renderChartSafe = function(canvasIdOrElem, config) {
            const elem = typeof canvasIdOrElem === 'string' ? document.getElementById(canvasIdOrElem) : canvasIdOrElem;
            if (!elem || typeof Chart === 'undefined') return null;
            try {
                const existing = Chart.getChart(elem);
                if (existing) {
                    existing.destroy();
                }
            } catch (err) {
                // Ignore cleanup error if canvas was fresh
            }
            return new Chart(elem, config);
        };
    </script>
    <?php if (isset($extra_css)): echo $extra_css; endif; ?>
</head>
<body>

