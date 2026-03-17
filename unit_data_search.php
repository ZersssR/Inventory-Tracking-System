<?php
include 'session.php';
include 'database.php';

// Check if user is adminStaff or adminIT
if (!isset($_SESSION['userType']) || !in_array($_SESSION['userType'], ['adminStaff', 'adminIT'])) {
    header("location: index.php");
    exit;
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Initialize unit_id search variable
$search_unit_id = '';
if (isset($_POST['search_unit_id'])) {
    $search_unit_id = $_POST['unit_id'];
}

// Fetch data from unit_location_history and loadout_history based on action_notice_no
$query1 = "
    SELECT ulh.action_notice_no AS ulh_action_notice, 
           ulh.unit_id,
           GROUP_CONCAT(DISTINCT ulh.description ORDER BY ulh.description) AS ulh_description,
           GROUP_CONCAT(DISTINCT ulh.loadout_location ORDER BY ulh.loadout_location) AS ulh_location,
           GROUP_CONCAT(DISTINCT ulh.assigned_at ORDER BY ulh.assigned_at) AS assigned_at,
            loadout_date
    FROM unit_location_history ulh
    JOIN loadout_history lh ON ulh.action_notice_no = lh.action_notice_no
    WHERE ulh.unit_id LIKE '%$search_unit_id%'  -- Filter by unit_id if provided
    GROUP BY ulh.unit_id, ulh.action_notice_no";  // Group by unit_id and action_notice_no

$result1 = $conn->query($query1);

// Fetch data from backload_product with optional unit_id filter
$query2 = "
    SELECT unit_id, description, backload_sheet_no, backload_date
    FROM backload_product
    WHERE unit_id LIKE '%$search_unit_id%'"; // Filter by unit_id if provided

$result2 = $conn->query($query2);

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
    <title>Transaction Unit ID | EPIC OG</title>
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

        /* Original UNIT.php styles preserved below */
        h1 {
            font-size: 30px;
            text-align: center;
            color: #345d9d;
            margin-bottom: 20px;
            border-bottom: 3px solid #345d9d;
            padding-bottom: 10px;
        }

        h3 {
            font-size: 1.2rem;
            color: #1e293b;
            margin: 30px 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
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
            display: inline-flex;
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

        .search-form {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0 30px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid rgba(0, 98, 169, 0.1);
        }

        .search-form input {
            padding: 12px 16px;
            font-size: 0.95rem;
            border-radius: 12px;
            border: 2px solid rgba(0, 98, 169, 0.15);
            width: 300px;
            transition: all 0.3s ease;
            outline: none;
        }

        .search-form input:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 4px rgba(0, 98, 169, 0.1);
        }

        .search-form button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #0062a9 0%, #0088cc 100%);
            color: white;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.2);
        }

        .search-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(0, 98, 169, 0.3);
        }

        .search-form button[type="button"] {
            background: #f1f5f9;
            color: #475569;
            box-shadow: none;
        }

        .search-form button[type="button"]:hover {
            background: #e2e8f0;
            color: #1e293b;
            transform: translateY(-2px);
        }

        .table-container {
            overflow-x: auto;
            margin: 20px 0;
            border-radius: 16px;
            border: 2px solid rgba(0, 98, 169, 0.15);
            background: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            font-size: 0.9rem;
        }

        th {
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

        td {
            padding: 12px;
            border-bottom: 1px solid rgba(0, 98, 169, 0.15);
            border-right: 1px solid rgba(0, 98, 169, 0.1);
            color: #334155;
            background: white;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #e6f0ff;
        }

        .print-logo {
            display: none;
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
            .search-form {
                flex-direction: column;
            }
            .search-form input {
                width: 100%;
            }
        }

        @media print {
            #sidebar,
            .header,
            .back-button,
            .actions,
            .search-form {
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
            
            table {
                border: 1px solid #000;
            }
            
            th, td {
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
                        <div class="title-badge">Transaction Search</div>
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
            
            <h1>Transaction Unit ID</h1>
            
            <div class="back-button">
                <a href="adminStaff.php" class="btn">
                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            
            <!-- Search Form -->
            <form method="POST" action="" class="search-form">
                <input type="text" name="unit_id" value="<?php echo htmlspecialchars($search_unit_id); ?>" placeholder="Enter Unit ID" required>
                <button type="submit" name="search_unit_id">
                    <i class="fa fa-search"></i> Search
                </button>
                <button type="button" onclick="window.location.href='unit_data_search.php'">
                    <i class="fa fa-refresh"></i> Clear
                </button>
            </form>
            
            <div class="actions">
                <button class="action-btn print-btn" onclick="window.print()">
                    <i class="fa fa-print"></i> Print Page
                </button>
                <a href="export_assignLoadout_transaction.php" class="action-btn excel-btn">
                    <i class="fa fa-file-excel"></i> Download Excel
                </a>
            </div>
            
            <h3>
                <i class="fa fa-exchange-alt" style="color: #0062a9;"></i>
                Unit Location and Loadout History
            </h3>
            
            <?php if ($result1 && $result1->num_rows > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Action Notice No</th>
                                <th>Description</th>
                                <th>Unit ID</th>
                                <th>Loadout Location</th>
                                <th>Assigned At</th>
                                <th>Loadout Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $last_action_notice = null;
                            $rowspan_count = 0;
                            $action_notice_rows = [];

                            // Pre-process the rows to group them by action notice number
                            while ($row = $result1->fetch_assoc()) {
                                if (!isset($action_notice_rows[$row['ulh_action_notice']])) {
                                    $action_notice_rows[$row['ulh_action_notice']] = [];
                                }
                                $action_notice_rows[$row['ulh_action_notice']][] = $row;
                            }

                            // Render the table rows
                            foreach ($action_notice_rows as $action_notice => $rows):
                                $rowspan_count = count($rows);
                                foreach ($rows as $index => $row):
                            ?>
                                    <tr>
                                        <?php if ($index === 0): ?>
                                            <td rowspan="<?php echo $rowspan_count; ?>"><strong style="color: #0062a9;"><?php echo htmlspecialchars($action_notice); ?></strong></td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($row['ulh_description']); ?></td>
                                        <td><span style="color: #0062a9; font-weight: 600;"><?php echo htmlspecialchars($row['unit_id']); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['ulh_location']); ?></td>
                                        <td><?php echo htmlspecialchars($row['assigned_at']); ?></td>
                                        <td><?php echo htmlspecialchars($row['loadout_date']); ?></td>
                                    </tr>
                            <?php
                                endforeach;
                            endforeach;
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #64748b; background: #f8fafc; border-radius: 16px; border: 2px dashed rgba(0, 98, 169, 0.2);">
                    <i class="fa fa-info-circle" style="font-size: 2.5rem; color: #0062a9; opacity: 0.4; margin-bottom: 10px;"></i>
                    <p>No records found in Unit Location or Loadout History.</p>
                </div>
            <?php endif; ?>
            
            <div class="actions" style="margin-top: 40px;">
                <a href="export_backload_transaction.php" class="action-btn excel-btn">
                    <i class="fa fa-file-excel"></i> Download Backload Excel
                </a>
            </div>
            
            <h3>
                <i class="fa fa-undo-alt" style="color: #FF5733;"></i>
                Backload Product History
            </h3>
            
            <?php if ($result2 && $result2->num_rows > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Backload Sheet No</th>
                                <th>Description</th>
                                <th>Unit ID</th>
                                <th>Backload Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $backload_rows = [];
                            $rowspan_count = 0;

                            // Pre-process the rows to group them by Backload Sheet No
                            while ($row = $result2->fetch_assoc()) {
                                if (!isset($backload_rows[$row['backload_sheet_no']])) {
                                    $backload_rows[$row['backload_sheet_no']] = [];
                                }
                                $backload_rows[$row['backload_sheet_no']][] = $row;
                            }

                            // Render the table rows
                            foreach ($backload_rows as $backload_sheet_no => $rows):
                                $rowspan_count = count($rows);
                                foreach ($rows as $index => $row):
                            ?>
                                    <tr>
                                        <?php if ($index === 0): ?>
                                            <td rowspan="<?php echo $rowspan_count; ?>"><strong style="color: #FF5733;"><?php echo htmlspecialchars($backload_sheet_no); ?></strong></td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                                        <td><span style="color: #0062a9; font-weight: 600;"><?php echo htmlspecialchars($row['unit_id']); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['backload_date']); ?></td>
                                    </tr>
                            <?php
                                endforeach;
                            endforeach;
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #64748b; background: #f8fafc; border-radius: 16px; border: 2px dashed rgba(0, 98, 169, 0.2);">
                    <i class="fa fa-info-circle" style="font-size: 2.5rem; color: #0062a9; opacity: 0.4; margin-bottom: 10px;"></i>
                    <p>No records found in Backload Product History.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>