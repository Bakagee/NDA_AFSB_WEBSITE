<?php
require_once '../database_connection.php';
startSecureSession();
requireAdminRole();

// Get POST data
$module = $_POST['module'] ?? '';
$status = $_POST['status'] ?? false;

// Validate module name
$valid_modules = [
    'moduleDocumentation',
    'moduleMedical',
    'modulePhysical',
    'moduleSandModelling',
    'moduleBoardInterview'
];

if (!in_array($module, $valid_modules)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid module']);
    exit;
}

try {
    $conn = connectDB();
    
    // Update module status in database
    $stmt = $conn->prepare("UPDATE system_settings SET is_enabled = ? WHERE module_name = ?");
    $status_int = $status ? 1 : 0;
    $module_name = str_replace('module', '', strtolower($module));
    $stmt->bind_param("is", $status_int, $module_name);
    
    if ($stmt->execute()) {
        // Log the activity
        $admin_id = $_SESSION['user_id'];
        $action = $status ? "enabled" : "disabled";
        $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, affected_table, record_id) VALUES (?, ?, 'system_settings', ?)");
        $log_stmt->bind_param("isi", $admin_id, $action, $module_name);
        $log_stmt->execute();
        
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to update module status");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
} 