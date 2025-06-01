<?php
require_once __DIR__ . '/ocr_functions.php';
require_once __DIR__ . '/stage_functions.php';

/**
 * Process the uploaded NDA AFSB Screening Documentation Form
 * 
 * This function analyzes the uploaded form image using OCR to detect checked boxes
 * and extracts the form data to automatically update the verification status.
 * 
 * @param int $candidate_id The candidate ID
 * @param array $file The uploaded file data ($_FILES['screening_form'])
 * @return array Processing result with detected data
 */
function processScreeningDocumentationForm($candidate_id, $file) {
    $result = [
        'success' => false,
        'message' => '',
        'candidate_info' => [],
        'documents' => [
            'original' => [],
            'filled' => []
        ],
        'verification_issues' => [],
        'officer_info' => [],
        'verification_date' => date('Y-m-d')
    ];
    
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = 'No file uploaded or upload error occurred.';
        return $result;
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $file_type = $file['type'];
    if (!in_array($file_type, $allowed_types)) {
        $result['message'] = 'Invalid file type. Only JPG, PNG and PDF files are allowed.';
        return $result;
    }
    
    // Create directory if it doesn't exist
    $upload_dir = '../uploads/screening_forms/' . $candidate_id . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = 'screening_form_' . time() . '_' . uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . $file_name;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        $result['message'] = 'Failed to move uploaded file.';
        return $result;
    }
    
    // Convert PDF to image if needed
    $image_to_process = $file_path;
    if ($file_extension === 'pdf') {
        // You would need to implement PDF to image conversion here
        // For example, using Imagick or a third-party service
        // For now, we'll just indicate that PDF processing is limited
        $result['message'] = 'PDF processing is limited. For best results, upload a clear image of the form.';
    }
    
    // Process image with OCR to extract text
    $ocr_text = processOCR($image_to_process);
    
    if (empty($ocr_text)) {
        $result['message'] = 'Could not extract any text from the form. Please upload a clearer image.';
        return $result;
    }
    
    // Extract candidate information from OCR text
    $result['candidate_info'] = extractCandidateInfo($ocr_text);
    
    // Extract document verifications from OCR text
    $result['documents'] = extractDocumentVerifications($ocr_text);
    
    // Extract verification issues from OCR text
    $result['verification_issues'] = extractVerificationIssues($ocr_text);
    
    // Extract officer information from OCR text
    $result['officer_info'] = extractOfficerInfo($ocr_text);
    
    // Store the processed form in database
    storeProcessedForm($candidate_id, $file_path, $ocr_text, $result);
    
    // Update document verifications based on detected checkboxes
    updateDocumentVerifications($candidate_id, $result);
    
    $result['success'] = true;
    $result['message'] = 'Screening form successfully processed.';
    
    return $result;
}

/**
 * Extract candidate information from OCR text
 */
function extractCandidateInfo($ocr_text) {
    $info = [];
    
    // Extract full name
    if (preg_match('/Full\s+Name\s*[:]*\s*([\w\s\.]+)/i', $ocr_text, $matches)) {
        $info['full_name'] = trim($matches[1]);
    }
    
    // Extract state of origin
    if (preg_match('/State\s+of\s+Origin\s*[:]*\s*([\w\s\.]+)/i', $ocr_text, $matches)) {
        $info['state_of_origin'] = trim($matches[1]);
    }
    
    // Extract chest number
    if (preg_match('/Chest\s+Number\s*[:]*\s*([\w\d\-\/]+)/i', $ocr_text, $matches)) {
        $info['chest_number'] = trim($matches[1]);
    }
    
    return $info;
}

/**
 * Extract document verifications based on detected checkboxes in OCR text
 */
function extractDocumentVerifications($ocr_text) {
    $documents = [
        'original' => [
            'primary_school_certificate' => false,
            'primary_school_testimonial' => false,
            'ssce_certificate' => false,
            'ssce_testimonial' => false,
            'nin_slip' => false,
            'bvn_printout' => false,
            'certificate_of_indigene' => false,
            'jamb_result_slip' => false,
            'birth_certificate' => false
        ],
        'filled' => [
            'acknowledgement_form' => false,
            'nda_screening_card' => false,
            'letter_of_attestation' => false,
            'letter_of_consent' => false
        ]
    ];
    
    // Detection patterns for checked boxes
    $checkbox_patterns = [
        // The pattern looks for a checkbox-like character or [x] or [X] or [✓] near the document name
        'primary_school_certificate' => '/\[[xX✓\s*]\]\s*Primary\s+School\s+Certificate/i',
        'primary_school_testimonial' => '/\[[xX✓\s*]\]\s*Primary\s+School\s+Testimonial/i',
        'ssce_certificate' => '/\[[xX✓\s*]\]\s*SSCE\s+Certificate\/Result/i',
        'ssce_testimonial' => '/\[[xX✓\s*]\]\s*SSCE\s+Testimonial/i',
        'nin_slip' => '/\[[xX✓\s*]\]\s*NIN\s+Slip/i',
        'bvn_printout' => '/\[[xX✓\s*]\]\s*BVN\s+Printout/i',
        'certificate_of_indigene' => '/\[[xX✓\s*]\]\s*Certificate\s+of\s+Indigene/i',
        'jamb_result_slip' => '/\[[xX✓\s*]\]\s*JAMB\s+Result\s+Slip/i',
        'birth_certificate' => '/\[[xX✓\s*]\]\s*Birth\s+Certificate|Declaration\s+of\s+Age/i',
        
        // Filled documents
        'acknowledgement_form' => '/\[[xX✓\s*]\]\s*Acknowledgement\s+Form/i',
        'nda_screening_card' => '/\[[xX✓\s*]\]\s*NDA\s+Screening\s+Test\s+Admission\s+Card/i',
        'letter_of_attestation' => '/\[[xX✓\s*]\]\s*Letter\s+of\s+Attestation/i',
        'letter_of_consent' => '/\[[xX✓\s*]\]\s*Letter\s+of\s+Consent/i'
    ];
    
    // Check for each document
    foreach ($checkbox_patterns as $doc_key => $pattern) {
        if (preg_match($pattern, $ocr_text)) {
            if (array_key_exists($doc_key, $documents['original'])) {
                $documents['original'][$doc_key] = true;
            } else if (array_key_exists($doc_key, $documents['filled'])) {
                $documents['filled'][$doc_key] = true;
            }
        }
    }
    
    return $documents;
}

/**
 * Extract verification issues from OCR text
 */
function extractVerificationIssues($ocr_text) {
    $issues = [
        'name_mismatch' => 'No',
        'dob_issue' => 'No Issue',
        'alteration' => 'No',
        'fake_document' => 'No',
        'missing_document' => 'No'
    ];
    
    // Check for name mismatch
    if (preg_match('/Name\s+Mismatch\s*:\s*\[[xX✓\s*]\]\s*Yes/i', $ocr_text)) {
        $issues['name_mismatch'] = 'Yes';
    }
    
    // Check for DOB issues
    if (preg_match('/Date\s+of\s+Birth\s+Issue\s*:\s*\[[xX✓\s*]\]\s*Over\s+Age/i', $ocr_text)) {
        $issues['dob_issue'] = 'Over Age';
    } else if (preg_match('/Date\s+of\s+Birth\s+Issue\s*:\s*\[[xX✓\s*]\]\s*Under\s+Age/i', $ocr_text)) {
        $issues['dob_issue'] = 'Under Age';
    }
    
    // Check for document alterations
    if (preg_match('/Alteration\s+on\s+Document\s*\(s\)\s*:\s*\[[xX✓\s*]\]\s*Yes/i', $ocr_text)) {
        $issues['alteration'] = 'Yes';
    }
    
    // Check for fake documents
    if (preg_match('/Fake\s+Document\s+Detected\s*:\s*\[[xX✓\s*]\]\s*Yes/i', $ocr_text)) {
        $issues['fake_document'] = 'Yes';
    }
    
    // Check for missing documents
    if (preg_match('/Any\s+Missing\s+Document\s*\(s\)\s*:\s*\[[xX✓\s*]\]\s*Yes/i', $ocr_text)) {
        $issues['missing_document'] = 'Yes';
    }
    
    return $issues;
}

/**
 * Extract officer information from OCR text
 */
function extractOfficerInfo($ocr_text) {
    $info = [];
    
    // Extract officer name
    if (preg_match('/Screening\s+Officer\'s\s+Name\s*[:]*\s*([\w\s\.]+)/i', $ocr_text, $matches)) {
        $info['name'] = trim($matches[1]);
    }
    
    // Extract any other officer information as needed
    
    return $info;
}

/**
 * Store processed form data in database
 */
function storeProcessedForm($candidate_id, $file_path, $ocr_text, $processed_data) {
    $conn = connectDB();
    
    // Convert processed data to JSON
    $processed_data_json = json_encode($processed_data);
    
    // Check if there's an existing record
    $stmt = $conn->prepare("SELECT id FROM screening_forms WHERE candidate_id = ?");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    
    if ($exists) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE screening_forms 
                              SET file_path = ?, ocr_text = ?, processed_data = ?,
                                  processed_at = NOW(), processed_by = ?
                              WHERE candidate_id = ?");
        $stmt->bind_param("sssii", $file_path, $ocr_text, $processed_data_json, $_SESSION['user_id'], $candidate_id);
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO screening_forms 
                             (candidate_id, file_path, ocr_text, processed_data, processed_at, processed_by)
                             VALUES (?, ?, ?, ?, NOW(), ?)");
        $stmt->bind_param("isssi", $candidate_id, $file_path, $ocr_text, $processed_data_json, $_SESSION['user_id']);
    }
    
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

/**
 * Update document verifications based on detected checkboxes
 */
function updateDocumentVerifications($candidate_id, $processed_data) {
    $conn = connectDB();
    
    // Prepare data for document_verifications table
    $verified_documents = [];
    
    // Add all verified original documents
    foreach ($processed_data['documents']['original'] as $doc_key => $is_verified) {
        if ($is_verified) {
            $verified_documents[] = $doc_key;
        }
    }
    
    // Add all verified filled documents
    foreach ($processed_data['documents']['filled'] as $doc_key => $is_verified) {
        if ($is_verified) {
            $verified_documents[] = $doc_key;
        }
    }
    
    // Convert to JSON
    $verified_json = json_encode($verified_documents);
    $verification_details_json = json_encode($processed_data['verification_issues']);
    
    // Determine verification status based on issues
    $verification_status = 'verified'; // Default
    
    // If any critical issues are detected, mark as rejected
    if ($processed_data['verification_issues']['name_mismatch'] === 'Yes' ||
        $processed_data['verification_issues']['dob_issue'] !== 'No Issue' ||
        $processed_data['verification_issues']['alteration'] === 'Yes' ||
        $processed_data['verification_issues']['fake_document'] === 'Yes' ||
        $processed_data['verification_issues']['missing_document'] === 'Yes') {
        
        $verification_status = 'rejected';
    }
    
    // Check if verification record exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM document_verifications WHERE candidate_id = ?");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $exists = $row['count'] > 0;
    $stmt->close();
    
    // Prepare verification notes
    $verification_notes = "Automatically processed from screening form on " . date('Y-m-d H:i:s');
    if (!empty($processed_data['officer_info']['name'])) {
        $verification_notes .= "\nScreening Officer: " . $processed_data['officer_info']['name'];
    }
    
    // Add notes about verification issues
    if ($verification_status === 'rejected') {
        $verification_notes .= "\n\nVerification Issues Detected:";
        foreach ($processed_data['verification_issues'] as $issue => $value) {
            if ($issue === 'name_mismatch' && $value === 'Yes') {
                $verification_notes .= "\n- Name mismatch detected";
            }
            if ($issue === 'dob_issue' && $value !== 'No Issue') {
                $verification_notes .= "\n- Date of Birth issue: " . $value;
            }
            if ($issue === 'alteration' && $value === 'Yes') {
                $verification_notes .= "\n- Document alterations detected";
            }
            if ($issue === 'fake_document' && $value === 'Yes') {
                $verification_notes .= "\n- Fake document detected";
            }
            if ($issue === 'missing_document' && $value === 'Yes') {
                $verification_notes .= "\n- Missing required documents";
            }
        }
    }
    
    // Update or insert verification record
    if ($exists) {
        $stmt = $conn->prepare("UPDATE document_verifications 
                              SET verified_documents = ?, verification_notes = ?, 
                                  verification_status = ?, verification_details = ?,
                                  verified_by = ?, verified_at = NOW(), auto_processed = 1
                              WHERE candidate_id = ?");
        $stmt->bind_param("ssssii", $verified_json, $verification_notes, $verification_status, $verification_details_json, $_SESSION['user_id'], $candidate_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO document_verifications 
                              (candidate_id, verified_documents, verification_notes, verification_status, 
                               verification_details, verified_by, verified_at, auto_processed)
                              VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)");
        $stmt->bind_param("issssi", $candidate_id, $verified_json, $verification_notes, $verification_status, $verification_details_json, $_SESSION['user_id']);
    }
    
    $stmt->execute();
    $stmt->close();
    
    // Update candidate stage status
    $stage_status = ($verification_status === 'verified') ? 'passed' : 'failed';
    
    $stmt = $conn->prepare("UPDATE candidate_stages 
                          SET status = ?, updated_at = NOW(), updated_by = ? 
                          WHERE candidate_id = ? AND stage_id = (SELECT id FROM stages WHERE stage_name = 'documentation')");
    $stmt->bind_param("sii", $stage_status, $_SESSION['user_id'], $candidate_id);
    $stmt->execute();
    $stmt->close();
    
    // If passed, move to next stage
    if ($stage_status === 'passed') {
        moveToNextStage($conn, $candidate_id);
    }
    
    $conn->close();
}
?>
