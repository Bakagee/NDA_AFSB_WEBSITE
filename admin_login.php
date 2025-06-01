<?php
// admin_login.php
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
    $stmt = $conn->prepare("SELECT admin_id, username, password, full_name, email FROM admins WHERE username = ? AND status = 'active'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        
        // Verify password
        if (verifyPassword($password, $admin['password'])) {
            // Password is correct, start session
            startSecureSession();
            
            $_SESSION['user_id'] = $admin['admin_id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['full_name'] = $admin['full_name'];
            $_SESSION['email'] = $admin['email'];
            $_SESSION['user_role'] = 'admin';
            
            // Update last login timestamp
            $updateStmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?");
            $updateStmt->bind_param("i", $admin['admin_id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Redirect to admin dashboard
            header("Location: admin/dashboard.php");
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