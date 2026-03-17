<!DOCTYPE html>
<html lang="en">
<head>
    <title>Inventory Tracking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <style>
    /* Full-screen background with gradient effect */
    body {
        margin: 0;
    padding: 0;
    background-size: cover;
    background-image: url('background7.dng');
    background-position: center;
    height: 100vh;
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
        font-family: 'Roboto', sans-serif; /* Modern font */
    }

    /* Form container styling */
    .login-container {
        background: #ffffff; /* White background */
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0px 8px 30px rgba(0, 0, 0, 0.2); /* Soft shadow */
        max-width: 400px;
        width: 100%;
        text-align: left;
        transition: transform 0.3s ease-in-out;
    }

    /* Hover effect on container */
    .login-container:hover {
        transform: scale(1.03); /* Slight zoom effect */
    }

    /* Title styling */
    h1 {
        font-size: 22px;
        margin-bottom: 20px;
        color: #333333;
        font-weight: 600;
        text-align: center;
    }

    /* Input field styling */
    .form-control {
        width: 100%;
        padding: 12px;
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 16px;
        transition: border 0.3s ease;
    }

    /* Input focus effect */
    .form-control:focus {
        border: 1px solid #74ebd5; /* Matching focus color */
        outline: none;
    }

    /* Button styling */
    .btn-primary {
        background: linear-gradient(135deg, #74ebd5 0%, #acb6e5 100%); /* Gradient button */
        border: none;
        color: white;
        padding: 8px;
        font-size: 18px;
        width: 35%;
        border-radius: 20px;
        cursor: pointer;
        transition: background 0.3s ease;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #67d3c0 0%, #9ea9cf 100%); /* Subtle hover change */
    }

    /* Text link styling */
    .password-text, .register-text {
        font-size: 14px;
        color: #666666;
        margin-top: 10px;
    }

    /* Logo styling */
    img.logo {
        width: 120px;
        margin-bottom: 20px;
    }

    /* Footer text styling */
    .copyright {
        margin-top: 20px;
        font-size: 12px;
        color: #888888;
    }

    /* Password container to hold input and icon */
    .password-container {
        position: relative;
        width: 100%;
    }

    .password-container input {
        width: 100%;
        padding-right: 13px; /* Add padding to make space for the icon */
    }

    .password-container i {
        position: absolute;
        top: 55%;
        right: 10px; /* Adjust distance from the right edge */
        transform: translateY(-50%);
        cursor: pointer;
        color:black;
        font-size: 12px;
    }

</style>
</head>
<body>
<?php
session_start(); // Start session

$_SESSION['loggedin'] = false;

require 'database.php'; // Ensure this is included after the session check

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    // Prepare and bind
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        // Check if the user is already logged in
        if ($user['login_status'] == 1) {
            echo "<div class='alert alert-danger'>This user is already logged in from another session.</div>";
        } else {
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Update login_status to 1 (logged in)
                $update_stmt = $conn->prepare("UPDATE users SET login_status = 1 WHERE email = ?");
                $update_stmt->bind_param("s", $email);
                $update_stmt->execute();

                // Set session variables
                $_SESSION['email'] = $user['email'];
                $_SESSION['userType'] = $user['userType'];
                $_SESSION['user_id'] = $user['user_id']; // Set user ID in session
                $_SESSION['loggedin'] = true;

                // Set a cookie if "Remember Me" is checked
                if ($remember) {
                    setcookie("email", $user['email'], time() + (86400 * 30), "/"); // 30 days
                }

                // Redirect user based on role
                session_write_close(); // Save session data
                switch ($user['userType']) {
                    case 'adminStaff':
                        header('Location: adminStaff.php');
                        exit();
                    case 'adminIT':
                        header('Location: adminIT.php');
                        exit();
                    case 'staff':
                        header('Location: staff_dashboard.php');
                        exit();
                    case 'client':
                        header('Location: customer_dashboard.php');
                        exit();
                    default:
                        echo "<div class='alert alert-danger'>Unknown user type.</div>";
                        break;
                }
            } else {
                echo "<div class='alert alert-danger'>Invalid password.</div>";
            }
        }
    } else {
        echo "<div class='alert alert-danger'>Email does not exist.</div>";
    }
    $stmt->close();
    $conn->close();
}
?>

<div class="login-container">
        <form class="login-form" method="post" action="">
            <div class="text-center">
                <img src="eog.png" alt="EPIC_OG Logo" class="logo">
            </div>
          
            <h1>Welcome Back</h1>
        
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control input-small" name="email" id="email" required>
            </div>
            <div class="password-container">
            <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control input-small" name="password" id="password" required>
                <i class="bi bi-eye" id="togglePassword"></i>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label" for="remember">Remember Me</label>
            </div>
            <div class="mb-3 text-center">
                <input type="submit" class="btn btn-primary" value="Login" name="login">
            </div>
            <p class="password-text"><a href="mailto:fathiahizzti@gmail.com">Forgot Password?</a></p>
            <p class="register-text">New Account? Please <a href="mailto:fathiahizzti@gmail.com">Contact Admin</a>.</p>
        </form>
        <div class="copyright">
            &copy; 2024 EPIC OG SDN BHD. All rights reserved.
        </div>
    </div>
    <script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
</script>    
</body>
</html>