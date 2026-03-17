<?php
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

// Check if reference_number is passed via GET
if (isset($_GET['reference_number'])) {
    $reference_number = $_GET['reference_number'];
} else {
    echo "Error: Reference number not provided!";
    exit;
}

// Get the main request information from tecrf
// Also verify that this request belongs to the logged-in user
$header_sql = "SELECT tecrf_id, date, client, project, location, date_required, charge_code, status 
               FROM tecrf 
               WHERE reference_number = ? AND user_id = ?";
$header_stmt = $conn->prepare($header_sql);
$header_stmt->bind_param("si", $reference_number, $user_id);
$header_stmt->execute();
$header_result = $header_stmt->get_result();

if (!$header_result || $header_result->num_rows === 0) {
    echo "Error: Request not found or you don't have permission to view it!";
    exit;
}

$header_row = $header_result->fetch_assoc();
$tecrf_id = $header_row['tecrf_id'];

// Get the requested items from tecrf_items
$items_sql = "SELECT description, size, request_quantity, uom, remarks, current_stock, 
                     floor, bay, location_code 
              FROM tecrf_items 
              WHERE tecrf_id = ? 
              ORDER BY tecrf_item_id";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $tecrf_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

// Get assigned items from unit_location_history and product tables
$assigned_sql = "
    SELECT 
        ulh.description,
        ulh.unit_id,
        ulh.loadout_location,
        ulh.action_notice_no,
        ulh.assigned_at,
        p.tec_id,
        p.tec_expiry,
        p.status as certificate_status,
        p.certificate_validity
    FROM unit_location_history ulh
    LEFT JOIN product p ON ulh.unit_id = p.unit_id
    WHERE ulh.action_notice_no = ?
    ORDER BY ulh.assigned_at DESC";

$assigned_stmt = $conn->prepare($assigned_sql);
$assigned_stmt->bind_param("s", $reference_number);
$assigned_stmt->execute();
$assigned_result = $assigned_stmt->get_result();

// Get current date for display
$current_date = date('d/m/Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Request Details | EPIC OG</title>
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
            margin-bottom: 25px;
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

        .card-title h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
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

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: #0062a9;
            color: white;
        }

        .btn-primary:hover {
            background: #004d88;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 98, 169, 0.2);
        }

        .btn-secondary {
            background: white;
            color: #0062a9;
            border: 1px solid #0062a9;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 98, 169, 0.1);
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
        }

        .info-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 500;
            color: #1e293b;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            gap: 8px;
            width: fit-content;
        }

        .status-pending {
            background: #fff3e6;
            color: #f97316;
            border: 1px solid #f97316;
        }

        .status-in-progress {
            background: #e6f0ff;
            color: #0062a9;
            border: 1px solid #0062a9;
        }

        .status-completed {
            background: #e8f7ed;
            color: #22c55e;
            border: 1px solid #22c55e;
        }

        .status-approved {
            background: #f3e8ff;
            color: #8b5cf6;
            border: 1px solid #8b5cf6;
        }

        .status-rejected {
            background: #fee9e9;
            color: #ef4444;
            border: 1px solid #ef4444;
        }

        .status-assigned {
            background: #f3e8ff;
            color: #8b5cf6;
            border: 1px solid #8b5cf6;
        }

        .status-cancel {
            background: #fee9e9;
            color: #ef4444;
            border: 1px solid #ef4444;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        thead th {
            background: #f8fafc;
            color: #1e293b;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 18px 16px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        tbody td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 0.9rem;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Certificate Status */
        .cert-status {
            font-weight: 600;
        }
        
        .status-valid {
            color: #22c55e;
        }
        
        .status-expired {
            color: #ef4444;
        }
        
        .status-near-expiry {
            color: #f97316;
        }

        /* Empty Message */
        .empty-message {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
            font-style: italic;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px dashed #cbd5e1;
        }

        .empty-message i {
            font-size: 2rem;
            color: #94a3b8;
            margin-bottom: 10px;
        }

        /* Reference Number Highlight */
        .ref-highlight {
            background: #0062a9;
            color: white;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 500;
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
            .action-buttons {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media print {
            #sidebar, .header, .back-btn, .action-buttons {
                display: none;
            }
            #main-content {
                margin-left: 0;
                padding: 20px;
            }
            .content-card {
                box-shadow: none;
                border: 1px solid #ddd;
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
                        <div class="title-badge">Request Details</div>
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

        <!-- Request Information Card -->
        <div class="content-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-file-lines"></i>
                    <h2>Request Information</h2>
                </div>
                <div>
                    <span class="ref-highlight">
                        <i class="fa-regular fa-hashtag"></i>
                        <?php echo htmlspecialchars($reference_number); ?>
                    </span>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Reference Number</span>
                    <span class="info-value"><?php echo htmlspecialchars($reference_number); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date Request</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($header_row['date'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date Required</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($header_row['date_required'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Client</span>
                    <span class="info-value"><?php echo htmlspecialchars($header_row['client']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Project</span>
                    <span class="info-value"><?php echo htmlspecialchars($header_row['project']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Location</span>
                    <span class="info-value"><?php echo htmlspecialchars($header_row['location']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Charge Code</span>
                    <span class="info-value"><?php echo htmlspecialchars($header_row['charge_code']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <?php
                        $status = strtolower($header_row['status'] ?? 'pending');
                        $status_class = '';
                        $status_icon = '';
                        
                        switch($status) {
                            case 'pending':
                                $status_class = 'status-pending';
                                $status_icon = 'fa-regular fa-clock';
                                break;
                            case 'in progress':
                                $status_class = 'status-in-progress';
                                $status_icon = 'fa-solid fa-spinner';
                                break;
                            case 'completed':
                                $status_class = 'status-completed';
                                $status_icon = 'fa-solid fa-check-double';
                                break;
                            case 'approved':
                                $status_class = 'status-approved';
                                $status_icon = 'fa-solid fa-check-circle';
                                break;
                            case 'rejected':
                                $status_class = 'status-rejected';
                                $status_icon = 'fa-solid fa-times-circle';
                                break;
                            case 'assigned':
                                $status_class = 'status-assigned';
                                $status_icon = 'fa-solid fa-box-open';
                                break;
                            case 'cancel':
                                $status_class = 'status-cancel';
                                $status_icon = 'fa-solid fa-ban';
                                break;
                            default:
                                $status_class = 'status-pending';
                                $status_icon = 'fa-regular fa-clock';
                        }
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <i class="<?php echo $status_icon; ?>"></i>
                            <?php echo htmlspecialchars(ucfirst($header_row['status'] ?? 'Pending')); ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Requested Items Card -->
        <div class="content-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <h3>Requested Items</h3>
                </div>
            </div>

            <?php if ($items_result->num_rows > 0) : ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Description</th>
                                <th>Size</th>
                                <th>UOM</th>
                                <th>Request Quantity</th>
                                <th>Current Stock</th>
                                <th>Floor</th>
                                <th>Bay</th>
                                <th>Location</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php while ($item = $items_result->fetch_assoc()) : ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                                    <td><?php echo htmlspecialchars($item['size']); ?></td>
                                    <td><?php echo htmlspecialchars($item['uom']); ?></td>
                                    <td><?php echo htmlspecialchars($item['request_quantity']); ?></td>
                                    <td><?php echo htmlspecialchars($item['current_stock']); ?></td>
                                    <td><?php echo htmlspecialchars($item['floor']); ?></td>
                                    <td><?php echo htmlspecialchars($item['bay']); ?></td>
                                    <td><?php echo htmlspecialchars($item['location_code']); ?></td>
                                    <td><?php echo htmlspecialchars($item['remarks']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="empty-message">
                    <i class="fa-regular fa-file-lines"></i>
                    <p>No items requested for this reference number.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Assigned Items Card -->
        <?php if ($assigned_result->num_rows > 0) : ?>
        <div class="content-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-box-open"></i>
                    <h3>Assigned Items</h3>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tagging No</th>
                            <th>Description</th>
                            <th>Loadout Location</th>
                            <th>Action Notice No</th>
                            <th>TEC ID</th>
                            <th>Certificate Expiry</th>
                            <th>Certificate Status</th>
                            <th>Assigned Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php while ($row = $assigned_result->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($row['unit_id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['description'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['loadout_location'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['action_notice_no'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['tec_id'] ?? 'N/A'); ?></td>
                                <td><?php echo $row['tec_expiry'] ? date('d/m/Y', strtotime($row['tec_expiry'])) : 'N/A'; ?></td>
                                <td>
                                    <?php 
                                    $cert_status = strtolower($row['certificate_status'] ?? '');
                                    $cert_class = '';
                                    if (strpos($cert_status, 'expired') !== false) {
                                        $cert_class = 'status-expired';
                                    } elseif (strpos($cert_status, 'valid') !== false) {
                                        $cert_class = 'status-valid';
                                    } elseif (strpos($cert_status, 'near') !== false) {
                                        $cert_class = 'status-near-expiry';
                                    }
                                    ?>
                                    <span class="cert-status <?php echo $cert_class; ?>">
                                        <?php echo htmlspecialchars($row['certificate_status'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if (isset($row['assigned_at']) && $row['assigned_at'] != '0000-00-00 00:00:00') {
                                        echo date('d/m/Y H:i', strtotime($row['assigned_at']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="list_tecrf.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
</body>
</html>

<?php
// Close all statements
if (isset($header_stmt)) $header_stmt->close();
if (isset($items_stmt)) $items_stmt->close();
if (isset($assigned_stmt)) $assigned_stmt->close();
$conn->close();
?>