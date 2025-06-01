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

/**
 * Get physical assessment data for a candidate
 */
function getPhysicalAssessmentData($candidate_id) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM physical_assessments WHERE candidate_id = ?");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assessment = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $assessment;
}

/**
 * Get candidates that are in the physical stage for a specific state
 */
function getCandidatesInPhysicalStage($state) {
    $conn = connectDB();
    
    $sql = "SELECT DISTINCT c.candidate_id, c.nda_application_number, c.first_name, c.surname,  
                   c.status, c.profile_picture, cs.status as physical_status,
                   ms.overall_fitness as medical_status
            FROM candidates c
            JOIN states s ON c.state_id = s.id
            LEFT JOIN candidate_stages cs ON c.candidate_id = cs.candidate_id
            LEFT JOIN stages st ON cs.stage_id = st.id
            JOIN medical_screening ms ON c.candidate_id = ms.candidate_id
            WHERE (s.state_name = ? OR s.state_code = ?)
            AND JSON_EXTRACT(ms.overall_fitness, '$.status') = 'fit'
            AND (
                (st.stage_name = 'physical' AND cs.status = 'pending')
                OR NOT EXISTS (
                    SELECT 1 FROM candidate_stages cs2
                    JOIN stages st2 ON cs2.stage_id = st2.id
                    WHERE cs2.candidate_id = c.candidate_id
                    AND st2.stage_name = 'physical'
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

// Initialize variables
$success_message = '';
$error_message = '';
$selected_candidate = null;
$physical_data = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_physical'])) {
        handlePhysicalAssessment();
    }
}

// Get candidates for the officer's assigned state who are in the physical stage
$candidates = getCandidatesInPhysicalStage($state);

// Get candidate details if a candidate is selected
if (isset($_GET['candidate_id'])) {
    $candidate_id = $_GET['candidate_id'];
    $selected_candidate = getCandidateDetails($candidate_id);
    $physical_data = getPhysicalAssessmentData($candidate_id);
}

/**
 * Handle physical assessment submission
 */
function handlePhysicalAssessment() {
    global $error_message, $success_message, $officer_id;
    
    $candidate_id = $_POST['candidate_id'];
    
    // Collect all test results
    $test_results = [
        'race_3_2km' => [
            'cage' => $_POST['race_cage'],
            'points' => $_POST['race_points']
        ],
        'individual_obstacle' => [
            'grade' => $_POST['individual_grade'],
            'points' => $_POST['individual_points'],
            'notes' => $_POST['individual_notes']
        ],
        'group_obstacle' => [
            'grade' => $_POST['group_grade'],
            'points' => $_POST['group_points'],
            'notes' => $_POST['group_notes']
        ],
        'rope_climbing' => [
            'points' => $_POST['rope_points'],
            'notes' => $_POST['rope_notes']
        ]
    ];
    
    // Calculate total points
    $total_points = array_sum([
        $test_results['race_3_2km']['points'],
        $test_results['individual_obstacle']['points'],
        $test_results['group_obstacle']['points'],
        $test_results['rope_climbing']['points']
    ]);
    
    $assessment_summary = [
        'total_points' => $total_points,
        'max_points' => 40, // 10 points for each test
        'percentage' => ($total_points / 40) * 100
    ];
    
    $notes = $_POST['notes'] ?? '';
    
    $result = processPhysicalAssessment(
        $candidate_id,
        $test_results,
        $assessment_summary,
        $notes,
        $officer_id
    );
    
    if ($result) {
        $success_message = "Physical assessment completed successfully. Total points: " . $total_points . "/40";
    } else {
        $error_message = "Failed to save physical assessment results.";
    }
}

/**
 * Process physical assessment and save to database
 */
function processPhysicalAssessment($candidate_id, $test_results, $assessment_summary, $notes, $officer_id) {
    $conn = connectDB();
    
    // Check if assessment record exists
    $check_stmt = $conn->prepare("SELECT id FROM physical_assessments WHERE candidate_id = ?");
    $check_stmt->bind_param("i", $candidate_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    // Prepare test results JSON
    $test_results_json = json_encode($test_results);
    $assessment_summary_json = json_encode($assessment_summary);
    
    if ($exists) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE physical_assessments SET 
                               test_results = ?, 
                               assessment_summary = ?,
                               notes = ?, 
                               assessed_by = ?, 
                               assessed_at = NOW()
                               WHERE candidate_id = ?");
        $stmt->bind_param("sssii", 
            $test_results_json, 
            $assessment_summary_json,
            $notes, 
            $officer_id, 
            $candidate_id
        );
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO physical_assessments 
                               (candidate_id, test_results, assessment_summary, notes, assessed_by, assessed_at) 
                               VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isssi", 
            $candidate_id, 
            $test_results_json, 
            $assessment_summary_json,
            $notes, 
            $officer_id
        );
    }
    
    $result = $stmt->execute();
    $stmt->close();
    
    // If physical assessment is successful, move to sand modelling stage
    if ($result) {
        // Get sand modelling stage ID
        $stmt = $conn->prepare("SELECT id FROM stages WHERE stage_name = 'sand_modelling'");
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
            // Add candidate to sand modelling stage with pending status
            $status = 'pending';
            $stmt = $conn->prepare("INSERT INTO candidate_stages 
                                  (candidate_id, stage_id, status, created_at, created_by) 
                                  VALUES (?, ?, ?, NOW(), ?)");
            $stmt->bind_param("iisi", $candidate_id, $next_stage_id, $status, $officer_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    $conn->close();
    
    return $result;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Physical Assessment - NDA Portal</title>
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
        .points-display {
            font-size: 1.2em;
            font-weight: 600;
            color: #198754;
        }
        .cage-option {
            padding: 10px;
            margin: 5px 0;
            border-radius: 8px;
            background: white;
            border: 1px solid #dee2e6;
            cursor: pointer;
            transition: all 0.2s;
        }
        .cage-option:hover {
            background: #f8f9fa;
        }
        .cage-option.selected {
            background: #198754;
            color: white;
            border-color: #198754;
        }
        .grade-option {
            padding: 8px 15px;
            margin: 5px;
            border-radius: 20px;
            background: white;
            border: 1px solid #dee2e6;
            cursor: pointer;
            transition: all 0.2s;
        }
        .grade-option:hover {
            background: #f8f9fa;
        }
        .grade-option.selected {
            background: #198754;
            color: white;
            border-color: #198754;
        }
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
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
                        <a class="nav-link" href="documentation.php">Documentation</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="medical.php">Medical</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="physical.php">Physical</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sand_modelling.php">Sand Modelling</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="board_interview.php">Board Interview</a>
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
                            <i class="fas fa-users me-2"></i>Candidates in Physical Stage
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($candidates)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No candidates found in physical stage.</p>
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

                                    // Get physical assessment status for this candidate
                                    $candidate_physical = getPhysicalAssessmentData($candidate['candidate_id']);
                                    $total_points = $candidate_physical ? json_decode($candidate_physical['assessment_summary'], true)['total_points'] : null;
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
                                        <?php if ($total_points !== null): ?>
                                            <span class="badge bg-success ms-2"><?php echo $total_points; ?>/40</span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Physical Assessment Form -->
            <div class="col-md-8">
                <?php if ($selected_candidate): ?>
                    <?php if ($physical_data): ?>
                        <div class="alert alert-info mb-4">
                            <h5 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Physical Assessment Already Completed</h5>
                            <p class="mb-0">
                                This candidate's physical assessment was completed on 
                                <?php echo date('F j, Y, g:i a', strtotime($physical_data['assessed_at'])); ?>.
                                <?php 
                                $assessment_summary = json_decode($physical_data['assessment_summary'], true);
                                $total_points = $assessment_summary['total_points'];
                                ?>
                                <span class="text-success">Physical Assessment Score: <?php echo $total_points; ?>/40</span>
                                <br>
                                <small class="text-muted">Note: This is 40% of the total possible score (100 points). The candidate will proceed to sand modelling (20 points) and then board interview (40 points).</small>
                            </p>
                            <?php if ($physical_data['notes']): ?>
                                <hr>
                                <p class="mb-0"><strong>Notes:</strong> <?php echo htmlspecialchars($physical_data['notes']); ?></p>
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
                                    
                                    <?php if ($physical_data): ?>
                                        <div class="mb-3">
                                            <?php 
                                            $assessment_summary = json_decode($physical_data['assessment_summary'], true);
                                            $total_points = $assessment_summary['total_points'];
                                            $percentage = $assessment_summary['percentage'];
                                            ?>
                                            <div class="points-display">
                                                Total Points: <?php echo $total_points; ?>/40 (<?php echo number_format($percentage, 1); ?>%)
                                            </div>
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

                    <!-- Physical Assessment Form -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-running me-2"></i>Physical Assessment Form
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Scoring System:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Physical Assessment: 40 points (current stage)</li>
                                    <li>Sand Modelling: 20 points (next stage)</li>
                                    <li>Board Interview: 40 points (final stage)</li>
                                    <li>Total Possible Score: 100 points</li>
                                </ul>
                            </div>
                            <form action="" method="POST" id="physicalForm">
                                <input type="hidden" name="candidate_id" value="<?php echo $selected_candidate['candidate_id']; ?>">
                                
                                <!-- 3.2km Race -->
                                <div class="test-section">
                                    <h6 class="mb-3">3.2km Race</h6>
                                    
                                    <div class="test-item">
                                        <label class="form-label">Cage Reached</label>
                                        <div class="cage-options">
                                            <div class="cage-option" data-cage="1" data-points="10">
                                                <i class="fas fa-trophy me-2"></i>First Cage (10 points)
                                            </div>
                                            <div class="cage-option" data-cage="2" data-points="7">
                                                <i class="fas fa-medal me-2"></i>Second Cage (7 points)
                                            </div>
                                            <div class="cage-option" data-cage="3" data-points="5">
                                                <i class="fas fa-award me-2"></i>Third Cage (5 points)
                                            </div>
                                            <div class="cage-option" data-cage="4" data-points="0">
                                                <i class="fas fa-times me-2"></i>Fourth Cage (0 points)
                                            </div>
                                        </div>
                                        <input type="hidden" name="race_cage" id="race_cage">
                                        <input type="hidden" name="race_points" id="race_points">
                                    </div>
                                </div>

                                <!-- Individual Obstacle Course -->
                                <div class="test-section">
                                    <h6 class="mb-3">Individual Obstacle Course</h6>
                                    
                                    <div class="test-item">
                                        <label class="form-label">Instructor's Grade</label>
                                        <div class="grade-options">
                                            <div class="grade-option" data-grade="A" data-points="10">A (10 points)</div>
                                            <div class="grade-option" data-grade="B" data-points="8">B (8 points)</div>
                                            <div class="grade-option" data-grade="C" data-points="6">C (6 points)</div>
                                            <div class="grade-option" data-grade="D" data-points="4">D (4 points)</div>
                                            <div class="grade-option" data-grade="E" data-points="2">E (2 points)</div>
                                        </div>
                                        <input type="hidden" name="individual_grade" id="individual_grade">
                                        <input type="hidden" name="individual_points" id="individual_points">
                                        
                                        <div class="mt-3">
                                            <label class="form-label">Notes</label>
                                            <textarea class="form-control" name="individual_notes" rows="2" 
                                                      placeholder="Add any observations about the candidate's performance..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Group Obstacle Course -->
                                <div class="test-section">
                                    <h6 class="mb-3">Group Obstacle Course</h6>
                                    
                                    <div class="test-item">
                                        <label class="form-label">Instructor's Grade</label>
                                        <div class="grade-options">
                                            <div class="grade-option" data-grade="A" data-points="10">A (10 points)</div>
                                            <div class="grade-option" data-grade="B" data-points="8">B (8 points)</div>
                                            <div class="grade-option" data-grade="C" data-points="6">C (6 points)</div>
                                            <div class="grade-option" data-grade="D" data-points="4">D (4 points)</div>
                                            <div class="grade-option" data-grade="E" data-points="2">E (2 points)</div>
                                        </div>
                                        <input type="hidden" name="group_grade" id="group_grade">
                                        <input type="hidden" name="group_points" id="group_points">
                                        
                                        <div class="mt-3">
                                            <label class="form-label">Notes</label>
                                            <textarea class="form-control" name="group_notes" rows="2" 
                                                      placeholder="Add any observations about the candidate's performance..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Rope Climbing -->
                                <div class="test-section">
                                    <h6 class="mb-3">Rope Climbing</h6>
                                    
                                    <div class="test-item">
                                        <div class="mt-3">
                                            <label class="form-label">Points (0-10)</label>
                                            <input type="number" class="form-control" name="rope_points" min="0" max="10" required>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <label class="form-label">Notes</label>
                                            <textarea class="form-control" name="rope_notes" rows="2" 
                                                      placeholder="Add any observations about the candidate's performance..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div class="mb-3">
                                    <label for="notes" class="form-label">
                                        <i class="fas fa-sticky-note me-2"></i>Additional Notes (Optional)
                                    </label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                                              placeholder="Any additional observations or comments..."><?php echo isset($physical_data['notes']) ? htmlspecialchars($physical_data['notes']) : ''; ?></textarea>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="submit_physical" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save me-2"></i>Save Physical Assessment Results
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
                            <p class="text-muted">Choose a candidate from the list to begin physical assessment.</p>
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

            // Handle cage selection
            $('.cage-option').click(function() {
                $('.cage-option').removeClass('selected');
                $(this).addClass('selected');
                
                var cage = $(this).data('cage');
                var points = $(this).data('points');
                
                $('#race_cage').val(cage);
                $('#race_points').val(points);
            });

            // Handle grade selection
            $('.grade-option').click(function() {
                var parent = $(this).closest('.grade-options');
                parent.find('.grade-option').removeClass('selected');
                $(this).addClass('selected');
                
                var grade = $(this).data('grade');
                var points = $(this).data('points');
                
                if (parent.closest('.test-item').find('#individual_grade').length) {
                    $('#individual_grade').val(grade);
                    $('#individual_points').val(points);
                } else {
                    $('#group_grade').val(grade);
                    $('#group_points').val(points);
                }
            });

            // Form submission validation
            $('#physicalForm').submit(function(e) {
                var raceCage = $('#race_cage').val();
                var individualGrade = $('#individual_grade').val();
                var groupGrade = $('#group_grade').val();
                var ropePoints = $('input[name="rope_points"]').val();

                if (!raceCage) {
                    e.preventDefault();
                    alert('Please select the cage reached in the 3.2km race.');
                    return false;
                }

                if (!individualGrade) {
                    e.preventDefault();
                    alert('Please select a grade for the individual obstacle course.');
                    return false;
                }

                if (!groupGrade) {
                    e.preventDefault();
                    alert('Please select a grade for the group obstacle course.');
                    return false;
                }

                if (!ropePoints) {
                    e.preventDefault();
                    alert('Please fill in all required fields for rope climbing.');
                    return false;
                }

                return confirm('Are you sure you want to save the physical assessment results? This action cannot be undone.');
            });
        });
    </script>
</body>
</html> 