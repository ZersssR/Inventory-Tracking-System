<?php
include 'session.php';
include 'database.php';

// Check if user is adminStaff or adminIT
if (!isset($_SESSION['userType']) || !in_array($_SESSION['userType'], ['adminStaff', 'adminIT'])) {
    header("location: index.php");
    exit;
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection parameters
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "inventory_tracking";

// Create connection
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to calculate days offshore
function calculateDaysOffshore($loadout_date) {
    if (!$loadout_date || $loadout_date == '0000-00-00') {
        return '-'; // Return '-' if the loadout_date is empty or invalid
    }

    try {
        $loadout_date = new DateTime($loadout_date); // Attempt to parse the date
        $current_date = new DateTime(); // Get the current date
        $interval = $loadout_date->diff($current_date); // Calculate the difference
        return $interval->days; // Return the number of days
    } catch (Exception $e) {
        return '-'; // In case of error, return '-'
    }
}

// Function to calculate TEC expiry and return a CSS class
function calculateTecExpiry($tec_expiry) {
    if (empty($tec_expiry) || $tec_expiry == "0000-00-00") {
        return 'expired'; // Default to expired if missing
    }

    $expiry_date = strtotime($tec_expiry);
    $today = strtotime(date('Y-m-d'));
    $days_remaining = ($expiry_date - $today) / (60 * 60 * 24);

    if ($days_remaining < 0) {
        return 'expired'; // Expired (Red)
    } elseif ($days_remaining <= 28) {
        return 'chocolate'; // Less than 4 weeks (Brown)
    } elseif ($days_remaining <= 56) {
        return 'orange'; // Less than 8 weeks (Orange)
    } elseif ($days_remaining <= 84) {
        return 'yellow'; // Less than 12 weeks (Yellow)
    } else {
        return 'green'; // More than 12 weeks (Green)
    }
}

// Get the product details for editing
$product = null;
if (isset($_GET['unit_id']) && !isset($_POST['unit_id'])) {
    $tec_id = $_GET['unit_id'];
    $sql = "SELECT * FROM product WHERE unit_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $tec_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
}

// Search functionality
$searchTerm = '';
$filterDescription = '';
$filterTecGroup = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['unit_id'])) {
    $searchTerm = $_POST['search'];
    $filterDescription = $_POST['filter_description'];
    $filterTecGroup = $_POST['filter_tec_group'];
}

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = isset($_GET['items_per_page']) ? (int)$_GET['items_per_page'] : 50;
$offset = ($page - 1) * $itemsPerPage;

// SQL query to fetch data with pagination
$sql = "SELECT * FROM product WHERE 1=1";
if ($searchTerm) {
    $sql .= " AND (category LIKE '%$searchTerm%' OR description LIKE '%$searchTerm%'  OR unit_id LIKE '%$searchTerm%' OR tec_id LIKE '%$searchTerm%' OR action_notice_no LIKE '%$searchTerm%')";
}
if ($filterDescription) {
    $sql .= " AND description = '$filterDescription'";
}
if ($filterTecGroup) {
    $sql .= " AND tec_group = '$filterTecGroup'";
}
$sql .= " ORDER BY description ASC LIMIT $itemsPerPage OFFSET $offset"; // Add ORDER BY description ASC to sort alphabetically

$result = $conn->query($sql);

// Check for SQL errors
if ($result === false) {
    die("SQL Error: " . $conn->error);
}

// Get the total number of records for pagination
$total_sql = "SELECT COUNT(*) AS total FROM product WHERE 1=1";
if ($searchTerm) {
    $total_sql .= " AND (category LIKE '%$searchTerm%' OR description LIKE '%$searchTerm%'  OR unit_id LIKE '%$searchTerm%' OR tec_id LIKE '%$searchTerm%')";
}
if ($filterDescription) {
    $total_sql .= " AND description = '$filterDescription'";
}
if ($filterTecGroup) {
    $total_sql .= " AND tec_group = '$filterTecGroup'";
}

$total_result = $conn->query($total_sql);
$total_row = $total_result->fetch_assoc();
$total_items = $total_row['total'];
$total_pages = ceil($total_items / $itemsPerPage);

// Calculate the range of items being displayed
$start_item = $offset + 1;
$end_item = min($offset + $itemsPerPage, $total_items);

// Initialize the row counter
$row_counter = $start_item;

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
    <title>List Items | EPIC OG</title>
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

        /* Original LIST_ITEMS.php styles preserved below */
        .expired {
            background-color: #FF6961 !important; /* Red */
        }
        .chocolate {
            background-color: chocolate !important; /* Brown */
        }
        .orange {
            background-color: #FFC067 !important; /* Orange */
        }
        .yellow {
            background-color: #e7f56e !important; /* Yellow */
        }
        .green {
            background-color:rgb(156, 222, 156) !important; /* Green */
        }

        .back-button {
            margin-bottom: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            color: #ffffff;
            background: linear-gradient(135deg, #0062a9 0%, #0088cc 100%);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.2);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(0, 98, 169, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
        }

        .btn_edit_icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            background: white;
            border: 2px solid rgba(0, 98, 169, 0.2);
            border-radius: 10px;
            color: #0062a9;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn_edit_icon:hover {
            background: #0062a9;
            color: white;
            border-color: #0062a9;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.2);
        }

        .search-container {
            width: 100%;
            margin: 20px 0 30px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid rgba(0, 98, 169, 0.1);
        }

        .search-container form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .search-container input,
        .search-container select {
            flex: 1;
            min-width: 200px;
            padding: 12px 16px;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            outline: none;
        }

        .search-container input:focus,
        .search-container select:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 4px rgba(0, 98, 169, 0.1);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            padding: 8px 16px;
            text-decoration: none;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 12px;
            color: #0062a9;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .pagination a:hover {
            background: #0062a9;
            color: white;
            border-color: #0062a9;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.2);
        }

        .pagination .disabled {
            background: #f1f5f9;
            color: #94a3b8;
            border-color: #e2e8f0;
            cursor: not-allowed;
        }

        .pagination input[type="number"] {
            width: 60px;
            padding: 8px;
            text-align: center;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 12px;
            outline: none;
        }

        .pagination input[type="number"]:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 4px rgba(0, 98, 169, 0.1);
        }

        .table-container {
            margin-top: 30px;
        }

        .scrollable-table {
            max-height: 500px;
            overflow-y: auto;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 16px;
            background: white;
        }

/* Table styling with vertical lines */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    background: white;
}

th {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    color: #1e293b;
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 8px;
    text-align: center;
    border: 1px solid rgba(0, 98, 169, 0.2); /* Add border to th */
    border-bottom: 3px solid #0062a9;
    position: sticky;
    top: 0;
    z-index: 5;
    white-space: nowrap;
}

td {
    padding: 10px 8px;
    text-align: center;
    border: 1px solid rgba(0, 98, 169, 0.15); /* Add border to td */
    color: #334155;
}

/* Make sure the table has borders all around */
table {
    border: 2px solid rgba(0, 98, 169, 0.2);
}

/* Ensure the last row doesn't have double borders */
tr:last-child td {
    border-bottom: 1px solid rgba(0, 98, 169, 0.15);
}

/* Keep the hover effect */
tr:hover td {
    background: rgba(0, 98, 169, 0.05) !important;
}
        .add-btn {
            text-align: center;
            margin: 20px 0;
        }

        .add-btn .btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            .search-container form {
                flex-direction: column;
            }
            .search-container input,
            .search-container select {
                width: 100%;
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
                        <div class="title-badge">List Items</div>
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
                    <i class="fa fa-list"></i>
                    List Items
                </h1>
                
                <div class="back-button">
                    <a href="adminStaff.php" class="btn">
                        <i class="fa fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Search form -->
            <div class="search-container">
                <form method="post" action="">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search by category, description, unit ID, TEC ID, action notice...">
                    
                    <select name="filter_description">
                        <option value="">All Descriptions</option>
                        <?php
                        $desc_sql = "SELECT DISTINCT description FROM product ORDER BY description ASC";
                        $desc_result = $conn->query($desc_sql);
                        while ($desc_row = $desc_result->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($desc_row['description']) . '"' . ($filterDescription == $desc_row['description'] ? ' selected' : '') . '>' . htmlspecialchars($desc_row['description']) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <select name="filter_tec_group">
                        <option value="">All TEC Groups</option>
                        <?php
                        $tec_group_sql = "SELECT DISTINCT tec_group FROM product";
                        $tec_group_result = $conn->query($tec_group_sql);
                        while ($tec_group_row = $tec_group_result->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($tec_group_row['tec_group']) . '"' . ($filterTecGroup == $tec_group_row['tec_group'] ? ' selected' : '') . '>' . htmlspecialchars($tec_group_row['tec_group']) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <button type="submit" class="btn">
                        <i class="fa fa-search"></i> Search
                    </button>
                </form>
            </div>

            <!-- Pagination display -->
            <div class="pagination">
                <!-- Previous Button -->
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&items_per_page=<?php echo $itemsPerPage; ?>">« Previous</a>
                <?php else: ?>
                    <span class="disabled">« Previous</span>
                <?php endif; ?>

                <!-- Page Number Input -->
                <form method="get" action="" style="display: inline;">
                    <span>Page:</span>
                    <input type="number" name="page" value="<?php echo $page; ?>" min="1" max="<?php echo $total_pages; ?>">
                    <input type="hidden" name="items_per_page" value="<?php echo $itemsPerPage; ?>">
                    <button type="submit" class="btn" style="padding: 8px 16px;">Go</button>
                </form>

                <!-- Total Pages Display -->
                <span>of <?php echo $total_pages; ?></span>

                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&items_per_page=<?php echo $itemsPerPage; ?>">Next »</a>
                <?php else: ?>
                    <span class="disabled">Next »</span>
                <?php endif; ?>
            </div>
            
            <!-- Display the range of items being displayed -->
            <div style="text-align: center; margin: 15px 0;">
                <p style="color: #475569; background: #f8fafc; padding: 8px 16px; border-radius: 30px; display: inline-block; border: 1px solid rgba(0, 98, 169, 0.15);">
                    <i class="fa fa-list"></i> Showing items <?php echo $start_item; ?> to <?php echo $end_item; ?> of <?php echo $total_items; ?>
                </p>
            </div>
            
            <div class="add-btn">
                <a href="add_product.php" class="btn">
                    <i class="fas fa-cart-plus"></i> Add New Item
                </a>
            </div>

            <!-- Products table -->
            <div class="table-container">
                <div class="scrollable-table">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>TEC ID</th>
                                <th>TEC Expiry</th>
                                <th>Unit ID</th>
                                <th>Action Notice</th>
                                <th>Loadout Date</th>
                                <th>Loadout Location</th>
                                <th>Backload Date</th>
                                <th>Backload Sheet</th>
                                <th>Description</th>
                                <th>Size</th>
                                <th>SWL</th>
                                <th>Remarks</th>
                                <th>Status</th>
                                <th>Certificate</th>
                                <th>Days Offshore</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>TEC Group</th>
                                <th>PO Number</th>
                                <th>Serial No</th>
                                <th>Qty Inhouse</th>
                                <th>Qty In Use</th>
                                <th>Qty Damage</th>
                                <th>Date Damage</th>
                                <th>Qty Dispose</th>
                                <th>Date Dispose</th>
                                <th>Qty Valid</th>
                                <th>Storage Location</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php $row_class = calculateTecExpiry($row['tec_expiry']); ?>
                                <tr class="<?php echo htmlspecialchars(calculateTecExpiry($row['tec_expiry'])) ?: 'expired'; ?>">
                                    <td><strong><?php echo $row_counter++; ?></strong></td>
                                    <td><span style="color: #0062a9; font-weight: 600;"><?php echo htmlspecialchars($row['tec_id']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['tec_expiry']); ?></td>
                                    <td><?php echo htmlspecialchars($row['unit_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['action_notice_no']); ?></td>
                                    <td><?php echo htmlspecialchars($row['loadout_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['loadout_location']); ?></td>
                                    <td><?php echo htmlspecialchars($row['backload_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['backload_sheet_no']); ?></td>
                                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td><?php echo htmlspecialchars($row['size']); ?></td>
                                    <td><?php echo htmlspecialchars($row['swl']); ?></td>
                                    <td><?php echo htmlspecialchars($row['remarks']); ?></td>
                                    <td>
                                        <span style="background: #e8f7ed; color: #16a34a; padding: 4px 8px; border-radius: 20px; font-weight: 600; font-size: 0.7rem;">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['certificate_validity']); ?></td>
                                    <td><strong><?php echo htmlspecialchars(calculateDaysOffshore($row['loadout_date'])); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td><?php echo htmlspecialchars($row['type']); ?></td>
                                    <td><?php echo htmlspecialchars($row['tec_group']); ?></td>
                                    <td><?php echo htmlspecialchars($row['po_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['serial_no']); ?></td>
                                    <td><?php echo htmlspecialchars($row['qty_inhouse']); ?></td>
                                    <td><?php echo htmlspecialchars($row['qty_use']); ?></td>
                                    <td><?php echo htmlspecialchars($row['qty_damage']); ?></td>
                                    <td><?php echo htmlspecialchars($row['date_damage']); ?></td>
                                    <td><?php echo htmlspecialchars($row['qty_dispose']); ?></td>
                                    <td><?php echo htmlspecialchars($row['date_dispose']); ?></td>
                                    <td><?php echo htmlspecialchars($row['qty_valid']); ?></td>
                                    <td><?php echo htmlspecialchars($row['storage_location']); ?></td>
                                    <td>
                                        <a href="edit_list.php?unit_id=<?php echo $row['unit_id']; ?>" class="btn_edit_icon">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
$conn->close();
?>