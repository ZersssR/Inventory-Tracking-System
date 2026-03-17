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
    header('Content-Disposition: attachment; filename="inventory_list_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Build query for export (with search filters if applied)
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Whitelist of allowed search fields for security
    $allowed_search_fields = [
        'part_id', 'category', 'description', 'size', 
        'store_name', 'floor_name', 'bay_name', 'location_code', 
        'uom_name', 'unit_price', 'opening_stock', 'current_stock', 'stock_out'
    ];

    if (!empty($_GET['search_field']) && !empty($_GET['search_value'])) {
        $field = $_GET['search_field'];
        $value = $_GET['search_value'];
        
        if (in_array($field, $allowed_search_fields)) {
            switch ($field) {
                case 'part_id':
                case 'category':
                case 'description':
                case 'size':
                case 'store_name':
                case 'floor_name':
                case 'bay_name':
                case 'location_code':
                case 'uom_name':
                    $table_prefix = in_array($field, ['store_name', 'floor_name', 'bay_name', 'uom_name']) ? substr($field, 0, 1) . '.' : 'ip.';
                    $where_clauses[] = "$table_prefix$field LIKE ?";
                    $params[] = '%' . $value . '%';
                    $types .= 's';
                    break;
                case 'unit_price':
                    $where_clauses[] = "ip.unit_price = ?";
                    $params[] = $value;
                    $types .= 'd';
                    break;
                case 'opening_stock':
                case 'current_stock':
                case 'stock_out':
                    $where_clauses[] = "ip.$field = ?";
                    $params[] = $value;
                    $types .= 'i';
                    break;
            }
        }
    }
    
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    // Export query
    $export_query = "SELECT 
        ip.inventory_id,
        ip.part_id,
        ip.category,
        ip.description,
        COALESCE(ip.size, 'N/A') as size,
        s.store_name,
        f.floor_name,
        b.bay_name,
        COALESCE(ip.location_code, 'N/A') as location_code,
        u.uom_name,
        ip.unit_price,
        COALESCE(ip.opening_stock, 0) as opening_stock,
        COALESCE(ip.stock_out, 0) as stock_out,
        (COALESCE(ip.opening_stock, 0) - COALESCE(ip.stock_out, 0)) as current_stock,
        CASE WHEN ip.status = 1 THEN 'Active' ELSE 'Inactive' END as status,
        ip.created_at,
        ip.updated_at,
        COALESCE(ip.updated_by, 'N/A') as updated_by
        FROM inventory_product ip
        LEFT JOIN store s ON ip.store_id = s.store_id
        LEFT JOIN floor f ON ip.floor_id = f.floor_id
        LEFT JOIN bay b ON ip.bay_id = b.bay_id
        LEFT JOIN unit_of_measurement u ON ip.uom_id = u.uom_id
        $where_sql
        ORDER BY ip.created_at DESC";
    
    $export_stmt = $conn->prepare($export_query);
    if (!empty($params)) {
        $export_stmt->bind_param($types, ...$params);
    }
    $export_stmt->execute();
    $export_result = $export_stmt->get_result();
    
    // Create Excel file
    echo "Inventory ID\tPart ID\tCategory\tDescription\tSize\tStore\tFloor\tBay\tLocation Code\tUOM\tUnit Price\tOpening Stock\tStock Out\tCurrent Stock\tStatus\tCreated Date\tLast Updated\tUpdated By\n";
    
    while ($row = $export_result->fetch_assoc()) {
        echo implode("\t", [
            $row['inventory_id'],
            $row['part_id'],
            $row['category'],
            $row['description'],
            $row['size'],
            $row['store_name'],
            $row['floor_name'],
            $row['bay_name'],
            $row['location_code'],
            $row['uom_name'],
            $row['unit_price'],
            $row['opening_stock'],
            $row['stock_out'],
            $row['current_stock'],
            $row['status'],
            $row['created_at'],
            $row['updated_at'],
            $row['updated_by']
        ]) . "\n";
    }
    exit;
}

// Pagination settings
$items_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Handle update form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_inventory'])) {
    $inventory_id = $_POST['inventory_id'];
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
    $stock_out = $_POST['stock_out'] ?: 0;
    $status = isset($_POST['status']) ? 1 : 0;
    $updated_by = $_SESSION['username'];
    
    // Calculate current stock: opening_stock - stock_out
    $current_stock = ($opening_stock !== null && $opening_stock !== '') ? $opening_stock - $stock_out : 0;

    $update_stmt = $conn->prepare("UPDATE inventory_product SET part_id=?, category=?, description=?, size=?, store_id=?, floor_id=?, bay_id=?, location_code=?, uom_id=?, unit_price=?, opening_stock=?, stock_out=?, current_stock=?, status=?, updated_by=? WHERE inventory_id=?");
    $update_stmt->bind_param("ssssiiisidiiisis", $part_id, $category, $description, $size, $store_id, $floor_id, $bay_id, $location_code, $uom_id, $unit_price, $opening_stock, $stock_out, $current_stock, $status, $updated_by, $inventory_id);

    if ($update_stmt->execute()) {
        $update_success = "Inventory item updated successfully.";
    } else {
        $update_error = "Error: " . $update_stmt->error;
    }
    $update_stmt->close();
}

// Build search query with security fix
$where_clauses = [];
$params = [];
$types = '';

// Whitelist of allowed search fields for security
$allowed_search_fields = [
    'part_id', 'category', 'description', 'size', 
    'store_name', 'floor_name', 'bay_name', 'location_code', 
    'uom_name', 'unit_price', 'opening_stock', 'current_stock', 'stock_out'
];

if (!empty($_GET['search_field']) && !empty($_GET['search_value'])) {
    $field = $_GET['search_field'];
    $value = $_GET['search_value'];
    
    // Validate field against whitelist
    if (in_array($field, $allowed_search_fields)) {
        switch ($field) {
            case 'part_id':
            case 'category':
            case 'description':
            case 'size':
            case 'store_name':
            case 'floor_name':
            case 'bay_name':
            case 'location_code':
            case 'uom_name':
                $table_prefix = in_array($field, ['store_name', 'floor_name', 'bay_name', 'uom_name']) ? substr($field, 0, 1) . '.' : 'ip.';
                $where_clauses[] = "$table_prefix$field LIKE ?";
                $params[] = '%' . $value . '%';
                $types .= 's';
                break;
            case 'unit_price':
                $where_clauses[] = "ip.unit_price = ?";
                $params[] = $value;
                $types .= 'd';
                break;
            case 'opening_stock':
            case 'current_stock':
            case 'stock_out':
                $where_clauses[] = "ip.$field = ?";
                $params[] = $value;
                $types .= 'i';
                break;
        }
    } else {
        // Log invalid search attempt for security monitoring
        error_log("Invalid search field attempt: " . $field . " from IP: " . $_SERVER['REMOTE_ADDR']);
    }
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total 
                FROM inventory_product ip
                LEFT JOIN store s ON ip.store_id = s.store_id
                LEFT JOIN floor f ON ip.floor_id = f.floor_id
                LEFT JOIN bay b ON ip.bay_id = b.bay_id
                LEFT JOIN unit_of_measurement u ON ip.uom_id = u.uom_id
                $where_sql";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_items = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Fetch inventory items with pagination
$query = "SELECT ip.*, s.store_name, f.floor_name, b.bay_name, u.uom_name
          FROM inventory_product ip
          LEFT JOIN store s ON ip.store_id = s.store_id
          LEFT JOIN floor f ON ip.floor_id = f.floor_id
          LEFT JOIN bay b ON ip.bay_id = b.bay_id
          LEFT JOIN unit_of_measurement u ON ip.uom_id = u.uom_id
          $where_sql
          ORDER BY ip.created_at DESC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    // Add pagination parameters
    $types .= 'ii';
    $params[] = $items_per_page;
    $params[] = $offset;
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $items_per_page, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch options for dropdowns
$stores = $conn->query("SELECT store_id, store_name FROM store WHERE status = 1");
$floors = $conn->query("SELECT floor_id, floor_name FROM floor");
$bays = $conn->query("SELECT bay_id, bay_name FROM bay");
$uoms = $conn->query("SELECT uom_id, uom_name FROM unit_of_measurement");

// Generate navigation function - TEST VERSION
function generateNav($username) {
    // Get current page filename
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // FORCE active class for testing
    $inventory_active = 'active'; // Paksa jadi active dulu untuk test
    
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
                <li class="active"> <!-- PAKSA active -->
                    <a href="inventory_list.php">
                        <i class="fa fa-list"></i>
                        <span>Inventory List</span>
                    </a>
                </li>
                <li class="">
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
    <title>Inventory List | EPIC OG</title>
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

        .btn i {
            font-size: 1rem;
        }

        /* Messages */
        .message {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: #e8f7ed;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .message.error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .message i {
            font-size: 1.2rem;
        }

        /* Modern Search Container */
        .search-wrapper {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px -10px rgba(0, 98, 169, 0.15);
            border: 1px solid rgba(0, 98, 169, 0.15);
            position: relative;
            overflow: hidden;
        }

        .search-wrapper::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 200px;
            height: 200px;
            background: rgba(0, 98, 169, 0.03);
            border-radius: 50%;
            pointer-events: none;
        }

        .search-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .search-header i {
            font-size: 1.5rem;
            color: #0062a9;
            background: rgba(0, 98, 169, 0.1);
            padding: 12px;
            border-radius: 14px;
        }

        .search-header h3 {
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .search-header span {
            color: #64748b;
            font-size: 0.85rem;
            margin-left: auto;
            background: white;
            padding: 6px 12px;
            border-radius: 30px;
            border: 1px solid rgba(0, 98, 169, 0.2);
        }

        .search-grid {
            display: grid;
            grid-template-columns: 1fr 2fr auto auto;
            gap: 15px;
            align-items: start;
        }

        .search-field {
            position: relative;
        }

        .search-field label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .search-field select,
        .search-field input {
            width: 100%;
            padding: 14px 18px;
            background: white;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 16px;
            color: #1e293b;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s ease;
            appearance: none;
            cursor: pointer;
        }

        .search-field select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%230062a9' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            background-size: 16px;
        }

        .search-field select:hover,
        .search-field input:hover {
            border-color: #0062a9;
            background-color: #ffffff;
        }

        .search-field select:focus,
        .search-field input:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 4px rgba(0, 98, 169, 0.1);
            background-color: #ffffff;
        }

        .search-field input {
            background: white;
            cursor: text;
        }

        .search-field input::placeholder {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .search-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            height: 100%;
        }

        .search-btn-modern {
            padding: 14px 28px;
            background: linear-gradient(135deg, #0062a9 0%, #0088cc 100%);
            border: none;
            border-radius: 16px;
            color: white;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 20px -8px rgba(0, 98, 169, 0.4);
            white-space: nowrap;
            height: fit-content;
        }

        .search-btn-modern:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px -8px rgba(0, 98, 169, 0.5);
        }

        .clear-btn-modern {
            padding: 14px 24px;
            background: white;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 16px;
            color: #475569;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            white-space: nowrap;
            height: fit-content;
        }

        .clear-btn-modern:hover {
            border-color: #0062a9;
            color: #0062a9;
            background: #f8fafc;
            transform: translateY(-3px);
        }

        #search_form {
            display: none;
            width: 100%;
        }

        /* Table Container with rounded rectangle and visible lines */
        .table-container {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            border: 2px solid rgba(0, 98, 169, 0.15);
            margin: 25px 0;
            box-shadow: 0 10px 30px -10px rgba(0, 98, 169, 0.2);
        }

        .table-responsive {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 1400px;
            background: white;
        }

        th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: #1e293b;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 18px 15px;
            text-align: left;
            border-bottom: 3px solid #0062a9;
            border-right: 1px solid rgba(0, 98, 169, 0.1);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        th:last-child {
            border-right: none;
        }

        td {
            padding: 16px 15px;
            color: #334155;
            border-bottom: 1px solid rgba(0, 98, 169, 0.15);
            border-right: 1px solid rgba(0, 98, 169, 0.1);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
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

        .clickable-row {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .clickable-row:hover td {
            background: #e6f0ff !important;
        }

        /* Stock indicators with better visibility */
        .stock-critical {
            color: #b91c1c;
            font-weight: 700;
            position: relative;
            background: rgba(220, 38, 38, 0.1);
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
        }

        .stock-critical::after {
            content: '⚠️';
            margin-left: 5px;
            font-size: 0.8rem;
        }

        .stock-warning {
            color: #c2410c;
            font-weight: 700;
            background: rgba(249, 115, 22, 0.1);
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
        }

        .stock-low {
            color: #854d0e;
            font-weight: 700;
            background: rgba(202, 138, 4, 0.1);
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
        }

        .stock-good {
            color: #166534;
            font-weight: 700;
            background: rgba(22, 163, 74, 0.1);
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
        }

        .edit-btn {
            padding: 8px 16px;
            background: white;
            border: 2px solid rgba(0, 98, 169, 0.2);
            border-radius: 12px;
            color: #0062a9;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .edit-btn:hover {
            background: #0062a9;
            color: white;
            border-color: #0062a9;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.3);
        }

        /* Status badge */
        .status-badge {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
            letter-spacing: 0.3px;
            border: 1px solid transparent;
        }

        /* Pagination */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid rgba(0, 98, 169, 0.15);
        }

        .pagination-info {
            color: #475569;
            font-size: 0.9rem;
            font-weight: 500;
            margin-right: auto;
            background: #f8fafc;
            padding: 8px 16px;
            border-radius: 30px;
            border: 1px solid rgba(0, 98, 169, 0.15);
        }

        .pagination-arrow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            background: white;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 12px;
            color: #0062a9;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 1rem;
            font-weight: 600;
        }

        .pagination-arrow:hover:not(.disabled) {
            background: #0062a9;
            border-color: #0062a9;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.2);
        }

        .pagination-arrow.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        .pagination-numbers {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pagination-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            height: 42px;
            padding: 0 10px;
            background: white;
            border: 2px solid rgba(0, 98, 169, 0.15);
            border-radius: 12px;
            color: #0062a9;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .pagination-number:hover {
            background: #e6f0ff;
            border-color: #0062a9;
            transform: translateY(-2px);
        }

        .pagination-number.active {
            background: #0062a9;
            border-color: #0062a9;
            color: white;
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.2);
        }

        /* No data */
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
            background: #f8fafc;
            border-radius: 24px;
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

        /* Modal styles (keep as is from previous version) */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            margin: 30px auto;
            padding: 30px;
            width: 90%;
            max-width: 800px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
            border: 2px solid rgba(0, 98, 169, 0.2);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #0062a9;
        }

        .modal-header h3 {
            color: #1e293b;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i {
            color: #0062a9;
        }

        .close {
            color: #94a3b8;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s ease;
            line-height: 1;
        }

        .close:hover {
            color: #0062a9;
        }

        .modal-loading {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 24px;
            flex-direction: column;
            gap: 15px;
        }

        .modal-loading i {
            font-size: 2.5rem;
            color: #0062a9;
        }

        .modal-loading span {
            color: #1e293b;
            font-size: 1rem;
            font-weight: 500;
        }

        .modal-body {
            padding: 10px 0;
        }

        .modal-form-group {
            margin-bottom: 20px;
        }

        .modal-form-group label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .modal-form-group input,
        .modal-form-group select,
        .modal-form-group textarea {
            width: 100%;
            padding: 12px 16px;
            background: #f8fafc;
            border: 2px solid rgba(0, 98, 169, 0.2);
            border-radius: 12px;
            color: #1e293b;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s ease;
        }

        .modal-form-group input:focus,
        .modal-form-group select:focus,
        .modal-form-group textarea:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 4px rgba(0, 98, 169, 0.1);
            background: white;
        }

        .modal-form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
            flex-wrap: wrap;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1e293b;
            cursor: pointer;
            font-weight: 500;
        }

        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .status-indicator {
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e8f3f7;
        }

        .modal-btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .modal-btn-primary {
            background: #0062a9;
            color: white;
            box-shadow: 0 8px 15px rgba(0, 98, 169, 0.2);
        }

        .modal-btn-primary:hover {
            background: #004d88;
            transform: translateY(-2px);
        }

        .modal-btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .modal-btn-secondary:hover {
            background: #e2e8f0;
            color: #1e293b;
            transform: translateY(-2px);
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
            .search-grid {
                grid-template-columns: 1fr;
            }
            .search-actions {
                flex-direction: column;
                width: 100%;
            }
            .search-btn-modern,
            .clear-btn-modern {
                width: 100%;
                justify-content: center;
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
        }

        @media (max-width: 768px) {
            #sidebar {
                transform: translateX(-100%);
            }
            #main-content {
                margin-left: 0;
            }
            .pagination {
                flex-wrap: wrap;
                justify-content: center;
            }
            .pagination-info {
                width: 100%;
                text-align: center;
                margin-right: 0;
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

        /* Toast Notifications */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            background: white;
            border-radius: 12px;
            color: #1e293b;
            z-index: 3000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0062a9;
            font-weight: 500;
            border: 2px solid rgba(0, 98, 169, 0.2);
        }

        .toast.success {
            border-left-color: #22c55e;
        }

        .toast.error {
            border-left-color: #ef4444;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
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
                        <div class="title-badge">Inventory Management</div>
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
                    <i class="fa fa-list"></i>
                    Inventory Items List
                </h2>
                
                <div class="action-buttons">
                    <a href="inventory_create.php" class="btn btn-primary">
                        <i class="fa fa-plus"></i> Create New Item
                    </a>
                    <a href="inventory_list.php?export=excel<?= isset($_GET['search_field']) ? '&search_field=' . urlencode($_GET['search_field']) . '&search_value=' . urlencode($_GET['search_value']) : '' ?>" class="btn btn-export">
                        <i class="fa fa-file-excel"></i> Export to Excel
                    </a>
                </div>
            </div>
            
            <?php if (isset($update_success)): ?>
                <div class="message success">
                    <i class="fa fa-check-circle"></i>
                    <?php echo $update_success; unset($update_success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($update_error)): ?>
                <div class="message error">
                    <i class="fa fa-exclamation-circle"></i>
                    <?php echo $update_error; unset($update_error); ?>
                </div>
            <?php endif; ?>

            <!-- Modern Search Form -->
            <div class="search-wrapper">
                <div class="search-header">
                    <i class="fa fa-search"></i>
                    <h3>Advanced Search</h3>
                    <span><i class="fa fa-filter"></i> Filter inventory items</span>
                </div>
                
                <div class="search-grid">
                    <div class="search-field">
                        <label>Search By Field</label>
                        <select id="search_field_selector">
                            <option value="">Choose field...</option>
                            <option value="part_id">Part ID</option>
                            <option value="category">Category</option>
                            <option value="description">Description</option>
                            <option value="size">Size</option>
                            <option value="store_name">Store</option>
                            <option value="floor_name">Floor</option>
                            <option value="bay_name">Bay</option>
                            <option value="location_code">Location Code</option>
                            <option value="uom_name">UOM</option>
                            <option value="unit_price">Unit Price</option>
                            <option value="opening_stock">Opening Stock</option>
                            <option value="stock_out">Stock Out</option>
                            <option value="current_stock">Current Stock</option>
                        </select>
                    </div>

                    <form method="GET" action="" id="search_form" style="display: contents;">
                        <input type="hidden" id="search_field_hidden" name="search_field">
                        
                        <div class="search-field" style="grid-column: span 2;">
                            <label>Search Value</label>
                            <input type="text" id="search_value" name="search_value" placeholder="Type your search term..." required>
                        </div>
                        
                        <div class="search-actions">
                            <button type="submit" class="search-btn-modern">
                                <i class="fa fa-search"></i> Search
                            </button>
                            <a href="inventory_list.php" class="clear-btn-modern">
                                <i class="fa fa-refresh"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <!-- Table Container with rounded rectangle and visible lines -->
                <div class="table-container">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Part ID</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Size</th>
                                    <th>Store</th>
                                    <th>Floor</th>
                                    <th>Bay</th>
                                    <th>Location</th>
                                    <th>UOM</th>
                                    <th>Price</th>
                                    <th>Opening</th>
                                    <th>Out</th>
                                    <th>Current</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = $offset + 1;
                                while ($row = $result->fetch_assoc()): 
                                    $current_stock_display = $row['current_stock'];
                                    if ($row['opening_stock'] !== null && $row['stock_out'] !== null) {
                                        $calculated_current = $row['opening_stock'] - $row['stock_out'];
                                        $current_stock_display = $calculated_current;
                                    }
                                    
                                    // Determine stock status class
                                    $stock_class = 'stock-good';
                                    if ($current_stock_display <= 0) {
                                        $stock_class = 'stock-critical';
                                    } elseif ($current_stock_display < 5) {
                                        $stock_class = 'stock-critical';
                                    } elseif ($current_stock_display < 10) {
                                        $stock_class = 'stock-warning';
                                    } elseif ($current_stock_display < 20) {
                                        $stock_class = 'stock-low';
                                    }
                                ?>
                                    <tr class="clickable-row" onclick="window.location.href='inventory_detail.php?id=<?php echo $row['inventory_id']; ?>'">
                                        <td><strong><?php echo $counter++; ?></strong></td>
                                        <td><strong style="color: #0062a9;"><?php echo htmlspecialchars($row['part_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                                        <td><?php echo htmlspecialchars($row['size'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['store_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['floor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['bay_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['location_code'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['uom_name']); ?></td>
                                        <td><strong>RM <?php echo number_format($row['unit_price'], 2); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['opening_stock'] ?: '0'); ?></td>
                                        <td><?php echo htmlspecialchars($row['stock_out']); ?></td>
                                        <td><span class="<?php echo $stock_class; ?>"><?php echo $current_stock_display; ?></span></td>
                                        <td>
                                            <span class="status-badge" style="<?php echo $row['status'] ? 'background: #e8f7ed; color: #16a34a; border-color: #bbf7d0;' : 'background: #fee2e2; color: #dc2626; border-color: #fecaca;'; ?>">
                                                <?php echo $row['status'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="edit-btn" 
                                                data-inventory-id="<?php echo $row['inventory_id']; ?>" 
                                                data-part-id="<?php echo htmlspecialchars($row['part_id']); ?>" 
                                                data-category="<?php echo htmlspecialchars($row['category']); ?>" 
                                                data-description="<?php echo htmlspecialchars($row['description']); ?>" 
                                                data-size="<?php echo htmlspecialchars($row['size'] ?: ''); ?>" 
                                                data-store-id="<?php echo $row['store_id']; ?>" 
                                                data-floor-id="<?php echo $row['floor_id']; ?>" 
                                                data-bay-id="<?php echo $row['bay_id']; ?>" 
                                                data-location-code="<?php echo htmlspecialchars($row['location_code'] ?: ''); ?>" 
                                                data-uom-id="<?php echo $row['uom_id']; ?>" 
                                                data-unit-price="<?php echo $row['unit_price']; ?>" 
                                                data-opening-stock="<?php echo htmlspecialchars($row['opening_stock'] ?: '0'); ?>" 
                                                data-stock-out="<?php echo $row['stock_out']; ?>" 
                                                data-current-stock="<?php echo $current_stock_display; ?>"
                                                data-status="<?php echo $row['status']; ?>"
                                                onclick="event.stopPropagation(); openEditModal(this)">
                                                <i class="fa fa-edit"></i> Edit
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Scroll indicator for horizontal scroll -->
                <div class="scroll-indicator">
                    <span>Scroll horizontally for more columns</span>
                    <i class="fas fa-arrow-right"></i>
                </div>
                <!-- Pagination -->
                <div class="pagination">
                    <div class="pagination-info">
                        <i class="fa fa-list"></i> Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_items); ?> of <?php echo $total_items; ?> items
                    </div>
                    
                    <div class="pagination-numbers">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-arrow <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <i class="fa fa-angle-double-left"></i>
                        </a>
                        
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-arrow <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <i class="fa fa-angle-left"></i>
                        </a>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="pagination-number <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-arrow <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <i class="fa fa-angle-right"></i>
                        </a>
                        
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="pagination-arrow <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <i class="fa fa-angle-double-right"></i>
                        </a> 
                    </div>
                </div>


            <?php else: ?>
                <div class="no-data">
                    <i class="fa fa-box-open"></i>
                    <p>No inventory items found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fa fa-edit"></i>
                    Edit Inventory Item
                </h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <!-- Loading overlay (initially hidden) -->
            <div class="modal-loading" id="modalLoading" style="display: none;">
                <i class="fa fa-spinner fa-spin"></i>
                <span>Loading item data...</span>
            </div>
            
            <div class="modal-body">
                <form id="editForm" method="POST" action="">
                    <input type="hidden" id="inventory_id" name="inventory_id">
                    <input type="hidden" name="update_inventory" value="1">
                    
                    <div class="modal-form-group">
                        <label for="edit_part_id">Part ID *</label>
                        <input type="text" id="edit_part_id" name="part_id" required>
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="edit_category">Category *</label>
                        <input type="text" id="edit_category" name="category" required>
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="edit_description">Description *</label>
                        <textarea id="edit_description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="edit_size">Size</label>
                        <input type="text" id="edit_size" name="size">
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="edit_store_id">Store *</label>
                        <select id="edit_store_id" name="store_id" required>
                            <option value="">Select Store</option>
                            <?php
                            $stores->data_seek(0);
                            while ($row = $stores->fetch_assoc()): ?>
                                <option value="<?php echo $row['store_id']; ?>"><?php echo $row['store_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="edit_floor_id">Floor *</label>
                        <select id="edit_floor_id" name="floor_id" required>
                            <option value="">Select Floor</option>
                            <?php
                            $floors->data_seek(0);
                            while ($row = $floors->fetch_assoc()): ?>
                                <option value="<?php echo $row['floor_id']; ?>"><?php echo $row['floor_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="edit_bay_id">Bay *</label>
                        <select id="edit_bay_id" name="bay_id" required>
                            <option value="">Select Bay</option>
                            <?php
                            $bays->data_seek(0);
                            while ($row = $bays->fetch_assoc()): ?>
                                <option value="<?php echo $row['bay_id']; ?>"><?php echo $row['bay_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="edit_location_code">Location Code</label>
                        <input type="text" id="edit_location_code" name="location_code">
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="edit_uom_id">Unit of Measurement *</label>
                        <select id="edit_uom_id" name="uom_id" required>
                            <option value="">Select UOM</option>
                            <?php
                            $uoms->data_seek(0);
                            while ($row = $uoms->fetch_assoc()): ?>
                                <option value="<?php echo $row['uom_id']; ?>"><?php echo $row['uom_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="edit_unit_price">Unit Price *</label>
                        <input type="number" id="edit_unit_price" name="unit_price" step="0.01" required>
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="edit_opening_stock">Opening Stock</label>
                        <input type="number" id="edit_opening_stock" name="opening_stock" min="0" oninput="calculateCurrentStock()">
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="edit_stock_out">Stock Out</label>
                        <input type="number" id="edit_stock_out" name="stock_out" min="0" oninput="calculateCurrentStock()">
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="edit_current_stock">Current Stock (Auto-calculated)</label>
                        <input type="number" id="edit_current_stock" name="current_stock" min="0" readonly style="background: #f1f5f9;">
                    </div>
                    
                    <div class="modal-form-group">
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="edit_status" name="status" value="1">
                                <span id="status-label-text">Active</span>
                            </label>
                            <div class="status-indicator" id="status-indicator"></div>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="submit" class="modal-btn modal-btn-primary">
                            <i class="fa fa-save"></i> Save Changes
                        </button>
                        <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal()">
                            <i class="fa fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Clock already initialized above
        
        // Search field change handler
        document.getElementById('search_field_selector').addEventListener('change', function() {
            const selectedField = this.value;
            const form = document.getElementById('search_form');
            const hiddenField = document.getElementById('search_field_hidden');
            const valInput = document.getElementById('search_value');
            const searchGrid = document.querySelector('.search-grid');

            if (selectedField) {
                form.style.display = 'contents';
                hiddenField.value = selectedField;
                
                // Update placeholder and input type based on selection
                const fieldText = this.options[this.selectedIndex].text.toLowerCase();
                valInput.placeholder = "Enter " + fieldText + "...";

                if (['unit_price', 'opening_stock', 'current_stock', 'stock_out'].includes(selectedField)) {
                    valInput.type = 'number';
                    valInput.step = (selectedField === 'unit_price') ? '0.01' : '1';
                } else {
                    valInput.type = 'text';
                }
                
                // Add highlight effect to the search value field
                valInput.style.borderColor = '#0062a9';
                valInput.style.boxShadow = '0 0 0 4px rgba(0, 98, 169, 0.1)';
                
                valInput.focus();
            } else {
                form.style.display = 'none';
                valInput.style.borderColor = '';
                valInput.style.boxShadow = '';
            }
        });

        // Remove highlight when input loses focus
        document.getElementById('search_value').addEventListener('blur', function() {
            this.style.borderColor = '';
            this.style.boxShadow = '';
        });

        // Calculate current stock
        function calculateCurrentStock() {
            const openingStock = parseFloat(document.getElementById('edit_opening_stock').value) || 0;
            const stockOut = parseFloat(document.getElementById('edit_stock_out').value) || 0;
            const currentStock = openingStock - stockOut;
            
            document.getElementById('edit_current_stock').value = currentStock >= 0 ? currentStock : 0;
        }

        // Open edit modal with loading state
        function openEditModal(button) {
            const modal = document.getElementById('editModal');
            const loadingOverlay = document.getElementById('modalLoading');
            
            // Show modal first
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Show loading overlay
            loadingOverlay.style.display = 'flex';
            
            // Small delay to simulate loading and ensure DOM is ready
            setTimeout(() => {
                try {
                    // Populate form with data attributes
                    document.getElementById('inventory_id').value = button.getAttribute('data-inventory-id');
                    document.getElementById('edit_part_id').value = button.getAttribute('data-part-id');
                    document.getElementById('edit_category').value = button.getAttribute('data-category');
                    document.getElementById('edit_description').value = button.getAttribute('data-description');
                    document.getElementById('edit_size').value = button.getAttribute('data-size');
                    document.getElementById('edit_store_id').value = button.getAttribute('data-store-id');
                    document.getElementById('edit_floor_id').value = button.getAttribute('data-floor-id');
                    document.getElementById('edit_bay_id').value = button.getAttribute('data-bay-id');
                    document.getElementById('edit_location_code').value = button.getAttribute('data-location-code');
                    document.getElementById('edit_uom_id').value = button.getAttribute('data-uom-id');
                    document.getElementById('edit_unit_price').value = button.getAttribute('data-unit-price');
                    document.getElementById('edit_opening_stock').value = button.getAttribute('data-opening-stock');
                    document.getElementById('edit_stock_out').value = button.getAttribute('data-stock-out');
                    document.getElementById('edit_current_stock').value = button.getAttribute('data-current-stock');
                    
                    const statusCheckbox = document.getElementById('edit_status');
                    statusCheckbox.checked = (button.getAttribute('data-status') == 1);
                    
                    updateStatusIndicator();
                    
                    // Hide loading overlay
                    loadingOverlay.style.display = 'none';
                    
                } catch (error) {
                    console.error('Error loading data:', error);
                    showToast('Failed to load item data', 'error');
                    
                    // Hide loading overlay
                    loadingOverlay.style.display = 'none';
                }
            }, 300); // Small delay to show loading effect
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideIn 0.3s reverse';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }

        // Update status indicator
        function updateStatusIndicator() {
            const statusCheckbox = document.getElementById('edit_status');
            const statusIndicator = document.getElementById('status-indicator');
            const statusLabel = document.getElementById('status-label-text');
            
            if (statusCheckbox.checked) {
                statusIndicator.textContent = 'Active';
                statusIndicator.style.backgroundColor = '#e8f7ed';
                statusIndicator.style.color = '#16a34a';
                statusIndicator.style.border = '2px solid #bbf7d0';
                statusLabel.textContent = 'Active';
            } else {
                statusIndicator.textContent = 'Inactive';
                statusIndicator.style.backgroundColor = '#fee2e2';
                statusIndicator.style.color = '#dc2626';
                statusIndicator.style.border = '2px solid #fecaca';
                statusLabel.textContent = 'Inactive';
            }
        }

        // Add change listener for status checkbox
        document.getElementById('edit_status')?.addEventListener('change', updateStatusIndicator);

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Close modal function
        function closeModal() {
            const modal = document.getElementById('editModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Reset form
            document.getElementById('editForm').reset();
            formChanged = false; // Reset the form changed flag when closing
        }

        // Keep the form visible if a search was already performed
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('search_field')) {
                const field = urlParams.get('search_field');
                const selector = document.getElementById('search_field_selector');
                if (selector) {
                    selector.value = field;
                    selector.dispatchEvent(new Event('change'));
                    document.getElementById('search_value').value = urlParams.get('search_value');
                }
            }
        }

        // Handle escape key to close modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        // Confirm before leaving if form is dirty
        let formChanged = false;
        document.getElementById('editForm')?.addEventListener('input', function() {
            formChanged = true;
        });

        // FIX: Reset formChanged flag when form is submitted
        document.getElementById('editForm')?.addEventListener('submit', function() {
            formChanged = false;
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged && document.getElementById('editModal').style.display === 'block') {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>
</html>