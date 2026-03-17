<?php
include 'session.php';
include 'database.php';

// Check if user is adminStaff or adminIT
if (!isset($_SESSION['userType']) || !in_array($_SESSION['userType'], ['adminStaff', 'adminIT'])) {
    header("location: index.php");
    exit;
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Fetch distinct loadout locations from the product table
$query = "SELECT DISTINCT loadout_location FROM product
          WHERE action_notice_no IS NOT NULL AND 
          loadout_date IS NOT NULL 
          AND action_notice_no <> ''";
$result = mysqli_query($conn, $query);

// Generate navigation function (matching inventory_list.php)
function generateNav($username) {
    return <<<HTML
    <div id="sidebar">
        <div class="sidebar-header">
            <div class="logo-area">
                <i class="fa fa-cube"></i>
                <span class="brand">EPIC OG</span>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li class="active">
                    <a href="adminStaff.php">
                        <i class="fa fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="inventory_list.php">
                        <i class="fa fa-list"></i>
                        <span>Inventory List</span>
                    </a>
                </li>
                <li>
                    <a href="approval.php">
                        <i class="fa fa-tasks"></i>
                        <span>Request List</span>
                    </a>
                </li>
                <li class="logout">
                    <a href="logout.php">
                        <i class="fa fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
HTML;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Select Location | EPIC OG</title>
    <!-- Fonts and Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            height: 100%;
            width: 100%;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #d9eaef 0%, #e8f3f7 100%);
            color: #1e293b;
        }

        /* Modern Sidebar - Single Tone (matching inventory_list.php) */
        #sidebar {
            height: 100vh;
            width: 280px;
            position: fixed;
            top: 0;
            left: 0;
            background: #04548d;
            box-shadow: 4px 0 30px rgba(0, 98, 169, 0.3);
            z-index: 10;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 30px 24px;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-area i {
            font-size: 2rem;
            color: white;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 12px;
        }

        .brand {
            color: white;
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .sidebar-menu {
            flex: 1;
            padding: 0 16px;
            overflow-y: auto;
        }

        .sidebar-menu ul {
            list-style: none;
        }

        .sidebar-menu ul li {
            margin-bottom: 6px;
        }

        .sidebar-menu ul li a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 14px 20px;
            font-size: 0.95rem;
            font-weight: 500;
            border-radius: 12px;
            transition: all 0.2s ease;
            gap: 14px;
        }

        .sidebar-menu ul li a i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
        }

        .sidebar-menu ul li.active a {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar-menu ul li.active a i {
            color: white;
        }

        .sidebar-menu ul li:not(.logout):hover a {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-menu ul li:not(.logout):hover a i {
            color: white;
        }

        .sidebar-menu ul li.logout {
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }

        .sidebar-menu ul li.logout a {
            color: rgba(255, 255, 255, 0.7);
        }

        .sidebar-menu ul li.logout:hover a {
            background: rgba(239, 68, 68, 0.2);
            color: #fff;
        }

        /* Main Content */
        #main-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Header - Matching Sidebar Color */
        .header {
            background: #0062a9;
            border-radius: 24px;
            padding: 20px 30px;
            margin-bottom: 35px;
            box-shadow: 0 15px 35px rgba(0, 98, 169, 0.2);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: -80%;
            left: -5%;
            width: 350px;
            height: 350px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            pointer-events: none;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-container {
            background: white;
            padding: 8px 15px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .logo-wrapper img {
            height: 35px;
            width: auto;
            display: block;
        }

        .title-badge {
            font-size: 1rem;
            font-weight: 600;
            color: white;
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 18px;
            border-radius: 40px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
        }

        .header-right {
            text-align: right;
        }

        .welcome-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
            justify-content: flex-end;
        }

        .welcome-row h4 {
            font-size: 1rem;
            font-weight: 500;
            color: white;
        }

        .welcome-row span {
            color: white;
            font-size: 1.2rem;
        }

        .header-right p {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .clock-container {
            background: rgba(255, 255, 255, 0.12);
            padding: 10px 20px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
        }

        .clock {
            font-size: 0.9rem;
            color: white;
            font-weight: 600;
            text-align: center;
            line-height: 1.4;
        }

        /* Container - Styled like inventory_list.php container but with original content */
        .main-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .container {
            background: white;
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 15px 35px rgba(0, 98, 169, 0.08);
            border: 1px solid rgba(0, 98, 169, 0.1);
            margin-bottom: 30px;
        }

        .sorting-form {
            text-align: center;
            margin-bottom: 30px;
        }

        .sorting-form h2 {
            font-size: 28px;
            color: #1e293b;
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #0062a9;
            padding-bottom: 15px;
            font-weight: 700;
        }

        .container2 {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px -10px rgba(0, 98, 169, 0.15);
            border: 1px solid rgba(0, 98, 169, 0.15);
            position: relative;
            overflow: hidden;
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }

        .container2::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 200px;
            height: 200px;
            background: rgba(0, 98, 169, 0.03);
            border-radius: 50%;
            pointer-events: none;
        }

        h3 {
            color: #1e293b;
            font-size: 1.3rem;
            margin-bottom: 25px;
            font-weight: 600;
        }

        label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 10px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        select {
            padding: 14px 20px;
            width: 100%;
            max-width: 400px;
            font-size: 0.95rem;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 16px;
            margin: 0 auto 30px;
            display: block;
            outline: none;
            transition: all 0.3s ease;
            background-color: white;
            color: #1e293b;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%230062a9' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            background-size: 16px;
        }

        select:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 4px rgba(0, 98, 169, 0.1);
        }

        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #0062a9 0%, #0088cc 100%);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px -8px rgba(0, 98, 169, 0.4);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px -8px rgba(0, 98, 169, 0.5);
        }

        .back-button {
            display: inline-block;
            padding: 12px 24px;
            color: white;
            background: #f97316;
            border: none;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            box-shadow: 0 8px 15px rgba(249, 115, 22, 0.2);
        }

        .back-button:hover {
            background: #ea580c;
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(249, 115, 22, 0.3);
        }

        .back-button i {
            margin-right: 8px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            #sidebar {
                width: 240px;
            }
            #main-content {
                margin-left: 240px;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            #sidebar {
                transform: translateX(-100%);
            }
            #main-content {
                margin-left: 0;
            }
            .container2 {
                padding: 20px;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #e8f3f7;
        }

        ::-webkit-scrollbar-thumb {
            background: #0062a9;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #004d88;
        }
    </style>
    <script>
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const time = `${hours}:${minutes}:${seconds}`;
            
            const day = String(now.getDate()).padStart(2, '0');
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const year = now.getFullYear();
            const date = `${day}/${month}/${year}`;
            
            document.getElementById('clock').innerHTML = `${time}<br>${date}`;
        }
        
        setInterval(updateClock, 1000);
    </script>
</head>
<body>
    <?= generateNav($username) ?>
    
    <div id="main-content">
        <div class="main-container">
            <div class="header">
                <div class="header-content">
                    <div class="header-left">
                        <div class="logo-wrapper">
                            <div class="logo-container">
                                <img src="eog3.jpg" alt="EPIC OG Logo">
                            </div>
                            <div class="title-badge">Backload Items</div>
                        </div>
                    </div>
                    <div class="clock-container">
                        <div class="clock" id="clock"></div>
                    </div>
                </div>
            </div>

            <div class="container">
                <div class="sorting-form">
                    <h2>Select Location</h2>
                    <a href="adminStaff.php" class="back-button">
                        <i class="fa fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <!-- Container for Available Loadout Locations -->
                <div class="container2">
                    <h3>
                        <i class="fa fa-map-marker-alt" style="color: #0062a9; margin-right: 10px;"></i>
                        Available Loadout Locations
                    </h3>

                    <form action="receive_items.php" method="GET">
                        <label for="location">Select a location to proceed:</label>
                        <select name="location" id="location" required>
                            <option value="" disabled selected>-- Choose a location --</option>
                            <option value="TUA">TUA</option>
                            <option value="GUDP">GUDP</option>
                            <option value="PAA">PAA</option>
                            <option value="GUC">GUC</option>
                            <option value="GUA">GUA</option>
                            <option value="TEA">TEA</option>
                            <option value="GUB">GUB</option>
                            <option value="GUDC">GUDC</option>
                            <option value="TUB">TUB</option>
                            <option value="GUE">GUE</option>
                            <option value="GUF">GUF</option>
                        </select>
                        
                        <button type="submit" class="btn">
                            <i class="fa fa-arrow-right"></i> Proceed to Receive Items
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>