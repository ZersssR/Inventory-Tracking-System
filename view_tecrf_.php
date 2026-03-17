<?php
include 'session.php';
include 'database.php';

if (!isset($_SESSION['tecrf_data'])) {
    header("Location: tecrf.php");
    exit();
}

if (!$conn) {
    die("Database connection failed in view_tecrf_.php");
}

$tecrf_data = $_SESSION['tecrf_data'];

// Use existing reference number from session
if (!isset($_SESSION['reference_number'])) {
    $year = date('Y');
    $month = date('m');
    
    $sql = "SELECT reference_number FROM tecrf 
            WHERE reference_number LIKE 'TECRF/$year/$month/%' 
            ORDER BY reference_number DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $last_ref = $result->fetch_assoc()['reference_number'];
        $parts = explode('/', $last_ref);
        
        if (count($parts) === 4) {
            $last_num = intval($parts[3]);
            $new_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $new_num = '0001';
        }
    } else {
        $new_num = '0001';
    }
    
    $_SESSION['reference_number'] = "TECRF/$year/$month/$new_num";
}

$reference_number = $_SESSION['reference_number'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Review TECRF | EPIC OG</title>
    <!-- Fonts and Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        /* Modern Sidebar - Sama macam tecrf.php */
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
            width: calc(100% - 280px);
        }

        /* Header */
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

        /* Container */
        .container {
            width: 100%;
            max-width: 1300px;
            padding: 30px;
            margin: 0 auto;
            background-color: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 98, 169, 0.15);
            border: 1px solid rgba(0, 98, 169, 0.1);
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid #e9ecef;
        }

        .header-container img {
            height: 50px;
        }

        h1 {
            text-align: center;
            font-size: 18px;
            margin: 0;
            padding: 0;
            color: #0062a9;
            font-weight: 600;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 25px;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #dee2e6;
        }

        th, td {
            padding: 14px 12px;
            text-align: left;
            font-size: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            background-color: white;
            color: #212529;
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* Summary Box */
        .summary-box {
            background: #f8fafc;
            padding: 20px 25px;
            border-radius: 16px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .summary-box h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #0062a9;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid #0062a9;
            padding-bottom: 8px;
        }

        .summary-item {
            margin-bottom: 10px;
        }

        .summary-label {
            font-weight: 600;
            color: #495057;
            font-size: 12px;
        }

        .summary-value {
            color: #212529;
            font-size: 13px;
            padding: 4px 0;
        }

        .reference-number {
            font-weight: bold;
            color: #0062a9;
            font-size: 14px;
            padding: 4px 10px;
            background-color: #e8f0fe;
            border-radius: 20px;
            display: inline-block;
        }

        /* Buttons */
        .btn {
            color: #ffffff;
            background: #0062a9;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            cursor: pointer;
            margin: 5px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 4px 8px rgba(0, 98, 169, 0.2);
        }

        .btn:hover {
            background: #004d88;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 98, 169, 0.3);
        }

        .btn-submit {
            background: #28a745;
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
        }

        .btn-submit:hover {
            background: #218838;
        }

        .btn-back {
            background: #6c757d;
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);
        }

        .btn-back:hover {
            background: #5a6268;
        }

        .btn-container {
            text-align: center;
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        /* Back button */
        .back-btn {
            display: inline-block;
            padding: 10px 22px;
            color: #ffffff;
            background: #0062a9;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 10px rgba(0, 98, 169, 0.2);
        }

        .back-btn:hover {
            background: #004d88;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 98, 169, 0.3);
        }

        .back-btn i {
            margin-right: 8px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            #main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
            
            .container {
                width: 100%;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #e9ecef;
        }

        ::-webkit-scrollbar-thumb {
            background: #adb5bd;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #6c757d;
        }

        /* Header info table (same as tecrf.php) */
        .header-container table {
            width: auto;
            margin-left: 20px;
            box-shadow: none;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            margin-bottom: 0;
        }

        .header-container table td {
            padding: 8px 15px;
            background: white;
            border-bottom: 1px solid #e9ecef;
        }

        .header-container table tr:last-child td {
            border-bottom: none;
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
                <li>
                    <a href="customer_dashboard.php">
                        <i class="fa fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="active">
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
                        <div class="title-badge">Review TECRF</div>
                    </div>
                </div>
                <div class="header-right">
                    <div class="welcome-row">
                        <h4>Welcome back, <?php 
                            $user_id = $_SESSION['user_id'] ?? 0;
                            $user_sql = "SELECT full_name FROM users WHERE user_id = ?";
                            $stmt = $conn->prepare($user_sql);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $user_result = $stmt->get_result();
                            $user_data = $user_result->fetch_assoc();
                            echo htmlspecialchars($user_data['full_name'] ?? 'User');
                        ?></h4>
                        <span>👋</span>
                    </div>
                    <p>EPIC OG Inventory Tracking System</p>
                </div>
                <div class="clock-container">
                    <div class="clock" id="clock"></div>
                </div>
            </div>
        </div>

        <a href="tecrf.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Edit</a>
        
        <div class="container">
            <div class="header-container">
                <img src="eog.jpg" alt="Logo">
                <h1>TECRF Request Summary</h1>
                <table>
                    <tr>
                        <td>Reference No.:</td>
                        <td><strong style="color: #0062a9;"><?php echo $reference_number; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Date:</td>
                        <td><label id="dateLabel">Current date</label></td>
                    </tr>
                </table>
            </div>
            
            <!-- Summary Information -->
            <div class="summary-box">
                <h4><i class="fas fa-info-circle" style="margin-right: 8px;"></i>Request Information</h4>
                
                <!-- Using CSS Grid for a cleaner, more organized layout -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <!-- Column 1 -->
                    <div>
                        <div class="summary-item">
                            <div class="summary-label">Date Required:</div>
                            <div class="summary-value"><?php echo htmlspecialchars($tecrf_data['date_required']); ?></div>
                        </div>
                        <div class="summary-item" style="margin-top: 15px;">
                            <div class="summary-label">Client:</div>
                            <div class="summary-value"><?php echo htmlspecialchars($tecrf_data['client']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Column 2 -->
                    <div>
                        <div class="summary-item">
                            <div class="summary-label">Charge Code:</div>
                            <div class="summary-value"><?php echo htmlspecialchars($tecrf_data['charge_code']); ?></div>
                        </div>
                        <div class="summary-item" style="margin-top: 15px;">
                            <div class="summary-label">Project:</div>
                            <div class="summary-value"><?php echo htmlspecialchars($tecrf_data['project']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Column 3 -->
                    <div>
                        <div class="summary-item">
                            <div class="summary-label">Location:</div>
                            <div class="summary-value"><?php echo htmlspecialchars($tecrf_data['location']); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Optional: Add a subtle divider if you want to show other fields -->
                <?php if (!empty($tecrf_data['client_other']) || !empty($tecrf_data['project_other']) || !empty($tecrf_data['location_other'])): ?>
                <hr style="margin: 20px 0 10px; border: 0; border-top: 1px dashed #dee2e6;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; padding-top: 5px;">
                    <?php if (!empty($tecrf_data['client_other'])): ?>
                    <div class="summary-item">
                        <div class="summary-label">Other Client:</div>
                        <div class="summary-value" style="color: #fd7e14;"><?php echo htmlspecialchars($tecrf_data['client_other']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($tecrf_data['project_other'])): ?>
                    <div class="summary-item">
                        <div class="summary-label">Other Project:</div>
                        <div class="summary-value" style="color: #fd7e14;"><?php echo htmlspecialchars($tecrf_data['project_other']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($tecrf_data['location_other'])): ?>
                    <div class="summary-item">
                        <div class="summary-label">Other Location:</div>
                        <div class="summary-value" style="color: #fd7e14;"><?php echo htmlspecialchars($tecrf_data['location_other']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Items from session -->
            <h4 style="color: #0062a9; font-weight: 600; margin-bottom: 15px; font-size: 14px;">
                <i class="fas fa-list" style="margin-right: 8px;"></i>Requested Items
            </h4>
            <table class="item-list">
                <thead>
                    <tr>
                        <th>NO</th>
                        <th>Description</th>
                        <th>Size</th>
                        <th>UOM</th>
                        <th>Floor</th>
                        <th>Bay</th>
                        <th>Location</th>
                        <th>Current Stock</th>
                        <th>Request Quantity</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (!empty($tecrf_data['descriptions'])) {
                        $itemCount = count($tecrf_data['descriptions']);
                        
                        for ($i = 0; $i < $itemCount; $i++) {
                            echo '<tr>';
                            echo '<td><strong>' . ($i + 1) . '</strong></td>';
                            echo '<td>' . htmlspecialchars($tecrf_data['descriptions'][$i]) . '</td>';
                            echo '<td>' . htmlspecialchars($tecrf_data['sizes'][$i] ?? '-') . '</td>';
                            echo '<td>' . htmlspecialchars($tecrf_data['uoms'][$i] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($tecrf_data['floors'][$i] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($tecrf_data['bays'][$i] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($tecrf_data['location_codes'][$i] ?? '') . '</td>';
                            echo '<td><span style="font-weight: 600; color: #0062a9;">' . htmlspecialchars($tecrf_data['current_stocks'][$i] ?? 0) . '</span></td>';
                            echo '<td><span style="font-weight: 600;">' . htmlspecialchars($tecrf_data['req_qtys'][$i]) . '</span></td>';
                            echo '<td>' . htmlspecialchars($tecrf_data['remarks'][$i] ?? '') . '</td>';
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
            
            <div class="btn-container">
                <!-- Form untuk submit final -->
                <form action="tecrf.php" method="post" id="submitForm" style="display: inline;">
                    <input type="hidden" name="final_submit" value="1">
                    <input type="hidden" name="reference_number" value="<?php echo $reference_number; ?>">
                    
                    <!-- Main data -->
                    <input type="hidden" name="date_required" value="<?php echo htmlspecialchars($tecrf_data['date_required']); ?>">
                    <input type="hidden" name="client" value="<?php echo htmlspecialchars($tecrf_data['original_client'] ?? $tecrf_data['client']); ?>">
                    <input type="hidden" name="project" value="<?php echo htmlspecialchars($tecrf_data['original_project'] ?? $tecrf_data['project']); ?>">
                    <input type="hidden" name="location" value="<?php echo htmlspecialchars($tecrf_data['original_location'] ?? $tecrf_data['location']); ?>">
                    <input type="hidden" name="charge_code" value="<?php echo htmlspecialchars($tecrf_data['charge_code']); ?>">
                    
                    <!-- Other values -->
                    <?php if (isset($tecrf_data['client_other']) && !empty($tecrf_data['client_other'])): ?>
                    <input type="hidden" name="client_other" value="<?php echo htmlspecialchars($tecrf_data['client_other']); ?>">
                    <?php endif; ?>
                    
                    <?php if (isset($tecrf_data['project_other']) && !empty($tecrf_data['project_other'])): ?>
                    <input type="hidden" name="project_other" value="<?php echo htmlspecialchars($tecrf_data['project_other']); ?>">
                    <?php endif; ?>
                    
                    <?php if (isset($tecrf_data['location_other']) && !empty($tecrf_data['location_other'])): ?>
                    <input type="hidden" name="location_other" value="<?php echo htmlspecialchars($tecrf_data['location_other']); ?>">
                    <?php endif; ?>
                    
                    <!-- Items -->
                    <?php 
                    if (!empty($tecrf_data['descriptions'])) {
                        $itemCount = count($tecrf_data['descriptions']);
                        
                        for ($i = 0; $i < $itemCount; $i++) {
                            echo '<input type="hidden" name="description[]" value="' . htmlspecialchars($tecrf_data['descriptions'][$i]) . '">';
                            echo '<input type="hidden" name="size[]" value="' . htmlspecialchars($tecrf_data['sizes'][$i] ?? '') . '">';
                            echo '<input type="hidden" name="request_quantity[]" value="' . htmlspecialchars($tecrf_data['req_qtys'][$i]) . '">';
                            echo '<input type="hidden" name="uom[]" value="' . htmlspecialchars($tecrf_data['uoms'][$i] ?? '') . '">';
                            echo '<input type="hidden" name="remarks[]" value="' . htmlspecialchars($tecrf_data['remarks'][$i] ?? '') . '">';
                        }
                    }
                    ?>
                    
                    <button type="button" class="btn btn-submit" onclick="submitRequest()">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                </form>
                
                <button type="button" class="btn btn-back" onclick="window.location.href='tecrf.php'">
                    <i class="fas fa-edit"></i> Back to Edit
                </button>
            </div>
        </div>
    </div>

    <script>
    function submitRequest() {
        if (confirm('Are you sure you want to submit this request?')) {
            document.getElementById('submitForm').submit();
        }
    }

    function getCurrentDate() {
        const today = new Date();
        const day = String(today.getDate()).padStart(2, '0');
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const year = today.getFullYear();
        return `${day}/${month}/${year}`;
    }

    document.getElementById('dateLabel').textContent = getCurrentDate();
    </script>
</body>
</html>