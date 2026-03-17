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
$username = $user_data['username'] ?? '';
$email = $user_data['email'] ?? '';

// Fetch total requests count for this user
$total_sql = "SELECT COUNT(*) as total FROM tecrf WHERE user_id = ?";
$stmt = $conn->prepare($total_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_result = $stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_requests = $total_row['total'] ?? 0;

// Fetch counts by status
$status_sql = "SELECT 
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM tecrf WHERE user_id = ?";
$stmt = $conn->prepare($status_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$status_result = $stmt->get_result();
$status_counts = $status_result->fetch_assoc();

$pending = $status_counts['pending'] ?? 0;
$in_progress = $status_counts['in_progress'] ?? 0;
$completed = $status_counts['completed'] ?? 0;
$approved = $status_counts['approved'] ?? 0;
$rejected = $status_counts['rejected'] ?? 0;

// Fetch assigned items count (items where items_assigned = 1)
$assigned_sql = "SELECT COUNT(*) as total FROM tecrf 
                 WHERE user_id = ? AND items_assigned = 1";
$stmt = $conn->prepare($assigned_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$assigned_result = $stmt->get_result();
$assigned_row = $assigned_result->fetch_assoc();
$assigned_items = $assigned_row['total'] ?? 0;

// Fetch recent requests (last 5)
$recent_sql = "SELECT tecrf_id, reference_number, date, status, created_at 
               FROM tecrf 
               WHERE user_id = ? 
               ORDER BY created_at DESC 
               LIMIT 5";
$stmt = $conn->prepare($recent_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_requests = $stmt->get_result();

// Get current date for display
$current_date = date('d/m/Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Customer Dashboard | EPIC OG</title>
    <!-- Fonts and Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* New container for logo with white background */
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

        /* Stats Grid - Now with 2 columns after removing assigned items card */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 98, 169, 0.08);
            border: 1px solid rgba(0, 98, 169, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 98, 169, 0.15);
            border-color: #0062a9;
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .stat-header h3 {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            background: #0062a9;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.2);
        }

        .stat-icon i {
            font-size: 1.3rem;
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Status Badges - Fixed border issue */
        .status-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
            pointer-events: none; /* Prevents clicking on badges inside the card */
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            background: white;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 2px solid transparent;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
            flex: 1;
            min-width: 120px;
        }

        .status-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Individual status colors - Using border-color instead of border */
        .status-badge.pending-badge {
            border-color: #f97316;
            background: linear-gradient(135deg, #fff6e9 0%, #fff 100%);
        }

        .status-badge.pending-badge i {
            color: #f97316;
        }

        .status-badge.pending-badge .badge-count {
            background: rgba(249, 115, 22, 0.1);
            color: #f97316;
        }

        .status-badge.progress-badge {
            border-color: #0062a9;
            background: linear-gradient(135deg, #e6f0ff 0%, #fff 100%);
        }

        .status-badge.progress-badge i {
            color: #0062a9;
        }

        .status-badge.progress-badge .badge-count {
            background: rgba(0, 98, 169, 0.1);
            color: #0062a9;
        }

        .status-badge.completed-badge {
            border-color: #22c55e;
            background: linear-gradient(135deg, #e8f7ed 0%, #fff 100%);
        }

        .status-badge.completed-badge i {
            color: #22c55e;
        }

        .status-badge.completed-badge .badge-count {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }

        .badge-count {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-left: auto;
        }

        /* Action Cards Grid - Updated to 2 columns after removing location card */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 98, 169, 0.1);
            box-shadow: 0 10px 30px rgba(0, 98, 169, 0.08);
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 98, 169, 0.15);
            border-color: #0062a9;
        }

        .action-icon {
            width: 70px;
            height: 70px;
            background: #0062a9;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            box-shadow: 0 10px 20px rgba(0, 98, 169, 0.2);
        }

        .action-icon i {
            font-size: 2rem;
        }

        .action-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .action-desc {
            font-size: 0.9rem;
            color: #64748b;
            line-height: 1.5;
        }

        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 98, 169, 0.05);
            border: 1px solid rgba(0, 98, 169, 0.1);
            margin-bottom: 30px;
        }

        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .chart-header h3 {
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-header i {
            color: #0062a9;
            font-size: 1.2rem;
        }

        /* Activity Grid - Now with single column after removing assigned items section */
        .activity-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
        }

        .activity-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 98, 169, 0.05);
            border: 1px solid rgba(0, 98, 169, 0.1);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            transition: all 0.3s ease;
        }

        .activity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 98, 169, 0.15);
            border-color: #0062a9;
        }

        .activity-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e8f3f7;
        }

        .activity-header i {
            color: #0062a9;
            font-size: 1.3rem;
            background: #e8f3f7;
            padding: 10px;
            border-radius: 12px;
        }

        .activity-header h4 {
            color: #1e293b;
            font-size: 1rem;
            font-weight: 600;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #e8f3f7;
            pointer-events: none; /* Prevents clicking on individual items */
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-info {
            flex: 1;
        }

        .activity-title {
            color: #1e293b;
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .activity-meta {
            color: #64748b;
            font-size: 0.75rem;
            display: flex;
            gap: 15px;
        }

        .activity-badge {
            padding: 6px 14px;
            background: #e8f3f7;
            border-radius: 30px;
            color: #0062a9;
            font-size: 0.7rem;
            font-weight: 600;
            border: 2px solid transparent;
        }

        /* Status Colors */
        .status-pending { color: #f97316; }
        .status-progress { color: #0062a9; }
        .status-completed { color: #22c55e; }
        .status-approved { color: #8b5cf6; }
        .status-rejected { color: #ef4444; }

        /* Chart canvas */
        canvas {
            max-height: 280px;
            width: 100% !important;
        }

        /* Click indicator */
        .click-indicator {
            position: absolute;
            bottom: 10px;
            right: 10px;
            font-size: 0.7rem;
            color: #0062a9;
            opacity: 0.5;
            transition: opacity 0.2s ease;
        }

        .stat-card:hover .click-indicator,
        .activity-card:hover .click-indicator {
            opacity: 1;
        }

        /* Recent activity feed styles */
        .recent-activity-feed {
            max-height: 200px;
            overflow-y: auto;
            padding-right: 5px;
            pointer-events: none; /* Prevents clicking on individual items */
        }

        .recent-activity-feed::-webkit-scrollbar {
            width: 4px;
        }

        .recent-activity-feed::-webkit-scrollbar-track {
            background: #e8f3f7;
        }

        .recent-activity-feed::-webkit-scrollbar-thumb {
            background: #0062a9;
            border-radius: 4px;
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 1200px) {
            .action-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

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
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .action-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
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

        .no-data {
            text-align: center;
            color: #64748b;
            padding: 20px;
            font-style: italic;
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
    <div id="main-content">
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <div class="logo-wrapper">
                        <div class="logo-container">
                            <img src="eog3.jpg" alt="EPIC OG Logo">
                        </div>
                        <div class="title-badge">Customer Dashboard</div>
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

        <!-- Stats Cards -->
        <div class="stats-grid">
            <!-- My TECRF Requests Card - Clickable -->
            <a href="list_tecrf.php" class="stat-card">
                <div class="stat-header">
                    <h3>My TECRF Requests</h3>
                    <div class="stat-icon">
                        <i class="fa-solid fa-file-signature"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $total_requests; ?></div>
                <div class="stat-label">Total Requests</div>
                <div class="status-badges">
                    <div class="status-badge pending-badge">
                        <i class="fa-solid fa-clock"></i>
                        Pending 
                        <span class="badge-count"><?php echo $pending; ?></span>
                    </div>
                    <div class="status-badge progress-badge">
                        <i class="fa-solid fa-spinner"></i>
                        In Progress 
                        <span class="badge-count"><?php echo $in_progress; ?></span>
                    </div>
                    <div class="status-badge completed-badge">
                        <i class="fa-solid fa-check-double"></i>
                        Completed 
                        <span class="badge-count"><?php echo $completed; ?></span>
                    </div>
                </div>
                <div class="click-indicator">
                    <i class="fa-solid fa-arrow-right"></i> View all requests
                </div>
            </a>

            <!-- Recent Activity Card - Clickable -->
            <a href="list_tecrf.php" class="stat-card">
                <div class="stat-header">
                    <h3>Recent Activity</h3>
                    <div class="stat-icon">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                </div>
                <div class="recent-activity-feed">
                    <?php
                    // Fetch recent activity (last 5 status changes)
                    $activity_sql = "SELECT reference_number, status, updated_at 
                                     FROM tecrf 
                                     WHERE user_id = ? 
                                     ORDER BY updated_at DESC 
                                     LIMIT 5";
                    $stmt = $conn->prepare($activity_sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $activity_result = $stmt->get_result();
                    
                    if ($activity_result->num_rows > 0):
                        while($activity = $activity_result->fetch_assoc()):
                            $status_color = '';
                            $status_icon = '';
                            
                            if($activity['status'] == 'Pending') {
                                $status_color = '#f97316';
                                $status_icon = 'fa-clock';
                            } elseif($activity['status'] == 'In Progress') {
                                $status_color = '#0062a9';
                                $status_icon = 'fa-spinner';
                            } elseif($activity['status'] == 'Completed') {
                                $status_color = '#22c55e';
                                $status_icon = 'fa-check-circle';
                            } elseif($activity['status'] == 'Approved') {
                                $status_color = '#8b5cf6';
                                $status_icon = 'fa-check-double';
                            } elseif($activity['status'] == 'Rejected') {
                                $status_color = '#ef4444';
                                $status_icon = 'fa-times-circle';
                            }
                    ?>
                            <div style="display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #e8f3f7;">
                                <div style="width: 32px; height: 32px; background: <?php echo $status_color; ?>20; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid <?php echo $status_icon; ?>" style="color: <?php echo $status_color; ?>; font-size: 0.9rem;"></i>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-size: 0.85rem; font-weight: 500; color: #1e293b;">
                                        <?php echo htmlspecialchars($activity['reference_number']); ?>
                                    </div>
                                    <div style="font-size: 0.7rem; color: #64748b;">
                                        <?php echo date('d/m/Y H:i', strtotime($activity['updated_at'])); ?>
                                    </div>
                                </div>
                                <span style="padding: 4px 8px; background: <?php echo $status_color; ?>10; color: <?php echo $status_color; ?>; border-radius: 20px; font-size: 0.7rem; font-weight: 600;">
                                    <?php echo $activity['status']; ?>
                                </span>
                            </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <div style="text-align: center; color: #64748b; padding: 20px;">
                            <i class="fa-solid fa-inbox" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                            <p>No recent activity</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="click-indicator">
                    <i class="fa-solid fa-arrow-right"></i> View all activity
                </div>
            </a>
        </div>

        <!-- Action Cards - Update Location removed, now only 2 cards -->
        <div class="action-grid">
            <a href="tecrf.php" class="action-card">
                <div class="action-icon">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
                <div class="action-title">Create TECRF</div>
                <div class="action-desc">Submit new equipment request</div>
            </a>

            <a href="list_tecrf.php" class="action-card">
                <div class="action-icon">
                    <i class="fa-solid fa-list"></i>
                </div>
                <div class="action-title">My Requests</div>
                <div class="action-desc">View all your TECRF requests (<?php echo $total_requests; ?>)</div>
            </a>
        </div>

        <!-- Request Status Chart -->
        <div class="chart-container">
            <div class="chart-header">
                <h3>
                    <i class="fa-solid fa-chart-pie"></i>
                    Request Status Breakdown
                </h3>
                <i class="fa-solid fa-chart-line"></i>
            </div>
            
            <canvas id="requestChart"></canvas>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        // Request Status Chart
        const ctx = document.getElementById("requestChart").getContext("2d");
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Completed'],
                datasets: [{
                    data: [
                        <?php echo $pending; ?>,
                        <?php echo $in_progress; ?>,
                        <?php echo $completed; ?>
                    ],
                    backgroundColor: [
                        '#f97316',
                        '#0062a9',
                        '#22c55e'
                    ],
                    borderWidth: 0,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#1e293b',
                            font: { 
                                size: 11,
                                weight: '500'
                            },
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.raw || 0;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '65%',
                layout: {
                    padding: {
                        bottom: 20
                    }
                }
            }
        });
    });
    </script>
</body>
</html>

<?php
$conn->close();
?>