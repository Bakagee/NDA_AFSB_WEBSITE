<?php
// database_connection.php - Include this file in all scripts that need DB access
function connectDB() {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'afsb_screening_db';
    
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// For hashing passwords during user creation
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// For verifying passwords during login
function verifyPassword($password, $hashedPassword) {
    return password_verify($password, $hashedPassword);
}

// Session management helper functions
function startSecureSession() {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit;
    }
}

function requireAdminRole() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header("Location: unauthorized.php");
        exit;
    }
}

function requireOfficerRole() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'officer') {
        header("Location: unauthorized.php");
        exit;
    }
}

function logoutUser() {
    session_start();
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}
?>



