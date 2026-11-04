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
    <link rel="stylesheet" href="<?php echo isset($css_path) ? $css_path : 'assets/css/main.css'; ?>">
    <?php if (isset($extra_css)): echo $extra_css; endif; ?>
</head>
<body>

