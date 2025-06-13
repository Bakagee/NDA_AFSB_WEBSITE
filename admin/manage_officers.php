<?php
// Start session
session_start();

// Include database connection
require_once '../database_connection.php';

// Check if user is logged in as admin
requireAdminRole();

// Initialize variables
$username = $password = $rank = $full_name = $email = $phone = $assigned_state = $profile_image = "";
$error_message = $success_message = "";

// Function to validate form data
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_officer'])) {
    // Initialize database connection
    $conn = connectDB();
    
    // Validate and sanitize input
    $username = validateInput($_POST['username'] ?? '');
    $password = validateInput($_POST['password'] ?? '');
    $rank = validateInput($_POST['rank'] ?? '');
    $full_name = validateInput($_POST['full_name'] ?? '');
    $email = validateInput($_POST['email'] ?? '');
    $phone = validateInput($_POST['phone'] ?? '');
    $assigned_state = validateInput($_POST['assigned_state'] ?? '');
    $profile_image = 'default_officer.png'; // Default profile image
    
    // Validate required fields
    if (empty($username) || empty($password) || empty($rank) || empty($full_name) || 
        empty($email) || empty($phone) || empty($assigned_state)) {
        $error_message = "All fields are required.";
    } else {
        // Check if username already exists
        $check_sql = "SELECT officer_id FROM officers WHERE username = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $error_message = "This username is already taken.";
        } else {
            // Prepare insert statement
            $sql = "INSERT INTO officers (username, password, rank, full_name, email, phone, 
                    assigned_state, created_by, profile_image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                // Set parameters and bind variables
                $stmt->bind_param("sssssssss", $param_username, $param_password, $param_rank, 
                                $param_full_name, $param_email, $param_phone, $param_assigned_state,
                                $param_created_by, $param_profile_image);
                
                $param_username = $username;
                $param_password = hashPassword($password);
                $param_rank = $rank;
                $param_full_name = $full_name;
                $param_email = $email;
                $param_phone = $phone;
                $param_assigned_state = $assigned_state;
                $param_created_by = $_SESSION['user_id'];
                $param_profile_image = $profile_image;
                
                // Attempt to execute the prepared statement
                if ($stmt->execute()) {
                    $success_message = "Officer account created successfully!";
                    // Clear form values after successful submission
                    $username = $password = $rank = $full_name = $email = $phone = $assigned_state = "";
                } else {
                    $error_message = "Something went wrong. Please try again later. Error: " . $stmt->error;
                }
                
                // Close statement
                $stmt->close();
            } else {
                $error_message = "Error preparing statement: " . $conn->error;
            }
        }
        $check_stmt->close();
    }
    
    // Close connection
    $conn->close();
}

// Get list of Nigerian states for dropdown
function getNigerianStates() {
    return [
        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 
        'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo', 
        'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 
        'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 
        'Yobe', 'Zamfara'
    ];
}

$states = getNigerianStates();

// Get list of officers
function getOfficers() {
    $conn = connectDB();
    $sql = "SELECT o.*, s.state_name, a.username as created_by_name 
            FROM officers o 
            LEFT JOIN states s ON o.assigned_state = s.state_name 
            LEFT JOIN officers a ON o.created_by = a.officer_id 
            ORDER BY o.created_at DESC";
    
    $result = $conn->query($sql);
    $officers = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $officers[] = $row;
        }
    }
    
    $conn->close();
    return $officers;
}

// Handle officer status update
if (isset($_POST['action']) && isset($_POST['officer_id'])) {
    $officer_id = $_POST['officer_id'];
    $action = $_POST['action'];
    
    $conn = connectDB();
    
    if ($action === 'deactivate') {
        $sql = "UPDATE officers SET is_active = 0 WHERE officer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $officer_id);
        if ($stmt->execute()) {
            $success_message = "Officer deactivated successfully.";
        } else {
            $error_message = "Error deactivating officer.";
        }
    } elseif ($action === 'activate') {
        $sql = "UPDATE officers SET is_active = 1 WHERE officer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $officer_id);
        if ($stmt->execute()) {
            $success_message = "Officer activated successfully.";
        } else {
            $error_message = "Error activating officer.";
        }
    }
    
    $conn->close();
}

$officers = getOfficers();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Officers - NDA AFSB Screening System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            margin-bottom: 30px;
            color: #003366;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .btn-primary {
            background-color: #003366;
            border-color: #003366;
        }
        .btn-primary:hover {
            background-color: #002244;
            border-color: #002244;
        }
        .alert {
            margin-bottom: 20px;
        }
        .officers-table {
            margin-top: 30px;
        }
        .officers-table th {
            background-color: #003366;
            color: white;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
        }
        .status-active {
            background-color: #28a745;
            color: white;
        }
        .status-inactive {
            background-color: #dc3545;
            color: white;
        }
        .action-buttons .btn {
            margin: 0 2px;
        }
        .nav-tabs {
            margin-bottom: 20px;
        }
        .tab-content {
            padding: 20px 0;
        }
        .back-button {
            position: fixed;
            top: 80px;
            left: 20px;
            background: var(--primary-color);
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

        @media (max-width: 768px) {
            .back-button {
                top: 70px;
                left: 15px;
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
        }
        .profile-image-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 3px solid #1C6B4C;
        }
        
        .profile-image-container {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <!-- Back Button -->
    <button class="back-button" onclick="window.location.href='dashboard.php'">
        <i class="fas fa-arrow-left"></i>
    </button>

    <div class="container">
        <h2>Manage Officers</h2>
        
        <?php if(!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if(!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" id="officerTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="list-tab" data-toggle="tab" href="#list" role="tab">Officers List</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="create-tab" data-toggle="tab" href="#create" role="tab">Create New Officer</a>
            </li>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content" id="officerTabsContent">
            <!-- Officers List Tab -->
            <div class="tab-pane fade show active" id="list" role="tabpanel">
                <div class="table-responsive officers-table">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Assigned State</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($officers as $officer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($officer['rank']); ?></td>
                                <td><?php echo htmlspecialchars($officer['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($officer['username']); ?></td>
                                <td><?php echo htmlspecialchars($officer['email']); ?></td>
                                <td><?php echo htmlspecialchars($officer['phone']); ?></td>
                                <td><?php echo htmlspecialchars($officer['assigned_state']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $officer['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $officer['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($officer['is_active']): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="officer_id" value="<?php echo $officer['officer_id']; ?>">
                                                <input type="hidden" name="action" value="deactivate">
                                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Are you sure you want to deactivate this officer?')">
                                                    <i class="fas fa-user-slash"></i> Deactivate
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="officer_id" value="<?php echo $officer['officer_id']; ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to activate this officer?')">
                                                    <i class="fas fa-user-check"></i> Activate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Create New Officer Tab -->
            <div class="tab-pane fade" id="create" role="tabpanel">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                    <div class="form-group text-center">
                        <div class="profile-image-container">
                            <img src="../img/default_officer.png" alt="Profile Preview" class="profile-image-preview" id="profile-preview">
                            <label class="profile-image-upload" title="Upload Profile Picture">
                                <i class="fas fa-camera"></i>
                                <input type="file" name="profile_image" accept="image/*" onchange="previewImage(this)">
                            </label>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" name="username" class="form-control" value="<?php echo $username; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Rank</label>
                                <input type="text" name="rank" class="form-control" value="<?php echo $rank; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo $full_name; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo $email; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo $phone; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Assigned State</label>
                        <select name="assigned_state" class="form-control">
                            <option value="">Select State</option>
                            <?php foreach ($states as $state): ?>
                            <option value="<?php echo $state; ?>" <?php echo ($assigned_state == $state) ? 'selected' : ''; ?>>
                                <?php echo $state; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="create_officer" class="btn btn-primary">Create Officer Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Activate tab based on URL hash
        $(document).ready(function() {
            var hash = window.location.hash;
            if (hash) {
                $('.nav-tabs a[href="' + hash + '"]').tab('show');
            }
            
            // Update hash in URL when tab changes
            $('.nav-tabs a').on('click', function (e) {
                $(this).tab('show');
                window.location.hash = this.hash;
            });
        });

        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('profile-preview').src = e.target.result;
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>