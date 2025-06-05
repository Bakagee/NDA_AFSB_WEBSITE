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
 * Get sand modelling assessment data for a candidate
 */
function getSandModellingData($candidate_id) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM sand_modelling_assessments WHERE candidate_id = ?");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assessment = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $assessment;
}

/**
 * Get candidates that are in the sand modelling stage for a specific state
 */
function getCandidatesInSandModellingStage($state) {
    $conn = connectDB();
    
    $sql = "SELECT DISTINCT c.candidate_id, c.nda_application_number, c.first_name, c.surname,  
                   c.status, c.profile_picture, cs.status as sand_modelling_status,
                   pa.assessment_summary as physical_score
            FROM candidates c
            JOIN states s ON c.state_id = s.id
            LEFT JOIN candidate_stages cs ON c.candidate_id = cs.candidate_id
            LEFT JOIN stages st ON cs.stage_id = st.id
            JOIN physical_assessments pa ON c.candidate_id = pa.candidate_id
            WHERE (s.state_name = ? OR s.state_code = ?)
            AND (
                (st.stage_name = 'sand_modelling' AND cs.status = 'pending')
                OR NOT EXISTS (
                    SELECT 1 FROM candidate_stages cs2
                    JOIN stages st2 ON cs2.stage_id = st2.id
                    WHERE cs2.candidate_id = c.candidate_id
                    AND st2.stage_name = 'sand_modelling'
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
$sand_modelling_data = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_sand_modelling'])) {
        handleSandModellingAssessment();
    }
}

// Get candidates for the officer's assigned state who are in the sand modelling stage
$candidates = getCandidatesInSandModellingStage($state);

// Get candidate details if a candidate is selected
if (isset($_GET['candidate_id'])) {
    $candidate_id = $_GET['candidate_id'];
    $selected_candidate = getCandidateDetails($candidate_id);
    $sand_modelling_data = getSandModellingData($candidate_id);
}

/**
 * Handle sand modelling assessment submission
 */
function handleSandModellingAssessment() {
    global $error_message, $success_message, $officer_id;
    
    $candidate_id = $_POST['candidate_id'];
    
    // Collect assessment data
    $assessment_data = [
        'spatial_awareness' => [
            'points' => $_POST['spatial_points'],
            'notes' => $_POST['spatial_notes']
        ],
        'problem_solving' => [
            'points' => $_POST['problem_points'],
            'notes' => $_POST['problem_notes']
        ],
        'creativity' => [
            'points' => $_POST['creativity_points'],
            'notes' => $_POST['creativity_notes']
        ],
        'teamwork' => [
            'points' => $_POST['teamwork_points'],
            'notes' => $_POST['teamwork_notes']
        ]
    ];
    
    // Calculate total points
    $total_points = array_sum([
        $assessment_data['spatial_awareness']['points'],
        $assessment_data['problem_solving']['points'],
        $assessment_data['creativity']['points'],
        $assessment_data['teamwork']['points']
    ]);
    
    $assessment_summary = [
        'total_points' => $total_points,
        'max_points' => 20, // 5 points for each category
        'percentage' => ($total_points / 20) * 100
    ];
    
    $notes = $_POST['notes'] ?? '';
    
    $result = processSandModellingAssessment(
        $candidate_id,
        $assessment_data,
        $assessment_summary,
        $notes,
        $officer_id
    );
    
    if ($result) {
        $success_message = "Sand modelling assessment completed successfully. Total points: " . $total_points . "/20";
    } else {
        $error_message = "Failed to save sand modelling assessment results.";
    }
}

/**
 * Process sand modelling assessment and save to database
 */
function processSandModellingAssessment($candidate_id, $assessment_data, $assessment_summary, $notes, $officer_id) {
    $conn = connectDB();
    
    // Check if assessment record exists
    $check_stmt = $conn->prepare("SELECT id FROM sand_modelling_assessments WHERE candidate_id = ?");
    $check_stmt->bind_param("i", $candidate_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    // Prepare assessment data JSON
    $assessment_data_json = json_encode($assessment_data);
    $assessment_summary_json = json_encode($assessment_summary);
    
    if ($exists) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE sand_modelling_assessments SET 
                               test_results = ?, 
                               assessment_summary = ?,
                               notes = ?, 
                               assessed_by = ?, 
                               assessed_at = NOW()
                               WHERE candidate_id = ?");
        $stmt->bind_param("sssii", 
            $assessment_data_json, 
            $assessment_summary_json,
            $notes, 
            $officer_id, 
            $candidate_id
        );
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO sand_modelling_assessments 
                               (candidate_id, test_results, assessment_summary, notes, assessed_by, assessed_at) 
                               VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isssi", 
            $candidate_id, 
            $assessment_data_json, 
            $assessment_summary_json,
            $notes, 
            $officer_id
        );
    }
    
    $result = $stmt->execute();
    $stmt->close();
    
    // If sand modelling assessment is successful, move to interview stage
    if ($result) {
        // Get interview stage ID
        $stmt = $conn->prepare("SELECT id FROM stages WHERE stage_name = 'interview'");
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
            // Add candidate to interview stage with pending status
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
    <title>Sand Modelling Assessment - NDA Portal</title>
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
        .assessment-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #e9ecef;
        }
        .assessment-item {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: white;
            border: 1px solid #dee2e6;
        }
        .assessment-item:hover {
            background: #f8f9fa;
        }
        .points-display {
            font-size: 1.2em;
            font-weight: 600;
            color: #198754;
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
                        <a class="nav-link active" href="sand_modelling.php">Sand Modelling</a>
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
                            <i class="fas fa-users me-2"></i>Candidates in Sand Modelling Stage
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($candidates)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No candidates found in sand modelling stage.</p>
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

                                    // Get sand modelling assessment status for this candidate
                                    $candidate_sand = getSandModellingData($candidate['candidate_id']);
                                    $total_points = $candidate_sand ? json_decode($candidate_sand['assessment_summary'], true)['total_points'] : null;
                                    
                                    // Get physical assessment score
                                    $physical_score = $candidate['physical_score'] ? json_decode($candidate['physical_score'], true)['total_points'] : null;
                                ?>
                                    <a href="?candidate_id=<?php echo $candidate['candidate_id']; ?>" 
                                       class="list-group-item list-group-item-action d-flex align-items-center <?php echo ($selected_candidate && $selected_candidate['candidate_id'] == $candidate['candidate_id']) ? 'active' : ''; ?>">
                                        <img src="<?php echo htmlspecialchars($list_profile_path); ?>" 
                                             alt="Profile" class="rounded-circle me-3" width="40" height="40"
                                             onerror="this.src='../assets/images/default-profile.png'">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($candidate['surname'] . ', ' . $candidate['first_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($candidate['nda_application_number']); ?></small>
                                            <?php if ($physical_score !== null): ?>
                                                <br>
                                                <small class="text-muted">Physical Score: <?php echo $physical_score; ?>/40</small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($total_points !== null): ?>
                                            <span class="badge bg-success ms-2"><?php echo $total_points; ?>/20</span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sand Modelling Assessment Form -->
            <div class="col-md-8">
                <?php if ($selected_candidate): ?>
                    <?php if ($sand_modelling_data): ?>
                        <div class="alert alert-info mb-4">
                            <h5 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Sand Modelling Assessment Already Completed</h5>
                            <p class="mb-0">
                                This candidate's sand modelling assessment was completed on 
                                <?php echo date('F j, Y, g:i a', strtotime($sand_modelling_data['assessed_at'])); ?>.
                                <?php 
                                $assessment_summary = json_decode($sand_modelling_data['assessment_summary'], true);
                                $total_points = $assessment_summary['total_points'];
                                ?>
                                <span class="text-success">Sand Modelling Score: <?php echo $total_points; ?>/20</span>
                                <br>
                                <small class="text-muted">Note: This is 20% of the total possible score (100 points). The candidate will proceed to board interview (40 points).</small>
                            </p>
                            <?php if ($sand_modelling_data['notes']): ?>
                                <hr>
                                <p class="mb-0"><strong>Notes:</strong> <?php echo htmlspecialchars($sand_modelling_data['notes']); ?></p>
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
                                    
                                    <?php 
                                    // Get physical assessment score
                                    $physical_score = 0;
                                    $physical_data = getPhysicalAssessmentData($selected_candidate['candidate_id']);
                                    if ($physical_data) {
                                        $physical_summary = json_decode($physical_data['assessment_summary'], true);
                                        $physical_score = $physical_summary['total_points'];
                                    }

                                    // Get sand modelling score
                                    $sand_score = 0;
                                    if ($sand_modelling_data) {
                                        $sand_summary = json_decode($sand_modelling_data['assessment_summary'], true);
                                        $sand_score = $sand_summary['total_points'];
                                    }

                                    // Calculate total score
                                    $total_score = $physical_score + $sand_score;
                                    ?>
                                    
                                    <div class="mb-3">
                                        <div class="points-display">
                                            <strong>Total Score:</strong> <?php echo $total_score; ?>/60
                                        </div>
                                    </div>
                                    
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

                    <!-- Sand Modelling Assessment Form -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-cubes me-2"></i>Sand Modelling Assessment Form
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Scoring System:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Physical Assessment: 40 points (completed)</li>
                                    <li>Sand Modelling: 20 points (current stage)</li>
                                    <li>Board Interview: 40 points (next stage)</li>
                                    <li>Total Possible Score: 100 points</li>
                                </ul>
                            </div>
                            <form action="" method="POST" id="sandModellingForm">
                                <input type="hidden" name="candidate_id" value="<?php echo $selected_candidate['candidate_id']; ?>">
                                
                                <!-- Spatial Awareness -->
                                <div class="assessment-section">
                                    <h6 class="mb-3">Spatial Awareness (5 points)</h6>
                                    
                                    <div class="assessment-item">
                                        <div class="mb-3">
                                            <label class="form-label">Points (0-5)</label>
                                            <input type="number" class="form-control" name="spatial_points" min="0" max="5" required>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <label class="form-label">Notes</label>
                                            <textarea class="form-control" name="spatial_notes" rows="2" 
                                                      placeholder="Add observations about the candidate's spatial awareness..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Problem Solving -->
                                <div class="assessment-section">
                                    <h6 class="mb-3">Problem Solving (5 points)</h6>
                                    
                                    <div class="assessment-item">
                                        <div class="mb-3">
                                            <label class="form-label">Points (0-5)</label>
                                            <input type="number" class="form-control" name="problem_points" min="0" max="5" required>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <label class="form-label">Notes</label>
                                            <textarea class="form-control" name="problem_notes" rows="2" 
                                                      placeholder="Add observations about the candidate's problem-solving abilities..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Creativity -->
                                <div class="assessment-section">
                                    <h6 class="mb-3">Creativity (5 points)</h6>
                                    
                                    <div class="assessment-item">
                                        <div class="mb-3">
                                            <label class="form-label">Points (0-5)</label>
                                            <input type="number" class="form-control" name="creativity_points" min="0" max="5" required>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <label class="form-label">Notes</label>
                                            <textarea class="form-control" name="creativity_notes" rows="2" 
                                                      placeholder="Add observations about the candidate's creativity..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Teamwork -->
                                <div class="assessment-section">
                                    <h6 class="mb-3">Teamwork (5 points)</h6>
                                    
                                    <div class="assessment-item">
                                        <div class="mb-3">
                                            <label class="form-label">Points (0-5)</label>
                                            <input type="number" class="form-control" name="teamwork_points" min="0" max="5" required>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <label class="form-label">Notes</label>
                                            <textarea class="form-control" name="teamwork_notes" rows="2" 
                                                      placeholder="Add observations about the candidate's teamwork..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div class="mb-3">
                                    <label for="notes" class="form-label">
                                        <i class="fas fa-sticky-note me-2"></i>Additional Notes (Optional)
                                    </label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                                              placeholder="Any additional observations or comments..."><?php echo isset($sand_modelling_data['notes']) ? htmlspecialchars($sand_modelling_data['notes']) : ''; ?></textarea>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="submit_sand_modelling" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save me-2"></i>Save Sand Modelling Assessment Results
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
                            <p class="text-muted">Choose a candidate from the list to begin sand modelling assessment.</p>
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

            // Form submission validation
            $('#sandModellingForm').submit(function(e) {
                var spatialPoints = $('input[name="spatial_points"]').val();
                var problemPoints = $('input[name="problem_points"]').val();
                var creativityPoints = $('input[name="creativity_points"]').val();
                var teamworkPoints = $('input[name="teamwork_points"]').val();

                if (!spatialPoints || !problemPoints || !creativityPoints || !teamworkPoints) {
                    e.preventDefault();
                    alert('Please fill in all required fields for the assessment.');
                    return false;
                }

                return confirm('Are you sure you want to save the sand modelling assessment results? This action cannot be undone.');
            });
        });
    </script>
</body>
</html> 