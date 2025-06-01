<?php
// Include database connection
require_once 'database_connection.php';

// Function to validate form data
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Initialize variables
$username = $password = $full_name = $email = $phone = $profile_image = $role = $status = "";
$message = "";

// Process form data when the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get form data
    $username = validateInput($_POST["username"]);
    $password = $_POST["password"];
    $full_name = validateInput($_POST["full_name"]);
    $email = validateInput($_POST["email"]);
    $phone = !empty($_POST["phone"]) ? validateInput($_POST["phone"]) : null;
    $profile_image = !empty($_POST["profile_image"]) ? validateInput($_POST["profile_image"]) : 'default_admin.png';
    $role = !empty($_POST["role"]) ? validateInput($_POST["role"]) : 'admin';
    $status = !empty($_POST["status"]) ? validateInput($_POST["status"]) : 'active';
    
    // Connect to database
    $conn = connectDB();
    
    // Check if the admins table exists, if not create it
    $tableCheck = $conn->query("SHOW TABLES LIKE 'admins'");
    if ($tableCheck->num_rows == 0) {
        // Create the admins table based on the provided structure
        $createTable = "CREATE TABLE IF NOT EXISTS admins (
            admin_id INT(11) AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            profile_image VARCHAR(255) DEFAULT 'default_admin.png',
            role VARCHAR(20) DEFAULT 'admin',
            last_login DATETIME DEFAULT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($createTable) === FALSE) {
            $message = "Error creating table: " . $conn->error;
        }
    }
    
    // Check if username already exists
    $checkUsername = $conn->prepare("SELECT admin_id FROM admins WHERE username = ?");
    $checkUsername->bind_param("s", $username);
    $checkUsername->execute();
    $checkUsername->store_result();
    
    if ($checkUsername->num_rows > 0) {
        $message = "Error: Username already exists. Please choose another username.";
    } else {
        $checkUsername->close();
        
        // Check if email already exists
        $checkEmail = $conn->prepare("SELECT admin_id FROM admins WHERE email = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        $checkEmail->store_result();
        
        if ($checkEmail->num_rows > 0) {
            $message = "Error: Email already exists. Please use another email address.";
        } else {
            $checkEmail->close();
            
            // Prepare an insert statement
            $sql = "INSERT INTO admins (username, password, full_name, email, phone, profile_image, role, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            if ($stmt = $conn->prepare($sql)) {
                // Set parameters and bind variables
                $stmt->bind_param("ssssssss", $param_username, $param_password, $param_full_name, 
                                $param_email, $param_phone, $param_profile_image, $param_role, $param_status);
                
                $param_username = $username;
                $param_password = hashPassword($password); // Hash the password
                $param_full_name = $full_name;
                $param_email = $email;
                $param_phone = $phone;
                $param_profile_image = $profile_image;
                $param_role = $role;
                $param_status = $status;
                
                // Attempt to execute the prepared statement
                if ($stmt->execute()) {
                    // Admin created successfully
                    $message = "Admin account created successfully!";
                    
                    // Clear form values after successful submission
                    $username = $password = $full_name = $email = $phone = $profile_image = $role = $status = "";
                } else {
                    $message = "Error: " . $stmt->error;
                }
                
                // Close statement
                $stmt->close();
            } else {
                $message = "Error preparing statement: " . $conn->error;
            }
        }
    }
    
    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin - NDA AFSB</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            margin-bottom: 20px;
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
        .card-header {
            background-color: #003366;
            color: white;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                Add New Admin
            </div>
            <div class="card-body">
                <?php if(!empty($message)): ?>
                    <div class="alert alert-<?php echo strpos($message, "successfully") !== false ? 'success' : 'danger'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo $username; ?>" required>
                        <small class="form-text text-muted">Username must be unique</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                        <small class="form-text text-muted">Choose a strong password</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo $full_name; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $email; ?>" required>
                        <small class="form-text text-muted">Email must be unique</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone (Optional)</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo $phone; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Profile Image (Optional)</label>
                        <input type="text" name="profile_image" class="form-control" value="<?php echo $profile_image; ?>" placeholder="default_admin.png">
                        <small class="form-text text-muted">Enter image filename or leave blank for default</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" class="form-control">
                            <option value="admin" <?php echo ($role == 'admin' || empty($role)) ? 'selected' : ''; ?>>Admin</option>
                            <option value="super_admin" <?php echo ($role == 'super_admin') ? 'selected' : ''; ?>>Super Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="active" <?php echo ($status == 'active' || empty($status)) ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($status == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group mb-0">
                        <button type="submit" class="btn btn-primary btn-block">Add Admin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>