<?php
include 'session.php';
include 'database.php';

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Get TECRF counts by status
$tecrfQuery = "SELECT status, COUNT(*) as count FROM tecrf GROUP BY status";
$tecrfResult = mysqli_query($conn, $tecrfQuery);
$tecrfCounts = ['Pending' => 0, 'In Progress' => 0, 'Assigned' => 0, 'Completed' => 0];
while ($row = mysqli_fetch_assoc($tecrfResult)) {
    $tecrfCounts[$row['status']] = $row['count'];
}

// Get inventory stats
$inventoryQuery = "SELECT 
    COUNT(*) as total_items,
    SUM(CASE WHEN current_stock = 0 THEN 1 ELSE 0 END) as zero_stock
    FROM inventory_product";
$inventoryResult = mysqli_query($conn, $inventoryQuery);
$inventoryStats = mysqli_fetch_assoc($inventoryResult);

// Generate navigation function
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
    <title>Admin Dashboard | EPIC OG</title>
    <!-- Fonts and Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Chart.js -->
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

        /* Stats Grid */
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

        /* Status Badges */
        .status-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
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
            border: 1px solid;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
            flex: 1;
            min-width: 120px;
            pointer-events: none;
        }

        .status-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .status-badge.pending-badge {
            border-color: #f97316;
            background: linear-gradient(135deg, #fff6e9 0%, #fff 100%);
        }

        .status-badge.pending-badge i {
            color: #f97316;
        }

        .status-badge.progress-badge {
            border-color: #0062a9;
            background: linear-gradient(135deg, #e6f0ff 0%, #fff 100%);
        }

        .status-badge.progress-badge i {
            color: #0062a9;
        }

        .status-badge.completed-badge {
            border-color: #22c55e;
            background: linear-gradient(135deg, #e8f7ed 0%, #fff 100%);
        }

        .status-badge.completed-badge i {
            color: #22c55e;
        }

        .status-badge.zero-badge {
            border-color: #f97316;
            background: linear-gradient(135deg, #fff6e9 0%, #fff 100%);
        }

        .status-badge.zero-badge i {
            color: #f97316;
        }

        .badge-count {
            background: rgba(0, 98, 169, 0.1);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #0062a9;
            font-weight: 700;
            margin-left: auto;
        }

        .status-badge.zero-badge .badge-count {
            background: rgba(249, 115, 22, 0.15);
            color: #f97316;
        }

        /* Single Chart Container */
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 98, 169, 0.08);
            border: 1px solid rgba(0, 98, 169, 0.1);
            margin-top: 30px;
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
        }

        .chart-header i {
            color: #0062a9;
            font-size: 1.2rem;
            background: rgba(0, 98, 169, 0.1);
            padding: 10px;
            border-radius: 12px;
        }

        .chart-wrapper {
            position: relative;
            height: 400px;
            width: 100%;
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .stats-grid {
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
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <div class="logo-wrapper">
                        <div class="logo-container">
                            <img src="eog3.jpg" alt="EPIC OG Logo">
                        </div>
                        <div class="title-badge">Admin Dashboard</div>
                    </div>
                </div>
                <div class="header-right">
                    <div class="welcome-row">
                        <h4>Welcome back, <?= htmlspecialchars($username) ?></h4>
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
            <!-- TECRF Overview Card -->
            <a href="approval.php" class="stat-card">
                <div class="stat-header">
                    <h3>TECRF Overview</h3>
                    <div class="stat-icon">
                        <i class="fa-solid fa-file-signature"></i>
                    </div>
                </div>
                <div class="stat-value"><?= array_sum($tecrfCounts) ?></div>
                <div class="stat-label">Total Requests</div>
                <div class="status-badges">
                    <div class="status-badge pending-badge">
                        <i class="fa-solid fa-clock"></i>
                        Pending 
                        <span class="badge-count"><?= $tecrfCounts['Pending'] ?></span>
                    </div>
                    <div class="status-badge progress-badge">
                        <i class="fa-solid fa-spinner"></i>
                        In Progress 
                        <span class="badge-count"><?= $tecrfCounts['In Progress'] ?></span>
                    </div>
                    <div class="status-badge completed-badge">
                        <i class="fa-solid fa-check-double"></i>
                        Completed 
                        <span class="badge-count"><?= $tecrfCounts['Completed'] ?></span>
                    </div>
                </div>
            </a>

            <!-- Inventory Status Card -->
            <a href="inventory_list.php" class="stat-card">
                <div class="stat-header">
                    <h3>Inventory Status</h3>
                    <div class="stat-icon">
                        <i class="fa-solid fa-boxes"></i>
                    </div>
                </div>
                <div class="stat-value"><?= $inventoryStats['total_items'] ?? 0 ?></div>
                <div class="stat-label">Total Items</div>
                <div class="status-badges">
                    <div class="status-badge zero-badge">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                        Zero Stock 
                        <span class="badge-count"><?= $inventoryStats['zero_stock'] ?? 0 ?></span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Single Chart - TECRF Status Distribution -->
        <div class="chart-container">
            <div class="chart-header">
                <h3>TECRF Status Distribution</h3>
                <i class="fa-solid fa-chart-pie"></i>
            </div>
            <div class="chart-wrapper">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Status Distribution Chart (Donut)
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Completed'],
                datasets: [{
                    data: [
                        <?= $tecrfCounts['Pending'] ?>, 
                        <?= $tecrfCounts['In Progress'] ?>, 
                        <?= $tecrfCounts['Completed'] ?>
                    ],
                    backgroundColor: [
                        '#f97316',  // Orange for Pending
                        '#0062a9',  // Blue for In Progress
                        '#22c55e'   // Green for Completed
                    ],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                family: 'Inter',
                                size: 12
                            },
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleFont: {
                            family: 'Inter',
                            size: 13
                        },
                        bodyFont: {
                            family: 'Inter',
                            size: 12
                        },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>