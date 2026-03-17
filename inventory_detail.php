<?php
include 'session.php';
include 'database.php';

// Check if user is adminStaff or adminIT
if (!isset($_SESSION['userType']) || !in_array($_SESSION['userType'], ['adminStaff', 'adminIT'])) {
    header("location: index.php");
    exit;
}

// Check if inventory ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("location: inventory_list.php");
    exit;
}

$inventory_id = $_GET['id'];

// Fetch inventory item details
$query = "SELECT ip.*, s.store_name, f.floor_name, b.bay_name, u.uom_name
          FROM inventory_product ip
          LEFT JOIN store s ON ip.store_id = s.store_id
          LEFT JOIN floor f ON ip.floor_id = f.floor_id
          LEFT JOIN bay b ON ip.bay_id = b.bay_id
          LEFT JOIN unit_of_measurement u ON ip.uom_id = u.uom_id
          WHERE ip.inventory_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $inventory_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("location: inventory_list.php");
    exit;
}

$item = $result->fetch_assoc();

// Calculate current stock
$current_stock_display = $item['current_stock'];
if ($item['opening_stock'] !== null && $item['stock_out'] !== null) {
    $calculated_current = $item['opening_stock'] - $item['stock_out'];
    $current_stock_display = $calculated_current;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Inventory Detail</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            font-family: Arial, sans-serif;
            color: black;
            font-size: 12px;
        }
        
        #sidebar {
            height: 100%;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #1d3557;
            color: white;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 1;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        #sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        #sidebar-header h2 {
            margin: 0;
            font-size: 12px;
            font-weight: bold;
            color: #a8dadc;
        }

        #sidebar ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        #sidebar ul li {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: background-color 0.3s ease;
        }

        #sidebar ul li:hover {
            background-color: #457b9d;
        }

        #sidebar ul li a {
            color: #f1faee;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 12px;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        #sidebar ul li a:hover {
            color: #a8dadc;
        }

        #sidebar ul li i {
            font-size: 12px;
            margin-right: 10px;
            color: #a8dadc;
            transition: color 0.3s ease;
        }

        #sidebar ul li:hover i {
            color: white;
        }

        #main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 20px;
            background-color: #f4f4f4;
            border-bottom: 1px solid #ddd;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-content img {
            height: 50px;
        }

        .header-content h1 {
            font-size: 12px;
            margin: 0;
        }

        .text-content {
            margin-left: 15px;
        }

        .text-content h4 {
            margin: 0;
            font-size: 12px;
        }

        .text-content p {
            margin: 0;
            font-size: 12px;
            color: #555;
        }

        .clock-container {
            margin-left: auto;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 80px;
            height: 80px;
            position: relative;
        }

        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            background-color: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .back-btn {
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            background-color: #1d3557;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background-color: #457b9d;
        }
        
        .detail-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #1d3557;
        }
        
        .detail-header h2 {
            color: #1d3557;
            margin: 0;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .detail-item {
            background-color: white;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .detail-label {
            font-weight: bold;
            color: #1d3557;
            margin-bottom: 5px;
            font-size: 11px;
            text-transform: uppercase;
        }
        
        .detail-value {
            color: #333;
            font-size: 13px;
            word-break: break-word;
        }
        
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        
        .status-inactive {
            color: #dc3545;
            font-weight: bold;
        }
        
        .stock-warning {
            color: #dc3545;
            font-weight: bold;
        }
        
        .stock-info {
            color: #28a745;
        }
        
        .timestamp-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-top: 20px;
            border-left: 4px solid #1d3557;
        }
        
        .timestamp-title {
            font-weight: bold;
            color: #1d3557;
            margin-bottom: 15px;
            font-size: 13px;
        }
        
        .timestamp-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .detail-grid,
            .timestamp-grid {
                grid-template-columns: 1fr;
            }
            
            #main-content {
                margin-left: 0;
                padding: 10px;
            }
            
            #sidebar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div id="sidebar">
        <div id="sidebar-header">
            <h2><i class="fa fa-bars"></i> Menu</h2>
        </div>
        <ul>
            <li><a href="adminStaff.php"><i class="fa fa-th-large"></i> Dashboard</a></li>
            <li><a href="inventory_list.php"><i class="fa fa-list"></i> Inventory List</a></li>
            <li><a href="approval.php"><i class="fa fa-tasks"></i>Request List</a></li>
            <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div id="main-content">
        <div class="header">
            <div class="header-content">
                <img src="eog.png" alt="EPIC_OG Logo">
                <h1>Admin Staff Dashboard</h1>
                <div class="text-content">
                    <h4>Hi,</h4>
                    <p>Welcome to EPIC OG Inventory Tracking System!</p>
                </div>
                <div class="clock-container">
                    <div class="clock" id="clock"></div>
                </div>
            </div>
        </div>

        <div class="container">
            <a href="inventory_list.php" class="btn back-btn">
                <i class="fa fa-arrow-left"></i> Back to Inventory List
            </a>
            
            <div class="detail-header">
                <h2>Inventory Item Details</h2>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Part ID</div>
                    <div class="detail-value"><?php echo htmlspecialchars($item['part_id']); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Category</div>
                    <div class="detail-value"><?php echo htmlspecialchars($item['category']); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Description</div>
                    <div class="detail-value"><?php echo htmlspecialchars($item['description']); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Size</div>
                    <div class="detail-value"><?php echo htmlspecialchars($item['size'] ?: 'N/A'); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Store</div>
                    <div class="detail-value"><?php echo htmlspecialchars($item['store_name']); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Floor</div>
                    <div class="detail-value"><?php echo htmlspecialchars($item['floor_name']); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Bay</div>
                    <div class="detail-value"><?php echo htmlspecialchars($item['bay_name']); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Location Code</div>
                    <div class="detail-value"><?php echo htmlspecialchars($item['location_code'] ?: 'N/A'); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">UOM</div>
                    <div class="detail-value"><?php echo htmlspecialchars($item['uom_name']); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Unit Price</div>
                    <div class="detail-value">RM <?php echo number_format($item['unit_price'], 2); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Opening Stock</div>
                    <div class="detail-value"><?php echo htmlspecialchars($item['opening_stock'] ?: 'N/A'); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Stock Out</div>
                    <div class="detail-value"><?php echo htmlspecialchars($item['stock_out']); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Current Stock</div>
                    <div class="detail-value <?php echo ($current_stock_display < 0) ? 'stock-warning' : 'stock-info'; ?>">
                        <?php echo $current_stock_display; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value <?php echo ($item['status'] == 1) ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo ($item['status'] == 1) ? 'Active' : 'Inactive'; ?>
                    </div>
                </div>
            </div>
            
            <div class="timestamp-section">
                <div class="timestamp-title">TIMESTAMP INFORMATION</div>
                <div class="timestamp-grid">
                    <div class="detail-item">
                        <div class="detail-label">Created At</div>
                        <div class="detail-value"><?php echo date('Y-m-d H:i:s', strtotime($item['created_at'])); ?></div>
                    </div>
                    
        
                    <div class="detail-item">
                        <div class="detail-label">Updated At</div>
                        <div class="detail-value"><?php echo $item['updated_at'] ? date('Y-m-d H:i:s', strtotime($item['updated_at'])) : 'N/A'; ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Updated By</div>
                        <div class="detail-value"><?php echo htmlspecialchars($item['updated_by'] ?: 'N/A'); ?></div>
                    </div>
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
    </script>
</body>
</html>