<?php
include 'session.php';
include 'database.php'; // Include your database connection file

// Check if user is adminStaff or adminIT
if (!isset($_SESSION['userType']) || !in_array($_SESSION['userType'], ['adminStaff', 'adminIT'])) {
    header("location: index.php");
    exit;
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Fetch filter values
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

$items_per_page = 1000000000000000000; // Change this value to reduce the limit
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Fetch Loadout Items
$loadout_query = "SELECT description, unit_id, loadout_date, loadout_location, action_notice_no FROM loadout_history WHERE MONTH(loadout_date) = ? AND YEAR(loadout_date) = ? LIMIT ?, ?";
$loadout_stmt = $conn->prepare($loadout_query);
$loadout_stmt->bind_param('iiii', $filter_month, $filter_year, $offset, $items_per_page);
$loadout_stmt->execute();
$loadout_result = $loadout_stmt->get_result();

// Fetch Backload Items
$backload_query = "SELECT description, unit_id, backload_date FROM backload_product WHERE MONTH(backload_date) = ? AND YEAR(backload_date) = ? LIMIT ?, ?";
$backload_stmt = $conn->prepare($backload_query);
$backload_stmt->bind_param('iiii', $filter_month, $filter_year, $offset, $items_per_page);
$backload_stmt->execute();
$backload_result = $backload_stmt->get_result();

// Count Total Items
$loadout_count_query = "SELECT COUNT(*) AS total FROM loadout_history WHERE MONTH(loadout_date) = ? AND YEAR(loadout_date) = ?";
$backload_count_query = "SELECT COUNT(*) AS total FROM backload_product WHERE MONTH(backload_date) = ? AND YEAR(backload_date) = ?";

$loadout_stmt_count = $conn->prepare($loadout_count_query);
$backload_stmt_count = $conn->prepare($backload_count_query);
$loadout_stmt_count->bind_param('ii', $filter_month, $filter_year);
$backload_stmt_count->bind_param('ii', $filter_month, $filter_year);
$loadout_stmt_count->execute();
$loadout_count_result = $loadout_stmt_count->get_result()->fetch_assoc()['total'];
$backload_stmt_count->execute();
$backload_count_result = $backload_stmt_count->get_result()->fetch_assoc()['total'];

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
    <title>Monthly Reports | EPIC OG</title>
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
        .container {
            background: white;
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 15px 35px rgba(0, 98, 169, 0.08);
            border: 1px solid rgba(0, 98, 169, 0.1);
            width: 100%;
        }

        /* Original report styles preserved below */
        h1 {
            font-size: 28px;
            color: #2a5298;
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2a5298;
            padding-bottom: 10px;
            font-weight: 700;
        }
        
        .back-button {
            margin-bottom: 20px;
            text-align: left;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            color: #ffffff;
            background: linear-gradient(135deg, #0062a9 0%, #0088cc 100%);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.2);
        }

        .btn:hover {
            background: #004d88;
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(0, 98, 169, 0.3);
        }
        .summary {
            margin: 20px 0 30px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid rgba(0, 98, 169, 0.1);
        }

        .summary h2 {
            color: #1e293b;
            font-size: 1.2rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .summary p {
            font-size: 1rem;
            color: #475569;
            margin: 8px 0;
            padding: 8px 12px;
            background: white;
            border-radius: 8px;
            border: 1px solid rgba(0, 98, 169, 0.1);
        }

        /* Filter form styling */
        #filter-form {
            display: flex;
            align-items: center;
            gap: 15px;
            font-family: 'Inter', sans-serif;
            justify-content: flex-end;
            margin: 20px 0;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
        }

        #filter-form label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e293b;
        }

        #filter-form select {
            padding: 10px 16px;
            font-size: 0.9rem;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 12px;
            background-color: white;
            color: #1e293b;
            outline: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        #filter-form select:focus,
        #filter-form select:hover {
            border-color: #0062a9;
            box-shadow: 0 0 0 4px rgba(0, 98, 169, 0.1);
        }

        #filter-form button {
            padding: 10px 24px;
            background: linear-gradient(135deg, #0062a9 0%, #0088cc 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.2);
        }

        #filter-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(0, 98, 169, 0.3);
        }

        /* Action buttons */
        .actions {
            margin: 30px 0 20px 0;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .action-btn {
            padding: 12px 24px;
            font-size: 0.9rem;
            border: none;
            border-radius: 12px;
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .print-btn {
            background: #f97316;
            box-shadow: 0 8px 15px rgba(249, 115, 22, 0.2);
        }

        .print-btn:hover {
            background: #ea580c;
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(249, 115, 22, 0.3);
        }

        .excel-btn {
            background: #10b981;
            box-shadow: 0 8px 15px rgba(16, 185, 129, 0.2);
        }

        .excel-btn:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(16, 185, 129, 0.3);
        }

        /* Table styling */
        h2 {
            font-size: 1.3rem;
            color: #1e293b;
            margin: 30px 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        h2:first-of-type {
            margin-top: 0;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 2px solid rgba(0, 98, 169, 0.15);
            font-size: 0.9rem;
        }

        .table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: #1e293b;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px 12px;
            text-align: left;
            border-bottom: 3px solid #0062a9;
            border-right: 1px solid rgba(0, 98, 169, 0.1);
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid rgba(0, 98, 169, 0.15);
            border-right: 1px solid rgba(0, 98, 169, 0.1);
            color: #334155;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tr:hover td {
            background: #e6f0ff;
        }

        .print-logo {
            display: none;
        }

        /* Chart styling */
        #reportChart {
            max-width: 400px;
            max-height: 400px;
            margin: 20px auto;
            display: block;
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
            #filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            #filter-form select,
            #filter-form button {
                width: 100%;
            }
        }

        @media print {
            #sidebar,
            .header,
            .back-button,
            .actions,
            #filter-form {
                display: none;
            }
            
            #main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
                padding: 15px;
            }
            
            .print-logo {
                display: block;
                margin-bottom: 20px;
            }
            
            .print-logo img {
                width: 120px;
                height: auto;
            }
            
            .table {
                border: 1px solid #000;
            }
            
            .table th,
            .table td {
                border: 1px solid #000;
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
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <div class="logo-wrapper">
                        <div class="logo-container">
                            <img src="eog3.jpg" alt="EPIC OG Logo">
                        </div>
                        <div class="title-badge">Monthly Reports</div>
                    </div>
                </div>
                <div class="clock-container">
                    <div class="clock" id="clock"></div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="print-logo">
                <img src="eog.png" alt="Company Logo">
            </div>
            
            <h1>Reports</h1>
            
            <div class="back-button">
                <a href="adminStaff.php" class="btn">
                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            
            <div class="summary">
                <h2><i class="fa fa-chart-pie"></i> Report Summary</h2>
                <p><i class="fa fa-arrow-up" style="color: #4CAF50;"></i> Total Loadout Items: <?= $loadout_count_result ?></p>
                <p><i class="fa fa-arrow-down" style="color: #FF5733;"></i> Total Backload Items: <?= $backload_count_result ?></p>

                <div style="width: 400px; height: 400px; margin: 20px auto;">
                    <canvas id="reportChart"></canvas>
                </div>
            </div>

            <!-- Sorting Form -->
            <form method="GET" action="" id="filter-form">
                <label for="month">Month:</label>
                <select name="month" id="month">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $i == $filter_month ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <label for="year">Year:</label>
                <select name="year" id="year">
                    <?php for ($y = date('Y') - 10; $y <= date('Y'); $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $filter_year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>

                <button type="submit">
                    <i class="fa fa-filter"></i> Apply Filter
                </button>
            </form>

            <div class="actions">
                <button class="action-btn print-btn" onclick="window.print()">
                    <i class="fa fa-print"></i> Print Page
                </button>
                <a href="export_report.php?month=<?= $filter_month ?>&year=<?= $filter_year ?>" class="action-btn excel-btn">
                    <i class="fa fa-file-excel"></i> Export to Excel
                </a>
            </div>

            <h2>
                <i class="fa fa-arrow-up" style="color: #4CAF50;"></i>
                Loadout Items (<?= date('F Y', strtotime("$filter_year-$filter_month-01")) ?>)
            </h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Description</th>
                        <th>Unit ID</th>
                        <th>Loadout Date</th>
                        <th>Loadout Location</th>
                        <th>Action Notice No</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = $offset + 1; while ($row = $loadout_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= $no++ ?></strong></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td><span style="color: #0062a9; font-weight: 600;"><?= htmlspecialchars($row['unit_id']) ?></span></td>
                            <td><?= htmlspecialchars($row['loadout_date']) ?></td>
                            <td><?= htmlspecialchars($row['loadout_location']) ?></td>
                            <td><?= htmlspecialchars($row['action_notice_no']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($loadout_result->num_rows == 0): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #64748b;">
                                <i class="fa fa-info-circle"></i> No loadout items found for this period.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2>
                <i class="fa fa-arrow-down" style="color: #FF5733;"></i>
                Backload Items (<?= date('F Y', strtotime("$filter_year-$filter_month-01")) ?>)
            </h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Description</th>
                        <th>Unit ID</th>
                        <th>Backload Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = $offset + 1; while ($row = $backload_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= $no++ ?></strong></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td><span style="color: #0062a9; font-weight: 600;"><?= htmlspecialchars($row['unit_id']) ?></span></td>
                            <td><?= htmlspecialchars($row['backload_date']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($backload_result->num_rows == 0): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 30px; color: #64748b;">
                                <i class="fa fa-info-circle"></i> No backload items found for this period.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.getElementById('month').addEventListener('change', function () {
            document.getElementById('filter-form').submit();
        });

        document.getElementById('year').addEventListener('change', function () {
            document.getElementById('filter-form').submit();
        });

        // Initialize chart
        const ctx = document.getElementById('reportChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Loadout Items', 'Backload Items'],
                datasets: [{
                    data: [<?= $loadout_count_result ?>, <?= $backload_count_result ?>],
                    backgroundColor: ['#4CAF50', '#FF5733'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                family: 'Inter',
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>

<?php
$loadout_stmt->close();
$backload_stmt->close();
$loadout_stmt_count->close();
$backload_stmt_count->close();
$conn->close();
?>