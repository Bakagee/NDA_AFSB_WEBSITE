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
 * Get final screened candidates with scores
 */
function getFinalScreenedCandidates() {
    $conn = connectDB();
    
    $sql = "SELECT 
                c.candidate_id,
                c.chest_number,
                CONCAT(c.first_name, ' ', c.surname, IF(c.other_name IS NOT NULL, CONCAT(' ', c.other_name), '')) as full_name,
                s.state_name as state,
                dv.verification_status as documentation_status,
                JSON_EXTRACT(ms.overall_fitness, '$.status') as medical_status,
                JSON_EXTRACT(pa.assessment_summary, '$.total_points') as physical_score,
                JSON_EXTRACT(sma.assessment_summary, '$.total_points') as sand_modelling_score,
                JSON_EXTRACT(bi.assessment_summary, '$.total_points') as board_interview_score,
                (
                    COALESCE(JSON_EXTRACT(pa.assessment_summary, '$.total_points'), 0) + 
                    COALESCE(JSON_EXTRACT(sma.assessment_summary, '$.total_points'), 0) + 
                    COALESCE(JSON_EXTRACT(bi.assessment_summary, '$.total_points'), 0)
                ) as total_score
            FROM candidates c
            JOIN states s ON c.state_id = s.id
            LEFT JOIN document_verifications dv ON c.candidate_id = dv.candidate_id
            LEFT JOIN medical_screening ms ON c.candidate_id = ms.candidate_id
            LEFT JOIN physical_assessments pa ON c.candidate_id = pa.candidate_id
            LEFT JOIN sand_modelling_assessments sma ON c.candidate_id = sma.candidate_id
            LEFT JOIN board_interview_assessments bi ON c.candidate_id = bi.candidate_id
            WHERE bi.id IS NOT NULL
            ORDER BY s.state_name, total_score DESC";
    
    $result = $conn->query($sql);
    $candidates = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $candidates[] = $row;
        }
    }
    
    $conn->close();
    return $candidates;
}

// Get final screened candidates
$candidates = getFinalScreenedCandidates();

// Group candidates by state
$candidates_by_state = [];
foreach ($candidates as $candidate) {
    $state = $candidate['state'];
    if (!isset($candidates_by_state[$state])) {
        $candidates_by_state[$state] = [];
    }
    $candidates_by_state[$state][] = $candidate;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Screened Candidates - NDA AFSB</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            padding-top: 60px;
        }
        
        .navbar {
            background-color: #1C6B4C;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 600;
            color: #fff !important;
        }
        
        .state-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .state-header {
            background-color: #1C6B4C;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .candidate-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: transform 0.2s;
        }
        
        .candidate-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .score-badge {
            background-color: #1C6B4C;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: 500;
        }
        
        .stage-score {
            font-size: 0.9rem;
            color: #666;
        }
        
        .total-score {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1C6B4C;
        }
        
        .btn-export {
            background-color: #1C6B4C;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .btn-export:hover {
            background-color: #15573A;
            color: white;
        }
        
        /* Print-specific styles */
        @media print {
            body {
                padding-top: 0;
                background: white;
            }
            
            .navbar, .btn-print, .no-print {
                display: none !important;
            }
            
            .container {
                width: 100%;
                max-width: none;
                padding: 0;
                margin: 0;
            }
            
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 30px;
                padding: 20px;
                border-bottom: 2px solid #1C6B4C;
            }
            
            .print-header img {
                height: 80px;
                margin-bottom: 10px;
            }
            
            .print-header h1 {
                color: #1C6B4C;
                margin: 10px 0;
                font-size: 24px;
            }
            
            .print-header p {
                color: #666;
                margin: 5px 0;
            }
            
            .print-footer {
                display: block !important;
                text-align: center;
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
                font-size: 12px;
                color: #666;
            }

            /* Hide the card view in print */
            .state-section, .candidate-card {
                display: none !important;
            }

            /* Show the table view in print */
            .print-table {
                display: table !important;
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }

            .print-table th {
                background-color: #1C6B4C !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                padding: 8px;
                text-align: left;
                border: 1px solid #ddd;
            }

            .print-table td {
                padding: 8px;
                border: 1px solid #ddd;
            }

            .print-table tr:nth-child(even) {
                background-color: #f9f9f9;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .state-title {
                background-color: #f5f5f5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                font-weight: bold;
                padding: 10px;
                margin-top: 20px;
                border: 1px solid #ddd;
            }
        }
        
        .print-header, .print-footer, .print-table {
            display: none;
        }
        
        .btn-print {
            background-color: #1C6B4C;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .btn-print:hover {
            background-color: #15573A;
            color: white;
        }

        .back-button {
            position: fixed;
            top: 80px;
            left: 20px;
            background: var(--primary-color);
            color: #1C6B4C;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .back-button:hover {
            transform: translateX(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }

        @media (max-width: 768px) {
            .back-button {
                top: 70px;
                left: 15px;
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <button class="back-button" onclick="window.location.href='dashboard.php'">
        <i class="fas fa-arrow-left"></i>
    </button>

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
                    <li class="nav-item">
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

    <!-- Add print header and footer -->
    <div class="print-header">
        <img src="../img/nda-logo.png" alt="NDA Logo">
        <h1>NIGERIAN DEFENCE ACADEMY</h1>
        <h2>Armed Forces Selection Board</h2>
        <p>Final Screening Results</p>
        <p>Generated on: <?php echo date('F j, Y, g:i a'); ?></p>
    </div>

    <!-- Add print table -->
    <div class="print-table">
        <table>
            <thead>
                <tr>
                    <th>State</th>
                    <th>Chest No.</th>
                    <th>Full Name</th>
                    <th>Documentation</th>
                    <th>Medical</th>
                    <th>Physical</th>
                    <th>Sand Modelling</th>
                    <th>Board Interview</th>
                    <th>Total Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidates_by_state as $state => $state_candidates): ?>
                    <tr>
                        <td colspan="9" class="state-title"><?php echo htmlspecialchars($state); ?></td>
                    </tr>
                    <?php foreach ($state_candidates as $candidate): ?>
                        <tr>
                            <td></td>
                            <td><?php echo htmlspecialchars($candidate['chest_number']); ?></td>
                            <td><?php echo htmlspecialchars($candidate['full_name']); ?></td>
                            <td><?php echo ucfirst($candidate['documentation_status'] ?? 'pending'); ?></td>
                            <td><?php echo $candidate['medical_status'] ?? 'N/A'; ?></td>
                            <td><?php echo $candidate['physical_score'] ?? 'N/A'; ?></td>
                            <td><?php echo $candidate['sand_modelling_score'] ?? 'N/A'; ?></td>
                            <td><?php echo $candidate['board_interview_score'] ?? 'N/A'; ?></td>
                            <td><?php echo $candidate['total_score']; ?>/100</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="print-footer">
        <p>This document is computer-generated and does not require a signature.</p>
        <p>© <?php echo date('Y'); ?> Nigerian Defence Academy. All rights reserved.</p>
    </div>

    <!-- Main Content -->
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Final Screened Candidates</h2>
            <button class="btn btn-print" onclick="printResults()">
                <i class="fas fa-print mr-2"></i>Print Results
            </button>
        </div>

        <?php foreach ($candidates_by_state as $state => $state_candidates): ?>
        <div class="state-section">
            <div class="state-header">
                <h3 class="mb-0"><?php echo htmlspecialchars($state); ?></h3>
            </div>
            
            <?php foreach ($state_candidates as $candidate): ?>
            <div class="candidate-card">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <h5 class="mb-1"><?php echo htmlspecialchars($candidate['full_name']); ?></h5>
                        <small class="text-muted">Chest No: <?php echo htmlspecialchars($candidate['chest_number']); ?></small>
                    </div>
                    <div class="col-md-7">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stage-score">
                                    Documentation: <?php 
                                        $doc_status = $candidate['documentation_status'] ?? 'pending';
                                        echo ucfirst($doc_status); 
                                    ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stage-score">
                                    Medical: <?php echo $candidate['medical_status'] ?? 'N/A'; ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stage-score">
                                    Physical: <?php echo $candidate['physical_score'] ?? 'N/A'; ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stage-score">
                                    Sand Modelling: <?php echo $candidate['sand_modelling_score'] ?? 'N/A'; ?>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3">
                                <div class="stage-score">
                                    Board Interview: <?php echo $candidate['board_interview_score'] ?? 'N/A'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 text-right">
                        <div class="total-score">
                            <?php echo $candidate['total_score']; ?>/100
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        function printResults() {
            window.print();
        }
    </script>
</body>
</html> 