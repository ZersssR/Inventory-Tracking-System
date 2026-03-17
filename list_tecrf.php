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

// Default sorting
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Validate sorting options to prevent SQL injection
$allowed_sort_columns = ['reference_number', 'date', 'client', 'project', 'status', 'created_at'];
$allowed_sort_orders = ['asc', 'desc'];

if (!in_array($sort_column, $allowed_sort_columns)) {
    $sort_column = 'created_at';
}
if (!in_array($sort_order, $allowed_sort_orders)) {
    $sort_order = 'desc';
}

// Fetch data from the database with sorting and filtering by user_id
$sql = "SELECT tecrf_id, reference_number, client, project, date, status, created_at 
        FROM tecrf 
        WHERE user_id = ? 
        ORDER BY $sort_column $sort_order";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Get current date for display
$current_date = date('d/m/Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>My Requests | EPIC OG</title>
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

        /* Sorting Form */
        .sorting-controls {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .sort-group {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8fafc;
            padding: 8px 15px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .sort-group label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .sort-group select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #1e293b;
            background: white;
            cursor: pointer;
            outline: none;
            transition: all 0.2s ease;
        }

        .sort-group select:hover {
            border-color: #0062a9;
        }

        .sort-group select:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 3px rgba(0, 98, 169, 0.1);
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

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            gap: 6px;
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

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: #0062a9;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-view:hover {
            background: #004d88;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 98, 169, 0.2);
        }

        .btn-view i {
            font-size: 0.9rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 15px;
        }

        .empty-state p {
            font-size: 1rem;
            margin-bottom: 20px;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #0062a9;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #004d88;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 98, 169, 0.2);
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
            .sorting-controls {
                width: 100%;
            }
            .sort-group {
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

        /* Date cell */
        .date-cell {
            color: #64748b;
            font-size: 0.85rem;
        }

        /* Reference link */
        .ref-link {
            color: #0062a9;
            text-decoration: none;
            font-weight: 500;
        }

        .ref-link:hover {
            text-decoration: underline;
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
                <li class="active">
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
                        <div class="title-badge">My Requests</div>
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
                    <i class="fa-solid fa-list-check"></i>
                    <h2>TECRF Requests List</h2>
                </div>
                <div>
                    <a href="customer_dashboard.php" class="back-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Sorting Form -->
            <form method="GET" class="sorting-controls">
                <div class="sort-group">
                    <label for="sort">Sort by:</label>
                    <select name="sort" id="sort" onchange="this.form.submit()">
                        <option value="reference_number" <?php if ($sort_column == 'reference_number') echo 'selected'; ?>>Reference Number</option>
                        <option value="client" <?php if ($sort_column == 'client') echo 'selected'; ?>>Client</option>
                        <option value="project" <?php if ($sort_column == 'project') echo 'selected'; ?>>Project</option>
                        <option value="date" <?php if ($sort_column == 'date') echo 'selected'; ?>>Date Requested</option>
                        <option value="status" <?php if ($sort_column == 'status') echo 'selected'; ?>>Status</option>
                        <option value="created_at" <?php if ($sort_column == 'created_at') echo 'selected'; ?>>Created Date</option>
                    </select>
                </div>

                <div class="sort-group">
                    <label for="order">Order:</label>
                    <select name="order" id="order" onchange="this.form.submit()">
                        <option value="asc" <?php if ($sort_order == 'asc') echo 'selected'; ?>>Ascending</option>
                        <option value="desc" <?php if ($sort_order == 'desc') echo 'selected'; ?>>Descending</option>
                    </select>
                </div>
            </form>

            <!-- Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No.</th> <!-- ADDED: No. column -->
                            <th>Reference Number</th>
                            <th>Client</th>
                            <th>Project</th>
                            <th>Date Request</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0) : ?>
                            <?php 
                            // ADDED: Counter variable for row numbers
                            $row_number = 1; 
                            ?>
                            <?php while ($row = $result->fetch_assoc()) : ?>
                                <tr>
                                    <td style="font-weight: 500; color: #0062a9;"><?php echo $row_number++; ?></td> <!-- ADDED: Row number -->
                                    <td>
                                        <a href="display_tecrf.php?reference_number=<?php echo urlencode($row['reference_number']); ?>" class="ref-link">
                                            <i class="fa-regular fa-file-lines" style="margin-right: 6px;"></i>
                                            <?php echo htmlspecialchars($row['reference_number']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['client']); ?></td>
                                    <td><?php echo htmlspecialchars($row['project']); ?></td>
                                    <td class="date-cell">
                                        <i class="fa-regular fa-calendar" style="margin-right: 6px;"></i>
                                        <?php echo date('d/m/Y', strtotime($row['date'])); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_icon = '';
                                        switch(strtolower($row['status'])) {
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
                                            default:
                                                $status_class = 'status-pending';
                                                $status_icon = 'fa-regular fa-clock';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <i class="<?php echo $status_icon; ?>"></i>
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="display_tecrf.php?reference_number=<?php echo urlencode($row['reference_number']); ?>" class="btn-view">
                                                <i class="fa-regular fa-eye"></i>
                                                View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="7" class="empty-state"> <!-- CHANGED: colspan from 6 to 7 -->
                                    <i class="fa-regular fa-folder-open"></i>
                                    <p>No TECRF requests found</p>
                                    <a href="tecrf.php" class="btn-primary">
                                        <i class="fa-solid fa-plus"></i>
                                        Create Your First Request
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Summary -->
            <?php if ($result->num_rows > 0) : ?>
                <div style="margin-top: 20px; text-align: right; color: #64748b; font-size: 0.85rem;">
                    <i class="fa-regular fa-file-lines"></i>
                    Total: <?php echo $result->num_rows; ?> request(s)
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>