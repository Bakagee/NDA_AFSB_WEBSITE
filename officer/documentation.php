<?php
// Include required files
require_once '../database_connection.php';
require_once 'functions/stage_functions.php';

// Start secure session
session_start();

// Check if user is logged in as officer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'officer') {
    header('Location: ../officer_login.php');
    exit;
}

// Get officer details
$officer_id = $_SESSION['user_id'];
$officer = getOfficerDetails($officer_id);

// Get state information
$state = $officer['assigned_state'];

// Initialize variables
$success_message = '';
$error_message = '';
$selected_candidate = null;
$verification_data = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_documents'])) {
        handleDocumentVerification();
    }
}

// Get candidates for the officer's assigned state who are in the documentation stage
$candidates = getCandidatesInDocumentationStage($state);

// Get candidate details if a candidate is selected
if (isset($_GET['candidate_id'])) {
    $candidate_id = $_GET['candidate_id'];
    $selected_candidate = getCandidateDetails($candidate_id);
    $verification_data = getDocumentVerificationData($candidate_id);
}

// Get document types required for verification
$required_documents = getRequiredDocuments();

/**
 * Handle document verification
 */
function handleDocumentVerification() {
    global $error_message, $success_message;
    
    $candidate_id = $_POST['candidate_id'];
    $verified_documents = isset($_POST['verified_documents']) ? $_POST['verified_documents'] : [];
    $verification_flags = isset($_POST['verification_flags']) ? $_POST['verification_flags'] : [];
    $verification_notes = $_POST['verification_notes'];
    $disqualification_reason = $_POST['disqualification_reason'] ?? '';
    
    // Check if confirmation checkboxes are checked
    $confirm_no_flags = isset($_POST['confirm_no_flags']) && $_POST['confirm_no_flags'] === 'on';
    $confirm_all_docs = isset($_POST['confirm_all_docs']) && $_POST['confirm_all_docs'] === 'on';
    
    // If there are flags, we don't need the no_flags confirmation
    if (!empty($verification_flags)) {
        if (empty($disqualification_reason)) {
            $error_message = "Please provide a reason for disqualification when flags are present.";
            return;
        }
        $is_qualified = false;
    } else {
        // If no flags, require both confirmations
        if (!$confirm_no_flags || !$confirm_all_docs) {
            $error_message = "Please confirm that you have checked all documents and the candidate is free of flags.";
            return;
        }
        $is_qualified = count($verified_documents) === count(getRequiredDocuments()['all_docs']);
    }
    
    $result = processDocumentVerification(
        $candidate_id,
        $verified_documents,
        $verification_flags,
        $verification_notes,
        $is_qualified,
        $disqualification_reason
    );
    
    if ($result) {
        // Set success message in session
        $_SESSION['success_message'] = $is_qualified ? 
            "Document verification completed successfully. Candidate is qualified for the next stage." : 
            "Document verification completed. Candidate has been disqualified.";
        
        // Redirect to refresh the page with new data
        header("Location: documentation.php?candidate_id=" . $candidate_id);
        exit;
    } else {
        $error_message = "Failed to update document verification.";
    }
}

// Get success message from session if it exists
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying
}

/**
 * Process document verification and save to database
 */
function processDocumentVerification($candidate_id, $verified_documents, $verification_flags, $notes, $is_qualified, $disqualification_reason) {
    global $officer_id;
    
    $conn = connectDB();
    
    // Check if verification record exists
    $check_stmt = $conn->prepare("SELECT id FROM document_verifications WHERE candidate_id = ?");
    $check_stmt->bind_param("i", $candidate_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    // Prepare verification details
    $verification_details = json_encode([
        'flags' => $verification_flags,
        'disqualification_reason' => $disqualification_reason
    ]);
    
    // Prepare values for binding
    $verified_docs_json = json_encode($verified_documents);
    $verification_status = $is_qualified ? 'verified' : 'rejected';
    
    if ($exists) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE document_verifications SET 
                               verified_documents = ?, 
                               verification_notes = ?, 
                               verification_status = ?, 
                               verified_by = ?, 
                               verified_at = NOW(),
                               verification_details = ?
                               WHERE candidate_id = ?");
        $stmt->bind_param("sssiss", 
            $verified_docs_json, 
            $notes, 
            $verification_status,
            $officer_id, 
            $verification_details,
            $candidate_id
        );
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO document_verifications 
                               (candidate_id, verified_documents, verification_notes, verification_status, verified_by, verified_at, verification_details) 
                               VALUES (?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->bind_param("isssis", 
            $candidate_id, 
            $verified_docs_json, 
            $notes, 
            $verification_status,
            $officer_id,
            $verification_details
        );
    }
    
    $result = $stmt->execute();
    $stmt->close();
    
    // If verification is successful and candidate is qualified, update candidate stage
    if ($result && $is_qualified) {
        $update_stmt = $conn->prepare("UPDATE candidate_stages 
                                     SET stage_id = (SELECT id FROM stages WHERE stage_name = 'medical'),
                                         updated_at = NOW()
                                     WHERE candidate_id = ?");
        $update_stmt->bind_param("i", $candidate_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    $conn->close();
    
    return $result;
}

/**
 * Get officer details from database
 */
function getOfficerDetails($officer_id) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT officer_id, username, rank, full_name, email, phone, assigned_state, profile_image 
                           FROM officers WHERE officer_id = ?");
    $stmt->bind_param("i", $officer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $officer = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $officer;
}

/**
 * Get candidates that are in the documentation stage for a specific state
 */
function getCandidatesInDocumentationStage($state) {
    $conn = connectDB();
    
    $sql = "SELECT DISTINCT c.candidate_id, c.nda_application_number, c.first_name, c.surname,  
                   c.status, c.profile_picture, cs.status as doc_status,
                   dv.verification_status, dv.verified_at, st.stage_name as current_stage
            FROM candidates c
            JOIN states s ON c.state_id = s.id
            JOIN candidate_stages cs ON c.candidate_id = cs.candidate_id
            JOIN stages st ON cs.stage_id = st.id
            LEFT JOIN document_verifications dv ON c.candidate_id = dv.candidate_id
            WHERE (s.state_name = ? OR s.state_code = ?)
            AND (
                (st.stage_name = 'documentation' AND cs.status = 'pending')
                OR EXISTS (
                    SELECT 1 FROM document_verifications 
                    WHERE candidate_id = c.candidate_id
                )
            )
            GROUP BY c.candidate_id
            ORDER BY c.surname, c.first_name ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $state, $state);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $candidates = [];
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    return $candidates;
}

/**
 * Get candidate details by ID
 */
function getCandidateDetails($candidate_id) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT c.*, s.state_name, s.state_code 
                            FROM candidates c
                            JOIN states s ON c.state_id = s.id
                            WHERE c.candidate_id = ?");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidate = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $candidate;
}

/**
 * Get document verification data for a candidate
 */
function getDocumentVerificationData($candidate_id) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM document_verifications WHERE candidate_id = ?");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $verification = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $verification;
}

/**
 * Get required documents for verification
 */
function getRequiredDocuments() {
    return [
        'all_docs' => [
            'birth_certificate' => 'Birth Certificate',
            'waec_result' => 'WAEC Result',
            'jamb_result' => 'JAMB Result',
            'state_of_origin_certificate' => 'State of Origin Certificate',
            'passport_photograph' => 'Postcard Photograph',
            'primary_school_certificate' => 'Primary School Certificate',
            'primary_school_testimonial' => 'Primary School Testimonial',
            'secondary_school_testimonial' => 'Secondary School Testimonial',
            'indigene_certificate' => 'Indigene Certificate',
            'bvn' => 'BVN',
            'nin' => 'NIN',
            'nda_admission_card' => 'NDA Admission Card',
            'attestation_letter' => 'Attestation Letter',
            'parent_consent_form' => 'Parent Consent Form',
            'acknowledgement_form' => 'Acknowledgement Form'
        ],
        'validation_flags' => [
            'name_mismatch' => 'Name Mismatch',
            'date_of_birth_mismatch' => 'Date of Birth Mismatch',
            'underage' => 'Underage',
            'overage' => 'Overage',
            'forgery_or_alteration' => 'Forgery or Alteration'
        ]
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation Verification - NDA Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .candidate-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .candidate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .document-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #e9ecef;
        }
        .verification-section {
            background: #fff3cd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #ffeaa7;
        }
        .disqualification-section {
            background: #f8d7da;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #f5c6cb;
        }
        .verification-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-qualified { background: #d4edda; color: #155724; }
        .status-disqualified { background: #f8d7da; color: #721c24; }
        .objective-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .checklist-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 8px;
            background: white;
            border: 1px solid #dee2e6;
        }
        .checklist-item:hover {
            background: #f8f9fa;
        }
        .flag-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #ffc107;
        }
        .flag-item.checked {
            background: #f8d7da;
            border-color: #dc3545;
        }
        .tooltip-icon {
            color: #6c757d;
            cursor: help;
        }
        .document-section .card {
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .document-section .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .document-section .form-check {
            padding: 8px 12px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .document-section .form-check:hover {
            background-color: #f8f9fa;
        }
        .document-section .form-check-input:checked + .form-check-label {
            color: #198754;
            font-weight: 500;
        }
        .flag-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #dee2e6;
            transition: all 0.2s;
        }
        .flag-item:hover {
            background: #f8f9fa;
        }
        .flag-item.checked {
            background: #f8d7da;
            border-color: #dc3545;
        }
        .missing-docs-list {
            font-size: 0.9em;
        }
        .tooltip-icon {
            color: #6c757d;
            cursor: help;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <img src="../img/nda-logo.png" alt="NDA Logo" height="40" onerror="this.src='../img/placeholder-logo.png'">
                <span>NDA AFSB</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="documentation.php">Documentation</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <button class="btn btn-link text-white dropdown-toggle" type="button" id="profileDropdown" data-bs-toggle="dropdown">
                            <img src="../img/<?php echo htmlspecialchars($officer['profile_image'] ?: 'default_officer.png'); ?>" 
                                 alt="Profile" class="rounded-circle" width="32" height="32" onerror="this.src='../img/default_officer.png'">
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($officer['rank']); ?> <?php echo htmlspecialchars($officer['username']); ?></span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <div class="dropdown-header">
                                <div class="text-center">
                                    <img src="../img/<?php echo htmlspecialchars($officer['profile_image'] ?: 'default_officer.png'); ?>" 
                                         alt="Profile" class="rounded-circle" width="48" height="48" style="margin-bottom: 10px;" 
                                         onerror="this.src='../img/default_officer.png'">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($officer['full_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($officer['rank']); ?></small>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user-circle mr-2"></i> My Profile
                            </a>
                            <a class="dropdown-item" href="change_password.php">
                                <i class="fas fa-key mr-2"></i> Change Password
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-4">
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Candidates List -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users me-2"></i>Candidates in Documentation Stage
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($candidates)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No candidates found in documentation stage.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($candidates as $candidate): 
                                    $list_profile_picture = $candidate['profile_picture'];
                                    $list_profile_path = '';
                                    
                                    if (!empty($list_profile_picture) && file_exists('../uploads/candidates/' . $list_profile_picture)) {
                                        $list_profile_path = '../uploads/candidates/' . $list_profile_picture;
                                    } else {
                                        $list_profile_path = '../assets/images/default-profile.png';
                                    }

                                    // Get verification status for this candidate
                                    $verification_status = $candidate['verification_status'] ?? 'pending';
                                    $verified_at = $candidate['verified_at'] ? date('M j, Y', strtotime($candidate['verified_at'])) : '';
                                    $current_stage = $candidate['current_stage'];
                                ?>
                                    <a href="?candidate_id=<?php echo $candidate['candidate_id']; ?>" 
                                       class="list-group-item list-group-item-action d-flex align-items-center <?php echo ($selected_candidate && $selected_candidate['candidate_id'] == $candidate['candidate_id']) ? 'active' : ''; ?>">
                                        <img src="<?php echo htmlspecialchars($list_profile_path); ?>" 
                                             alt="Profile" class="rounded-circle me-3" width="40" height="40"
                                             onerror="this.src='../assets/images/default-profile.png'">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($candidate['surname'] . ', ' . $candidate['first_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($candidate['nda_application_number']); ?></small>
                                            <?php if ($verified_at): ?>
                                                <br>
                                                <small class="text-muted">Verified: <?php echo $verified_at; ?></small>
                                            <?php endif; ?>
                                            <?php if ($current_stage !== 'documentation'): ?>
                                                <br>
                                                <small class="text-muted">Current Stage: <?php echo ucfirst($current_stage); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($verification_status === 'verified'): ?>
                                            <span class="badge bg-success ms-2">Verified</span>
                                        <?php elseif ($verification_status === 'rejected'): ?>
                                            <span class="badge bg-danger ms-2">Rejected</span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Candidate Details and Verification -->
            <div class="col-md-8">
                <?php if ($selected_candidate): ?>
                    <?php if ($verification_data): ?>
                        <div class="alert alert-info mb-4">
                            <h5 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Documentation Already Verified</h5>
                            <p class="mb-0">
                                This candidate's documentation has already been verified on 
                                <?php echo date('F j, Y, g:i a', strtotime($verification_data['verified_at'])); ?>.
                                <?php if ($verification_data['verification_status'] === 'verified'): ?>
                                    <span class="text-success">The candidate was qualified.</span>
                                <?php else: ?>
                                    <span class="text-danger">The candidate was disqualified.</span>
                                <?php endif; ?>
                            </p>
                            <?php if ($verification_data['verification_notes']): ?>
                                <hr>
                                <p class="mb-0"><strong>Notes:</strong> <?php echo htmlspecialchars($verification_data['verification_notes']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Candidate Information -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user me-2"></i>Candidate Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <?php
                                    $profile_picture = $selected_candidate['profile_picture'];
                                    $profile_path = '';
                                    
                                    if (!empty($profile_picture) && file_exists('../uploads/candidates/' . $profile_picture)) {
                                        $profile_path = '../uploads/candidates/' . $profile_picture;
                                    } else {
                                        $profile_path = '../assets/images/default-profile.png';
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($profile_path); ?>" 
                                         alt="Profile" class="rounded-circle mb-3" width="120" height="120"
                                         onerror="this.src='../assets/images/default-profile.png'">
                                </div>
                                <div class="col-md-9">
                                    <h4><?php echo htmlspecialchars($selected_candidate['surname'] . ', ' . $selected_candidate['first_name']); ?></h4>
                                    <p class="text-muted"><?php echo htmlspecialchars($selected_candidate['nda_application_number']); ?></p>
                                    
                                    <?php if ($verification_data): ?>
                                        <div class="mb-3">
                                            <?php if ($verification_data['verification_status'] === 'verified'): ?>
                                                <span class="verification-status status-qualified">
                                                    <i class="fas fa-check-circle me-1"></i>QUALIFIED
                                                </span>
                                            <?php else: ?>
                                                <span class="verification-status status-disqualified">
                                                    <i class="fas fa-times-circle me-1"></i>DISQUALIFIED
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>State:</strong> <?php echo htmlspecialchars($selected_candidate['state_name']); ?></p>
                                            <p><strong>Gender:</strong> <?php echo htmlspecialchars($selected_candidate['sex']); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($selected_candidate['date_of_birth']); ?></p>
                                            <p><strong>Status:</strong> <?php echo htmlspecialchars($selected_candidate['status']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Document Verification Form -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-clipboard-check me-2"></i>Document Verification Process
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST" id="verificationForm">
                                <input type="hidden" name="candidate_id" value="<?php echo $selected_candidate['candidate_id']; ?>">
                                
                                <!-- Step 1: Documents Verification -->
                                <div class="document-section">
                                    <h6 class="mb-3">
                                        <i class="fas fa-folder-open me-2"></i>Step 1: Documents Verification
                                    </h6>
                                    
                                    <!-- Quick Actions -->
                                    <div class="d-flex gap-2 mb-3">
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="checkAllDocs">
                                            <i class="fas fa-check-double me-1"></i>Check All Documents
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="uncheckAllDocs">
                                            <i class="fas fa-times me-1"></i>Uncheck All
                                        </button>
                                    </div>

                                    <!-- Document Categories -->
                                    <div class="row g-3">
                                        <!-- Personal Documents -->
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-header bg-light">
                                                    <h6 class="mb-0"><i class="fas fa-user me-2"></i>Personal Documents</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="document-list">
                                                        <?php
                                                        $personal_docs = [
                                                            'birth_certificate' => 'Birth Certificate',
                                                            'state_of_origin_certificate' => 'State of Origin Certificate',
                                                            'indigene_certificate' => 'Indigene Certificate',
                                                            'passport_photograph' => 'Postcard Photograph',
                                                            'bvn' => 'BVN',
                                                            'nin' => 'NIN'
                                                        ];
                                                        foreach ($personal_docs as $doc_key => $doc_name): ?>
                                                            <div class="form-check mb-2">
                                                                <input class="form-check-input document-checkbox" type="checkbox" 
                                                                       name="verified_documents[]" 
                                                                       value="<?php echo $doc_key; ?>"
                                                                       id="doc_<?php echo $doc_key; ?>">
                                                                <label class="form-check-label" for="doc_<?php echo $doc_key; ?>">
                                                                    <i class="fas fa-file-alt me-1"></i>
                                                                    <?php echo htmlspecialchars($doc_name); ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Academic Documents -->
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-header bg-light">
                                                    <h6 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Academic Documents</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="document-list">
                                                        <?php
                                                        $academic_docs = [
                                                            'waec_result' => 'WAEC Result',
                                                            'jamb_result' => 'JAMB Result',
                                                            'primary_school_certificate' => 'Primary School Certificate',
                                                            'primary_school_testimonial' => 'Primary School Testimonial',
                                                            'secondary_school_testimonial' => 'Secondary School Testimonial'
                                                        ];
                                                        foreach ($academic_docs as $doc_key => $doc_name): ?>
                                                            <div class="form-check mb-2">
                                                                <input class="form-check-input document-checkbox" type="checkbox" 
                                                                       name="verified_documents[]" 
                                                                       value="<?php echo $doc_key; ?>"
                                                                       id="doc_<?php echo $doc_key; ?>">
                                                                <label class="form-check-label" for="doc_<?php echo $doc_key; ?>">
                                                                    <i class="fas fa-file-alt me-1"></i>
                                                                    <?php echo htmlspecialchars($doc_name); ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- NDA Specific Documents -->
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-header bg-light">
                                                    <h6 class="mb-0"><i class="fas fa-university me-2"></i>NDA Specific Documents</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="document-list">
                                                        <?php
                                                        $nda_docs = [
                                                            'nda_admission_card' => 'NDA Admission Card',
                                                            'attestation_letter' => 'Attestation Letter',
                                                            'parent_consent_form' => 'Parent Consent Form',
                                                            'acknowledgement_form' => 'Acknowledgement Form'
                                                        ];
                                                        foreach ($nda_docs as $doc_key => $doc_name): ?>
                                                            <div class="form-check mb-2">
                                                                <input class="form-check-input document-checkbox" type="checkbox" 
                                                                       name="verified_documents[]" 
                                                                       value="<?php echo $doc_key; ?>"
                                                                       id="doc_<?php echo $doc_key; ?>">
                                                                <label class="form-check-label" for="doc_<?php echo $doc_key; ?>">
                                                                    <i class="fas fa-file-alt me-1"></i>
                                                                    <?php echo htmlspecialchars($doc_name); ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 2: Verification Status -->
                                <div class="verification-section">
                                    <h6 class="mb-3">
                                        <i class="fas fa-clipboard-list me-2"></i>Step 2: Verification Status
                                    </h6>
                                    
                                    <!-- Verification Flags Section -->
                                    <div class="flags-section mb-3">
                                        <h6 class="mb-2">Verification Flags</h6>
                                        <p class="text-muted mb-3">Check any issues found during verification:</p>
                                        
                                        <!-- Missing Documents Flag -->
                                        <div class="flag-item mb-3">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input flag-checkbox" 
                                                       name="verification_flags[]" 
                                                       value="missing_documents"
                                                       id="flag_missing_documents">
                                                <label class="form-check-label" for="flag_missing_documents">
                                                    <i class="fas fa-times text-danger me-2"></i>
                                                    Missing Documents
                                                    <i class="fas fa-info-circle tooltip-icon ms-2" 
                                                       title="One or more required documents are missing"></i>
                                                </label>
                                            </div>
                                            <div class="missing-docs-list mt-2 ms-4" style="display: none;">
                                                <div class="alert alert-warning mb-0">
                                                    <small>
                                                        <strong>Missing Documents:</strong>
                                                        <ul class="mb-0 mt-1" id="missingDocsList"></ul>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Other Verification Flags -->
                                        <?php foreach ($required_documents['validation_flags'] as $flag_key => $flag_name): ?>
                                            <div class="flag-item">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input flag-checkbox" 
                                                           name="verification_flags[]" 
                                                           value="<?php echo $flag_key; ?>"
                                                           id="flag_<?php echo $flag_key; ?>">
                                                    <label class="form-check-label" for="flag_<?php echo $flag_key; ?>">
                                                        <i class="fas fa-times text-danger me-2"></i>
                                                        <?php echo htmlspecialchars($flag_name); ?>
                                                        <?php if ($flag_key === 'name_mismatch'): ?>
                                                            <i class="fas fa-info-circle tooltip-icon ms-2" 
                                                               title="Candidate's names across all documents differ significantly"></i>
                                                        <?php elseif ($flag_key === 'date_of_birth_mismatch'): ?>
                                                            <i class="fas fa-info-circle tooltip-icon ms-2" 
                                                               title="DOB on documents doesn't match the form"></i>
                                                        <?php elseif ($flag_key === 'underage'): ?>
                                                            <i class="fas fa-info-circle tooltip-icon ms-2" 
                                                               title="Candidate is below the minimum age requirement"></i>
                                                        <?php elseif ($flag_key === 'overage'): ?>
                                                            <i class="fas fa-info-circle tooltip-icon ms-2" 
                                                               title="Candidate is above the maximum age requirement"></i>
                                                        <?php elseif ($flag_key === 'forgery_or_alteration'): ?>
                                                            <i class="fas fa-info-circle tooltip-icon ms-2" 
                                                               title="Suspected tampering or forged documents"></i>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Disqualification Reason Section -->
                                    <div class="disqualification-section" id="disqualificationSection" style="display: none;">
                                        <h6 class="mb-3">
                                            <i class="fas fa-ban me-2"></i>Disqualification Details
                                        </h6>
                                        <div class="mb-3">
                                            <label for="disqualification_reason" class="form-label">
                                                <strong>Reason for Disqualification:</strong>
                                            </label>
                                            <textarea class="form-control" id="disqualification_reason" 
                                                      name="disqualification_reason" rows="3" 
                                                      placeholder="Please provide detailed reason for disqualification..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 3: Final Confirmation -->
                                <div class="card mb-4 border-primary">
                                    <div class="card-body">
                                        <h6 class="card-title text-primary mb-3">
                                            <i class="fas fa-check-circle me-2"></i>Final Confirmation
                                        </h6>
                                        
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="confirm_no_flags" name="confirm_no_flags">
                                            <label class="form-check-label" for="confirm_no_flags">
                                                I confirm that I have thoroughly checked all documents and this candidate is free of any verification flags
                                            </label>
                                        </div>
                                        
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="confirm_all_docs" name="confirm_all_docs">
                                            <label class="form-check-label" for="confirm_all_docs">
                                                I confirm that all required documents have been verified and are authentic
                                            </label>
                                        </div>
                                        
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>
                                            By checking these boxes, you are confirming that the candidate has passed the documentation verification stage.
                                        </div>
                                    </div>
                                </div>

                                <!-- Verification Notes -->
                                <div class="mb-4">
                                    <label for="verification_notes" class="form-label">
                                        <i class="fas fa-sticky-note me-2"></i>Additional Notes
                                    </label>
                                    <textarea class="form-control" id="verification_notes" name="verification_notes" rows="3" 
                                              placeholder="Add any additional notes or observations..."></textarea>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="verify_documents" class="btn btn-primary btn-lg">
                                        <i class="fas fa-check-circle me-2"></i>Submit Verification
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-user-circle fa-4x text-muted mb-3"></i>
                            <h5>Select a Candidate</h5>
                            <p class="text-muted">Choose a candidate from the list to begin document verification.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Handle document checkboxes
            $('.document-checkbox').change(function() {
                updateMissingDocuments();
                updateQualificationStatus();
            });

            // Check/Uncheck all documents
            $('#checkAllDocs').click(function() {
                $('.document-checkbox').prop('checked', true);
                updateMissingDocuments();
                updateQualificationStatus();
            });

            $('#uncheckAllDocs').click(function() {
                $('.document-checkbox').prop('checked', false);
                updateMissingDocuments();
                updateQualificationStatus();
            });

            // Handle flag checkboxes
            $('.flag-checkbox').change(function() {
                var anyFlagChecked = $('.flag-checkbox:checked').length > 0;
                $('#disqualificationSection').toggle(anyFlagChecked);
                
                if (anyFlagChecked) {
                    $('#confirm_no_flags').prop('checked', false).prop('disabled', true);
                } else {
                    $('#confirm_no_flags').prop('disabled', false);
                }
                
                updateQualificationStatus();
            });

            // Handle missing documents flag
            $('#flag_missing_documents').change(function() {
                $('.missing-docs-list').toggle($(this).is(':checked'));
            });

            // Function to update missing documents list
            function updateMissingDocuments() {
                var missingDocs = [];
                $('.document-checkbox').each(function() {
                    if (!$(this).is(':checked')) {
                        var docName = $(this).next('label').text().trim();
                        missingDocs.push(docName);
                    }
                });

                if (missingDocs.length > 0) {
                    $('#missingDocsList').empty();
                    missingDocs.forEach(function(doc) {
                        $('#missingDocsList').append('<li>' + doc + '</li>');
                    });
                    $('#flag_missing_documents').prop('checked', true);
                    $('.missing-docs-list').show();
                } else {
                    $('#flag_missing_documents').prop('checked', false);
                    $('.missing-docs-list').hide();
                }
            }

            // Function to update qualification status
            function updateQualificationStatus() {
                var totalDocs = $('.document-checkbox').length;
                var verifiedDocs = $('.document-checkbox:checked').length;
                var hasFlags = $('.flag-checkbox:checked').length > 0;
                var noFlagsConfirmed = $('#confirm_no_flags').is(':checked');
                var allDocsConfirmed = $('#confirm_all_docs').is(':checked');

                if (hasFlags) {
                    $('#statusText').html('<span class="text-danger"><i class="fas fa-times-circle me-2"></i>Candidate will be DISQUALIFIED due to verification flags</span>');
                } else if (verifiedDocs === totalDocs && noFlagsConfirmed && allDocsConfirmed) {
                    $('#statusText').html('<span class="text-success"><i class="fas fa-check-circle me-2"></i>Candidate will be QUALIFIED - All documents verified and no flags</span>');
                } else {
                    $('#statusText').html('<span class="text-warning"><i class="fas fa-exclamation-circle me-2"></i>Verification incomplete - Please check all documents and confirm status</span>');
                }
            }

            // Form submission validation
            $('#verificationForm').submit(function(e) {
                var anyFlagChecked = $('.flag-checkbox:checked').length > 0;
                var disqualificationReason = $('#disqualification_reason').val().trim();
                var noFlagsConfirmed = $('#confirm_no_flags').is(':checked');
                var allDocsConfirmed = $('#confirm_all_docs').is(':checked');

                if (anyFlagChecked && noFlagsConfirmed) {
                    e.preventDefault();
                    alert('Error: You cannot confirm "no flags" while having flags checked. Please either uncheck the flags or uncheck the confirmation.');
                    return false;
                }

                if (anyFlagChecked && !disqualificationReason) {
                    e.preventDefault();
                    alert('Please provide a reason for disqualification when flags are checked.');
                    return false;
                }

                if (!anyFlagChecked && (!noFlagsConfirmed || !allDocsConfirmed)) {
                    e.preventDefault();
                    alert('Please confirm that all documents are verified and there are no flags.');
                    return false;
                }

                return confirm('Are you sure you want to submit the verification results? This action cannot be undone.');
            });

            // Initialize status on page load
            updateMissingDocuments();
            updateQualificationStatus();
        });
    </script>
</body>
</html>