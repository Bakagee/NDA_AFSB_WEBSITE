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
 * Get candidate count
 */
function getCandidateCount() {
    $conn = connectDB();
    $result = $conn->query("SELECT COUNT(*) as count FROM candidates");
    $count = 0;
    if ($result && $row = $result->fetch_assoc()) {
        $count = $row['count'];
    }
    $conn->close();
    return $count;
}

/**
 * Get officers count
 */
function getOfficersCount() {
    $conn = connectDB();
    $result = $conn->query("SELECT COUNT(*) as count FROM officers");
    $count = 0;
    if ($result && $row = $result->fetch_assoc()) {
        $count = $row['count'];
    }
    $conn->close();
    return $count;
}

// Get counts for dashboard
$candidate_count = getCandidateCount();
$officers_count = getOfficersCount();

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
    <title>Admin Dashboard - NDA AFSB</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1C6B4C;
            --secondary-color: #A62828;
            --accent-color: #F7D774;
            --dark-color: #1F1F1F;
            --light-color: #F8F9FA;
            --grey-color: #D9D9D9;
            --slate-blue: #2B3D54;
            --success-color: #2DC26C;
            --warning-color: #FFCB05;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
            padding-top: 60px;
        }
        
        .navbar {
            background-color: var(--primary-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 600;
            display: flex;
            align-items: center;
            color: var(--light-color) !important;
        }
        
        .navbar-brand img {
            height: 30px;
            margin-right: 10px;
        }
        
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.85);
            transition: color 0.3s ease;
            font-weight: 500;
        }
        
        .navbar-dark .navbar-nav .nav-link:hover {
            color: var(--accent-color);
        }
        
        .navbar-dark .navbar-nav .active > .nav-link {
            color: var(--accent-color);
        }
        
        .profile-image {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-color);
            transition: all 0.3s ease;
        }
        
        .profile-image:hover {
            transform: scale(1.05);
        }
        
        .dropdown-menu {
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
            animation: fadeIn 0.3s ease;
            border-top: 3px solid var(--accent-color);
        }
        
        .dropdown-item {
            padding: 0.5rem 1.5rem;
            transition: background-color 0.2s ease;
            font-weight: 500;
        }
        
        .dropdown-item:active {
            background-color: var(--primary-color);
        }
        
        .dropdown-header {
            font-weight: 600;
            color: var(--slate-blue);
        }
        
        .btn-logout {
            color: var(--secondary-color);
        }
        
        .welcome-section {
            background-color: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
            animation: fadeInUp 0.5s ease;
            border-left: 5px solid var(--primary-color);
        }
        
        .welcome-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
        }
        
        .stats-card {
            border-radius: 1rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            cursor: pointer;
            overflow: hidden;
            border: none;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .card-img-overlay {
            background: rgba(0, 0, 0, 0.5);
            border-radius: 1rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        
        .action-card {
            border: none;
            border-radius: 1rem;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            margin-bottom: 1.5rem;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .action-card .card-header {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 1rem 1.5rem;
        }
        
        .action-card .card-body {
            padding: 1.5rem;
        }
        
        .action-card .icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }
        
        .action-card:hover .icon {
            transform: scale(1.1);
            color: var(--secondary-color);
        }
        
        .action-card .btn {
            border-radius: 2rem;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .action-card .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .action-card .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 0.3rem 0.5rem rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            color: var(--slate-blue);
            font-weight: 600;
            position: relative;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--accent-color);
        }
        
        .footer {
            background-color: var(--slate-blue);
            padding: 1.5rem 0;
            margin-top: 2rem;
            text-align: center;
            color: var(--light-color);
            font-size: 0.9rem;
        }
        
        /* Custom Card Colors */
        .bg-candidates {
            background-color: var(--slate-blue);
        }
        
        .bg-officers {
            background-color: var(--primary-color);
        }
        
        .bg-stages {
            background-color: var(--secondary-color);
        }
        
        .bg-states {
            background-color: var(--warning-color);
            color: var(--dark-color) !important;
        }
        
        .bg-states .card-title {
            color: var(--dark-color) !important;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
        
        .animate-fadeIn {
            animation: fadeIn 0.5s ease;
        }
        
        .animate-fadeInUp {
            animation: fadeInUp 0.5s ease;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .welcome-section {
                padding: 1.5rem;
            }
            
            .action-card {
                margin-bottom: 1rem;
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
                <span>AFSB Admin</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto align-items-center">
                    <li class="nav-item active">
                        <a class="nav-link" href="dashboard.php"><i class="fas fa-home mr-1"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_candidates.php"><i class="fas fa-users mr-1"></i> Candidates</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_officers.php"><i class="fas fa-user-shield mr-1"></i> Officers</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <img src="../img/<?php echo htmlspecialchars($admin['profile_image'] ?: 'default_admin.png'); ?>" alt="Profile" class="profile-image mr-2" onerror="this.src='../img/default_admin.png'">
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($admin['username']); ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="profileDropdown">
                            <h6 class="dropdown-header"><?php echo htmlspecialchars($admin['full_name']); ?></h6>
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle mr-2"></i> My Profile</a>
                            <a class="dropdown-item" href="settings.php"><i class="fas fa-cog mr-2"></i> Settings</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item btn-logout" href="../logout.php"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-4">
        <!-- Welcome Section -->
        <div class="welcome-section animate-fadeInUp">
            <h2 class="welcome-title"><?php echo $greeting; ?>, <?php echo htmlspecialchars($admin['full_name']); ?>!</h2>
            
            <div class="row mt-4">
                <div class="col-md-6 col-lg-3 mb-4 animate-fadeIn" style="animation-delay: 0.1s">
                    <div class="card text-white stats-card bg-candidates">
                        <div class="card-img-overlay d-flex flex-column justify-content-end">
                            <h5 class="card-title">Total Candidates</h5>
                            <p class="card-text display-4"><?php echo $candidate_count; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 mb-4 animate-fadeIn" style="animation-delay: 0.2s">
                    <div class="card text-white stats-card bg-officers">
                        <div class="card-img-overlay d-flex flex-column justify-content-end">
                            <h5 class="card-title">Active Officers</h5>
                            <p class="card-text display-4"><?php echo $officers_count; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 mb-4 animate-fadeIn" style="animation-delay: 0.3s">
                    <div class="card text-white stats-card bg-stages">
                        <div class="card-img-overlay d-flex flex-column justify-content-end">
                            <h5 class="card-title">Ongoing Stages</h5>
                            <p class="card-text display-4">5</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 mb-4 animate-fadeIn" style="animation-delay: 0.4s">
                    <div class="card text-white stats-card bg-states">
                        <div class="card-img-overlay d-flex flex-column justify-content-end">
                            <h5 class="card-title">Participating States</h5>
                            <p class="card-text display-4">37</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Cards -->
        <h4 class="section-title animate-fadeInUp" style="animation-delay: 0.5s">Quick Actions</h4>
        
        <div class="row">
            <!-- Add Candidate Card -->
            <div class="col-md-6 col-lg-4 animate-fadeInUp" style="animation-delay: 0.6s">
                <div class="card action-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Add Candidate</span>
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="card-body text-center">
                        <div class="icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h5 class="card-title">Register New Candidate</h5>
                        <p class="card-text">Add a new candidate to the AFSB screening system</p>
                        <a href="add_candidate.php" class="btn btn-primary mt-3">Add Candidate</a>
                    </div>
                </div>
            </div>
            
            <!-- View Candidates Card -->
            <div class="col-md-6 col-lg-4 animate-fadeInUp" style="animation-delay: 0.7s">
                <div class="card action-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>View Candidates</span>
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-body text-center">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5 class="card-title">Manage Candidates</h5>
                        <p class="card-text">View, edit, and manage all registered candidates</p>
                        <a href="manage_candidates.php" class="btn btn-primary mt-3">View All Candidates</a>
                    </div>
                </div>
            </div>
            
            <!-- Add Officer Card -->
            <div class="col-md-6 col-lg-4 animate-fadeInUp" style="animation-delay: 0.8s">
                <div class="card action-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Manage Officers</span>
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="card-body text-center">
                        <div class="icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h5 class="card-title">Screening Officers</h5>
                        <p class="card-text">Add, edit, or manage screening officers</p>
                        <a href="manage_officers.php" class="btn btn-primary mt-3">Manage Officers</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>Nigerian Defence Academy | Armed Forces Selection Board | Admin Portal</p>
            <p class="mb-0">&copy; <?php echo date('Y'); ?> NDA AFSB Screening System. All Rights Reserved.</p>
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
            
            // Animation for cards with delay
            setTimeout(function() {
                $('.stats-card').addClass('animate__animated animate__fadeIn');
            }, 300);
            
            setTimeout(function() {
                $('.action-card').addClass('animate__animated animate__fadeInUp');
            }, 600);
            
            // Dropdown animation
            $('.dropdown').on('show.bs.dropdown', function() {
                $(this).find('.dropdown-menu').first().stop(true, true).slideDown(200);
            });
            $('.dropdown').on('hide.bs.dropdown', function() {
                $(this).find('.dropdown-menu').first().stop(true, true).slideUp(100);
            });
        });
    </script>
</body>
</html>