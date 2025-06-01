<?php
// logout.php - Secure logout script for admin and officer users
require_once 'database_connection.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Log the logout if user was logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'];
    
    // Log the logout action
    $conn = connectDB();
    $sql = "INSERT INTO activity_logs (user_id, user_role, action_type, description, ip_address) 
            VALUES (?, ?, 'logout', 'User logged out', ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iss", $user_id, $user_role, $_SERVER['REMOTE_ADDR']);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->close();
}

// Destroy the session
session_unset();
session_destroy();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header("Location: index.php");
exit;
?>
