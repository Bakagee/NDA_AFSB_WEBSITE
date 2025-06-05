<?php
require_once '../database_connection.php';
startSecureSession();
requireAdminRole();

// Get POST data
$stage_id = $_POST['stage_id'] ?? null;

if (!$stage_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Stage ID is required']);
    exit;
}

try {
    $conn = connectDB();
    
    // Toggle the is_active status
    $stmt = $conn->prepare("UPDATE stages SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param("i", $stage_id);
    
    if ($stmt->execute()) {
        // Log the activity
        $admin_id = $_SESSION['user_id'];
        $action = "toggled stage status";
        $details = "Stage ID: " . $stage_id;
        $ip = $_SERVER['REMOTE_ADDR'];
        
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, user_type, user_role, activity_type, activity_details, ip_address) VALUES (?, 'admin', 'admin', ?, ?, ?)");
        $log_stmt->bind_param("isss", $admin_id, $action, $details, $ip);
        
        if (!$log_stmt->execute()) {
            // If logging fails, we still want to return success for the toggle
            error_log("Failed to log activity: " . $log_stmt->error);
        }
        
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to update stage status: " . $stmt->error);
    }
} catch (Exception $e) {
    error_log("Error in toggle_stage.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
} 