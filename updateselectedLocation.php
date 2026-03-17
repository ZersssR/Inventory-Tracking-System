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

// Check for selected location
$selected_location = $_GET['location'] ?? null;

// Fetch new location options
$new_location_query = "SELECT DISTINCT loadout_location FROM product 
          WHERE action_notice_no IS NOT NULL AND action_notice_no != ''
          AND loadout_date IS NOT NULL AND loadout_date != ''
          AND loadout_location IS NOT NULL AND loadout_location != ''";
$new_location_result = $conn->query($new_location_query);

// Fetch unique descriptions for the dropdown filter
$description_query = "SELECT DISTINCT description FROM product WHERE loadout_location = ?";
$description_stmt = $conn->prepare($description_query);
$description_stmt->bind_param("s", $selected_location);
$description_stmt->execute();
$description_result = $description_stmt->get_result();

// Fetch items by description if description filter is applied
$description_filter = $_GET['description'] ?? '';
$item_query = "SELECT description, unit_id, tec_expiry FROM product WHERE loadout_location = ? AND description LIKE ?";
$stmt = $conn->prepare($item_query);
$search_description = "%" . $description_filter . "%";
$stmt->bind_param("ss", $selected_location, $search_description);
$stmt->execute();
$item_result = $stmt->get_result();

// Get current date for display
$current_date = date('d/m/Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Update Selected Location | EPIC OG</title>
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

.card-title .main-icon {
    font-size: 1.5rem;
    color: #0062a9;
    background: #e8f3f7;
    padding: 12px;
    border-radius: 14px;
}

.card-title .location-badge i {
    font-size: 0.9rem;  /* Normal size for badge icon */
    background: none;    /* Remove background */
    padding: 0;         /* Remove padding */
    border-radius: 0;   /* Remove border radius */
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
            cursor: pointer;
        }

        .back-btn:hover {
            background: #0062a9;
            color: white;
            transform: translateX(-5px);
        }

        /* Filter Section */
        .filter-section {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }

        .filter-section label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e293b;
            font-size: 0.9rem;
        }

        .filter-section select {
            width: 100%;
            max-width: 400px;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            color: #1e293b;
            background: white;
            cursor: pointer;
            outline: none;
            transition: all 0.2s ease;
        }

        .filter-section select:hover {
            border-color: #0062a9;
        }

        .filter-section select:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 3px rgba(0, 98, 169, 0.1);
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e293b;
            font-size: 0.95rem;
        }

        .form-group select {
            width: 100%;
            max-width: 500px;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 1rem;
            color: #1e293b;
            background: white;
            cursor: pointer;
            outline: none;
            transition: all 0.3s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%230062a9' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            background-size: 16px;
        }

        .form-group select:hover {
            border-color: #0062a9;
        }

        .form-group select:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 3px rgba(0, 98, 169, 0.1);
        }

        /* Table Container */
        .table-container {
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin: 20px 0;
        }

        .scrollable-table {
            max-height: 400px;
            overflow-y: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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
            position: sticky;
            top: 0;
            background-color: #f8fafc;
            z-index: 5;
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

        /* Checkbox Styling */
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #0062a9;
        }

        /* Button Container */
        .button-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .btn-update {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 32px;
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

        .btn-update:hover {
            background: #004d88;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 98, 169, 0.4);
        }

        .btn-update i {
            font-size: 1.1rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 24px;
            width: 90%;
            max-width: 450px;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }

        .modal-content h3 {
            margin-bottom: 25px;
            font-size: 1.2rem;
            color: #1e293b;
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .btn-confirm, .btn-cancel {
            padding: 12px 30px;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-confirm {
            background-color: #0062a9;
            color: white;
        }

        .btn-confirm:hover {
            background-color: #004d88;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 98, 169, 0.3);
        }

        .btn-cancel {
            background-color: #ef4444;
            color: white;
        }

        .btn-cancel:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 15px;
        }

        .empty-state p {
            font-size: 1rem;
        }

        /* Location Badge */
        .location-badge {
            display: inline-block;
            background: #e8f3f7;
            color: #0062a9;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-left: 15px;
            border: 1px solid #0062a9;
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

        // Function to show the modal
        function showModal() {
            const modal = document.getElementById('confirmationModal');
            modal.style.display = 'block';
            return false; // Prevent immediate form submission
        }

        // Function to hide the modal
        function hideModal() {
            const modal = document.getElementById('confirmationModal');
            modal.style.display = 'none';
        }

        // Function to confirm submission
        function confirmSubmit() {
            document.getElementById('updateForm').submit(); // Submit the form
        }
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
                    <h2>Update Items from 
                        <span class="location-badge">
                            <i class="fa-solid fa-map-pin"></i> 
                            <?php echo htmlspecialchars($selected_location); ?>
                        </span>
                    </h2>
                </div>
                <div>
                    <button onclick="window.location.href='updateLocation.php'" class="back-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Locations
                    </button>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form action="updateselectedLocation.php" method="get">
                    <input type="hidden" name="location" value="<?php echo htmlspecialchars($selected_location); ?>">
                    <label for="description">
                        <i class="fa-solid fa-filter" style="color: #0062a9; margin-right: 8px;"></i>
                        Filter by Description:
                    </label>
                    <select name="description" id="description" onchange="this.form.submit()">
                        <option value="">All Descriptions</option>
                        <?php while ($desc = $description_result->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($desc['description']); ?>" <?php if ($description_filter == $desc['description']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($desc['description']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>

            <!-- Update Form -->
            <form id="updateForm" action="process_update.php" method="post" onsubmit="return showModal();">
                <input type="hidden" name="location" value="<?php echo htmlspecialchars($selected_location); ?>">
                
                <div class="form-group">
                    <label for="new_location">
                        <i class="fa-solid fa-location-arrow" style="color: #0062a9; margin-right: 8px;"></i>
                        Select New Location:
                    </label>
                    <select name="new_location" id="new_location" required>
                        <option value="" disabled selected>-- Choose new location --</option>
                        <?php
                        while ($row = $new_location_result->fetch_assoc()) {
                            $location = $row['loadout_location'];
                            $selected = ($location == ($_GET['new_location'] ?? '')) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($location) . "' $selected>" . htmlspecialchars($location) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <h3 style="margin-bottom: 15px; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-boxes" style="color: #0062a9;"></i>
                    Select Items to Update:
                </h3>

                <div class="table-container">
                    <div class="scrollable-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Description</th>
                                    <th>Unit ID</th>
                                    <th>Expiry Date</th>
                                    <th>Select</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($item_result->num_rows > 0) {
                                    $counter = 1;
                                    while ($item = $item_result->fetch_assoc()): ?>
                                        <tr>
                                            <td style="font-weight: 500; color: #0062a9;"><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                                            <td><code><?php echo htmlspecialchars($item['unit_id']); ?></code></td>
                                            <td>
                                                <i class="fa-regular fa-calendar" style="color: #64748b; margin-right: 5px;"></i>
                                                <?php echo htmlspecialchars($item['tec_expiry']); ?>
                                            </td>
                                            <td>
                                                <input type="checkbox" name="selected_items[]" value="<?php echo htmlspecialchars($item['unit_id']); ?>">
                                            </td>
                                        </tr>
                                    <?php endwhile; 
                                } else { ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">
                                            <i class="fa-regular fa-box-open"></i>
                                            <p>No items found in this location</p>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($item_result->num_rows > 0) : ?>
                    <div class="button-container">
                        <button type="submit" class="btn-update">
                            <i class="fa-solid fa-rotate"></i>
                            Update Selected Items
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Modal -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <i class="fa-solid fa-circle-question" style="font-size: 3rem; color: #0062a9; margin-bottom: 15px;"></i>
            <h3>Are you sure you want to update the location for selected items?</h3>
            <div class="modal-buttons">
                <button class="btn-confirm" onclick="confirmSubmit()">
                    <i class="fa-solid fa-check"></i> Yes, Update
                </button>
                <button class="btn-cancel" onclick="hideModal()">
                    <i class="fa-solid fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>