<?php
include 'session.php';
include 'database.php';

// Check if user is adminStaff or adminIT
if (!isset($_SESSION['userType']) || !in_array($_SESSION['userType'], ['adminStaff', 'adminIT'])) {
    header("location: index.php");
    exit;
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $part_id = $_POST['part_id'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    $size = $_POST['size'] ?: NULL;
    $store_id = $_POST['store_id'];
    $floor_id = $_POST['floor_id'];
    $bay_id = $_POST['bay_id'];
    $location_code = $_POST['location_code'] ?: NULL;
    $uom_id = $_POST['uom_id'];
    $unit_price = $_POST['unit_price'];
    $opening_stock = $_POST['opening_stock'] ?: NULL;
    $current_stock = $_POST['current_stock'] ?: 0;
    
    // Handle expire date
    $expire_date = !empty($_POST['expire_date']) ? $_POST['expire_date'] : NULL;

    // Insert into database (updated to include expire_date)
    $stmt = $conn->prepare("INSERT INTO inventory_product (part_id, category, description, size, store_id, floor_id, bay_id, location_code, uom_id, unit_price, opening_stock, current_stock, expire_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssiiisidiss", $part_id, $category, $description, $size, $store_id, $floor_id, $bay_id, $location_code, $uom_id, $unit_price, $opening_stock, $current_stock, $expire_date);

    if ($stmt->execute()) {
        $success = "Inventory item created successfully.";
    } else {
        $error = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch options for dropdowns
$stores = $conn->query("SELECT store_id, store_name FROM store WHERE status = 1");
$floors = $conn->query("SELECT floor_id, floor_name FROM floor");
$bays = $conn->query("SELECT bay_id, bay_name FROM bay");
$uoms = $conn->query("SELECT uom_id, uom_name FROM unit_of_measurement");

// Generate navigation function (copied from adminStaff.php)
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
    <title>Create Inventory Item | EPIC OG</title>
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

        /* New container for logo with white background */
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

        /* Form Container */
        .form-container {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 98, 169, 0.08);
            border: 1px solid rgba(0, 98, 169, 0.1);
            max-width: 900px;
            margin: 0 auto;
        }

        .form-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e8f3f7;
        }

        .form-header i {
            font-size: 2rem;
            color: #0062a9;
            background: #e8f3f7;
            padding: 15px;
            border-radius: 16px;
        }

        .form-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
        }

        .form-header p {
            color: #64748b;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #1e293b;
        }

        label i {
            color: #0062a9;
            margin-right: 6px;
            font-size: 0.9rem;
        }

        .required-star {
            color: #ef4444;
            margin-left: 4px;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s ease;
            background: white;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #0062a9;
            box-shadow: 0 0 0 4px rgba(0, 98, 169, 0.1);
        }

        input:hover, select:hover, textarea:hover {
            border-color: #04548d;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .input-hint {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 5px;
            font-style: italic;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e8f3f7;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: #0062a9;
            color: white;
            box-shadow: 0 8px 16px rgba(0, 98, 169, 0.2);
        }

        .btn-primary:hover {
            background: #04548d;
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(0, 98, 169, 0.3);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #1e293b;
            border: 2px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        .message {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .message i {
            font-size: 1.2rem;
        }

        .success {
            background: #dff0d8;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
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
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-group.full-width {
                grid-column: span 1;
            }
        }

        @media (max-width: 768px) {
            #sidebar {
                transform: translateX(-100%);
            }
            #main-content {
                margin-left: 0;
            }
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            .button-group {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
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
                        <div class="title-badge">Create Inventory Item</div>
                    </div>
                </div>
                <div class="header-right">
                    <div class="welcome-row">
                        <h4>Welcome back, <?= htmlspecialchars($username) ?></h4>
                        <span>👋</span>
                    </div>
                    <p>EPIC OG Inventory Tracking System</p>
                </div>
                <div class="clock-container">
                    <div class="clock" id="clock"></div>
                </div>
            </div>
        </div>

        <div class="form-container">
            <div class="form-header">
                <i class="fa fa-plus-circle"></i>
                <div>
                    <h2>Create New Inventory Item</h2>
                    <p>Fill in the details below to add a new item to the inventory</p>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="message success">
                    <i class="fa fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="message error">
                    <i class="fa fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>
                            <i class="fa fa-hashtag"></i>
                            Part ID <span class="required-star">*</span>
                        </label>
                        <input type="text" id="part_id" name="part_id" required placeholder="Enter part ID">
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fa fa-tag"></i>
                            Category <span class="required-star">*</span>
                        </label>
                        <input type="text" id="category" name="category" required placeholder="Enter category">
                    </div>

                    <div class="form-group full-width">
                        <label>
                            <i class="fa fa-align-left"></i>
                            Description <span class="required-star">*</span>
                        </label>
                        <textarea id="description" name="description" rows="3" required placeholder="Enter item description"></textarea>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fa fa-arrows-alt"></i>
                            Size
                        </label>
                        <input type="text" id="size" name="size" placeholder="Enter size (optional)">
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fa fa-warehouse"></i>
                            Store <span class="required-star">*</span>
                        </label>
                        <select id="store_id" name="store_id" required>
                            <option value="">Select Store</option>
                            <?php while ($row = $stores->fetch_assoc()): ?>
                                <option value="<?php echo $row['store_id']; ?>"><?php echo htmlspecialchars($row['store_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fa fa-layer-group"></i>
                            Floor <span class="required-star">*</span>
                        </label>
                        <select id="floor_id" name="floor_id" required>
                            <option value="">Select Floor</option>
                            <?php while ($row = $floors->fetch_assoc()): ?>
                                <option value="<?php echo $row['floor_id']; ?>"><?php echo htmlspecialchars($row['floor_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fa fa-archive"></i>
                            Bay <span class="required-star">*</span>
                        </label>
                        <select id="bay_id" name="bay_id" required>
                            <option value="">Select Bay</option>
                            <?php while ($row = $bays->fetch_assoc()): ?>
                                <option value="<?php echo $row['bay_id']; ?>"><?php echo htmlspecialchars($row['bay_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fa fa-map-pin"></i>
                            Location Code
                        </label>
                        <input type="text" id="location_code" name="location_code" placeholder="Enter location code">
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fa fa-balance-scale"></i>
                            Unit of Measurement <span class="required-star">*</span>
                        </label>
                        <select id="uom_id" name="uom_id" required>
                            <option value="">Select UOM</option>
                            <?php while ($row = $uoms->fetch_assoc()): ?>
                                <option value="<?php echo $row['uom_id']; ?>"><?php echo htmlspecialchars($row['uom_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fa fa-dollar-sign"></i>
                            Unit Price <span class="required-star">*</span>
                        </label>
                        <input type="number" id="unit_price" name="unit_price" step="0.01" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fa fa-box"></i>
                            Opening Stock
                        </label>
                        <input type="number" id="opening_stock" name="opening_stock" min="0" placeholder="0">
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fa fa-chart-bar"></i>
                            Current Stock
                        </label>
                        <input type="number" id="current_stock" name="current_stock" min="0" value="0" placeholder="0">
                    </div>

                    <div class="form-group full-width">
                        <label>
                            <i class="fa fa-calendar-times"></i>
                            Expire Date
                        </label>
                        <input type="date" id="expire_date" name="expire_date">
                        <div class="input-hint">
                            <i class="fa fa-info-circle"></i>
                            Leave empty if item doesn't expire
                        </div>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-plus"></i>
                        Create Inventory Item
                    </button>
                    <a href="inventory_list.php" class="btn btn-secondary">
                        <i class="fa fa-arrow-left"></i>
                        Back to Inventory List
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>