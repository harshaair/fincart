<?php
include "db.php";

// Check investments table structure
$result = $conn->query("DESCRIBE investments");
echo "<h2>Investments Table Structure:</h2>";
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

// Check goals table structure
$result = $conn->query("DESCRIBE goals");
echo "<h2>Goals Table Structure:</h2>";
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

// Check budgets table structure
$result = $conn->query("DESCRIBE budgets");
echo "<h2>Budgets Table Structure:</h2>";
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";
?>
