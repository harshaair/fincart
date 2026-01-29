<?php
include "db.php";

// List all tables
$result = $conn->query("SHOW TABLES FROM fincart");
echo "<h2>All Tables in fincart Database:</h2>";
echo "<ul>";
while ($row = $result->fetch_row()) {
    echo "<li>" . $row[0] . "</li>";
}
echo "</ul>";
?>
