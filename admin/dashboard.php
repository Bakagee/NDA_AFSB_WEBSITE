<?php
// Include necessary files
require_once '../database_connection.php';

// Start secure session
startSecureSession();

// Check if user is logged in as admin
requireAdminRole();

// Get admin details
$admin_id = $_SESSION['user_id'];
$admin = getAdminDetails($admin_id);

/**
 * Get admin details from database
 */
function getAdminDetails($admin_id) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT username, full_name, email, phone, profile_image FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $admin;
}

/**
 * Get screening statistics
 */
function getScreeningStatistics() {
    $conn = connectDB();
    $stats = array();
    
    // Total Candidates
    $sql = "SELECT COUNT(*) as total FROM candidates";
    $result = $conn->query($sql);
    $stats['total_candidates'] = $result->fetch_assoc()['total'];
    
    // Documentation Disqualified
    $sql = "SELECT COUNT(*) as doc_disqualified FROM candidate_stages 
            WHERE stage_id = 1 AND status = 'failed'";
    $result = $conn->query($sql);
    $stats['doc_disqualified'] = $result->fetch_assoc()['doc_disqualified'];
    
    // Medical Disqualified
    $sql = "SELECT COUNT(*) as medical_disqualified FROM candidate_stages 
            WHERE stage_id = 2 AND status = 'failed'";
    $result = $conn->query($sql);
    $stats['medical_disqualified'] = $result->fetch_assoc()['medical_disqualified'];
    
    // Get final candidates score
    $sql = "SELECT 
                COUNT(*) as total_passed,
                SUM(CASE WHEN cs1.status = 'passed' THEN 1 ELSE 0 END) as doc_passed,
                SUM(CASE WHEN cs2.status = 'passed' THEN 1 ELSE 0 END) as medical_passed,
                SUM(CASE WHEN cs3.status = 'passed' THEN 1 ELSE 0 END) as physical_passed,
                SUM(CASE WHEN cs4.status = 'passed' THEN 1 ELSE 0 END) as sand_passed,
                SUM(CASE WHEN cs5.status = 'passed' THEN 1 ELSE 0 END) as board_passed
            FROM candidates c
            LEFT JOIN candidate_stages cs1 ON c.candidate_id = cs1.candidate_id AND cs1.stage_id = 1
            LEFT JOIN candidate_stages cs2 ON c.candidate_id = cs2.candidate_id AND cs2.stage_id = 2
            LEFT JOIN candidate_stages cs3 ON c.candidate_id = cs3.candidate_id AND cs3.stage_id = 3
            LEFT JOIN candidate_stages cs4 ON c.candidate_id = cs4.candidate_id AND cs4.stage_id = 4
            LEFT JOIN candidate_stages cs5 ON c.candidate_id = cs5.candidate_id AND cs5.stage_id = 5";
    $result = $conn->query($sql);
    $stats['final_scores'] = $result->fetch_assoc();
    
    // Get stage statuses
    $sql = "SELECT stage_id, is_active FROM screening_stages ORDER BY stage_id";
    $result = $conn->query($sql);
    $stats['stage_statuses'] = array();
    while ($row = $result->fetch_assoc()) {
        $stats['stage_statuses'][$row['stage_id']] = $row['is_active'];
    }
    
    $conn->close();
    return $stats;
}

/**
 * Get candidate screening status by state
 */
function getCandidateScreeningStatus($state = null) {
    $conn = connectDB();
    $sql = "SELECT 
                c.candidate_id,
                c.chest_number,
                CONCAT(c.first_name, ' ', c.surname, IF(c.other_name IS NOT NULL, CONCAT(' ', c.other_name), '')) as full_name,
                s.state_name as state,
                c.status as final_status,
                cs1.status as documentation_status,
                cs2.status as medical_status,
                cs3.status as physical_status,
                cs4.status as sand_modelling_status,
                cs5.status as board_interview_status
            FROM candidates c
            JOIN states s ON c.state_id = s.id
            LEFT JOIN candidate_stages cs1 ON c.candidate_id = cs1.candidate_id AND cs1.stage_id = 1
            LEFT JOIN candidate_stages cs2 ON c.candidate_id = cs2.candidate_id AND cs2.stage_id = 2
            LEFT JOIN candidate_stages cs3 ON c.candidate_id = cs3.candidate_id AND cs3.stage_id = 3
            LEFT JOIN candidate_stages cs4 ON c.candidate_id = cs4.candidate_id AND cs4.stage_id = 4
            LEFT JOIN candidate_stages cs5 ON c.candidate_id = cs5.candidate_id AND cs5.stage_id = 5";
    
    if ($state) {
        $sql .= " WHERE s.state_name = ? OR s.state_code = ?";
    }
    
    $sql .= " ORDER BY c.chest_number";
    
    $stmt = $conn->prepare($sql);
    if ($state) {
        $stmt->bind_param("ss", $state, $state);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $conn->close();
    return $result;
}

/**
 * Get recent activity log
 */
function getRecentActivityLog($limit = 10) {
    $conn = connectDB();
    $sql = "SELECT 
                al.log_id,
                al.user_type,
                al.activity_type,
                al.activity_details,
                al.created_at,
                COALESCE(a.username, o.username) as username
            FROM activity_logs al
            LEFT JOIN admins a ON al.user_id = a.admin_id AND al.user_type = 'admin'
            LEFT JOIN officers o ON al.user_id = o.officer_id AND al.user_type = 'officer'
            ORDER BY al.created_at DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $conn->close();
    return $result;
}

/**
 * Get screening officers
 */
function getScreeningOfficers() {
    $conn = connectDB();
    $sql = "SELECT 
                o.officer_id,
                o.username,
                o.full_name,
                o.rank,
                o.assigned_state,
                o.last_login,
                o.status
            FROM officers o
            WHERE o.status = 'active'
            ORDER BY o.assigned_state, o.username";
    
    $result = $conn->query($sql);
    $conn->close();
    return $result;
}

// Get statistics and data for dashboard
$stats = getScreeningStatistics();
$candidates = getCandidateScreeningStatus();
$activity_log = getRecentActivityLog();
$officers = getScreeningOfficers();


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NDA AFSB</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1C6B4C;
            --secondary-color: #A62828;
            --accent-color: #F7D774;
            --dark-color: #1F1F1F;
            --light-color: #F8F9FA;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            padding-top: 60px;
        }
        
        .navbar {
            background-color: var(--primary-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 600;
            color: var(--light-color) !important;
        }
        
        .stats-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-passed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .activity-log {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .report-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .report-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .report-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .btn-export {
            margin-top: 10px;
        }
        
        .clickable-card {
            cursor: pointer;
            transition: transform 0.2s;
            margin-bottom: 1rem;
        }
        
        .clickable-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .activity-log {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .activity-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }

        .dashboard-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: flex-start;
            gap: 2.5rem;
            margin-top: 2rem;
        }

        .cards-section {
            flex: 2 1 600px;
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
        }

        .cards-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2.5rem;
        }

        .colorful-card {
            min-width: 220px;
            max-width: 260px;
            min-height: 90px;
            max-height: 110px;
            border-radius: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 1.13rem;
            font-weight: 600;
            cursor: pointer;
            transition: box-shadow 0.15s;
            text-align: center;
            padding: 1.5rem 1rem;
            color: #fff;
            background: #007A33;
        }
        .colorful-card.card-green { background: #007A33; }
        .colorful-card.card-red { background: #C8102E; }
        .colorful-card.card-yellow { background: #FFD100; color: #222; }
        .colorful-card.card-grey { background: #e0eafc; color: #222; }
        .colorful-card.card-purple { background: #fbc2eb; color: #222; }
        .colorful-card.card-orange { background: #FFD100; color: #222; }
        .colorful-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.10);
        }
        .colorful-card .card-title {
            font-size: 1.13rem;
            font-weight: 600;
            margin-bottom: 0;
            letter-spacing: 0.01em;
        }

        .stages-card {
            min-width: 260px;
            background: #f5f5f5;
            border-radius: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
            align-items: stretch;
        }
        .stages-title {
            font-weight: 600;
            margin-bottom: 1rem;
            text-align: left;
        }
        .stage-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.7rem;
        }
        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .toggle-btn {
            width: 38px;
            height: 22px;
            border-radius: 12px;
            background: #eee;
            position: relative;
            border: none;
            outline: none;
            cursor: pointer;
            transition: background 0.2s;
            margin-left: 0.5rem;
        }
        .toggle-btn.on {
            background: #2ecc40;
        }
        .toggle-btn.off {
            background: #ff4136;
        }
        .toggle-dot {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            transition: left 0.2s;
        }
        .toggle-btn.on .toggle-dot {
            left: 19px;
        }
        .toggle-label {
            font-size: 0.95rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }
        @media (max-width: 900px) {
            .dashboard-container {
                flex-direction: column;
                align-items: stretch;
            }
            .stages-card {
                margin: 0 auto;
            }
            .cards-row {
                flex-direction: column;
                gap: 1.5rem;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <img src="../img/nda-logo.png" alt="NDA Logo" height="30" class="mr-2">
                AFSB Admin
            </a>
            
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item active">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_candidates.php">Candidates</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_officers.php">Officers</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-toggle="dropdown">
                            <img src="../img/<?php echo htmlspecialchars($admin['profile_image']); ?>" alt="Profile" class="rounded-circle mr-2" width="30" height="30">
                            <?php echo htmlspecialchars($admin['username']); ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="profile.php">Profile</a>
                            <a class="dropdown-item" href="settings.php">Settings</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="../logout.php">Logout</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-4">
        <div class="dashboard-container">
            <div class="cards-section">
                <div class="cards-row">
                    <div class="colorful-card card-green" onclick="window.location.href='manage_candidates.php'">
                        <div class="card-title">Total Candidates</div>
                    </div>
                    <div class="colorful-card card-red" onclick="window.location.href='disqualified_candidates.php'">
                        <div class="card-title">Disqualified Candidates</div>
                    </div>
                    <div class="colorful-card card-yellow" onclick="window.location.href='manage_stages.php'">
                        <div class="card-title">Manage Screening Stages</div>
                    </div>
                </div>
                <div class="cards-row">
                    <div class="colorful-card card-grey" onclick="window.location.href='manage_officers.php'">
                        <div class="card-title">Manage officers</div>
                    </div>
                    <div class="colorful-card card-green" onclick="window.location.href='final_scores.php'">
                        <div class="card-title">Final Screened Candidates</div>
                    </div>
                    <div class="colorful-card card-purple" onclick="window.location.href='#recent-activity'">
                        <div class="card-title">Recent activity</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row mt-4" id="recent-activity">
            <div class="col-12">
                <h4>Recent Activity</h4>
                <div class="activity-log">
                    <?php while ($activity = $activity_log->fetch_assoc()): ?>
                    <div class="activity-item">
                        <div class="d-flex justify-content-between">
                            <strong><?php echo htmlspecialchars($activity['username']); ?></strong>
                            <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></small>
                        </div>
                        <div><?php echo htmlspecialchars($activity['activity_type']); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($activity['activity_details']); ?></small>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable for rankings
            $('#rankingsTable').DataTable({
                responsive: true,
                order: [[0, 'asc']],
                pageLength: 10,
                searching: false,
                lengthChange: false
            });
        });

        function toggleStage(stageId, event) {
            event.stopPropagation();
            fetch('toggle_stage.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'stage_id=' + stageId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the stage status.');
            });
        }
    </script>
</body>
</html>