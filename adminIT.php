<?php
// PHP section for database connection and CRUD operations
$host = 'localhost';        
$db_name = 'inventory_tracking'; 
$username = 'root';         
$password = '';             

// Create database connection
$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Corrected SQL queries
$query_total_users = "SELECT COUNT(*) AS total FROM users";
$query_adminIT = "SELECT COUNT(*) AS total FROM users WHERE userType = 'adminIT'";
$query_adminStaff = "SELECT COUNT(*) AS total FROM users WHERE userType = 'adminStaff'";
$query_users = "SELECT COUNT(*) AS total FROM users WHERE userType = 'client'";

// Execute queries and handle errors
$total_users_result = $conn->query($query_total_users);
if (!$total_users_result) {
    die("Error executing total users query: " . $conn->error);
}

$admin_it_result = $conn->query($query_adminIT);
if (!$admin_it_result) {
    die("Error executing admin IT query: " . $conn->error);
}

$admin_staff_result = $conn->query($query_adminStaff);
if (!$admin_staff_result) {
    die("Error executing admin Staff query: " . $conn->error);
}

$users_result = $conn->query($query_users);
if (!$users_result) {
    die("Error executing Users query: " . $conn->error);
}

// Fetch results
$total_users = $total_users_result->fetch_assoc()['total'];
$admin_it = $admin_it_result->fetch_assoc()['total'];
$admin_staff = $admin_staff_result->fetch_assoc()['total'];
$users = $users_result->fetch_assoc()['total'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Admin IT Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to right, #e0eafc, #cfdef3);
            color: #333;
            padding: 20px;
            position: relative; /* For absolute positioning of back button */
            font-size: 0.9em; /* Smaller font size */
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
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

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 20px;
            background-color: white;
            border-bottom: 1px solid #ddd;
            border-radius: 12px;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-content img {
            height: 50px;
        }

        .header-content h1 {
            font-size: 24px;
            margin: 0;
        }

        .text-content {
            margin-left: 15px;
        }

        .text-content h4 {
            margin: 0;
            font-size: 16px;
        }

        .clock-container {
            margin-left: auto;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 80px;
            height: 80px;
            position: relative;
        }

        #main-content {
            margin-left: 250px; /* Align with sidebar */
            padding: 20px;
            min-height: calc(100vh - 50px); /* Ensure content takes full height */
            display: flex;
            flex-direction: column;
        }

        .container{
            max-width: 2000px;
            margin: 40px auto;
            padding: 20px;
            background-color:white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border-radius: 12px;
        }
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            #sidebar {
                width: 200px;
            }
            .header {
                width: calc(100% - 200px);
                left: 200px;
            }
            .content {
                margin-left: 200px;
            }
        }
        /* Dashboard Cards */
        .card {
            background: white;
            border: none;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            display:flex;
            justify-content: space-between;
        }

        .card-header {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .card-body p {
            font-size: 22px;
            font-weight: bold;
            color: #007bff;
        }

        .btn {
            padding: 10px 15px;
            font-size: 16px;
            border-radius: 5px;
            transition: 0.3s ease;
        }

        .btn:hover {
            opacity: 0.8;
        }

    </style>
    </head>
<body>
    <!-- Sidebar -->
    <div id="sidebar">
        <div id="sidebar-header">
            <h2><i class="fa fa-bars"></i> Menu</h2>
        </div>
            <ul>
                <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="user_management.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>            
            </ul>
        </div>
        
    </div>

    <!-- Main Content -->
    <div id="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <img src="eog.png" alt="EPIC_OG Logo">
                <h1>Admin IT Dashboard</h1>
                <div class="text-content">
                    <h4>Hi!</h4>
                </div>
                <div class="clock-container">
                    <div class="clock" id="clock"></div>
                </div>
            </div>
        </div>

            <!-- Dashboard Overview -->
        <div class="container mt-4">
            <h3 class="text-center mb-4">Dashboard Overview</h3>

            <div class="text-end mb-3">
                <a href="signup.php" class="btn btn-success"><i class="fas fa-user-plus"></i> Create New Account</a>
            </div>

            <div class="row g-4">
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 text-center p-3 rounded-4 bg-primary text-black">
                        <h4 class="fw-bold">Total Users</h4>
                        <p class="fs-3 mb-0"> <?php echo $total_users; ?> </p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card shadow-sm border-0 text-center p-3 rounded-4 bg-success text-black">
                        <h4 class="fw-bold">Admin IT</h4>
                        <p class="fs-3 mb-0"> <?php echo $admin_it; ?> </p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card shadow-sm border-0 text-center p-3 rounded-4 bg-warning text-black">
                        <h4 class="fw-bold">Admin Staff</h4>
                        <p class="fs-3 mb-0"> <?php echo $admin_staff; ?> </p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card shadow-sm border-0 text-center p-3 rounded-4 bg-danger text-black">
                        <h4 class="fw-bold">Users</h4>
                        <p class="fs-3 mb-0"> <?php echo $users; ?> </p>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>

    <!-- JavaScript for Clock -->
    <script>
        function updateClock() {
            const now = new Date();
            document.getElementById("clock").innerText = now.toLocaleTimeString();
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>

</body>
</html>
