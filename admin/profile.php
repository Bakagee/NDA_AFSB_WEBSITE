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

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        if (updateAdminProfile($admin_id, $full_name, $email, $phone)) {
            $success_message = "Profile updated successfully!";
            $admin = getAdminDetails($admin_id); // Refresh admin data
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match!";
        } elseif (!verifyAdminPassword($admin_id, $current_password)) {
            $error_message = "Current password is incorrect!";
        } elseif (updateAdminPassword($admin_id, $new_password)) {
            $success_message = "Password changed successfully!";
        } else {
            $error_message = "Failed to change password. Please try again.";
        }
    } elseif (isset($_FILES['profile_image'])) {
        $upload_result = handleProfileImageUpload($admin_id, $_FILES['profile_image']);
        if ($upload_result['success']) {
            $success_message = "Profile picture updated successfully!";
            $admin = getAdminDetails($admin_id); // Refresh admin data
        } else {
            $error_message = $upload_result['message'];
        }
    }
}

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
 * Update admin profile information
 */
function updateAdminProfile($admin_id, $full_name, $email, $phone) {
    $conn = connectDB();
    $stmt = $conn->prepare("UPDATE admins SET full_name = ?, email = ?, phone = ? WHERE admin_id = ?");
    $stmt->bind_param("sssi", $full_name, $email, $phone, $admin_id);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $result;
}

/**
 * Verify admin's current password
 */
function verifyAdminPassword($admin_id, $current_password) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT password FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return password_verify($current_password, $admin['password']);
}

/**
 * Update admin's password
 */
function updateAdminPassword($admin_id, $new_password) {
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $conn = connectDB();
    $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
    $stmt->bind_param("si", $hashed_password, $admin_id);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $result;
}

/**
 * Handle profile image upload
 */
function handleProfileImageUpload($admin_id, $file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG and GIF are allowed.'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File size too large. Maximum size is 5MB.'];
    }
    
    $upload_dir = '../img/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'admin_' . $admin_id . '_' . time() . '.' . $file_extension;
    $target_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $conn = connectDB();
        $stmt = $conn->prepare("UPDATE admins SET profile_image = ? WHERE admin_id = ?");
        $stmt->bind_param("si", $new_filename, $admin_id);
        $result = $stmt->execute();
        $stmt->close();
        $conn->close();
        
        if ($result) {
            return ['success' => true, 'message' => 'Profile image updated successfully.'];
        }
    }
    
    return ['success' => false, 'message' => 'Failed to upload image. Please try again.'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - NDA AFSB</title>
    
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
        
        .profile-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 5px solid #1C6B4C;
        }
        
        .profile-image-container {
            position: relative;
            display: inline-block;
        }
        
        .profile-image-upload {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: #1C6B4C;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .profile-image-upload:hover {
            background: #15573A;
        }
        
        .profile-image-upload input {
            display: none;
        }
        
        .form-group label {
            font-weight: 500;
            color: #333;
        }
        
        .btn-primary {
            background-color: #1C6B4C;
            border-color: #1C6B4C;
        }
        
        .btn-primary:hover {
            background-color: #15573A;
            border-color: #15573A;
        }
        
        .alert {
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .back-button {
            position: fixed;
            top: 80px;
            left: 20px;
            background: #1C6B4C;
            color: white;
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

        /* Enhanced Responsive Styles */
        @media (max-width: 768px) {
            .back-button {
                top: 70px;
                left: 15px;
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }

            .profile-container {
                padding: 20px;
                margin: 10px;
            }

            .profile-image {
                width: 120px;
                height: 120px;
            }

            .profile-image-upload {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
            }

            .profile-header h2 {
                font-size: 1.5rem;
            }

            .profile-header p {
                font-size: 0.9rem;
            }

            .form-group label {
                font-size: 0.9rem;
            }

            .form-control {
                font-size: 0.9rem;
            }

            .btn-primary {
                width: 100%;
                margin-bottom: 15px;
            }

            .col-md-6 {
                margin-bottom: 30px;
            }
        }

        @media (max-width: 576px) {
            .navbar-brand img {
                height: 25px;
            }

            .navbar-brand {
                font-size: 1rem;
            }

            .nav-link {
                font-size: 0.9rem;
            }

            .profile-image {
                width: 100px;
                height: 100px;
            }

            .profile-image-upload {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }

            .profile-header h2 {
                font-size: 1.3rem;
            }

            .profile-header p {
                font-size: 0.8rem;
            }

            .form-group label {
                font-size: 0.8rem;
            }

            .form-control {
                font-size: 0.8rem;
                padding: 0.375rem 0.75rem;
            }

            .btn-primary {
                font-size: 0.9rem;
                padding: 0.375rem 0.75rem;
            }

            .alert {
                font-size: 0.9rem;
                padding: 0.75rem 1.25rem;
            }
        }

        /* Print Styles */
        @media print {
            .back-button,
            .navbar,
            .profile-image-upload,
            .btn-primary {
                display: none !important;
            }

            .profile-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .profile-image {
                border: 2px solid #1C6B4C;
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
                        <a class="nav-link dropdown-toggle active" href="#" id="profileDropdown" role="button" data-toggle="dropdown">
                            <img src="../img/<?php echo !empty($admin['profile_image']) ? htmlspecialchars($admin['profile_image']) : 'default_admin.png'; ?>" alt="Profile" class="rounded-circle mr-2" width="30" height="30">
                            <?php echo htmlspecialchars($admin['username']); ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item active" href="profile.php">Profile</a>
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
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-image-container">
                    <img src="../img/<?php echo !empty($admin['profile_image']) ? htmlspecialchars($admin['profile_image']) : 'default_admin.png'; ?>" alt="Profile" class="profile-image">
                    <label class="profile-image-upload" title="Change Profile Picture">
                        <i class="fas fa-camera"></i>
                        <input type="file" name="profile_image" form="profile-image-form" accept="image/*">
                    </label>
                </div>
                <h2><?php echo htmlspecialchars($admin['full_name']); ?></h2>
                <p class="text-muted"><?php echo htmlspecialchars($admin['username']); ?></p>
            </div>

            <form id="profile-image-form" action="" method="POST" enctype="multipart/form-data" style="display: none;">
                <input type="file" name="profile_image" onchange="this.form.submit()">
            </form>

            <div class="row">
                <div class="col-md-6">
                    <h4 class="mb-4">Profile Information</h4>
                    <form action="" method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($admin['phone']); ?>" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
                
                <div class="col-md-6">
                    <h4 class="mb-4">Change Password</h4>
                    <form action="" method="POST">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Auto-submit profile image form when file is selected
        document.querySelector('input[type="file"]').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html> 