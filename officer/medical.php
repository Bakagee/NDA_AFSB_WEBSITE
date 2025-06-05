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
$medical_data = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_medical'])) {
        handleMedicalScreening();
    }
}

// Get candidates for the officer's assigned state who are in the medical stage
$candidates = getCandidatesInMedicalStage($state);

// Get candidate details if a candidate is selected
if (isset($_GET['candidate_id'])) {
    $candidate_id = $_GET['candidate_id'];
    $selected_candidate = getCandidateDetails($candidate_id);
    $medical_data = getMedicalScreeningData($candidate_id);
}

/**
 * Handle medical screening submission
 */
function handleMedicalScreening() {
    global $error_message, $success_message, $officer_id;
    
    $candidate_id = $_POST['candidate_id'];
    
    // Collect all test results
    $test_results = [
        'height' => [
            'status' => $_POST['height_status'],
            'reason' => $_POST['height_status'] === 'failed' ? $_POST['height_reason'] : null
        ],
        'weight' => [
            'status' => $_POST['weight_status'],
            'reason' => $_POST['weight_status'] === 'failed' ? $_POST['weight_reason'] : null
        ],
        'blood_pressure' => [
            'status' => $_POST['bp_status'],
            'reason' => $_POST['bp_status'] === 'failed' ? $_POST['bp_reason'] : null
        ],
        'pulse_rate' => [
            'status' => $_POST['pulse_status'],
            'reason' => $_POST['pulse_status'] === 'failed' ? $_POST['pulse_reason'] : null
        ],
        'vision' => [
            'left_eye' => [
                'status' => $_POST['left_eye_status'],
                'reason' => $_POST['left_eye_status'] === 'failed' ? $_POST['left_eye_reason'] : null
            ],
            'right_eye' => [
                'status' => $_POST['right_eye_status'],
                'reason' => $_POST['right_eye_status'] === 'failed' ? $_POST['right_eye_reason'] : null
            ]
        ],
        'urine_test' => [
            'status' => $_POST['urine_status'],
            'reason' => $_POST['urine_status'] === 'failed' ? $_POST['urine_reason'] : null
        ],
        'blood_test' => [
            'status' => $_POST['blood_status'],
            'reason' => $_POST['blood_status'] === 'failed' ? $_POST['blood_reason'] : null
        ],
        'ecg' => [
            'status' => $_POST['ecg_status'],
            'reason' => $_POST['ecg_status'] === 'failed' ? $_POST['ecg_reason'] : null
        ],
        'xray' => [
            'status' => $_POST['xray_status'],
            'reason' => $_POST['xray_status'] === 'failed' ? $_POST['xray_reason'] : null
        ]
    ];
    
    // Determine overall fitness
    $is_fit = true;
    $failure_reasons = [];
    
    foreach ($test_results as $test => $result) {
        if ($test === 'vision') {
            if ($result['left_eye']['status'] === 'failed' || $result['right_eye']['status'] === 'failed') {
                $is_fit = false;
                if ($result['left_eye']['status'] === 'failed') {
                    $failure_reasons[] = "Left eye: " . $result['left_eye']['reason'];
                }
                if ($result['right_eye']['status'] === 'failed') {
                    $failure_reasons[] = "Right eye: " . $result['right_eye']['reason'];
                }
            }
        } else {
            if ($result['status'] === 'failed') {
                $is_fit = false;
                $failure_reasons[] = ucfirst($test) . ": " . $result['reason'];
            }
        }
    }
    
    $overall_fitness = [
        'status' => $is_fit ? 'fit' : 'not_fit',
        'reason' => $is_fit ? null : implode("; ", $failure_reasons)
    ];
    
    $notes = $_POST['notes'] ?? '';
    
    $result = processMedicalScreening(
        $candidate_id,
        $test_results,
        $overall_fitness,
        $notes,
        $officer_id
    );
    
    if ($result) {
        $success_message = $is_fit ? 
            "Medical screening completed successfully. Candidate is medically fit." : 
            "Medical screening completed. Candidate is not medically fit.";
    } else {
        $error_message = "Failed to save medical screening results.";
    }
}

/**
 * Process medical screening and save to database
 */
function processMedicalScreening($candidate_id, $test_results, $overall_fitness, $notes, $officer_id) {
    $conn = connectDB();
    
    // Check if screening record exists
    $check_stmt = $conn->prepare("SELECT id FROM medical_screening WHERE candidate_id = ?");
    $check_stmt->bind_param("i", $candidate_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    // Prepare test results JSON
    $test_results_json = json_encode($test_results);
    $overall_fitness_json = json_encode($overall_fitness);
    
    if ($exists) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE medical_screening SET 
                               test_results = ?, 
                               overall_fitness = ?,
                               notes = ?, 
                               screened_by = ?, 
                               screened_at = NOW()
                               WHERE candidate_id = ?");
        $stmt->bind_param("sssii", 
            $test_results_json, 
            $overall_fitness_json,
            $notes, 
            $officer_id, 
            $candidate_id
        );
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO medical_screening 
                               (candidate_id, test_results, overall_fitness, notes, screened_by, screened_at) 
                               VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isssi", 
            $candidate_id, 
            $test_results_json, 
            $overall_fitness_json,
            $notes, 
            $officer_id
        );
    }
    
    $result = $stmt->execute();
    $stmt->close();
    
    // If medical screening is successful and candidate is fit, move to physical stage
    if ($result && $overall_fitness['status'] === 'fit') {
        // Get physical stage ID
        $stmt = $conn->prepare("SELECT id FROM stages WHERE stage_name = 'physical'");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $physical_stage_id = $row['id'];
        $stmt->close();
        
        // Check if candidate is already in physical stage
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM candidate_stages 
                              WHERE candidate_id = ? AND stage_id = ?");
        $stmt->bind_param("ii", $candidate_id, $physical_stage_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $exists = $row['count'] > 0;
        $stmt->close();
        
        if (!$exists) {
            // Add candidate to physical stage with pending status
            $status = 'pending';
            $stmt = $conn->prepare("INSERT INTO candidate_stages 
                                  (candidate_id, stage_id, status, created_at, created_by) 
                                  VALUES (?, ?, ?, NOW(), ?)");
            $stmt->bind_param("iisi", $candidate_id, $physical_stage_id, $status, $officer_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Update candidate's current stage
        $stmt = $conn->prepare("UPDATE candidates SET current_stage = 'physical' WHERE candidate_id = ?");
        $stmt->bind_param("i", $candidate_id);
        $stmt->execute();
        $stmt->close();
        
        // Update medical stage status to passed
        $stmt = $conn->prepare("UPDATE candidate_stages cs
                              JOIN stages s ON cs.stage_id = s.id
                              SET cs.status = 'passed'
                              WHERE cs.candidate_id = ? AND s.stage_name = 'medical'");
        $stmt->bind_param("i", $candidate_id);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->close();
    
    return $result;
}

/**
 * Get candidates that are in the medical stage for a specific state
 */
function getCandidatesInMedicalStage($state) {
    $conn = connectDB();
    
    $sql = "SELECT DISTINCT c.candidate_id, c.nda_application_number, c.first_name, c.surname,  
                   c.status, c.profile_picture, cs.status as medical_status,
                   dv.verification_status as doc_status
            FROM candidates c
            JOIN states s ON c.state_id = s.id
            JOIN candidate_stages cs ON c.candidate_id = cs.candidate_id
            JOIN stages st ON cs.stage_id = st.id
            LEFT JOIN document_verifications dv ON c.candidate_id = dv.candidate_id
            WHERE (s.state_name = ? OR s.state_code = ?)
            AND (
                (st.stage_name = 'medical' AND cs.status = 'pending')
                OR EXISTS (
                    SELECT 1 FROM medical_screening 
                    WHERE candidate_id = c.candidate_id
                )
            )
            AND (
                dv.verification_status = 'verified'
                OR EXISTS (
                    SELECT 1 FROM candidate_stages cs2
                    JOIN stages st2 ON cs2.stage_id = st2.id
                    WHERE cs2.candidate_id = c.candidate_id
                    AND st2.stage_name = 'documentation'
                    AND cs2.status = 'passed'
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
 * Get medical screening data for a candidate
 */
function getMedicalScreeningData($candidate_id) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM medical_screening WHERE candidate_id = ?");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $screening = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $screening;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Screening - NDA Portal</title>
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
        .test-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #e9ecef;
        }
        .test-item {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: white;
            border: 1px solid #dee2e6;
        }
        .test-item:hover {
            background: #f8f9fa;
        }
        .reason-input {
            display: none;
            margin-top: 10px;
        }
        .reason-input.show {
            display: block;
        }
        .fitness-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }
        .status-fit { background: #d4edda; color: #155724; }
        .status-not-fit { background: #f8d7da; color: #721c24; }
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
                        <a class="nav-link active" href="medical.php">Medical</a>
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
                            <i class="fas fa-users me-2"></i>Candidates in Medical Stage
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($candidates)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No candidates found in medical stage.</p>
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

                                    // Get medical screening status for this candidate
                                    $candidate_medical = getMedicalScreeningData($candidate['candidate_id']);
                                    $medical_status = $candidate_medical ? json_decode($candidate_medical['overall_fitness'], true)['status'] : 'pending';
                                ?>
                                    <a href="?candidate_id=<?php echo $candidate['candidate_id']; ?>" 
                                       class="list-group-item list-group-item-action d-flex align-items-center <?php echo ($selected_candidate && $selected_candidate['candidate_id'] == $candidate['candidate_id']) ? 'active' : ''; ?>">
                                        <img src="<?php echo htmlspecialchars($list_profile_path); ?>" 
                                             alt="Profile" class="rounded-circle me-3" width="40" height="40"
                                             onerror="this.src='../assets/images/default-profile.png'">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($candidate['surname'] . ', ' . $candidate['first_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($candidate['nda_application_number']); ?></small>
                                        </div>
                                        <?php if ($medical_status === 'fit'): ?>
                                            <span class="badge bg-success ms-2">Fit</span>
                                        <?php elseif ($medical_status === 'not_fit'): ?>
                                            <span class="badge bg-danger ms-2">Not Fit</span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Medical Screening Form -->
            <div class="col-md-8">
                <?php if ($selected_candidate): ?>
                    <?php if ($medical_data): ?>
                        <div class="alert alert-info mb-4">
                            <h5 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Medical Screening Already Completed</h5>
                            <p class="mb-0">
                                This candidate's medical screening was completed on 
                                <?php echo date('F j, Y, g:i a', strtotime($medical_data['screened_at'])); ?>.
                                <?php 
                                $overall_fitness = json_decode($medical_data['overall_fitness'], true);
                                if ($overall_fitness['status'] === 'fit'): 
                                ?>
                                    <span class="text-success">The candidate was found medically fit.</span>
                                <?php else: ?>
                                    <span class="text-danger">The candidate was found not medically fit.</span>
                                <?php endif; ?>
                            </p>
                            <?php if ($medical_data['notes']): ?>
                                <hr>
                                <p class="mb-0"><strong>Notes:</strong> <?php echo htmlspecialchars($medical_data['notes']); ?></p>
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
                                    
                                    <?php if ($medical_data): ?>
                                        <div class="mb-3">
                                            <?php 
                                            $overall_fitness = json_decode($medical_data['overall_fitness'], true);
                                            if ($overall_fitness['status'] === 'fit'): 
                                            ?>
                                                <span class="fitness-status status-fit">
                                                    <i class="fas fa-check-circle me-1"></i>MEDICALLY FIT
                                                </span>
                                            <?php else: ?>
                                                <span class="fitness-status status-not-fit">
                                                    <i class="fas fa-times-circle me-1"></i>NOT MEDICALLY FIT
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

                    <!-- Medical Screening Form -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-stethoscope me-2"></i>Medical Screening Form
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST" id="medicalForm">
                                <input type="hidden" name="candidate_id" value="<?php echo $selected_candidate['candidate_id']; ?>">
                                
                                <!-- Physical Measurements -->
                                <div class="test-section">
                                    <h6 class="mb-3">Physical Measurements</h6>
                                    
                                    <!-- Height -->
                                    <div class="test-item">
                                        <label class="form-label">Height</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="height_status" value="passed" required>
                                                <label class="form-check-label">Passed</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="height_status" value="failed">
                                                <label class="form-check-label">Failed</label>
                                            </div>
                                        </div>
                                        <div class="reason-input" id="height_reason">
                                            <label class="form-label">Reason for failure</label>
                                            <input type="text" class="form-control" name="height_reason">
                                        </div>
                                    </div>
                                    
                                    <!-- Weight -->
                                    <div class="test-item">
                                        <label class="form-label">Weight</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="weight_status" value="passed" required>
                                                <label class="form-check-label">Passed</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="weight_status" value="failed">
                                                <label class="form-check-label">Failed</label>
                                            </div>
                                        </div>
                                        <div class="reason-input" id="weight_reason">
                                            <label class="form-label">Reason for failure</label>
                                            <input type="text" class="form-control" name="weight_reason">
                                        </div>
                                    </div>
                                </div>

                                <!-- Vital Signs -->
                                <div class="test-section">
                                    <h6 class="mb-3">Vital Signs</h6>
                                    
                                    <!-- Blood Pressure -->
                                    <div class="test-item">
                                        <label class="form-label">Blood Pressure</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="bp_status" value="passed" required>
                                                <label class="form-check-label">Normal</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="bp_status" value="failed">
                                                <label class="form-check-label">Abnormal</label>
                                            </div>
                                        </div>
                                        <div class="reason-input" id="bp_reason">
                                            <label class="form-label">Reason for abnormality</label>
                                            <input type="text" class="form-control" name="bp_reason">
                                        </div>
                                    </div>
                                    
                                    <!-- Pulse Rate -->
                                    <div class="test-item">
                                        <label class="form-label">Pulse Rate</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="pulse_status" value="passed" required>
                                                <label class="form-check-label">Normal</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="pulse_status" value="failed">
                                                <label class="form-check-label">Abnormal</label>
                                            </div>
                                        </div>
                                        <div class="reason-input" id="pulse_reason">
                                            <label class="form-label">Reason for abnormality</label>
                                            <input type="text" class="form-control" name="pulse_reason">
                                        </div>
                                    </div>
                                </div>

                                <!-- Vision Test -->
                                <div class="test-section">
                                    <h6 class="mb-3">Vision Test</h6>
                                    
                                    <!-- Left Eye -->
                                    <div class="test-item">
                                        <label class="form-label">Left Eye</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="left_eye_status" value="passed" required>
                                                <label class="form-check-label">Passed</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="left_eye_status" value="failed">
                                                <label class="form-check-label">Failed</label>
                                            </div>
                                        </div>
                                        <div class="reason-input" id="left_eye_reason">
                                            <label class="form-label">Reason for failure</label>
                                            <input type="text" class="form-control" name="left_eye_reason">
                                        </div>
                                    </div>
                                    
                                    <!-- Right Eye -->
                                    <div class="test-item">
                                        <label class="form-label">Right Eye</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="right_eye_status" value="passed" required>
                                                <label class="form-check-label">Passed</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="right_eye_status" value="failed">
                                                <label class="form-check-label">Failed</label>
                                            </div>
                                        </div>
                                        <div class="reason-input" id="right_eye_reason">
                                            <label class="form-label">Reason for failure</label>
                                            <input type="text" class="form-control" name="right_eye_reason">
                                        </div>
                                    </div>
                                </div>

                                <!-- Laboratory Tests -->
                                <div class="test-section">
                                    <h6 class="mb-3">Laboratory Tests</h6>
                                    
                                    <!-- Urine Test -->
                                    <div class="test-item">
                                        <label class="form-label">Urine Test</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="urine_status" value="passed" required>
                                                <label class="form-check-label">Normal</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="urine_status" value="failed">
                                                <label class="form-check-label">Abnormal</label>
                                            </div>
                                        </div>
                                        <div class="reason-input" id="urine_reason">
                                            <label class="form-label">Reason for abnormality</label>
                                            <input type="text" class="form-control" name="urine_reason">
                                        </div>
                                    </div>
                                    
                                    <!-- Blood Test -->
                                    <div class="test-item">
                                        <label class="form-label">Blood Test</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="blood_status" value="passed" required>
                                                <label class="form-check-label">Normal</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="blood_status" value="failed">
                                                <label class="form-check-label">Abnormal</label>
                                            </div>
                                        </div>
                                        <div class="reason-input" id="blood_reason">
                                            <label class="form-label">Reason for abnormality</label>
                                            <input type="text" class="form-control" name="blood_reason">
                                        </div>
                                    </div>
                                </div>

                                <!-- Special Tests -->
                                <div class="test-section">
                                    <h6 class="mb-3">Special Tests</h6>
                                    
                                    <!-- ECG -->
                                    <div class="test-item">
                                        <label class="form-label">ECG</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="ecg_status" value="passed" required>
                                                <label class="form-check-label">Normal</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="ecg_status" value="failed">
                                                <label class="form-check-label">Abnormal</label>
                                            </div>
                                        </div>
                                        <div class="reason-input" id="ecg_reason">
                                            <label class="form-label">Reason for abnormality</label>
                                            <input type="text" class="form-control" name="ecg_reason">
                                        </div>
                                    </div>
                                    
                                    <!-- X-ray -->
                                    <div class="test-item">
                                        <label class="form-label">X-ray</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="xray_status" value="passed" required>
                                                <label class="form-check-label">Normal</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="xray_status" value="failed">
                                                <label class="form-check-label">Abnormal</label>
                                            </div>
                                        </div>
                                        <div class="reason-input" id="xray_reason">
                                            <label class="form-label">Reason for abnormality</label>
                                            <input type="text" class="form-control" name="xray_reason">
                                        </div>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div class="mb-3">
                                    <label for="notes" class="form-label">
                                        <i class="fas fa-sticky-note me-2"></i>Additional Notes (Optional)
                                    </label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                                              placeholder="Any additional observations or comments..."><?php echo isset($medical_data['notes']) ? htmlspecialchars($medical_data['notes']) : ''; ?></textarea>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="submit_medical" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save me-2"></i>Save Medical Screening Results
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
                            <p class="text-muted">Choose a candidate from the list to begin medical screening.</p>
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

            // Show/hide reason input based on radio selection
            $('input[type="radio"]').change(function() {
                var testName = $(this).attr('name').replace('_status', '');
                var reasonDiv = $('#' + testName + '_reason');
                
                if ($(this).val() === 'failed') {
                    reasonDiv.addClass('show');
                    reasonDiv.find('input').prop('required', true);
                } else {
                    reasonDiv.removeClass('show');
                    reasonDiv.find('input').prop('required', false);
                }
            });

            // Form submission validation
            $('#medicalForm').submit(function(e) {
                var anyFailed = false;
                var missingReasons = false;

                // Check each test
                $('.test-item').each(function() {
                    var testName = $(this).find('input[type="radio"]').first().attr('name').replace('_status', '');
                    var failedSelected = $(this).find('input[value="failed"]').is(':checked');
                    var reasonInput = $('#' + testName + '_reason input');
                    
                    if (failedSelected && !reasonInput.val().trim()) {
                        missingReasons = true;
                        reasonInput.addClass('is-invalid');
                    } else {
                        reasonInput.removeClass('is-invalid');
                    }
                    
                    if (failedSelected) {
                        anyFailed = true;
                    }
                });

                if (missingReasons) {
                    e.preventDefault();
                    alert('Please provide reasons for all failed tests.');
                    return false;
                }

                return confirm('Are you sure you want to save the medical screening results? This action cannot be undone.');
            });
        });
    </script>
</body>
</html> 