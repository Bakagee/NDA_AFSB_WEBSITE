<?php
// Include database connection
require_once '../database_connection.php';

// Start secure session
startSecureSession();

// Check if user is logged in as officer
requireOfficerRole();

// Get officer details
$officer_id = $_SESSION['user_id'];
$officer = getOfficerDetails($officer_id);

// Get state information and candidate counts
$state = $officer['assigned_state'];
$candidateCounts = getCandidateCounts($state);
$stageStatistics = getStageStatistics($state);

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
 * Get candidate counts for a specific state
 */
function getCandidateCounts($state) {
    $conn = connectDB();
    
    $counts = [
        'total' => 0,
        'active' => 0,
        'passed' => 0,
        'failed' => 0,
        'documented' => 0,
        'flagged' => 0
    ];
    
    // Get total and status counts
    $sql = "SELECT c.status, COUNT(*) as count
            FROM candidates c
            JOIN states s ON c.state_id = s.id
            WHERE s.state_name = ? OR s.state_code = ?
            GROUP BY c.status";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $state, $state);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] == 'active') $counts['active'] = $row['count'];
        if ($row['status'] == 'passed') $counts['passed'] = $row['count'];
        if ($row['status'] == 'failed') $counts['failed'] = $row['count'];
        $counts['total'] += $row['count'];
    }
    
    $stmt->close();
    
    // Get count of documented candidates
    $sql = "SELECT COUNT(*) as count
            FROM candidates c
            JOIN states s ON c.state_id = s.id
            JOIN candidate_stages cs ON c.candidate_id = cs.candidate_id
            WHERE (s.state_name = ? OR s.state_code = ?) 
            AND cs.stage_id = 1 
            AND cs.status = 'passed'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $state, $state);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $counts['documented'] = $row['count'];
    }
    
    $stmt->close();
    
    // Get count of flagged candidates
    $sql = "SELECT COUNT(DISTINCT c.candidate_id) as count
            FROM candidates c
            JOIN states s ON c.state_id = s.id
            JOIN flags f ON c.candidate_id = f.candidate_id
            WHERE (s.state_name = ? OR s.state_code = ?) AND f.status = 'open'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $state, $state);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $counts['flagged'] = $row['count'];
    }
    
    $stmt->close();
    $conn->close();
    
    return $counts;
}

/**
 * Get statistics for each stage for a specific state
 */
function getStageStatistics($state) {
    $conn = connectDB();
    
    $stages = [
        'documentation' => ['total' => 0, 'is_active' => true],
        'medical' => ['total' => 0, 'is_active' => true],
        'physical' => ['total' => 0, 'is_active' => true],
        'sand_modelling' => ['total' => 0, 'is_active' => true],
        'interview' => ['total' => 0, 'is_active' => true]
    ];
    
    // Get stage statuses
    $sql = "SELECT stage_name, is_active FROM stages";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        if (isset($stages[$row['stage_name']])) {
            $stages[$row['stage_name']]['is_active'] = (bool)$row['is_active'];
        }
    }
    
    // Get stage statistics with LEFT JOIN to include stages with no candidates
    $sql = "SELECT st.stage_name, COALESCE(COUNT(DISTINCT c.candidate_id), 0) as count
            FROM stages st
            LEFT JOIN candidate_stages cs ON st.id = cs.stage_id
            LEFT JOIN candidates c ON cs.candidate_id = c.candidate_id
            LEFT JOIN states s ON c.state_id = s.id
            WHERE (s.state_name = ? OR s.state_code = ? OR s.state_name IS NULL)
            GROUP BY st.stage_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $state, $state);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (isset($stages[$row['stage_name']])) {
            $stages[$row['stage_name']]['total'] = (int)$row['count'];
        }
    }
    
    $stmt->close();
    
    // Get count of medically fit candidates for physical stage
    $sql = "SELECT COUNT(DISTINCT c.candidate_id) as count
            FROM candidates c
            JOIN states s ON c.state_id = s.id
            JOIN medical_screening ms ON c.candidate_id = ms.candidate_id
            WHERE (s.state_name = ? OR s.state_code = ?)
            AND JSON_EXTRACT(ms.overall_fitness, '$.status') = 'fit'
            AND (
                EXISTS (
                    SELECT 1 FROM candidate_stages cs
                    JOIN stages st ON cs.stage_id = st.id
                    WHERE cs.candidate_id = c.candidate_id
                    AND st.stage_name = 'physical'
                    AND cs.status = 'pending'
                )
                OR NOT EXISTS (
                    SELECT 1 FROM candidate_stages cs2
                    JOIN stages st2 ON cs2.stage_id = st2.id
                    WHERE cs2.candidate_id = c.candidate_id
                    AND st2.stage_name = 'physical'
                )
            )";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $state, $state);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stages['physical']['total'] = (int)$row['count'];
    }
    
    $stmt->close();
    $conn->close();
    
    return $stages;
}

// Current time greeting
$hour = date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour >= 12 && $hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard - NDA AFSB</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- Animate.css for animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #003366;
            --secondary-color: #0056b3;
            --accent-color: #ffc107;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            
            --documentation-color: #4285F4;
            --medical-color: #34A853;
            --physical-color: #FBBC05;
            --sand-modelling-color: #EA4335;
            --interview-color: #9C27B0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            padding-top: 76px; /* Adjusted for fixed navbar */
        }
        
        .navbar {
            background-color: var(--primary-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 0.5rem 1rem;
        }
        
        .navbar-brand {
            font-weight: bold;
            display: flex;
            align-items: center;
        }
        
        .navbar-brand img {
            height: 40px;
            margin-right: 10px;
            transition: transform 0.3s ease;
        }
        
        .navbar-brand:hover img {
            transform: scale(1.05);
        }
        
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 1rem 1rem;
            transition: all 0.3s ease;
        }
        
        .navbar-dark .navbar-nav .nav-link:hover,
        .navbar-dark .navbar-nav .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .navbar-dark .navbar-nav .nav-link.active {
            border-bottom: 3px solid var(--accent-color);
        }
        
        .profile-dropdown .dropdown-toggle {
            display: flex;
            align-items: center;
        }
        
        .profile-image {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            border: 2px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
        }
        
        .dropdown-toggle:hover .profile-image {
            border-color: white;
            transform: scale(1.05);
        }
        
        .dropdown-menu {
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
            animation: fadeIn 0.3s ease;
        }
        
        .dropdown-item {
            padding: 0.5rem 1.5rem;
            transition: background-color 0.2s ease;
        }
        
        .dropdown-item:active {
            background-color: var(--primary-color);
        }
        
        .btn-logout {
            color: var(--danger-color);
        }
        
        .welcome-section {
            background-color: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
            animation: fadeInDown 0.5s ease;
            border-left: 5px solid var(--primary-color);
        }
        
        .welcome-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .welcome-subtitle {
            color: #6c757d;
            margin-bottom: 1.5rem;
        }
        
        .state-badge {
            background-color: var(--primary-color);
            color: white;
            border-radius: 30px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 1rem;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
        }
        
        .stats-row {
            margin-top: 1.5rem;
        }
        
        .stats-card {
            background-color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            text-decoration: none;
            color: inherit;
        }
        
        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stage-card {
            position: relative;
            border: none;
            border-radius: 1rem;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 2rem;
            min-height: 320px;
            background-color: white;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
            animation: fadeInUp 0.5s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .stage-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.15);
            text-decoration: none;
            color: inherit;
        }
        
        .stage-card .card-header {
            padding: 1.5rem;
            font-weight: 600;
            color: white;
            border-bottom: none;
        }
        
        .stage-card .card-body {
            padding: 1.5rem;
        }
        
        .stage-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }
        
        .stage-card:hover .stage-icon {
            transform: scale(1.1);
        }
        
        .stage-progress {
            height: 10px;
            border-radius: 5px;
            margin: 1.5rem 0;
        }
        
        .stage-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
        }
        
        .stage-stat {
            text-align: center;
            padding: 0.5rem;
            flex: 1;
            border-radius: 0.5rem;
            background-color: rgba(0, 0, 0, 0.05);
            margin: 0 0.25rem;
        }
        
        .stage-stat-number {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .stage-stat-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            opacity: 0.8;
        }
        
        /* Colors for each stage */
        .documentation-header {
            background-color: var(--documentation-color);
        }
        
        .medical-header {
            background-color: var(--medical-color);
        }
        
        .physical-header {
            background-color: var(--physical-color);
        }
        
        .sand-modelling-header {
            background-color: var(--sand-modelling-color);
        }
        
        .interview-header {
            background-color: var(--interview-color);
        }
        
        .all-candidates-header {
            background-color: var(--primary-color);
        }
        
        .documentation-icon {
            color: var(--documentation-color);
        }
        
        .medical-icon {
            color: var(--medical-color);
        }
        
        .physical-icon {
            color: var(--physical-color);
        }
        
        .sand-modelling-icon {
            color: var(--sand-modelling-color);
        }
        
        .interview-icon {
            color: var(--interview-color);
        }
        
        .all-candidates-icon {
            color: var(--primary-color);
        }
        
        .footer {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        
        .footer-logo {
            height: 40px;
            margin-bottom: 1rem;
        }
        
        .footer-title {
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .footer-links {
            list-style: none;
            padding-left: 0;
        }
        
        .footer-links li {
            margin-bottom: 0.5rem;
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        .footer-bottom {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 1rem 0;
            margin-top: 2rem;
            text-align: center;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fast-stat-card {
            border-radius: 1rem;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            margin-bottom: 1.5rem;
            animation: fadeIn 0.5s ease;
        }
        
        .fast-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .fast-stat-body {
            background-color: white;
            padding: 1.5rem;
        }
        
        .fast-stat-header {
            padding: 1rem;
            color: white;
            font-weight: 600;
            text-align: center;
        }
        
        .fast-stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin: 1rem 0;
        }
        
        .fast-stat-label {
            text-align: center;
            font-size: 0.95rem;
            color: #6c757d;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            body {
                padding-top: 62px;
            }
            
            .navbar-brand img {
                height: 30px;
            }
            
            .welcome-section {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }
            
            .welcome-title {
                font-size: 1.5rem;
            }
            
            .stats-card {
                padding: 1rem;
            }
            
            .stats-icon {
                font-size: 2rem;
                margin-bottom: 1rem;
            }
            
            .stats-number {
                font-size: 2rem;
            }
            
            .stage-card {
                min-height: auto;
            }
            
            .stage-card .card-header,
            .stage-card .card-body {
                padding: 1rem;
            }
            
            .stage-icon {
                font-size: 2.5rem;
            }
            
            .stage-stat-number {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <img src="../img/nda-logo.png" alt="NDA Logo" onerror="this.src='../img/placeholder-logo.png'">
                <span>NDA AFSB</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="candidates.php">
                            <i class="fas fa-users mr-1"></i> Candidates
                        </a>
                    </li>
                    <li class="nav-item dropdown profile-dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <img src="../img/<?php echo htmlspecialchars($officer['profile_image'] ?: 'default_officer.png'); ?>" alt="Profile" class="profile-image" onerror="this.src='../img/default_officer.png'">
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($officer['rank']); ?> <?php echo htmlspecialchars($officer['username']); ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="profileDropdown">
                            <div class="dropdown-header">
                                <div class="text-center">
                                    <img src="../img/<?php echo htmlspecialchars($officer['profile_image'] ?: 'default_officer.png'); ?>" alt="Profile" class="profile-image" style="width: 60px; height: 60px; margin-bottom: 10px;" onerror="this.src='../img/default_officer.png'">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($officer['full_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($officer['rank']); ?></small>
                                </div>
                            </div>
                            <a class="dropdown-item btn-logout" href="../logout.php">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-4">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h2 class="welcome-title">
                <?php echo $greeting; ?>, <?php echo htmlspecialchars($officer['rank']); ?> <?php echo htmlspecialchars($officer['full_name']); ?>!
            </h2>
            <p class="welcome-subtitle">Welcome to your NDA AFSB screening officer dashboard.</p>
            
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div>
                    <div class="state-badge">
                        <i class="fas fa-map-marker-alt mr-1"></i> Assigned State: <?php echo htmlspecialchars($state); ?>
                    </div>
                    <p class="mb-0">
                        You are responsible for screening candidates from <?php echo htmlspecialchars($state); ?> state. 
                        This dashboard provides an overview of all screening stages and candidate progress.
                    </p>
                </div>
                <div class="d-none d-md-block">
                </div>
            </div>
        </div>
        
        <!-- Stage Cards Section Title -->
        <h4 class="mb-4 text-primary animate__animated animate__fadeInUp">Screening Stages</h4>
        
        <div class="row">
            <!-- All Candidates Card -->
            <div class="col-md-6 col-lg-4" style="animation-delay: 0.5s">
                <a href="candidates.php" class="stage-card">
                    <div class="card-header all-candidates-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>All Candidates</span>
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <div class="stage-icon all-candidates-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5 class="card-title">View All Candidates</h5>
                        <p class="card-text">Manage and view all candidates from <?php echo $state; ?> state</p>
                        
                        <div class="stage-progress">
                            <div class="progress">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        
                        <div class="stage-stats">
                            <div class="stage-stat">
                                <div class="stage-stat-number"><?php echo $candidateCounts['total']; ?></div>
                                <div class="stage-stat-label">Total</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            
            <?php
            $stages = [
                'documentation' => [
                    'title' => 'Documentation Stage',
                    'description' => 'Verify candidate credentials and documents',
                    'icon' => 'file-alt',
                    'color' => 'documentation',
                    'url' => 'documentation.php'
                ],
                'medical' => [
                    'title' => 'Medical Examination',
                    'description' => 'Health assessment and fitness evaluation',
                    'icon' => 'heartbeat',
                    'color' => 'medical',
                    'url' => 'medical.php'
                ],
                'physical' => [
                    'title' => 'Physical Assessment',
                    'description' => 'Physical fitness and endurance testing',
                    'icon' => 'running',
                    'color' => 'physical',
                    'url' => 'physical.php'
                ],
                'sand_modelling' => [
                    'title' => 'Sand Modelling',
                    'description' => 'Spatial awareness and problem-solving assessment',
                    'icon' => 'cubes',
                    'color' => 'sand-modelling',
                    'url' => 'sand_modelling.php'
                ],
                'interview' => [
                    'title' => 'Board Interview',
                    'description' => 'Final interview with the selection board',
                    'icon' => 'comments',
                    'color' => 'interview',
                    'url' => 'board_interview.php'
                ]
            ];

            $delay = 0.6;
            foreach ($stages as $stage_key => $stage_info):
                $is_active = $stageStatistics[$stage_key]['is_active'];
                $total = $stageStatistics[$stage_key]['total'];
                $delay += 0.1;
            ?>
            <div class="col-md-6 col-lg-4" style="animation-delay: <?php echo $delay; ?>s">
                <?php if ($is_active): ?>
                <a href="<?php echo $stage_info['url']; ?>" class="stage-card">
                <?php else: ?>
                <div class="stage-card" style="opacity: 0.7; cursor: not-allowed;">
                <?php endif; ?>
                    <div class="card-header <?php echo $stage_info['color']; ?>-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><?php echo ucfirst($stage_key); ?></span>
                            <i class="fas fa-<?php echo $stage_info['icon']; ?>"></i>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <div class="stage-icon <?php echo $stage_info['color']; ?>-icon">
                            <?php if ($is_active): ?>
                            <i class="fas fa-<?php echo $stage_info['icon']; ?>"></i>
                            <?php else: ?>
                            <i class="fas fa-lock"></i>
                            <?php endif; ?>
                        </div>
                        <h5 class="card-title"><?php echo $stage_info['title']; ?></h5>
                        <p class="card-text"><?php echo $stage_info['description']; ?></p>
                        
                        <?php if ($is_active): ?>
                        <div class="stage-progress">
                            <div class="progress">
                                <?php 
                                $percent = 0; // Default to 0%
                                if ($total > 0) {
                                    $percent = 100; // If there are candidates, show 100%
                                }
                                ?>
                                <div class="progress-bar bg-<?php echo $stage_info['color']; ?>" role="progressbar" 
                                     style="width: <?php echo $percent; ?>%" 
                                     aria-valuenow="<?php echo $percent; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                        </div>
                        
                        <div class="stage-stats">
                            <div class="stage-stat">
                                <div class="stage-stat-number"><?php echo $total; ?></div>
                                <div class="stage-stat-label">Total</div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-lock mr-2"></i> This stage is currently locked by the administrator.
                        </div>
                        <?php endif; ?>
                    </div>
                <?php if ($is_active): ?>
                </a>
                <?php else: ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-info-circle mr-2"></i> Important Information
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            <p><strong>Screening Protocol:</strong> Please ensure all candidates go through each stage in the correct sequence. 
                            Documentation must be completed before proceeding to medical examination, and so on.</p>
                            
                            <p><strong>Flagging Issues:</strong> Use the flag function to mark any inconsistencies or concerns about a candidate. 
                            All flags are visible to the Board Chairman for review.</p>
                            
                            <p class="mb-0"><strong>Technical Support:</strong> If you encounter any system issues, please contact the technical team at <a href="mailto:support@nda.mil.ng">support@nda.mil.ng</a>.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <img src="../img/nda-logo.png" alt="NDA Logo" class="footer-logo" onerror="this.src='../img/placeholder-logo.png'">
                    <h5 class="footer-title">Nigerian Defence Academy</h5>
                    <p>Armed Forces Selection Board Screening System</p>
                </div>
                <div class="col-md-4">
                    <h5 class="footer-title">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt mr-1"></i> Dashboard</a></li>
                        <li><a href="candidates.php"><i class="fas fa-users mr-1"></i> Candidates</a></li>
                        <li><a href="profile.php"><i class="fas fa-user-circle mr-1"></i> My Profile</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="footer-title">Contact</h5>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt mr-1"></i> NDA, Kaduna, Nigeria</li>
                        <li><i class="fas fa-phone mr-1"></i> +234 (0) XXXX XXXX</li>
                        <li><i class="fas fa-envelope mr-1"></i> info@nda.mil.ng</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Nigerian Defence Academy. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Tooltip initialization
            $('[data-toggle="tooltip"]').tooltip();
            
            // Add animation classes to elements on scroll
            function animateOnScroll() {
                $('.stage-card').each(function() {
                    var position = $(this).offset().top;
                    var scroll = $(window).scrollTop();
                    var windowHeight = $(window).height();
                    
                    if (scroll + windowHeight > position + 100) {
                        $(this).addClass('animate__animated animate__fadeInUp');
                    }
                });
            }
            
            // Run animation check on load and scroll
            animateOnScroll();
            $(window).scroll(animateOnScroll);
            
            // Dropdown animation
            $('.dropdown').on('show.bs.dropdown', function() {
                $(this).find('.dropdown-menu').first().stop(true, true).slideDown(200);
            });
            $('.dropdown').on('hide.bs.dropdown', function() {
                $(this).find('.dropdown-menu').first().stop(true, true).slideUp(100);
            });
            
            // Stage card hover effect
            $('.stage-card').hover(
                function() {
                    $(this).find('.stage-icon').addClass('animate__animated animate__pulse');
                },
                function() {
                    $(this).find('.stage-icon').removeClass('animate__animated animate__pulse');
                }
            );
        });
    </script>
</body>
</html>