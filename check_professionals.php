<?php
include "db.php";

// Check if professionals table has data
$result = $conn->query("SELECT COUNT(*) as count FROM professionals");
$row = $result->fetch_assoc();
$count = $row['count'];

echo "Professionals in database: " . $count . "\n";

if ($count == 0) {
    echo "\nNo professionals found. Inserting sample data...\n\n";
    
    // Insert sample professionals
    $professionals = [
        ['CA', 'Rajesh Kumar', 'CA (ICAI)', 12, 'Income Tax & Investment Planning', 'Experienced CA specializing in personal income tax and investment optimization for high net-worth individuals.', '9876543210', 'rajesh@example.com', 'Mumbai', 'Maharashtra', 500, 'Hindi, English'],
        ['CA', 'Priya Sharma', 'CA (ICAI)', 8, 'GST & Corporate Tax', 'Expert in GST compliance and corporate tax planning. Helps businesses optimize tax structure.', '9765432109', 'priya@example.com', 'Delhi', 'Delhi', 600, 'Hindi, English'],
        ['Financial Advisor', 'Amit Patel', 'CFP, MBA Finance', 10, 'Investment & Wealth Management', 'Certified financial planner helping clients build diversified investment portfolios.', '8765432109', 'amit@example.com', 'Bangalore', 'Karnataka', 400, 'Hindi, English, Gujarati'],
        ['Tax Consultant', 'Neha Singh', 'B.Com, Tax Specialist', 6, 'Personal Tax Planning', 'Specializes in helping individuals and freelancers with tax planning and returns.', '7654321098', 'neha@example.com', 'Pune', 'Maharashtra', 350, 'Hindi, English'],
        ['Financial Advisor', 'Vikram Reddy', 'CFA, MBA', 15, 'Retirement & Education Planning', 'Expert in long-term financial planning for retirement and education goals.', '6543210987', 'vikram@example.com', 'Hyderabad', 'Telangana', 700, 'Hindi, English, Telugu']
    ];
    
    foreach ($professionals as $prof) {
        $sql = "INSERT INTO professionals (professional_type, name, qualification, experience_years, specialization, bio, phone, email, city, state, consultation_fee, languages) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssisssssds", $prof[0], $prof[1], $prof[2], $prof[3], $prof[4], $prof[5], $prof[6], $prof[7], $prof[8], $prof[9], $prof[10], $prof[11]);
        
        if ($stmt->execute()) {
            echo "✓ Inserted: " . $prof[1] . " (" . $prof[0] . ")\n";
        } else {
            echo "✗ Failed to insert: " . $prof[1] . "\n";
        }
    }
    
    echo "\nSample data insertion complete!\n";
} else {
    echo "Professionals already exist in database.\n";
    $result = $conn->query("SELECT name, professional_type FROM professionals");
    while ($row = $result->fetch_assoc()) {
        echo "  • " . $row['name'] . " (" . $row['professional_type'] . ")\n";
    }
}
?>
