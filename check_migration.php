<?php
// Connect to database
$conn = new mysqli("localhost", "root", "", "fincart");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Checking appointments table structure...</h2>";
echo "<pre>";

// Check table structure
$result = $conn->query("DESCRIBE appointments");
if ($result) {
    echo "Current appointments table columns:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}

echo "\n\n<h2>Applying migration...</h2>\n";

// Read migration file
$migration = file_get_contents('migration_add_appointment_columns.sql');

// Split by semicolon and execute each statement
$statements = array_filter(array_map('trim', explode(';', $migration)), function($s) {
    return !empty($s) && substr(trim($s), 0, 2) !== '--';
});

foreach ($statements as $statement) {
    echo "Executing: " . substr($statement, 0, 50) . "...\n";
    if ($conn->query($statement)) {
        echo "✓ Success\n";
    } else {
        echo "✗ Error: " . $conn->error . "\n";
    }
}

echo "\n<h2>Final table structure:</h2>\n";
$result = $conn->query("DESCRIBE appointments");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "</pre>";
$conn->close();
?>
