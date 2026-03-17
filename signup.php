<?php
session_start();

$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "inventory_tracking"; 

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$errors = [];

// Signup process
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['signup'])) {
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confpassword = $_POST['confpassword'];
    $userType = $_POST['userType'];

    // Validate input
    if (empty($full_name)) {
        $errors[] = 'Please enter your full name';
    }
    if (empty($username)) {
        $errors[] = 'Please enter a username';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email';
    }
    if (empty($password)) {
        $errors[] = 'Please enter a password';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    } elseif (!preg_match('/\d/', $password)) {
        $errors[] = 'Password must contain at least one number';
    } elseif (!preg_match('/[@$!%*?&]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }

    if ($password !== $confpassword) {
        $errors[] = 'Passwords do not match';
    }

    // If no errors, process the data (e.g., save to database)
    if (empty($errors)) {
        // Hash the password for security
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Prepare and bind
        $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, password, userType) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $full_name, $username, $email, $hashedPassword, $userType);

        // Execute the statement
        if ($stmt->execute()) {
            // Redirect to login page after successful signup
            header('Location: signup.php');
            exit();
        } else {
            echo 'Error: ' . $stmt->error;
        }

        // Close statement
        $stmt->close();
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Signup</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to right, #e0eafc, #cfdef3);
            color: #333;
            padding: 20px;
            font-size: 12px;
        }

        /* Signup container styling */
        .signup-container {
            width: 80%;
            max-width: 1100px;
            padding: 30px; /* Reduced padding */
            margin: 0 auto; /* Centering */
            margin-left: 300px; /* Adjust according to sidebar width (sidebar is 250px, add some space) */
            max-height: max-content;
            background-color: rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border-radius: 10px;
        }


        /* Title styling */
        .signup-container h1 {
            font-size: 26px;
            color: #333333;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Form group */
        .form-group {
            margin-bottom: 20px;
            text-align: left;
            font-size: 12px;
            font-weight: bold;
        }

        /* Input fields */
        .signup-container input, 
        .signup-container select {
            width: 100%;
            padding: 12px;
            border: 1px solid #dddddd;
            border-radius: 8px;
            font-size: 14px;
            background: #f9f9f9;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        /* Input focus effect */
        .signup-container input:focus, 
        .signup-container select:focus {
            border-color: #74ebd5;
            box-shadow: 0 0 8px rgba(116, 235, 213, 0.5);
            outline: none;
        }

        /* Buttons container */
        .form-group-buttons {
            display: flex;
            gap: 15px;
        }

        /* Submit button */
        .btn-primary {
            flex: 1;
            background: linear-gradient(to right, #74ebd5, #acb6e5);
            border: none;
            color: white;
            padding: 12px;
            font-weight: bold;
            font-size: 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        /* Submit button hover */
        .btn-primary:hover {
            background: linear-gradient(to right, #5cc9b5, #9ba7d6);
            transform: translateY(-2px);
        }

        /* Reset button */
        .btn-secondary {
            flex: 1;
            background: linear-gradient(to right, #74ebd5, #acb6e5);
            color: white;
            border: none;
            padding: 12px;
            font-weight: bold;
            font-size: 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        /* Reset button hover */
        .btn-secondary:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }

        /* Footer styling */
        .signup-container .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #777777;
        }

        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            color: #ffffff;
            background-color: #1c3b66;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background-color: #2980b9;
            box-shadow: 0 4px 8px rgba(41, 128, 185, 0.2);
            transform: translateY(-2px);
        }
        #sidebar {
            height: 100vh; /* Ensures full viewport height */
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #1d3557;
            color: white;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 1000; /* Ensures it stays above other content */
            border-radius: 12px;
        }

        #sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;  /* Rounded corners */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }


        #sidebar-header h2 {
        margin: 0;
        font-size: 12px;
        font-weight: bold;
        color: #a8dadc;
        }

        #sidebar ul {
        list-style-type: none;
        padding: 0;
        margin: 0;
        }

        #sidebar ul li {
        padding: 15px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        transition: background-color 0.3s ease;
        }

        #sidebar ul li:hover {
        background-color: #457b9d;
        }

        #sidebar ul li a {
        color: #f1faee;
        text-decoration: none;
        display: flex;
        align-items: center;
        font-size: 12px;
        font-weight: 500;
        transition: color 0.3s ease;
        }

        #sidebar ul li a:hover {
        color: #a8dadc;
        }

        #sidebar ul li i {
        font-size: 12px;
        margin-right: 10px;
        color: #a8dadc;
        transition: color 0.3s ease;
        }

        #sidebar ul li:hover i {
        color: white;
        }
    </style>
</head>
<body>
    <div id="sidebar">
        <div id="sidebar-header">
            <h2><i class="fa fa-bars"></i> Menu</h2>
        </div>
        <ul>
            <li><a href="adminIT.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="user_management.php"><i class="fas fa-users-cog"></i> User Management</a></li>
            <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>            
        </ul>
    </div>
    <div class="signup-container">
        <a href="adminIT.php" class="back-btn">Back</a>
        <h1>Create Account</h1>
        <form action="signup.php" method="post">
            <input type="hidden" name="signup" value="1">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" placeholder="Enter your full name" required>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <div class="form-group">
                <label for="confpassword">Confirm Password</label>
                <input type="password" id="confpassword" name="confpassword" placeholder="Enter your password again" required>
            </div>
            <div class="form-group">
                <label for="userType">User Type</label>
                <select id="userType" name="userType" required>
                    <option value="client">Client</option>
                    <option value="adminStaff">Admin Staff</option>
                    <option value="adminIT">Admin IT</option>
                </select>
            </div>
            <div class="form-group-buttons">
                <button type="reset" class="btn-secondary">Reset</button>
                <button type="submit" class="btn-primary">Signup</button>
            </div>
        </form>
        <div class="footer">
            &copy; 2024 EPIC OG SDN BHD. All rights reserved.
        </div>
    </div>
</body>
</html>