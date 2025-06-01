<?php
// officer_login.php
require_once 'database_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        header("Location: index.php?error=empty_fields");
        exit;
    }
    
    $conn = connectDB();
    
    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT officer_id, username, password, full_name, rank, assigned_state FROM officers WHERE username = ? AND status = 'active'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $officer = $result->fetch_assoc();
        
        // Verify password
        if (verifyPassword($password, $officer['password'])) {
            // Password is correct, start session
            startSecureSession();
            
            $_SESSION['user_id'] = $officer['officer_id'];
            $_SESSION['username'] = $officer['username'];
            $_SESSION['full_name'] = $officer['full_name'];
            $_SESSION['rank'] = $officer['rank'];
            $_SESSION['assigned_state'] = $officer['assigned_state'];
            $_SESSION['user_role'] = 'officer';
            
            // Update last login timestamp
            $updateStmt = $conn->prepare("UPDATE officers SET last_login = NOW() WHERE officer_id = ?");
            $updateStmt->bind_param("i", $officer['officer_id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Redirect to officer dashboard
            header("Location: officer/dashboard.php");
            $stmt->close();
            $conn->close();
            exit;
        } else {
            // Invalid password
            $stmt->close();
            $conn->close();
            header("Location: index.php?error=invalid_credentials");
            exit;
        }
    } else {
        // Username not found
        $stmt->close();
        $conn->close();
        header("Location: index.php?error=invalid_credentials");
        exit;
    }
} else {
    // If not POST request, redirect to the login page
    header("Location: index.php");
    exit;
}
?>
