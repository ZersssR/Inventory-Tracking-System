<?php
include 'session.php';

// Dummy username for demonstration purposes
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

function generateNav($username) {
    return <<<HTML
    <div id="sidebar">
        <div id="sidebar-header">
            <h2>Menu</h2>
            <button id="close-btn" onclick="closeSidebar()">×</button>
        </div>
        <ul>
            <li><a href="list_iitems"><i class="fas fa-list"></i> List Items</a></li>
            <li><a href="#" onclick="showRequisitionOptions()"><i class="fas fa-clipboard"></i> Form Requisition</a></li>
            <li><a href="select_location.php"><i class="fas fa-box-open"></i> Receive Items</a></li>
            <li><a href="view-all_assigned_items.php"><i class="fas fa-box"></i> Issue Out Items</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
HTML;
}

function generateGrid($username) {
    return <<<HTML
    <div class="grid">
        <div class="grid-item" onclick="location.href='list_iitems.php'">
            <div class="icon">📄</div>
            <div class="text">List Item</div>
        </div>
        <div class="grid-item" onclick="showRequisitionOptions()">
            <div class="icon">📝</div>
            <div class="text">Form Requisition</div>
        </div>
        <div class="grid-item" onclick="location.href='view_all_assigned_items.php'">
            <div class="icon">📥</div>
            <div class="text">Receive Item</div>
        </div>
        <div class="grid-item" onclick="location.href='select_location.php'">
            <div class="icon">📤</div>
            <div class="text">Issue Out Items</div>
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
    <link rel="shortcut icon" type="x-icon" href="its.png">
    <title>Staff Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            background-color: grey;
            background-image: url('background1.gif');
            background-size: cover;
            font-family: Arial, sans-serif;
            color: white;
        }
        #sidebar {
            height: 100%;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #333;
            color: white;
            padding-top: 20px;
            transform: translateX(-250px);
            transition: transform 0.3s ease;
            z-index: 1;
        }
        #sidebar.open {
            transform: translateX(0);
        }
        #sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        #sidebar-header h2 {
            margin: 0;
        }
        #close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }
        #sidebar ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        #sidebar ul li {
            padding: 15px;
            border-bottom: 1px solid #444;
        }
        #sidebar ul li a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        #sidebar ul li i {
            font-size: 20px;
            margin-right: 10px;
        }
        #main-content {
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }
        #main-content.with-sidebar {
            margin-left: 250px;
        }
        .header {
            background-color: lightblue;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            gap: 15px;
        }
        .header-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header h1 {
            color: black;
            margin: 0;
        }
        #open-sidebar-btn {
            background: #333;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            font-size: 20px;
        }
        .header img {
            height: 50px;
        }
        .notification-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* Two columns */
            gap: 20px; /* Space between items */
            padding: 20px;
        }
        .grid-item {
            background-color: rgba(0, 0, 0, 0.7);
            padding: 20px;
            border-radius: 10px;
            cursor: pointer;
            text-align: center;
        }
        .grid-item .icon {
            font-size: 35px;
        }
        .grid-item .text {
            margin-top: 10px;
            font-size: 18px;
        }
        /* Modal styling */
        #modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2;
        }
        #modal-content {
            background: white;
            color: black;
            margin: 10% auto;
            padding: 20px;
            width: 50%;
            border-radius: 10px;
            position: relative;
            text-align: center;
        }
        #modal button {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
        }
        .requisition-container {
            display: flex;
            justify-content: space-around;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            max-width: 500px;
            width: 100%;
            margin: auto;
            margin-top: 20px;
        }
        .requisition-box {
            text-align: center;
            width: 45%;
        }
        .requisition-box h2 {
            font-size: 18px;
            color: #333;
        }
        .requisition-box .icon {
            font-size: 40px;
            color: #007bff;
            margin-bottom: 10px;
        }
        .requisition-box a {
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
            font-size: 16px;
            display: block;
            margin-top: 10px;
            transition: color 0.3s ease;
        }
        .requisition-box a:hover {
            color: #0056b3;
        }
    </style>

    <script>
        function showRequisitionOptions() {
            const options = `
                <h2>Choose Requisition Type</h2>
    <div class="requisition-container">
        <div class="requisition-box">
            <a href="purchase_requisition.php">
                <div class="icon">🛒</div>
                <h2>Purchase Requisition</h2>
            </a>
        </div>
        <div class="requisition-box">
            <a href="service_requisition.php">
                <div class="icon">🛠</div>
                <h2>Service Requisition</h2>
            </a>
        </div>
    </div>

                `;
            document.getElementById('modal-content').innerHTML = options;
            document.getElementById('modal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }

        function openSidebar() {
            document.getElementById('sidebar').classList.add('open');
            document.getElementById('main-content').classList.add('with-sidebar');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('main-content').classList.remove('with-sidebar');
        }
    </script>
</head>
<body>
    <?= generateNav($username) ?>
    <div id="main-content">
        <div class="header">
            <button id="open-sidebar-btn" onclick="openSidebar()">☰</button>
            <div class="header-content">
                <img src="eog.jpg" alt="EPIC_OG Logo">
                <h1>Staff Dashboard</h1>
            </div>
            <button class="notification-btn" onclick="location.href='notifications.php'">🔔</button>
        </div>
        <div class="container">
            <h2>Hi, <?= htmlspecialchars($username) ?>!</h2>
            <p>Welcome to EPIC OG Inventory Tracking System!</p>
            <?= generateGrid($username) ?>
        </div>
    </div>

    <div id="modal">
        <div id="modal-content">
            <!-- Requisition options will be injected here -->
        </div>
        <button onclick="closeModal()">Close</button>
    </div>
</body>
</html>
