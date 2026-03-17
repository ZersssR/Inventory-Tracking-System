<?php
// Include session and database connection
session_start();
include 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get logged-in user ID from session
$user_id = $_SESSION['user_id'];

// Fetch user details
$user_sql = "SELECT full_name, username, email FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
$full_name = $user_data['full_name'] ?? 'User';

// Fetch distinct loadout locations only if action_notice_no, loadout_date, and loadout_location exist
$query = "SELECT DISTINCT loadout_location FROM product 
          WHERE action_notice_no IS NOT NULL AND action_notice_no != ''
          AND loadout_date IS NOT NULL AND loadout_date != ''
          AND loadout_location IS NOT NULL AND loadout_location != ''";

$result = $conn->query($query);

// Get current date for display
$current_date = date('d/m/Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Update Location | EPIC OG</title>
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

        /* Modern Sidebar - Single Tone */
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

        /* Content Card */
        .content-card {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 98, 169, 0.08);
            border: 1px solid rgba(0, 98, 169, 0.1);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-title i {
            font-size: 1.5rem;
            color: #0062a9;
            background: #e8f3f7;
            padding: 12px;
            border-radius: 14px;
        }

        .card-title h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
        }

        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: #0062a9;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.95rem;
            border: 1px solid #0062a9;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #0062a9;
            color: white;
            transform: translateX(-5px);
        }

        /* Location Selection Form */
        .location-form {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 25px;
            padding: 20px 0;
        }

        .form-group {
            width: 100%;
            max-width: 500px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #1e293b;
            font-size: 1rem;
        }

        .form-group select {
            width: 100%;
            padding: 15px 20px;
            font-size: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            background: white;
            color: #1e293b;
            cursor: pointer;
            outline: none;
            transition: all 0.3s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%230062a9' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-size: 16px;
        }

        .form-group select:hover {
            border-color: #0062a9;
        }

        .form-group select:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 3px rgba(0, 98, 169, 0.1);
        }

        .form-group select option {
            padding: 12px;
        }

        .submit-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 40px;
            background: #0062a9;
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 98, 169, 0.3);
        }

        .submit-btn:hover {
            background: #004d88;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 98, 169, 0.4);
        }

        .submit-btn i {
            font-size: 1.1rem;
        }

        /* No Data Message */
        .no-data {
            text-align: center;
            padding: 50px 20px;
            color: #64748b;
        }

        .no-data i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 15px;
        }

        .no-data p {
            font-size: 1rem;
            margin-bottom: 5px;
        }

        .no-data small {
            color: #94a3b8;
            font-size: 0.9rem;
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

        /* Responsive */
        @media (max-width: 992px) {
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
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
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
    <!-- Sidebar -->
    <div id="sidebar">
        <div class="sidebar-header">
            <div class="logo-area">
                <i class="fa fa-cube"></i>
                <span class="brand">EPIC OG</span>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li>
                    <a href="customer_dashboard.php">
                        <i class="fa fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="tecrf.php">
                        <i class="fa fa-file-signature"></i>
                        <span>Create TECRF</span>
                    </a>
                </li>
                <li>
                    <a href="list_tecrf.php">
                        <i class="fa fa-list"></i>
                        <span>My Requests</span>
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

    <!-- Main Content -->
    <div id="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <div class="logo-wrapper">
                        <div class="logo-container">
                            <img src="eog3.jpg" alt="EPIC OG Logo">
                        </div>
                        <div class="title-badge">Update Location</div>
                    </div>
                </div>
                <div class="header-right">
                    <div class="welcome-row">
                        <h4>Welcome back, <?php echo htmlspecialchars($full_name); ?></h4>
                        <span>👋</span>
                    </div>
                    <p>EPIC OG Inventory Tracking System</p>
                </div>
                <div class="clock-container">
                    <div class="clock" id="clock"></div>
                </div>
            </div>
        </div>

        <!-- Content Card -->
        <div class="content-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-location-dot"></i>
                    <h2>Choose Your Location</h2>
                </div>
                <div>
                    <a href="customer_dashboard.php" class="back-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if ($result->num_rows > 0) : ?>
                <form action="updateselectedLocation.php" method="get" class="location-form">
                    <div class="form-group">
                        <label for="location">
                            <i class="fa-solid fa-map-pin" style="color: #0062a9; margin-right: 8px;"></i>
                            Select a location:
                        </label>
                        <select name="location" id="location" required>
                            <option value="" disabled selected>-- Choose a location --</option>
                            <?php while ($row = $result->fetch_assoc()) : ?>
                                <option value="<?php echo htmlspecialchars($row['loadout_location']); ?>">
                                    <?php echo htmlspecialchars($row['loadout_location']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fa-solid fa-arrow-right"></i>
                        Proceed to Next Step
                    </button>
                </form>
            <?php else : ?>
                <div class="no-data">
                    <i class="fa-regular fa-map"></i>
                    <p>No locations available</p>
                    <small>There are no locations with complete loadout information.</small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>