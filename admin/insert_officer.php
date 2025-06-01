<?php
// Include database connection
require_once '../database_connection.php';

// Function to validate form data
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Initialize variables
$username = $password = $rank = $full_name = $email = $phone = $assigned_state = "";
$username_err = $password_err = $rank_err = $full_name_err = $email_err = $assigned_state_err = "";
$success_message = $error_message = "";

// Process form data when the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        // Prepare a select statement to check if username exists
        $conn = connectDB();
        $sql = "SELECT officer_id FROM officers WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = trim($_POST["username"]);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = validateInput($_POST["username"]);
                }
            } else {
                $error_message = "Oops! Something went wrong. Please try again later.";
            }
            
            $stmt->close();
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate rank
    if (empty(trim($_POST["rank"]))) {
        $rank_err = "Please enter rank.";     
    } else {
        $rank = validateInput($_POST["rank"]);
    }
    
    // Validate full name
    if (empty(trim($_POST["full_name"]))) {
        $full_name_err = "Please enter full name.";     
    } else {
        $full_name = validateInput($_POST["full_name"]);
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter email.";     
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Prepare a select statement to check if email exists
        $conn = connectDB();
        $sql = "SELECT officer_id FROM officers WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST["email"]);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $email_err = "This email is already registered.";
                } else {
                    $email = validateInput($_POST["email"]);
                }
            } else {
                $error_message = "Oops! Something went wrong. Please try again later.";
            }
            
            $stmt->close();
        }
    }
    
    // Validate phone (optional)
    if (!empty(trim($_POST["phone"]))) {
        $phone = validateInput($_POST["phone"]);
    }
    
    // Validate assigned state
    if (empty(trim($_POST["assigned_state"]))) {
        $assigned_state_err = "Please select assigned state.";     
    } else {
        $assigned_state = validateInput($_POST["assigned_state"]);
    }
    
    // Check for errors before inserting into database
    if (empty($username_err) && empty($password_err) && empty($rank_err) && 
        empty($full_name_err) && empty($email_err) && empty($assigned_state_err)) {
        
        // Prepare an insert statement
        $sql = "INSERT INTO officers (username, password, rank, full_name, email, phone, assigned_state, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            // Set parameters and bind variables
            $stmt->bind_param("sssssssi", $param_username, $param_password, $param_rank, 
                              $param_full_name, $param_email, $param_phone, $param_assigned_state,
                              $param_created_by);
            
            $param_username = $username;
            $param_password = hashPassword($password); // Hash the password
            $param_rank = $rank;
            $param_full_name = $full_name;
            $param_email = $email;
            $param_phone = $phone;
            $param_assigned_state = $assigned_state;
            $param_created_by = 1; // Assuming admin ID 1 is creating this officer
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Officer created successfully
                $success_message = "Officer account created successfully!";
                
                // Clear form values after successful submission
                $username = $password = $rank = $full_name = $email = $phone = $assigned_state = "";
            } else {
                $error_message = "Something went wrong. Please try again later. Error: " . $stmt->error;
            }
            
            // Close statement
            $stmt->close();
        }
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Officer Account - NDA AFSB Screening System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
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
    </style>
</head>
<body>
    <div class="container">
        <h2>Create New Officer Account</h2>
        
        <?php if(!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if(!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                        <span class="invalid-feedback"><?php echo $username_err; ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $password; ?>">
                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Rank</label>
                        <input type="text" name="rank" class="form-control <?php echo (!empty($rank_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $rank; ?>">
                        <span class="invalid-feedback"><?php echo $rank_err; ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $full_name; ?>">
                        <span class="invalid-feedback"><?php echo $full_name_err; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Phone (Optional)</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo $phone; ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Assigned State</label>
                <select name="assigned_state" class="form-control <?php echo (!empty($assigned_state_err)) ? 'is-invalid' : ''; ?>">
                    <option value="">Select State</option>
                    <?php foreach($states as $state): ?>
                        <option value="<?php echo $state; ?>" <?php echo ($assigned_state == $state) ? 'selected' : ''; ?>>
                            <?php echo $state; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="invalid-feedback"><?php echo $assigned_state_err; ?></span>
            </div>
            
            <div class="form-group text-center">
                <input type="submit" class="btn btn-primary" value="Create Officer Account">
                <a href="manage_officers.php" class="btn btn-secondary ml-2">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
</qodoArtifact>