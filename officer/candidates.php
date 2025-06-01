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

// Get state information
$state = $officer['assigned_state'];

// Get candidates from the officer's state
$candidates = getCandidatesFromState($state);

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
 * Get candidates from a specific state
 */
function getCandidatesFromState($state) {
    $conn = connectDB();
    $candidates = [];
    
    // Query to get candidates from the officer's state
    $sql = "SELECT c.candidate_id, c.first_name, c.surname, c.chest_number, c.profile_picture 
            FROM candidates c
            JOIN states s ON c.state_id = s.id
            WHERE s.state_name = ? OR s.state_code = ?
            ORDER BY c.surname, c.first_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $state, $state);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    return $candidates;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Candidates - NDA AFSB Screening Portal</title>
    
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
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            padding-top: 76px; /* Adjusted for fixed navbar */
            overflow-x: hidden; /* Prevent horizontal scroll */
        }
        
        .container {
            padding-left: 15px;
            padding-right: 15px;
            max-width: 100%;
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
        
        .page-header {
            background-color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
            animation: fadeInDown 0.5s ease;
            border-left: 5px solid var(--primary-color);
        }
        
        .candidate-card {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
            height: 100%;
            animation: fadeInUp 0.5s ease;
        }
        
        .candidate-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .profile-img-container {
            width: 100%;
            height: 0;
            padding-bottom: 60%; /* Reduced from 75% to 60% */
            overflow: hidden;
            position: relative;
            background-color: #f5f5f5;
            border-radius: 0.75rem 0.75rem 0 0;
        }
        
        .profile-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .no-img {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            font-size: 36px; /* Reduced from 48px */
            color: #adb5bd;
        }
        
        .chest-number-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background-color: var(--primary-color);
            color: white;
            padding: 3px 8px;
            border-radius: 15px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            font-size: 0.8rem;
        }
        
        .search-box {
            background-color: white;
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
        }
        
        .footer {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
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
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            body {
                padding-top: 62px;
            }
            
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .page-header {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .search-box {
                padding: 0.75rem;
                margin-bottom: 1rem;
            }
            
            .search-box .col-md-4 {
                margin-top: 0.75rem;
            }
            
            .candidate-card {
                margin-bottom: 15px;
            }
            
            .row {
                margin-left: -5px;
                margin-right: -5px;
            }
            
            .col-md-6, .col-lg-4 {
                padding-left: 5px;
                padding-right: 5px;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 8px;
                padding-right: 8px;
            }
            
            .page-header {
                padding: 0.75rem;
            }
            
            .search-box {
                padding: 0.5rem;
            }
            
            .row {
                margin-left: -4px;
                margin-right: -4px;
            }
            
            .col-md-6, .col-lg-4 {
                padding-left: 4px;
                padding-right: 4px;
            }
            
            .search-box .input-group {
                flex-direction: column;
            }
            
            .search-box .input-group-prepend {
                margin-bottom: 0.5rem;
            }
            
            .search-box .input-group-text {
                border-radius: 0.25rem;
                width: 100%;
                justify-content: center;
            }
            
            .search-box .form-control {
                border-radius: 0.25rem !important;
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
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="candidates.php">
                            <i class="fas fa-users mr-1"></i> Candidates
                        </a>
                    </li>
                    
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
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user-circle mr-2"></i> My Profile
                            </a>
                            <a class="dropdown-item" href="change_password.php">
                                <i class="fas fa-key mr-2"></i> Change Password
                            </a>
                            <div class="dropdown-divider"></div>
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
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i class="fas fa-users mr-2"></i> All Candidates</h2>
                    <p class="lead mb-0">View all candidates from <?php echo htmlspecialchars($state); ?> state</p>
                </div>
                <div class="d-none d-md-block">
                    <img src="../img/candidates-graphic.svg" alt="Candidates" style="max-height: 80px;" onerror="this.style.display='none'">
                </div>
            </div>
        </div>
        
        <!-- Search Box -->
        <div class="search-box">
            <div class="row">
                <div class="col-md-8">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by name or chest number...">
                    </div>
                </div>
                <div class="col-md-4">
                    <select id="sortBy" class="form-control">
                        <option value="name">Sort by Name</option>
                        <option value="chest">Sort by Chest Number</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Candidates Grid -->
        <div class="row" id="candidatesContainer">
            <?php if (empty($candidates)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i> No candidates found for <?php echo htmlspecialchars($state); ?> state.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($candidates as $candidate): ?>
                    <div class="col-md-6 col-lg-4 candidate-item">
                        <div class="candidate-card">
                            <div class="profile-img-container">
                                <?php if (!empty($candidate['profile_picture'])): ?>
                                    <img src="../uploads/candidates/<?php echo htmlspecialchars($candidate['profile_picture']); ?>" class="profile-img" alt="Profile Picture">
                                <?php else: ?>
                                    <div class="no-img">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="chest-number-badge">
                                    #<?php echo htmlspecialchars($candidate['chest_number']); ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['surname']); ?></h5>
                                <p class="card-text">
                                    <strong>Chest Number:</strong> <?php echo htmlspecialchars($candidate['chest_number']); ?>
                                </p>
                                <!-- View Details button removed -->
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <img src="../img/nda-logo.png" alt="NDA Logo" class="footer-logo" onerror="this.src='../img/placeholder-logo.png'">
                    <h5 class="footer-title">Nigerian Defence Academy</h5>
                    <p>Armed Forces Selection Board</p>
                </div>
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h5 class="footer-title">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="candidates.php">Candidates</a></li>
                        <li><a href="reports.php">Reports</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h5 class="footer-title">Contact</h5>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt mr-2"></i> NDA Campus, Kaduna</li>
                        <li><i class="fas fa-phone mr-2"></i> +234 (0) 123 456 7890</li>
                        <li><i class="fas fa-envelope mr-2"></i> info@nda.mil.ng</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p class="mb-0">© <?php echo date('Y'); ?> Nigerian Defence Academy. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Search functionality
            $("#searchInput").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $(".candidate-item").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });
            
            // Sort by
            $("#sortBy").on("change", function() {
                sortCandidates($(this).val());
            });
            
            function sortCandidates(sortBy) {
                var $container = $('#candidatesContainer');
                var $items = $('.candidate-item');
                
                switch(sortBy) {
                    case 'name':
                        $items.sort(function(a, b) {
                            return $(a).find('.card-title').text().localeCompare($(b).find('.card-title').text());
                        });
                        break;
                    case 'chest':
                        $items.sort(function(a, b) {
                            return $(a).find('.chest-number-badge').text().localeCompare($(b).find('.chest-number-badge').text());
                        });
                        break;
                }
                
                $container.append($items);
            }
        });
    </script>
</body>
</html>