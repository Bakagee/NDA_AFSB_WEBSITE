<?php
require_once 'database_connection.php';

// Function to generate a random date of birth for NDA candidates (16-22 years old)
function generateRandomDOB() {
    $current_year = date('Y');
    $min_year = $current_year - 22; // 22 years old
    $max_year = $current_year - 16; // 16 years old
    
    $year = rand($min_year, $max_year);
    $month = rand(1, 12);
    $day = rand(1, 28); // Using 28 to avoid invalid dates
    
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

// Connect to database
$conn = connectDB();

// Get all candidates without a date of birth
$sql = "SELECT candidate_id FROM candidates WHERE date_of_birth IS NULL OR date_of_birth = ''";
$result = $conn->query($sql);

if ($result) {
    $updated = 0;
    $failed = 0;
    
    while ($row = $result->fetch_assoc()) {
        $dob = generateRandomDOB();
        
        // Update the candidate's date of birth
        $stmt = $conn->prepare("UPDATE candidates SET date_of_birth = ? WHERE candidate_id = ?");
        $stmt->bind_param("si", $dob, $row['candidate_id']);
        
        if ($stmt->execute()) {
            $updated++;
        } else {
            $failed++;
        }
        
        $stmt->close();
    }
    
    echo "Update completed:\n";
    echo "Successfully updated: $updated candidates\n";
    echo "Failed updates: $failed candidates\n";
} else {
    echo "Error fetching candidates: " . $conn->error;
}

$conn->close();
?> 