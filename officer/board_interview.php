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
 * Get board interview assessment data for a candidate
 */
function getBoardInterviewData($candidate_id) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM board_interview_assessments WHERE candidate_id = ?");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assessment = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $assessment;
}

/**
 * Get candidates that are in the board interview stage for a specific state
 */
function getCandidatesInBoardInterviewStage($state) {
    $conn = connectDB();
    
    $sql = "SELECT DISTINCT c.candidate_id, c.nda_application_number, c.first_name, c.surname,  
                   c.status, c.profile_picture, cs.status as interview_status,
                   pa.assessment_summary as physical_score,
                   sma.assessment_summary as sand_score,
                   bi.assessment_summary as interview_score
            FROM candidates c
            JOIN states s ON c.state_id = s.id
            LEFT JOIN candidate_stages cs ON c.candidate_id = cs.candidate_id
            LEFT JOIN stages st ON cs.stage_id = st.id
            JOIN physical_assessments pa ON c.candidate_id = pa.candidate_id
            JOIN sand_modelling_assessments sma ON c.candidate_id = sma.candidate_id
            LEFT JOIN board_interview_assessments bi ON c.candidate_id = bi.candidate_id
            WHERE (s.state_name = ? OR s.state_code = ?)
            AND (
                (st.stage_name = 'interview' AND (cs.status = 'pending' OR cs.status = 'passed'))
                OR NOT EXISTS (
                    SELECT 1 FROM candidate_stages cs2
                    JOIN stages st2 ON cs2.stage_id = st2.id
                    WHERE cs2.candidate_id = c.candidate_id
                    AND st2.stage_name = 'interview'
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
$board_interview_data = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_board_interview'])) {
        handleBoardInterviewAssessment();
    }
}

// Get candidates for the officer's assigned state who are in the board interview stage
$candidates = getCandidatesInBoardInterviewStage($state);

// Get candidate details if a candidate is selected
if (isset($_GET['candidate_id'])) {
    $candidate_id = $_GET['candidate_id'];
    $selected_candidate = getCandidateDetails($candidate_id);
    $board_interview_data = getBoardInterviewData($candidate_id);
}

/**
 * Handle board interview assessment submission
 */
function handleBoardInterviewAssessment() {
    global $error_message, $success_message, $officer_id;
    
    $candidate_id = $_POST['candidate_id'];
    
    // Collect assessment data
    $assessment_data = [
        'appearance' => [
            'points' => $_POST['appearance_points']
        ],
        'communication' => [
            'points' => $_POST['communication_points']
        ],
        'knowledge' => [
            'points' => $_POST['knowledge_points']
        ],
        'attitude' => [
            'points' => $_POST['attitude_points']
        ]
    ];
    
    // Calculate total points
    $total_points = array_sum([
        $assessment_data['appearance']['points'],
        $assessment_data['communication']['points'],
        $assessment_data['knowledge']['points'],
        $assessment_data['attitude']['points']
    ]);
    
    $assessment_summary = [
        'total_points' => $total_points,
        'max_points' => 40, // 10 points for each category
        'percentage' => ($total_points / 40) * 100
    ];
    
    $result = processBoardInterviewAssessment(
        $candidate_id,
        $assessment_data,
        $assessment_summary,
        $officer_id
    );
    
    if ($result) {
        $success_message = "Board interview assessment completed successfully. Total points: " . $total_points . "/40";
    } else {
        $error_message = "Failed to save board interview assessment results.";
    }
}

/**
 * Process board interview assessment and save to database
 */
function processBoardInterviewAssessment($candidate_id, $assessment_data, $assessment_summary, $officer_id) {
    $conn = connectDB();
    
    // Check if assessment record exists
    $check_stmt = $conn->prepare("SELECT id FROM board_interview_assessments WHERE candidate_id = ?");
    $check_stmt->bind_param("i", $candidate_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    // Prepare assessment data JSON
    $assessment_data_json = json_encode($assessment_data);
    $assessment_summary_json = json_encode($assessment_summary);
    
    if ($exists) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE board_interview_assessments SET 
                               test_results = ?, 
                               assessment_summary = ?,
                               assessed_by = ?, 
                               assessed_at = NOW()
                               WHERE candidate_id = ?");
        $stmt->bind_param("ssii", 
            $assessment_data_json, 
            $assessment_summary_json,
            $officer_id, 
            $candidate_id
        );
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO board_interview_assessments 
                               (candidate_id, test_results, assessment_summary, assessed_by, assessed_at) 
                               VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("issi", 
            $candidate_id, 
            $assessment_data_json, 
            $assessment_summary_json,
            $officer_id
        );
    }
    
    $result = $stmt->execute();
    $stmt->close();
    
    // If board interview assessment is successful, update candidate status
    if ($result) {
        // Update candidate status to completed
        $stmt = $conn->prepare("UPDATE candidates SET status = 'completed' WHERE candidate_id = ?");
        $stmt->bind_param("i", $candidate_id);
        $stmt->execute();
        $stmt->close();
        
        // Update interview stage status to passed
        $stmt = $conn->prepare("UPDATE candidate_stages cs 
                              JOIN stages s ON cs.stage_id = s.id 
                              SET cs.status = 'passed' 
                              WHERE cs.candidate_id = ? AND s.stage_name = 'interview'");
        $stmt->bind_param("i", $candidate_id);
        $stmt->execute();
        $stmt->close();
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
    <title>Board Interview Assessment - NDA Portal</title>
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
                        <a class="nav-link active" href="board_interview.php">Board Interview</a>
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
                            <i class="fas fa-users me-2"></i>Candidates in Board Interview Stage
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($candidates)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No candidates found in board interview stage.</p>
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

                                    // Get board interview assessment status for this candidate
                                    $candidate_interview = getBoardInterviewData($candidate['candidate_id']);
                                    $total_points = $candidate_interview ? json_decode($candidate_interview['assessment_summary'], true)['total_points'] : null;
                                    
                                    // Get previous scores
                                    $physical_score = 0;
                                    if ($candidate['physical_score']) {
                                        $physical_summary = json_decode($candidate['physical_score'], true);
                                        $physical_score = $physical_summary['total_points'];
                                    }
                                    
                                    $sand_score = 0;
                                    if ($candidate['sand_score']) {
                                        $sand_summary = json_decode($candidate['sand_score'], true);
                                        $sand_score = $sand_summary['total_points'];
                                    }
                                    
                                    $total_score = $physical_score + $sand_score;
                                ?>
                                    <a href="?candidate_id=<?php echo $candidate['candidate_id']; ?>" 
                                       class="list-group-item list-group-item-action d-flex align-items-center <?php echo ($selected_candidate && $selected_candidate['candidate_id'] == $candidate['candidate_id']) ? 'active' : ''; ?>">
                                        <img src="<?php echo htmlspecialchars($list_profile_path); ?>" 
                                             alt="Profile" class="rounded-circle me-3" width="40" height="40"
                                             onerror="this.src='../assets/images/default-profile.png'">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($candidate['surname'] . ', ' . $candidate['first_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($candidate['nda_application_number']); ?></small>
                                            <br>
                                            <small class="text-muted">Previous Score: <?php echo $total_score; ?>/60</small>
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

            <!-- Board Interview Assessment Form -->
            <div class="col-md-8">
                <?php if ($selected_candidate): ?>
                    <?php if ($board_interview_data): ?>
                        <div class="alert alert-info mb-4">
                            <h5 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Board Interview Assessment Already Completed</h5>
                            <p class="mb-0">
                                This candidate's board interview assessment was completed on 
                                <?php echo date('F j, Y, g:i a', strtotime($board_interview_data['assessed_at'])); ?>.
                                <?php 
                                $assessment_summary = json_decode($board_interview_data['assessment_summary'], true);
                                $total_points = $assessment_summary['total_points'];
                                ?>
                                <span class="text-success">Board Interview Score: <?php echo $total_points; ?>/40</span>
                            </p>
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
                                    $sand_data = getSandModellingData($selected_candidate['candidate_id']);
                                    if ($sand_data) {
                                        $sand_summary = json_decode($sand_data['assessment_summary'], true);
                                        $sand_score = $sand_summary['total_points'];
                                    }

                                    // Get board interview score
                                    $interview_score = 0;
                                    if ($board_interview_data) {
                                        $interview_summary = json_decode($board_interview_data['assessment_summary'], true);
                                        $interview_score = $interview_summary['total_points'];
                                    }

                                    // Calculate total score
                                    $total_score = $physical_score + $sand_score + $interview_score;
                                    ?>
                                    
                                    <div class="mb-3">
                                        <div class="points-display">
                                            <strong>Total Score:</strong> <?php echo $total_score; ?>/100
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

                    <!-- Board Interview Assessment Form -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-comments me-2"></i>Board Interview Assessment Form
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Scoring System:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Physical Assessment: 40 points (completed)</li>
                                    <li>Sand Modelling: 20 points (completed)</li>
                                    <li>Board Interview: 40 points (current stage)</li>
                                    <li>Total Possible Score: 100 points</li>
                                </ul>
                            </div>
                            <form action="" method="POST" id="boardInterviewForm">
                                <input type="hidden" name="candidate_id" value="<?php echo $selected_candidate['candidate_id']; ?>">
                                
                                <!-- Appearance -->
                                <div class="assessment-section">
                                    <h6 class="mb-3">Appearance (10 points)</h6>
                                    
                                    <div class="assessment-item">
                                        <div class="mb-3">
                                            <label class="form-label">Points (0-10)</label>
                                            <input type="number" class="form-control" name="appearance_points" min="0" max="10" required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Communication -->
                                <div class="assessment-section">
                                    <h6 class="mb-3">Communication (10 points)</h6>
                                    
                                    <div class="assessment-item">
                                        <div class="mb-3">
                                            <label class="form-label">Points (0-10)</label>
                                            <input type="number" class="form-control" name="communication_points" min="0" max="10" required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Knowledge -->
                                <div class="assessment-section">
                                    <h6 class="mb-3">Knowledge (10 points)</h6>
                                    
                                    <div class="assessment-item">
                                        <div class="mb-3">
                                            <label class="form-label">Points (0-10)</label>
                                            <input type="number" class="form-control" name="knowledge_points" min="0" max="10" required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Attitude -->
                                <div class="assessment-section">
                                    <h6 class="mb-3">Attitude (10 points)</h6>
                                    
                                    <div class="assessment-item">
                                        <div class="mb-3">
                                            <label class="form-label">Points (0-10)</label>
                                            <input type="number" class="form-control" name="attitude_points" min="0" max="10" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="submit_board_interview" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save me-2"></i>Save Board Interview Assessment Results
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
                            <p class="text-muted">Choose a candidate from the list to begin board interview assessment.</p>
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
            $('#boardInterviewForm').submit(function(e) {
                var appearancePoints = $('input[name="appearance_points"]').val();
                var communicationPoints = $('input[name="communication_points"]').val();
                var knowledgePoints = $('input[name="knowledge_points"]').val();
                var attitudePoints = $('input[name="attitude_points"]').val();

                if (!appearancePoints || !communicationPoints || !knowledgePoints || !attitudePoints) {
                    e.preventDefault();
                    alert('Please fill in all required fields for the assessment.');
                    return false;
                }

                return confirm('Are you sure you want to save the board interview assessment results? This action cannot be undone.');
            });
        });
    </script>
</body>
</html> 