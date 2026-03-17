<?php
include 'session.php';
include 'database.php';

// Check if user is adminStaff or adminIT
if (!isset($_SESSION['userType']) || !in_array($_SESSION['userType'], ['adminStaff', 'adminIT'])) {
    header("location: index.php");
    exit;
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Initialize search term
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// SQL query to fetch action_notice_no and unit_id from unit_location_history, 
// with loadout_date from loadout_history and status from tecrf
$sql = "
    SELECT DISTINCT 
        u.action_notice_no, 
        u.unit_id, 
        u.description,
        u.loadout_location,
        MAX(l.loadout_date) as latest_loadout_date,
        t.status,
        t.reference_number
    FROM unit_location_history u
    LEFT JOIN loadout_history l ON u.action_notice_no = l.action_notice_no 
        AND u.unit_id = l.unit_id
    INNER JOIN tecrf t ON u.action_notice_no = t.reference_number
    WHERE t.status = 'Assigned'";

// Apply search filter if a search term is provided
if (!empty($search_term)) {
    $search_term_escaped = mysqli_real_escape_string($conn, $search_term);
    $sql .= " AND u.action_notice_no LIKE '%$search_term_escaped%'";
}

$sql .= " GROUP BY u.action_notice_no, u.unit_id, u.description, u.loadout_location, t.status, t.reference_number
          ORDER BY u.action_notice_no ASC, u.unit_id ASC";

$result = $conn->query($sql);

$reference_numbers = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Format the date if it exists
        if ($row['latest_loadout_date']) {
            $row['latest_loadout_date'] = date('Y-m-d', strtotime($row['latest_loadout_date']));
        } else {
            $row['latest_loadout_date'] = 'No loadout date'; // Set a default value if the date is NULL
        }
        $reference_numbers[] = $row;
    }
} else {
    // Check if query failed or no results
    if (!$result) {
        error_log("Error in query: " . mysqli_error($conn));
    }
    // Don't exit, let the page show the empty state
}

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
    <title>Review Assigned Items | EPIC OG</title>
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
        }

        .container-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .container-header h1 {
            color: #1e293b;
            font-size: 1.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: none;
            margin: 0;
            padding: 0;
        }

        .container-header h1 i {
            color: #0062a9;
            font-size: 2rem;
        }

        /* Original VIEW_ALL_ASSIGNED_ITEMS.php styles preserved below */
        .back-btn {
            display: inline-block;
            padding: 12px 24px;
            color: white;
            background: linear-gradient(135deg, #0062a9 0%, #0088cc 100%);
            border: none;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.2);
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(0, 98, 169, 0.3);
        }

        .back-btn i {
            margin-right: 8px;
        }

        .search-container {
            position: relative;
            width: 100%;
            max-width: 400px;
            margin: 0 auto 30px auto;
        }

        .search-input {
            padding: 14px 50px 14px 20px;
            width: 100%;
            font-size: 0.95rem;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 40px;
            outline: none;
            transition: all 0.3s ease;
            background: white;
        }

        .search-input:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 4px rgba(0, 98, 169, 0.1);
        }

        .search-button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: linear-gradient(135deg, #0062a9 0%, #0088cc 100%);
            border: none;
            border-radius: 40px;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
            box-shadow: 0 4px 10px rgba(0, 98, 169, 0.2);
        }

        .search-button:hover {
            transform: translateY(-50%) scale(1.05);
            box-shadow: 0 6px 15px rgba(0, 98, 169, 0.3);
        }

        .search-button span {
            font-size: 1.2rem;
            color: white;
        }

        .scrollable-table {
            max-height: 500px;
            overflow-y: auto;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 16px;
            margin: 20px 0;
            background: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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
            text-align: center;
            border-bottom: 3px solid #0062a9;
            position: sticky;
            top: 0;
            z-index: 5;
            white-space: nowrap;
        }

        td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid rgba(0, 98, 169, 0.15);
            color: #334155;
        }

        tr:nth-child(even) {
            background-color: #f8fafc;
        }

        tr:hover td {
            background: #e6f0ff;
        }

        .view-link {
            display: inline-block;
            padding: 8px 16px;
            background: white;
            border: 2px solid rgba(0, 98, 169, 0.2);
            border-radius: 12px;
            color: #0062a9;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .view-link:hover {
            background: #0062a9;
            color: white;
            border-color: #0062a9;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.2);
        }

        .view-link i {
            margin-right: 5px;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
            background: #f8fafc;
            border-radius: 16px;
            border: 2px dashed rgba(0, 98, 169, 0.2);
            margin: 20px 0;
            font-size: 1rem;
        }

        .no-data i {
            font-size: 3rem;
            color: #0062a9;
            opacity: 0.4;
            margin-bottom: 15px;
        }

        .total-records {
            margin-top: 20px;
            padding: 12px 20px;
            background: #f8fafc;
            border-radius: 40px;
            border: 1px solid rgba(0, 98, 169, 0.15);
            text-align: right;
            color: #1e293b;
            font-weight: 500;
        }

        .total-records i {
            color: #0062a9;
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
            .container-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .search-container {
                max-width: 100%;
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
                        <div class="title-badge">Loadout Items</div>
                    </div>
                </div>
                <div class="clock-container">
                    <div class="clock" id="clock"></div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="container-header">
                <h1>
                    <i class="fa fa-box-open"></i>
                    Review Assigned Items
                </h1>
                
                <a href="adminStaff.php" class="back-btn">
                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            
            <form method="GET" action="">
                <div class="search-container">
                    <input type="text" name="search" class="search-input" placeholder="Search by Reference Number" 
                        value="<?php echo htmlspecialchars($search_term); ?>">
                    <button type="submit" class="search-button">
                        <span>🔍</span>
                    </button>
                </div>
            </form>
            
            <?php if (!empty($reference_numbers)): ?>
                <div class="scrollable-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Reference Number</th>
                                <th>Unit ID</th>
                                <th>Description</th>
                                <th>Loadout Location</th>
                                <th>Latest Loadout Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reference_numbers as $ref): ?>
                                <tr>
                                    <td><strong style="color: #0062a9;"><?php echo htmlspecialchars($ref['action_notice_no']); ?></strong></td>
                                    <td><span style="color: #0062a9; font-weight: 600;"><?php echo htmlspecialchars($ref['unit_id']); ?></span></td>
                                    <td><?php echo htmlspecialchars($ref['description']); ?></td>
                                    <td><?php echo htmlspecialchars($ref['loadout_location']); ?></td>
                                    <td><?php echo htmlspecialchars($ref['latest_loadout_date']); ?></td>
                                    <td>
                                        <span style="background: #e8f7ed; color: #16a34a; padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 0.75rem;">
                                            <?php echo htmlspecialchars($ref['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="view-link" href="view_assigned_items.php?reference_number=<?php echo urlencode($ref['action_notice_no']); ?>&unit_id=<?php echo urlencode($ref['unit_id']); ?>">
                                            <i class="fa fa-eye"></i> View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="total-records">
                    <i class="fa fa-list"></i> Total Records: <?php echo count($reference_numbers); ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fa fa-box-open"></i>
                    <p>No assigned items found in unit_location history.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
// Close the database connection
if (isset($conn)) {
    $conn->close();
}
?>