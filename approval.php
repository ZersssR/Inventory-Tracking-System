<?php 
include 'session.php';
include 'database.php';

// Check if user is adminStaff or adminIT
if (!isset($_SESSION['userType']) || !in_array($_SESSION['userType'], ['adminStaff', 'adminIT'])) {
    header("location: index.php");
    exit;
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Handle Excel Export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="tecrf_requests_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Build query for export
    $export_query = "SELECT 
        reference_number,
        client,
        project,
        date,
        status
        FROM tecrf
        ORDER BY reference_number DESC";
    
    $export_result = $conn->query($export_query);
    
    // Create Excel file
    echo "Reference Number\tClient\tProject\tDate Requested\tStatus\n";
    
    while ($row = $export_result->fetch_assoc()) {
        echo implode("\t", [
            $row['reference_number'],
            $row['client'],
            $row['project'],
            $row['date'],
            $row['status']
        ]) . "\n";
    }
    exit;
}

// Pagination variables
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Default sort and order
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'reference_number';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Validate input
$allowed_sorts = ['reference_number', 'client', 'project', 'date', 'status'];
$allowed_orders = ['asc', 'desc'];

if (!in_array($sort, $allowed_sorts)) $sort = 'reference_number';
if (!in_array($order, $allowed_orders)) $order = 'desc';

// Handle accept action
if (isset($_GET['accept']) && isset($_GET['reference_number'])) {
    $reference_number = $_GET['reference_number'];
    
    // Update status to 'In Progress'
    $update_sql = $conn->prepare("UPDATE tecrf SET status = 'In Progress' WHERE reference_number = ?");
    $update_sql->bind_param("s", $reference_number);
    
    if ($update_sql->execute()) {
        // Redirect to view_tecrf.php
        header("Location: view_tecrf.php?reference_number=" . urlencode($reference_number));
        exit();
    } else {
        $error_message = "Error updating status: " . $conn->error;
    }
    $update_sql->close();
}

// Get total records for pagination
$total_sql = "SELECT COUNT(*) as total FROM tecrf";
$total_result = $conn->query($total_sql);
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// Get counts by status for stats cards
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
    FROM tecrf";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Prepared statement for fetching sorted data with pagination
$sql = $conn->prepare("SELECT * FROM tecrf ORDER BY $sort $order LIMIT ? OFFSET ?");
$sql->bind_param("ii", $limit, $offset);
$sql->execute();
$result = $sql->get_result();

// Generate navigation function - TEST VERSION
function generateNav($username) {
    // Get current page filename
    $current_page = basename($_SERVER['PHP_SELF']);
    
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
                <li class="">
                    <a href="adminStaff.php">
                        <i class="fa fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="">
                    <a href="inventory_list.php">
                        <i class="fa fa-list"></i>
                        <span>Inventory List</span>
                    </a>
                </li>
                <li class="active"> <!-- PAKSA active kat sini -->
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
    <title>TECRF Approval | EPIC OG</title>
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

        /* Modern Sidebar - Single Tone (matching adminStaff.php) */
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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

        /* Status Badges for Stats */
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

        .badge-count {
            background: rgba(0, 98, 169, 0.1);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #0062a9;
            font-weight: 700;
            margin-left: auto;
        }

        /* Container */
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

        .container-header h2 {
            color: #1e293b;
            font-size: 1.4rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .container-header h2 i {
            color: #0062a9;
            font-size: 1.6rem;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #0062a9;
            color: white;
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.2);
        }

        .btn-primary:hover {
            background: #004d88;
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(0, 98, 169, 0.3);
        }

        .btn-export {
            background: #f97316;
            color: white;
            box-shadow: 0 8px 15px rgba(249, 115, 22, 0.2);
        }

        .btn-export:hover {
            background: #ea580c;
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(249, 115, 22, 0.3);
        }

        .btn-back {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-back:hover {
            background: #e2e8f0;
            color: #1e293b;
            transform: translateY(-2px);
        }

        .btn i {
            font-size: 1rem;
        }

        /* Error message */
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message i {
            font-size: 1.2rem;
        }

        /* Sorting and Filter Bar */
        .filter-bar {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            border: 1px solid rgba(0, 98, 169, 0.1);
        }

        .sorting-form {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .sorting-form label {
            color: #475569;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .sorting-form label i {
            color: #0062a9;
        }

        .sorting-form select {
            padding: 10px 16px;
            background: white;
            border: 1px solid rgba(0, 98, 169, 0.2);
            border-radius: 10px;
            color: #1e293b;
            font-size: 0.95rem;
            outline: none;
            cursor: pointer;
            min-width: 160px;
            transition: all 0.2s ease;
        }

        .sorting-form select:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 3px rgba(0, 98, 169, 0.1);
        }

        /* Table Container */
        .table-container {
            overflow-x: auto;
            margin: 20px 0;
            border-radius: 16px;
            border: 2px solid rgba(0, 98, 169, 0.15);
            max-height: 500px;
            overflow-y: auto;
            background: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            min-width: 900px;
        }

        thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: #1e293b;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 16px 15px;
            text-align: center;
            border-bottom: 3px solid #0062a9;
            border-right: 1px solid rgba(0, 98, 169, 0.1);
            white-space: nowrap;
        }

        th:last-child {
            border-right: none;
        }

        td {
            padding: 14px 15px;
            color: #334155;
            border-bottom: 1px solid rgba(0, 98, 169, 0.15);
            border-right: 1px solid rgba(0, 98, 169, 0.1);
            white-space: nowrap;
            background: white;
        }

        td:last-child {
            border-right: none;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #e6f0ff;
        }

        .number-column {
            width: 60px;
            text-align: center;
            font-weight: 600;
            color: #0062a9;
        }

        /* Status badges for table */
        .status-badge-table {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            min-width: 110px;
            text-align: center;
            border: 1px solid;
        }

        .status-pending {
            background: #fff6e9;
            color: #f97316;
            border-color: #fed7aa;
        }

        .status-progress {
            background: #e6f0ff;
            color: #0062a9;
            border-color: #b8d3ff;
        }

        .status-completed {
            background: #e8f7ed;
            color: #16a34a;
            border-color: #bbf7d0;
        }

        /* Action buttons */
/* Action buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: center;
    flex-wrap: nowrap;
    min-width: 200px;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
    border: 1px solid;
    min-width: 85px;
    box-sizing: border-box;
    cursor: pointer;
}

.accept-btn {
    background: #e8f7ed;
    border-color: #bbf7d0;
    color: #16a34a;
    flex: 1;
}

.accept-btn:hover {
    background: #16a34a;
    border-color: #16a34a;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(22, 163, 74, 0.2);
}

.accepted-btn {
    background: #f1f5f9;
    border-color: #e2e8f0;
    color: #94a3b8;
    cursor: default;
    pointer-events: none;
    flex: 1;
}

.view-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
    border: 1px solid #0062a9;
    background: white;
    color: #0062a9;
    min-width: 85px;
    box-sizing: border-box;
}

.view-btn:hover {
    background: #0062a9;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 98, 169, 0.2);
}

.view-btn i {
    font-size: 0.9rem;
}

.disabled-view {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
    border: 1px solid #e2e8f0;
    background: #f1f5f9;
    color: #94a3b8;
    min-width: 85px;
    box-sizing: border-box;
    cursor: not-allowed;
}

        .icon-link {
            color: #64748b;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            transition: all 0.2s ease;
            background: #f8fafc;
            border: 1px solid rgba(0, 98, 169, 0.1);
        }

        .icon-link:hover {
            background: #0062a9;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 98, 169, 0.2);
        }

        .disabled-icon {
            color: #cbd5e1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            cursor: not-allowed;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid rgba(0, 98, 169, 0.15);
            flex-wrap: wrap;
            gap: 15px;
        }

        .pagination-info {
            color: #475569;
            font-size: 0.9rem;
            font-weight: 500;
            background: #f8fafc;
            padding: 8px 16px;
            border-radius: 30px;
            border: 1px solid rgba(0, 98, 169, 0.15);
        }

        .pagination-info span {
            color: #0062a9;
            font-weight: 700;
        }

        .pagination {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 8px;
            background: white;
            border: 1px solid rgba(0, 98, 169, 0.2);
            border-radius: 10px;
            color: #0062a9;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .pagination a:hover {
            background: #0062a9;
            border-color: #0062a9;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 98, 169, 0.2);
        }

        .pagination .active {
            background: #0062a9;
            border-color: #0062a9;
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(0, 98, 169, 0.2);
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .pagination .dots {
            background: transparent;
            border: none;
            color: #94a3b8;
            min-width: auto;
        }

        /* Records per page selector */
        .records-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #475569;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .records-selector select {
            padding: 8px 12px;
            background: white;
            border: 1px solid rgba(0, 98, 169, 0.2);
            border-radius: 8px;
            color: #1e293b;
            font-size: 0.9rem;
            cursor: pointer;
            outline: none;
        }

        .records-selector select:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 3px rgba(0, 98, 169, 0.1);
        }

        /* Scroll indicator */
        .scroll-indicator {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 10px;
            color: #64748b;
            font-size: 0.85rem;
        }

        .scroll-indicator i {
            animation: bounce 1s infinite;
            color: #0062a9;
        }

        @keyframes bounce {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(5px); }
        }

        /* No data */
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
            background: #f8fafc;
            border-radius: 16px;
            border: 2px dashed rgba(0, 98, 169, 0.2);
        }

        .no-data i {
            font-size: 3.5rem;
            color: #0062a9;
            opacity: 0.4;
            margin-bottom: 15px;
        }

        .no-data p {
            font-size: 1.1rem;
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 1200px) {
            #sidebar {
                width: 240px;
            }
            #main-content {
                margin-left: 240px;
                padding: 20px;
            }
        }

        @media (max-width: 992px) {
            .container-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .action-buttons {
                width: 100%;
                justify-content: flex-start;
            }
            .pagination-container {
                flex-direction: column;
                align-items: flex-start;
            }
            .pagination {
                justify-content: center;
                width: 100%;
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
            .filter-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            .sorting-form {
                width: 100%;
            }
            .sorting-form select {
                flex: 1;
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
                        <div class="title-badge">TECRF Approval</div>
                    </div>
                </div>
                <div class="clock-container">
                    <div class="clock" id="clock"></div>
                </div>
            </div>
        </div>



        <div class="container">
            <div class="container-header">
                <h2>
                    <i class="fa fa-tasks"></i>
                    TECRF Request List
                </h2>
                
                <div class="action-buttons">
                    <a href="adminStaff.php" class="btn btn-back">
                        <i class="fa fa-arrow-left"></i> Back To Dashboard
                    </a>
                    <a href="approval.php?export=excel" class="btn btn-export">
                        <i class="fa fa-file-excel"></i> Export to Excel
                    </a>
                </div>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fa fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="sorting-form">
                    <label><i class="fa fa-sort"></i> Sort By:</label>
                    <select name="sort" id="sort" onchange="updateSort()">
                        <option value="reference_number" <?php echo $sort === 'reference_number' ? 'selected' : ''; ?>>Reference Number</option>
                        <option value="client" <?php echo $sort === 'client' ? 'selected' : ''; ?>>Client</option>
                        <option value="project" <?php echo $sort === 'project' ? 'selected' : ''; ?>>Project</option>
                        <option value="date" <?php echo $sort === 'date' ? 'selected' : ''; ?>>Date Requested</option>
                        <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>Status</option>
                    </select>
                    
                    <select name="order" id="order" onchange="updateSort()">
                        <option value="asc" <?php echo $order === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="desc" <?php echo $order === 'desc' ? 'selected' : ''; ?>>Descending</option>
                    </select>
                </div>
                
                <div class="records-selector">
                    <span>Show:</span>
                    <select onchange="window.location.href='?page=1&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&limit='+this.value">
                        <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="number-column">No.</th>
                            <th>Reference Number</th>
                            <th>Client</th>
                            <th>Project</th>
                            <th>Date Requested</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result->num_rows > 0): ?>
                            <?php 
                            $counter = $offset + 1;
                            while ($row = $result->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td class="number-column"><?php echo $counter++; ?></td>
                                    <td><strong style="color: #0062a9;"><?php echo htmlspecialchars($row['reference_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['client']); ?></td>
                                    <td><?php echo htmlspecialchars($row['project']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                                    <td>
                                        <?php 
                                        $status = $row['status'];
                                        $status_class = '';
                                        if ($status == 'Pending') {
                                            $status_class = 'status-pending';
                                        } elseif ($status == 'In Progress') {
                                            $status_class = 'status-progress';
                                        } elseif ($status == 'Completed') {
                                            $status_class = 'status-completed';
                                        }
                                        ?>
                                        <span class="status-badge-table <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
<td>
    <div class="action-buttons">
        <!-- Accept/Accepted Button -->
        <?php if ($row['status'] == 'Pending'): ?>
            <a href="?accept=1&reference_number=<?php echo urlencode($row['reference_number']); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>&page=<?php echo $page; ?>&limit=<?php echo $limit; ?>" 
            class="action-btn accept-btn" 
            onclick="return confirm('Accept this request? Status will be changed to In Progress.');">
                <i class="fas fa-check-circle"></i> Accept
            </a>
        <?php else: ?>
            <span class="action-btn accepted-btn">
                <i class="fas fa-check-circle"></i> Accepted
            </span>
        <?php endif; ?>
        
        <!-- View Button -->
        <?php if ($row['items_assigned'] == 1): ?>
            <span class="disabled-view" title="View disabled - items already assigned">
                <i class="fas fa-eye-slash"></i> View
            </span>
        <?php else: ?>
            <a href="view_tecrf.php?reference_number=<?php echo urlencode($row['reference_number']); ?>" class="view-btn" title="View request details">
                <i class="fas fa-eye"></i> View
            </a>
        <?php endif; ?>
    </div>
</td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="no-data">
                                    <i class="fa fa-inbox"></i>
                                    <p>No requests found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination and Records Info -->
            <div class="pagination-container">
                <div class="pagination-info">
                    <i class="fa fa-list"></i> Showing <span><?php echo $offset + 1; ?></span> to <span><?php echo min($offset + $limit, $total_records); ?></span> of <span><?php echo $total_records; ?></span> entries
                </div>
                
                <div class="pagination">
                    <!-- First Page -->
                    <?php if ($page > 1): ?>
                        <a href="?page=1&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&limit=<?php echo $limit; ?>" title="First Page">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
                    <?php endif; ?>
                    
                    <!-- Previous Page -->
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&limit=<?php echo $limit; ?>" title="Previous Page">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-angle-left"></i></span>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<span class="dots">...</span>';
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $page) {
                            echo '<span class="active">' . $i . '</span>';
                        } else {
                            echo '<a href="?page=' . $i . '&sort=' . $sort . '&order=' . $order . '&limit=' . $limit . '">' . $i . '</a>';
                        }
                    }
                    
                    if ($end_page < $total_pages) {
                        echo '<span class="dots">...</span>';
                    }
                    ?>
                    
                    <!-- Next Page -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&limit=<?php echo $limit; ?>" title="Next Page">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-angle-right"></i></span>
                    <?php endif; ?>
                    
                    <!-- Last Page -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $total_pages; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&limit=<?php echo $limit; ?>" title="Last Page">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

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
        updateClock();

        // Update sort function
        function updateSort() {
            const sort = document.getElementById('sort').value;
            const order = document.getElementById('order').value;
            window.location.href = '?page=1&sort=' + sort + '&order=' + order + '&limit=<?php echo $limit; ?>';
        }

        // Smooth scroll for horizontal overflow
        const tableContainer = document.querySelector('.table-container');
        if (tableContainer) {
            tableContainer.addEventListener('wheel', (e) => {
                if (e.deltaY !== 0 && tableContainer.scrollWidth > tableContainer.clientWidth) {
                    e.preventDefault();
                    tableContainer.scrollLeft += e.deltaY;
                }
            });
        }
    </script>
</body>
</html>
<?php 
$sql->close();
$conn->close(); 
?>