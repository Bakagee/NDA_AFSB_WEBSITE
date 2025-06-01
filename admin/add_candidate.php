<?php
// Include database connection
require_once '../database_connection.php';

// Start secure session
startSecureSession();

// Check if user is logged in as admin
requireAdminRole();

// Initialize variables
$first_name = $surname = $other_name = $jamb_number = $sex = $nda_application_number = '';
$jamb_score = $state_id = $service_choice_1 = $service_choice_2 = '';
$errors = [];
$success_message = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input fields
    $first_name = trim($_POST['first_name']);
    $surname = trim($_POST['surname']);
    $other_name = trim($_POST['other_name'] ?? '');
    $jamb_number = trim($_POST['jamb_number']);
    $sex = trim($_POST['sex']);
    $nda_application_number = trim($_POST['nda_application_number']);
    $jamb_score = (int)trim($_POST['jamb_score']);
    $state_id = (int)trim($_POST['state_id']);
    $service_choice_1 = trim($_POST['service_choice_1']);
    $service_choice_2 = trim($_POST['service_choice_2'] ?? '');
    
    // Validate required fields
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($surname)) $errors[] = "Surname is required";
    if (empty($jamb_number)) $errors[] = "JAMB number is required";
    if (empty($sex)) $errors[] = "Sex is required";
    if (empty($nda_application_number)) $errors[] = "NDA application number is required";
    if (empty($jamb_score)) $errors[] = "JAMB score is required";
    if (empty($state_id)) $errors[] = "State is required";
    if (empty($service_choice_1)) $errors[] = "1st Service choice is required";
    
    // Validate JAMB number format (example: 30445986GF)
    if (!empty($jamb_number) && !preg_match('/^\d{8}[A-Z]{2}$/', $jamb_number)) {
        $errors[] = "JAMB number must be in the format of 8 digits followed by 2 uppercase letters (e.g., 30445986GF)";
    }
    
    // Validate JAMB score range (0-400)
    if ($jamb_score < 0 || $jamb_score > 400) {
        $errors[] = "JAMB score must be between 0 and 400";
    }
    
    // Check if JAMB number already exists
    if (!empty($jamb_number)) {
        $conn = connectDB();
        $check_jamb = $conn->prepare("SELECT candidate_id FROM candidates WHERE jamb_number = ?");
        $check_jamb->bind_param("s", $jamb_number);
        $check_jamb->execute();
        $check_jamb->store_result();
        
        if ($check_jamb->num_rows > 0) {
            $errors[] = "A candidate with this JAMB number already exists";
        }
        
        $check_jamb->close();
    }
    
    // Check if NDA application number already exists
    if (!empty($nda_application_number)) {
        $conn = connectDB();
        $check_nda = $conn->prepare("SELECT candidate_id FROM candidates WHERE nda_application_number = ?");
        $check_nda->bind_param("s", $nda_application_number);
        $check_nda->execute();
        $check_nda->store_result();
        
        if ($check_nda->num_rows > 0) {
            $errors[] = "A candidate with this NDA application number already exists";
        }
        
        $check_nda->close();
    }
    
    // Handle profile picture upload if provided
    $profile_picture = 'default_candidate.jpg'; // Default image
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Only JPG, JPEG, and PNG images are allowed";
        }
        
        if ($file_size > $max_size) {
            $errors[] = "File size must not exceed 2MB";
        }
        
        if (empty($errors)) {
            $upload_dir = '../uploads/candidates/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            $filename = 'candidate_' . time() . '_' . uniqid() . '.' . $file_extension;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $profile_picture = 'uploads/candidates/' . $filename;
            } else {
                $errors[] = "Failed to upload image. Please try again.";
            }
        }
    }
    
    // If no errors, insert the candidate
    if (empty($errors)) {
        $conn = connectDB();
        
        $sql = "INSERT INTO candidates (first_name, surname, other_name, jamb_number, sex, 
                nda_application_number, jamb_score, state_id, service_choice_1, service_choice_2,
                profile_picture, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssiisssi", 
            $first_name, 
            $surname, 
            $other_name, 
            $jamb_number, 
            $sex, 
            $nda_application_number, 
            $jamb_score, 
            $state_id, 
            $service_choice_1, 
            $service_choice_2,
            $profile_picture,
            $_SESSION['user_id']
        );
        
        if ($stmt->execute()) {
            $candidate_id = $conn->insert_id;
            
            // Retrieve the generated chest number
            $chest_sql = "SELECT chest_number FROM candidates WHERE candidate_id = ?";
            $chest_stmt = $conn->prepare($chest_sql);
            $chest_stmt->bind_param("i", $candidate_id);
            $chest_stmt->execute();
            $chest_stmt->bind_result($chest_number);
            $chest_stmt->fetch();
            $chest_stmt->close();
            
            $success_message = "Candidate added successfully! Assigned chest number: <strong>" . $chest_number . "</strong>";
            
            // Clear form data after successful submission
            $first_name = $surname = $other_name = $jamb_number = $sex = $nda_application_number = '';
            $jamb_score = $state_id = $service_choice_1 = $service_choice_2 = '';
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Get all states for dropdown
function getAllStates() {
    $states = [];
    $conn = connectDB();
    
    $result = $conn->query("SELECT id, state_code, state_name FROM states ORDER BY state_name");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $states[] = $row;
        }
        $result->free();
    }
    
    $conn->close();
    return $states;
}

$states = getAllStates();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Candidate - NDA AFSB</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- Animate.css for animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        :root {
            --primary-green: #1C6B4C;
            --regimental-red: #A62828;
            --gold-accent: #F7D774;
            --jet-black: #1F1F1F;
            --soft-white: #F8F9FA;
            --ash-grey: #D9D9D9;
            --slate-blue: #2B3D54;
            --success-green: #2DC26C;
            --warning-yellow: #FFCB05;
        }
        
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--jet-black);
            padding-top: 76px; /* Account for fixed navbar */
        }
        
        /* Navbar Styling */
        .navbar {
            background-color: var(--primary-green);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            color: var(--soft-white) !important;
            font-weight: 600;
        }
        
        .navbar-brand img {
            height: 40px;
            margin-right: 10px;
        }
        
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255,255,255,0.85);
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .navbar-dark .navbar-nav .nav-link:hover,
        .navbar-dark .navbar-nav .nav-link:focus {
            color: var(--gold-accent);
        }
        
        .navbar-dark .navbar-nav .active > .nav-link {
            color: var(--gold-accent);
            font-weight: 600;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 0.25rem;
        }
        
        .dropdown-item.active, 
        .dropdown-item:active {
            background-color: var(--primary-green);
        }
        
        /* Card Styling */
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .card-header {
            background-color: var(--slate-blue);
            color: var(--soft-white);
            font-weight: 600;
            padding: 12px 20px;
            border-bottom: none;
        }
        
        .card-body {
            padding: 24px;
        }
        
        .card-footer {
            background-color: var(--soft-white);
            border-top: 1px solid var(--ash-grey);
            padding: 16px 24px;
        }
        
        /* Form Styling */
        .form-control {
            border: 1px solid var(--ash-grey);
            border-radius: 4px;
            padding: 10px 12px;
            height: auto;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(28, 107, 76, 0.25);
        }
        
        .form-group label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--slate-blue);
        }
        
        .required-field::after {
            content: "*";
            color: var(--regimental-red);
            margin-left: 4px;
        }
        
        .form-validate-error {
            display: none;
            color: var(--regimental-red);
            font-size: 0.85rem;
            margin-top: 5px;
        }
        
        .custom-file-label {
            padding: 10px 12px;
            height: auto;
        }
        
        /* Button Styling */
        .btn {
            font-weight: 500;
            padding: 8px 20px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-green);
            border-color: var(--primary-green);
        }
        
        .btn-primary:hover, 
        .btn-primary:focus {
            background-color: #155a3e;
            border-color: #155a3e;
        }
        
        .btn-secondary {
            background-color: var(--ash-grey);
            border-color: var(--ash-grey);
            color: var(--jet-black);
        }
        
        .btn-secondary:hover, 
        .btn-secondary:focus {
            background-color: #bfbfbf;
            border-color: #bfbfbf;
            color: var(--jet-black);
        }
        
        /* Alert Styling */
        .alert-success {
            background-color: rgba(45, 194, 108, 0.1);
            border-color: var(--success-green);
            color: #1a7343;
        }
        
        .alert-danger {
            background-color: rgba(166, 40, 40, 0.1);
            border-color: var(--regimental-red);
            color: var(--regimental-red);
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--ash-grey);
        }
        
        .page-title {
            color: var(--primary-green);
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .breadcrumb {
            background-color: transparent;
            padding: 0;
            margin-bottom: 0;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: ">";
        }
        
        .breadcrumb-item a {
            color: var(--slate-blue);
        }
        
        .breadcrumb-item.active {
            color: var(--primary-green);
        }
        
        /* Profile Preview */
        .profile-preview-container {
            position: relative;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin-bottom: 15px;
            border: 3px solid var(--gold-accent);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .profile-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-preview-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: rgba(31, 31, 31, 0.7);
            color: var(--soft-white);
            padding: 5px;
            font-size: 0.8rem;
            text-align: center;
        }
        
        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(31, 31, 31, 0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--ash-grey);
            border-top: 5px solid var(--primary-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Footer Styling */
        .footer {
            background-color: var(--slate-blue);
            color: var(--soft-white);
            padding-top: 40px;
            margin-top: 40px;
        }
        
        .footer-logo {
            height: 50px;
            margin-bottom: 15px;
        }
        
        .footer-title {
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--gold-accent);
        }
        
        .footer-links {
            list-style: none;
            padding-left: 0;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: var(--soft-white);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .footer-links a:hover {
            color: var(--gold-accent);
            text-decoration: none;
        }
        
        .footer-bottom {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 15px 0;
            margin-top: 30px;
            text-align: center;
        }
        
        /* Media Queries */
        @media (max-width: 767.98px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .breadcrumb {
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

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
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="candidatesDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-users mr-1"></i> Candidates
                        </a>
                        <div class="dropdown-menu" aria-labelledby="candidatesDropdown">
                            <a class="dropdown-item active" href="add_candidate.php">
                                <i class="fas fa-user-plus mr-1"></i> Add Candidate
                            </a>
                            <a class="dropdown-item" href="manage_candidates.php">
                                <i class="fas fa-list mr-1"></i> Manage Candidates
                            </a>
                            <a class="dropdown-item" href="import_candidates.php">
                                <i class="fas fa-file-import mr-1"></i> Import Candidates
                            </a>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="officersDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-user-shield mr-1"></i> Officers
                        </a>
                        <div class="dropdown-menu" aria-labelledby="officersDropdown">
                            <a class="dropdown-item" href="add_officer.php">
                                <i class="fas fa-user-plus mr-1"></i> Add Officer
                            </a>
                            <a class="dropdown-item" href="manage_officers.php">
                                <i class="fas fa-list mr-1"></i> Manage Officers
                            </a>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog mr-1"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-4">
        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">
                <i class="fas fa-user-plus mr-2"></i> Add New Candidate
            </h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="manage_candidates.php">Candidates</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Add Candidate</li>
                </ol>
            </nav>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $success_message; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeIn">
                <h5 class="alert-heading"><i class="fas fa-exclamation-triangle mr-2"></i> Please fix the following errors:</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" id="candidateForm">
            <div class="card animate__animated animate__fadeIn">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-user mr-2"></i> Candidate Personal Information</span>
                    <span class="badge badge-light">Step 1 of 2</span>
                </div>
                <div class="card-body">
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="first_name" class="required-field">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
                                    <div class="form-validate-error" id="first_name_error">Please enter a first name</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="surname" class="required-field">Surname</label>
                                    <input type="text" class="form-control" id="surname" name="surname" value="<?php echo htmlspecialchars($surname); ?>" required>
                                    <div class="form-validate-error" id="surname_error">Please enter a surname</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="other_name">Other Name</label>
                                    <input type="text" class="form-control" id="other_name" name="other_name" value="<?php echo htmlspecialchars($other_name); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="jamb_number" class="required-field">JAMB Number</label>
                                    <input type="text" class="form-control" id="jamb_number" name="jamb_number" value="<?php echo htmlspecialchars($jamb_number); ?>" placeholder="e.g., 30445986GF" required maxlength="10">
                                    <small class="form-text text-muted">Format: 8 digits followed by 2 uppercase letters</small>
                                    <div class="form-validate-error" id="jamb_number_error">Please enter a valid JAMB number (e.g., 30445986GF)</div>
                                </div>
                            </div>
                           
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="sex" class="required-field">Sex</label>
                                    <select class="form-control" id="sex" name="sex" required>
                                        <option value="">Select</option>
                                        <option value="M" <?php if($sex == 'M') echo 'selected'; ?>>Male</option>
                                        <option value="F" <?php if($sex == 'F') echo 'selected'; ?>>Female</option>
                                    </select>
                                    <div class="form-validate-error" id="sex_error">Please select a sex</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="nda_application_number" class="required-field">NDA Application Number</label>
                                    <input type="text" class="form-control" id="nda_application_number" name="nda_application_number" value="<?php echo htmlspecialchars($nda_application_number); ?>" required>
                                    <div class="form-validate-error" id="nda_application_number_error">Please enter an NDA application number</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="jamb_score" class="required-field">JAMB Score</label>
                                    <input type="number" class="form-control" id="jamb_score" name="jamb_score" value="<?php echo htmlspecialchars($jamb_score); ?>" min="0" max="400" required>
                                    <small class="form-text text-muted">Score between 0 and 400</small>
                                    <div class="form-validate-error" id="jamb_score_error">Please enter a valid JAMB score (0-400)</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="state_id" class="required-field">State</label>
                                    <select class="form-control" id="state_id" name="state_id" required>
                                        <option value="">Select State</option>
                                        <?php foreach ($states as $state): ?>
                                            <option value="<?php echo $state['id']; ?>" <?php if($state_id == $state['id']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($state['state_name']); ?> (<?php echo htmlspecialchars($state['state_code']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">This will determine the chest number</small>
                                    <div class="form-validate-error" id="state_id_error">Please select a state</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="profile_picture">Profile Picture</label>
                                    <div class="d-flex align-items-center">
                                        <div class="profile-preview-container mr-3" style="width: 80px; height: 80px;">
                                        <img id="preview-image" src="../img/default_candidate.jpg" class="profile-preview" alt="Profile">
                            <div class="profile-preview-overlay">Preview</div>
                        </div>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/jpg">
                            <label class="custom-file-label" for="profile_picture">Choose file</label>
                        </div>
                    </div>
                    <small class="form-text text-muted">Accepted formats: JPG, JPEG, PNG. Max size: 2MB</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card animate__animated animate__fadeIn animate__delay-1s">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list-alt mr-2"></i> Service Preferences</span>
        <span class="badge badge-light">Step 2 of 2</span>
    </div>
    <div class="card-body">
        <div class="form-section">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="service_choice_1" class="required-field">1st Service Choice</label>
                        <select class="form-control" id="service_choice_1" name="service_choice_1" required>
                            <option value="">Select Service</option>
                            <option value="Army" <?php if($service_choice_1 == 'Army') echo 'selected'; ?>>Army</option>
                            <option value="Navy" <?php if($service_choice_1 == 'Navy') echo 'selected'; ?>>Navy</option>
                            <option value="Air Force" <?php if($service_choice_1 == 'Air Force') echo 'selected'; ?>>Air Force</option>
                        </select>
                        <div class="form-validate-error" id="service_choice_1_error">Please select a service choice</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="service_choice_2">2nd Service Choice (Optional)</label>
                        <select class="form-control" id="service_choice_2" name="service_choice_2">
                            <option value="">Select Service</option>
                            <option value="Army" <?php if($service_choice_2 == 'Army') echo 'selected'; ?>>Army</option>
                            <option value="Navy" <?php if($service_choice_2 == 'Navy') echo 'selected'; ?>>Navy</option>
                            <option value="Air Force" <?php if($service_choice_2 == 'Air Force') echo 'selected'; ?>>Air Force</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer">
        <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='manage_candidates.php'">
                <i class="fas fa-arrow-left mr-1"></i> Cancel
            </button>
            <button type="submit" class="btn btn-primary" id="submitBtn">
                <i class="fas fa-save mr-1"></i> Save Candidate
            </button>
        </div>
    </div>
</div>
</form>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <img src="../img/nda-logo.png" alt="NDA Logo" class="footer-logo" onerror="this.src='../img/placeholder-logo.png'">
                <p>Nigerian Defence Academy Armed Forces Selection Board</p>
                <p><i class="fas fa-map-marker-alt mr-2"></i> Kaduna, Nigeria</p>
            </div>
            <div class="col-lg-4 mb-4">
                <h5 class="footer-title">Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt mr-2"></i> Dashboard</a></li>
                    <li><a href="manage_candidates.php"><i class="fas fa-users mr-2"></i> Candidates</a></li>
                    <li><a href="manage_officers.php"><i class="fas fa-user-shield mr-2"></i> Officers</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog mr-2"></i> Settings</a></li>
                </ul>
            </div>
            <div class="col-lg-4 mb-4">
                <h5 class="footer-title">Contact Information</h5>
                <ul class="footer-links">
                    <li><i class="fas fa-phone mr-2"></i> +234 (0) 123 456 7890</li>
                    <li><i class="fas fa-envelope mr-2"></i> info@nda.mil.ng</li>
                    <li><i class="fas fa-globe mr-2"></i> www.nda.edu.ng</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Nigerian Defence Academy. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- jQuery and Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
    $(document).ready(function() {
        // Show loading overlay on form submission
        $('#candidateForm').on('submit', function() {
            $('#loadingOverlay').addClass('active');
        });
        
        // Custom file input
        $('.custom-file-input').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').html(fileName);
            
            // Preview image
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#preview-image').attr('src', e.target.result);
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        // Form validation
        $('#candidateForm').on('submit', function(e) {
            let isValid = true;
            
            // Validate first name
            if ($('#first_name').val().trim() === '') {
                $('#first_name_error').show();
                isValid = false;
            } else {
                $('#first_name_error').hide();
            }
            
            // Validate surname
            if ($('#surname').val().trim() === '') {
                $('#surname_error').show();
                isValid = false;
            } else {
                $('#surname_error').hide();
            }
            
            // Validate JAMB number format
            const jambNumber = $('#jamb_number').val().trim();
            const jambPattern = /^\d{8}[A-Z]{2}$/;
            if (jambNumber === '' || !jambPattern.test(jambNumber)) {
                $('#jamb_number_error').show();
                isValid = false;
            } else {
                $('#jamb_number_error').hide();
            }
            
            // Validate sex
            if ($('#sex').val() === '') {
                $('#sex_error').show();
                isValid = false;
            } else {
                $('#sex_error').hide();
            }
            
            // Validate NDA application number
            if ($('#nda_application_number').val().trim() === '') {
                $('#nda_application_number_error').show();
                isValid = false;
            } else {
                $('#nda_application_number_error').hide();
            }
            
            // Validate JAMB score
            const jambScore = parseInt($('#jamb_score').val());
            if (isNaN(jambScore) || jambScore < 0 || jambScore > 400) {
                $('#jamb_score_error').show();
                isValid = false;
            } else {
                $('#jamb_score_error').hide();
            }
            
            // Validate state
            if ($('#state_id').val() === '') {
                $('#state_id_error').show();
                isValid = false;
            } else {
                $('#state_id_error').hide();
            }
            
            // Validate service choice 1
            if ($('#service_choice_1').val() === '') {
                $('#service_choice_1_error').show();
                isValid = false;
            } else {
                $('#service_choice_1_error').hide();
            }
            
            if (!isValid) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: $('.form-validate-error:visible').first().offset().top - 100
                }, 500);
            } else {
                $('#loadingOverlay').addClass('active');
            }
        });
        
        // Tooltips
        $('[data-toggle="tooltip"]').tooltip();
        
        // Hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    });
</script>
</body>
</html>