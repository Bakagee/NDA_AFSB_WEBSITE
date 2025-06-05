<?php
require_once '../database_connection.php';
require_once '../vendor/autoload.php'; // For TCPDF and PhpSpreadsheet
startSecureSession();
requireAdminRole();

// Get report type
$report_type = $_POST['report_type'] ?? '';

// Validate report type
$valid_reports = ['qualified', 'disqualified', 'medical', 'physical'];
if (!in_array($report_type, $valid_reports)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid report type']);
    exit;
}

try {
    $conn = connectDB();
    
    // Generate report based on type
    switch ($report_type) {
        case 'qualified':
            $sql = "SELECT 
                        c.chest_number,
                        c.full_name,
                        c.state,
                        c.final_status,
                        c.documentation_status,
                        c.medical_status,
                        c.physical_status,
                        c.sand_modelling_status,
                        c.board_interview_status
                    FROM candidates c
                    WHERE c.final_status = 'passed'
                    ORDER BY c.state, c.chest_number";
            $title = "Qualified Candidates Report";
            break;
            
        case 'disqualified':
            $sql = "SELECT 
                        c.chest_number,
                        c.full_name,
                        c.state,
                        c.final_status,
                        c.documentation_status,
                        c.medical_status,
                        c.physical_status,
                        c.sand_modelling_status,
                        c.board_interview_status
                    FROM candidates c
                    WHERE c.final_status = 'failed'
                    ORDER BY c.state, c.chest_number";
            $title = "Disqualified Candidates Report";
            break;
            
        case 'medical':
            $sql = "SELECT 
                        c.chest_number,
                        c.full_name,
                        c.state,
                        c.medical_status,
                        c.medical_notes
                    FROM candidates c
                    WHERE c.medical_status = 'failed'
                    ORDER BY c.state, c.chest_number";
            $title = "Medical Failures Report";
            break;
            
        case 'physical':
            $sql = "SELECT 
                        c.chest_number,
                        c.full_name,
                        c.state,
                        c.physical_score,
                        c.physical_status
                    FROM candidates c
                    WHERE c.physical_status IS NOT NULL
                    ORDER BY c.physical_score DESC";
            $title = "Physical Score Ranking";
            break;
    }
    
    $result = $conn->query($sql);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    // Generate PDF report
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('NDA AFSB System');
    $pdf->SetAuthor('NDA AFSB Admin');
    $pdf->SetTitle($title);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 12);
    
    // Add title
    $pdf->Cell(0, 10, $title, 0, 1, 'C');
    $pdf->Ln(10);
    
    // Add table headers
    $headers = array_keys($data[0]);
    foreach ($headers as $header) {
        $pdf->Cell(40, 7, ucwords(str_replace('_', ' ', $header)), 1);
    }
    $pdf->Ln();
    
    // Add table data
    foreach ($data as $row) {
        foreach ($row as $value) {
            $pdf->Cell(40, 6, $value, 1);
        }
        $pdf->Ln();
    }
    
    // Generate filename
    $filename = 'reports/' . $report_type . '_report_' . date('Y-m-d_H-i-s') . '.pdf';
    
    // Save PDF
    $pdf->Output($filename, 'F');
    
    // Log the activity
    $admin_id = $_SESSION['user_id'];
    $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, affected_table) VALUES (?, ?, 'reports')");
    $action = "Generated " . $title;
    $log_stmt->bind_param("is", $admin_id, $action);
    $log_stmt->execute();
    
    // Return download URL
    echo json_encode(['success' => true, 'download_url' => $filename]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
} 