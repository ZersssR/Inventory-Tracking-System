<?php
include 'session.php';
include 'database.php';

// Check if user is adminStaff or adminIT
if (!isset($_SESSION['userType']) || !in_array($_SESSION['userType'], ['adminStaff', 'adminIT'])) {
    header("location: index.php");
    exit;
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Get the selected location from the previous page
$selected_location = isset($_GET['location']) ? $_GET['location'] : '';

// Fetch the items (description and unit_id) based on the selected location
$query = "SELECT * FROM product 
          WHERE loadout_location = ?
          AND loadout_date IS NOT NULL  
          AND action_notice_no IS NOT NULL
          AND action_notice_no <> ''";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $selected_location);
$stmt->execute();
$result = $stmt->get_result();

// Fetch distinct descriptions for the filter dropdown
$distinct_query = "SELECT DISTINCT description FROM product 
                   WHERE loadout_location = ? 
                   AND loadout_date IS NOT NULL 
                   AND action_notice_no IS NOT NULL 
                   ORDER BY description ASC";
$distinct_stmt = $conn->prepare($distinct_query);
$distinct_stmt->bind_param("s", $selected_location);
$distinct_stmt->execute();
$distinct_result = $distinct_stmt->get_result();

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
    <title>Receive Items | EPIC OG</title>
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

        .container-header h2 {
            color: #1e293b;
            font-size: 1.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .container-header h2 i {
            color: #0062a9;
            font-size: 2rem;
        }

        .location-badge {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* Original RECEIVE_ITEMS.php styles preserved below */
        h3 {
            color: #1e293b;
            font-size: 1.3rem;
            margin: 25px 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

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

        .form-group {
            margin-bottom: 20px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid rgba(0, 98, 169, 0.1);
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .form-group input[type="text"],
        .form-group input[type="date"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="date"]:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 4px rgba(0, 98, 169, 0.1);
        }

        .filter-container {
            background: #f8fafc;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid rgba(0, 98, 169, 0.1);
            margin-bottom: 20px;
        }

        .filter-container label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .filter-container select,
        #unitIdSearch {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            outline: none;
            background: white;
        }

        .filter-container select:focus,
        #unitIdSearch:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 4px rgba(0, 98, 169, 0.1);
        }

        .scrollable-table {
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 16px;
            margin: 20px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
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

        .checkbox-large {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #0062a9;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            background: linear-gradient(135deg, #0062a9 0%, #0088cc 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.2);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(0, 98, 169, 0.3);
        }

        .submit {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
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

        function filterTable() {
            const descriptionFilter = document.getElementById("descriptionFilter").value.toLowerCase();
            const unitIdSearch = document.getElementById("unitIdSearch").value.toLowerCase();
            const table = document.getElementById("itemTable");
            const rows = table.getElementsByTagName("tr");

            for (let i = 1; i < rows.length; i++) {
                const descriptionCell = rows[i].getAttribute("data-description").toLowerCase();
                const unitIdCell = rows[i].cells[2].innerText.toLowerCase();

                const matchesDescription = !descriptionFilter || descriptionCell.includes(descriptionFilter);
                const matchesUnitId = !unitIdSearch || unitIdCell.includes(unitIdSearch);

                rows[i].style.display = (matchesDescription && matchesUnitId) ? "" : "none";
            }
        }

        $(document).ready(function() {
            $("#receiveForm").submit(function(event) {
                event.preventDefault();

                $.ajax({
                    type: "POST",
                    url: "process_receive.php",
                    data: $(this).serialize(),
                    success: function(response) {
                        alert('Items have been successfully received!');
                        window.location.href = "select_location.php";
                    },
                    error: function() {
                        alert('There was an error processing your request. Please try again.');
                    }
                });
            });
        });
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
                        <div class="title-badge">Receive Items</div>
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
                    <i class="fa fa-undo-alt"></i>
                    Receive Items
                </h2>
                <div class="location-badge">
                    <i class="fa fa-map-marker-alt"></i>
                    Location: <?php echo htmlspecialchars($selected_location); ?>
                </div>
            </div>

            <!-- Back Button -->
            <a href="select_location.php" class="back-btn">
                <i class="fa fa-arrow-left"></i> Back to Locations
            </a>

            <form id="receiveForm">
                <div class="form-group">
                    <label for="backload_sheet_no">Backload Sheet No:</label>
                    <input type="text" id="backload_sheet_no" name="backload_sheet_no" required>
                </div>

                <div class="form-group">
                    <label for="backload_date">Backload Date:</label>
                    <input type="date" id="backload_date" name="backload_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <input type="hidden" name="location" value="<?php echo htmlspecialchars($selected_location); ?>">

                <div class="filter-container">
                    <label for="descriptionFilter">Filter by Description:</label>
                    <select id="descriptionFilter" onchange="filterTable()">
                        <option value="">All Items</option>
                        <?php while ($desc_row = mysqli_fetch_assoc($distinct_result)) { ?>
                            <option value="<?php echo strtolower(htmlspecialchars($desc_row['description'])); ?>">
                                <?php echo htmlspecialchars($desc_row['description']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="filter-container">
                    <label for="unitIdSearch">Search by Unit ID:</label>
                    <input type="text" id="unitIdSearch" onkeyup="filterTable()" placeholder="Enter Unit ID">
                </div>

                <h3>
                    <i class="fa fa-boxes"></i>
                    Items to Receive:
                </h3>

                <div class="scrollable-table">
                    <table id="itemTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Description</th>
                                <th>Unit ID</th>
                                <th>Expiry Date</th>
                                <th>Select</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $index = 1;
                            while ($row = mysqli_fetch_assoc($result)) { ?>
                                <tr data-description="<?php echo htmlspecialchars($row['description']); ?>">
                                    <td><strong><?php echo $index++; ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td><span style="color: #0062a9; font-weight: 600;"><?php echo htmlspecialchars($row['unit_id']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['tec_expiry']); ?></td>
                                    <td>
                                        <input type="checkbox" class="checkbox-large" name="items[]" value="<?php echo htmlspecialchars($row['unit_id']); ?>">
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="submit">
                    <button type="submit" class="btn">
                        <i class="fa fa-check-circle"></i> Receive Selected Items
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php
$stmt->close();
$distinct_stmt->close();
$conn->close();
?>