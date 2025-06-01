<?php
/**
 * Stage Management Functions
 * 
 * This file contains shared functions for managing candidate stages across the application.
 */

if (!function_exists('moveToNextStage')) {
    /**
     * Move candidate to next stage after successful documentation verification
     * 
     * @param mysqli $conn Database connection
     * @param int $candidate_id The candidate ID
     * @return void
     */
    function moveToNextStage($conn, $candidate_id) {
        // Get next stage ID (assuming next stage after documentation is medical)
        $stmt = $conn->prepare("SELECT id FROM stages WHERE stage_name = 'medical'");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $next_stage_id = $row['id'];
        $stmt->close();
        
        // Check if candidate is already in next stage
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM candidate_stages 
                              WHERE candidate_id = ? AND stage_id = ?");
        $stmt->bind_param("ii", $candidate_id, $next_stage_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $exists = $row['count'] > 0;
        $stmt->close();
        
        if (!$exists) {
            // Add candidate to next stage with pending status
            $status = 'pending';
            $stmt = $conn->prepare("INSERT INTO candidate_stages 
                                  (candidate_id, stage_id, status, created_at, created_by) 
                                  VALUES (?, ?, ?, NOW(), ?)");
            $stmt->bind_param("iisi", $candidate_id, $next_stage_id, $status, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('getOfficerDetails')) {
    /**
     * Get officer details from the database
     * 
     * @param int $officer_id The officer ID
     * @return array|null Officer details or null if not found
     */
    function getOfficerDetails($officer_id) {
        $conn = connectDB();
        
        $stmt = $conn->prepare("SELECT * FROM officers WHERE officer_id = ?");
        $stmt->bind_param("i", $officer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $officer = $result->fetch_assoc();
        
        $stmt->close();
        $conn->close();
        
        return $officer;
    }
}

if (!function_exists('getCandidateDetails')) {
    /**
     * Get detailed candidate information including state name
     * 
     * @param int $candidate_id The candidate ID
     * @return array|null Candidate details or null if not found
     */
    function getCandidateDetails($candidate_id) {
        $conn = connectDB();
        
        $sql = "SELECT c.*, s.state_name 
                FROM candidates c 
                LEFT JOIN states s ON c.state_id = s.id 
                WHERE c.candidate_id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $candidate_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $candidate = $result->fetch_assoc();
        
        $stmt->close();
        $conn->close();
        
        return $candidate;
    }
} 