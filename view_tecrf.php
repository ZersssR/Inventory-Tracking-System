<?php
session_start();
include 'session.php';
include('database.php');

// Check if reference_number is provided
if (!isset($_GET['reference_number'])) {
    die("Reference number not provided.");
}

$reference_number = $_GET['reference_number'];
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Handle complete action
if (isset($_POST['complete']) && isset($_POST['reference_number'])) {
    $ref_no = $_POST['reference_number'];
    $completed_by = $_SESSION['full_name'] ?? 'Admin';
    $completed_date = date('Y-m-d H:i:s');
    
    // Get approve quantities from form
    $approve_quantities = $_POST['approve_quantity'] ?? [];
    $item_ids = $_POST['item_id'] ?? [];
    $descriptions = $_POST['description'] ?? [];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First, update all approve quantities in tecrf_items
        foreach ($item_ids as $index => $item_id) {
            $approve_qty = intval($approve_quantities[$index]);
            
            // Get the tecrf_item details
            $get_item_sql = $conn->prepare("SELECT tecrf_item_id, request_quantity, description FROM tecrf_items WHERE tecrf_item_id = ? AND tecrf_id = (SELECT tecrf_id FROM tecrf WHERE reference_number = ?)");
            $get_item_sql->bind_param("is", $item_id, $ref_no);
            $get_item_sql->execute();
            $item_result = $get_item_sql->get_result();
            
            if ($item_result->num_rows > 0) {
                $item_data = $item_result->fetch_assoc();
                
                // Validate approve quantity doesn't exceed request quantity
                if ($approve_qty > $item_data['request_quantity']) {
                    throw new Exception("Approve quantity cannot exceed request quantity for item: " . $item_data['description']);
                }
                
                // Update approve_quantity in tecrf_items table
                $update_item_sql = $conn->prepare("UPDATE tecrf_items SET approve_quantity = ? WHERE tecrf_item_id = ?");
                $update_item_sql->bind_param("ii", $approve_qty, $item_data['tecrf_item_id']);
                $update_item_sql->execute();
                $update_item_sql->close();
                
                // Update inventory_product current_stock (subtract approve quantity)
                if ($approve_qty > 0) {
                    // Get inventory items with this description that have stock
                    $inventory_sql = $conn->prepare("SELECT inventory_id, current_stock FROM inventory_product WHERE description = ? AND current_stock > 0 AND status = 1 ORDER BY expire_date ASC");
                    $inventory_sql->bind_param("s", $item_data['description']);
                    $inventory_sql->execute();
                    $inventory_result = $inventory_sql->get_result();
                    
                    $remaining_to_deduct = $approve_qty;
                    
                    while ($inventory_row = $inventory_result->fetch_assoc()) {
                        if ($remaining_to_deduct <= 0) {
                            break;
                        }
                        
                        $deduct_from_this = min($inventory_row['current_stock'], $remaining_to_deduct);
                        $new_stock = $inventory_row['current_stock'] - $deduct_from_this;
                        
                        // Update both current_stock AND stock_out
                        $update_inventory_sql = $conn->prepare("UPDATE inventory_product 
                                                SET current_stock = ?, 
                                                    stock_out = stock_out + ? 
                                                WHERE inventory_id = ?");
                        $update_inventory_sql->bind_param("iii", $new_stock, $deduct_from_this, $inventory_row['inventory_id']);
                        $update_inventory_sql->execute();
                        $update_inventory_sql->close();
                        
                        $remaining_to_deduct -= $deduct_from_this;
                    }
                    
                    $inventory_sql->close();
                    
                    // If we couldn't deduct all (insufficient stock), throw error
                    if ($remaining_to_deduct > 0) {
                        throw new Exception("Insufficient stock for item: " . $item_data['description'] . ". Need " . $approve_qty . " but only have " . ($approve_qty - $remaining_to_deduct) . " available.");
                    }
                }
            }
            $get_item_sql->close();
        }
        
        // Update tecrf status to 'Completed'
        $update_sql = $conn->prepare("UPDATE tecrf SET status = 'Completed', completed_by = ?, completed_date = ? WHERE reference_number = ?");
        $update_sql->bind_param("sss", $completed_by, $completed_date, $ref_no);
        $update_sql->execute();
        $update_sql->close();
        
        // Commit transaction
        $conn->commit();
        
        // Set success message and refresh the page to show updated data
        $success_message = "Request has been completed successfully! Stock has been updated.";
        
        // Refresh the data after update
        header("Location: " . $_SERVER['PHP_SELF'] . "?reference_number=" . urlencode($ref_no) . "&success=1");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error completing request: " . $e->getMessage();
    }
}

// Check for success parameter in URL
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Request has been completed successfully! Stock has been updated.";
}

// Fetch details from tecrf based on the reference number
$sql1 = $conn->prepare("SELECT * FROM tecrf WHERE reference_number = ?");
$sql1->bind_param('s', $reference_number);
$sql1->execute();
$result1 = $sql1->get_result();

if ($result1 && $result1->num_rows > 0) {
    $tecrf_data = $result1->fetch_assoc();
} else {
    die("No data found for reference number: " . htmlspecialchars($reference_number));
}

$view_button_disabled = ($tecrf_data['items_assigned'] == 1 && $tecrf_data['reassigned'] == 0) ? 'disabled' : '';

// Fetch all items from tecrf_items based on the tecrf_id
$sql2 = $conn->prepare("SELECT * FROM tecrf_items WHERE tecrf_id = ?");
$sql2->bind_param('i', $tecrf_data['tecrf_id']);
$sql2->execute();
$result2 = $sql2->get_result();

$tecrf_items_data = [];
if ($result2 && $result2->num_rows > 0) {
    while ($row = $result2->fetch_assoc()) {
        $tecrf_items_data[] = $row;
    }
}

// Fetch product details based on description from tecrf_items
$product_data = [];
foreach ($tecrf_items_data as $item) {
    $description = $item['description'];
    $sql3 = $conn->prepare("SELECT * FROM inventory_product WHERE description = ? AND status = 1");
    $sql3->bind_param('s', $description);
    $sql3->execute();
    $result3 = $sql3->get_result();
    while ($product = $result3->fetch_assoc()) {
        $product_data[] = $product;
    }
}

// Group products by description for quantity calculations
$grouped_products = [];
foreach ($product_data as $product) {
    $desc = $product['description'];
    if (!isset($grouped_products[$desc])) {
        $grouped_products[$desc] = [
            'products' => [],
            'total_stock' => 0
        ];
    }
    $grouped_products[$desc]['products'][] = $product;
    $grouped_products[$desc]['total_stock'] += $product['current_stock'];
}

// Generate navigation function matching adminStaff.php
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
    <title>View TECRF | EPIC OG</title>
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
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 98, 169, 0.08);
            border: 1px solid rgba(0, 98, 169, 0.1);
        }

        .container h1 {
            color: #1e293b;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 2px solid #e8f3f7;
            padding-bottom: 15px;
        }

        .container h1 i {
            color: #0062a9;
            background: #e8f3f7;
            padding: 10px;
            border-radius: 14px;
        }

        /* Back button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: white;
            border: 1px solid rgba(0, 98, 169, 0.2);
            border-radius: 14px;
            color: #0062a9;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 25px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
        }

        .back-btn:hover {
            background: #0062a9;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 98, 169, 0.2);
            border-color: #0062a9;
        }

        .back-btn i {
            font-size: 0.9rem;
        }

        /* Messages */
        .success-message {
            background: #e8f7ed;
            color: #16a34a;
            padding: 18px 24px;
            border-radius: 16px;
            margin-bottom: 25px;
            border: 1px solid rgba(34, 197, 94, 0.3);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .error-message {
            background: #fee9e7;
            color: #dc2626;
            padding: 18px 24px;
            border-radius: 16px;
            margin-bottom: 25px;
            border: 1px solid rgba(239, 68, 68, 0.3);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }

        /* Info Cards */
        .info-card {
            background: #f8fafc;
            border-radius: 20px;
            padding: 25px;
            border: 1px solid rgba(0, 98, 169, 0.1);
            margin-bottom: 25px;
        }

        .info-card h2 {
            color: #1e293b;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e8f3f7;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card h2 i {
            color: #0062a9;
            font-size: 1.1rem;
        }

        .info-row {
            display: flex;
            margin-bottom: 15px;
            color: #475569;
            font-size: 0.95rem;
        }

        .info-label {
            width: 130px;
            color: #64748b;
            font-weight: 500;
        }

        .info-value {
            flex: 1;
            color: #1e293b;
            font-weight: 600;
        }

        /* Status badge */
        .status-badge {
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-pending {
            background: #fff6e9;
            color: #f97316;
            border: 1px solid #f97316;
        }

        .status-progress {
            background: #e6f0ff;
            color: #0062a9;
            border: 1px solid #0062a9;
        }

        .status-completed {
            background: #e8f7ed;
            color: #16a34a;
            border: 1px solid #16a34a;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 16px;
            background: white;
            border: 1px solid rgba(0, 98, 169, 0.1);
            margin-top: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th {
            background: #e8f3f7;
            color: #0062a9;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 16px 15px;
            text-align: left;
            border-bottom: 2px solid rgba(0, 98, 169, 0.1);
        }

        td {
            padding: 15px;
            color: #475569;
            border-bottom: 1px solid #e8f3f7;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .num-right {
            text-align: right;
            font-weight: 500;
        }

        /* Approve input */
        .approve-input {
            width: 90px;
            padding: 10px 12px;
            background: white;
            border: 2px solid rgba(0, 98, 169, 0.2);
            border-radius: 12px;
            color: #1e293b;
            font-size: 0.9rem;
            font-weight: 500;
            text-align: center;
            transition: all 0.2s ease;
        }

        .approve-input:focus {
            outline: none;
            border-color: #0062a9;
            box-shadow: 0 0 0 3px rgba(0, 98, 169, 0.1);
        }

        .approve-input:disabled {
            background: #f1f5f9;
            border-color: #cbd5e1;
            opacity: 0.7;
            cursor: not-allowed;
        }

        .balance-positive {
            color: #16a34a;
            font-weight: 600;
        }

        .balance-negative {
            color: #dc2626;
            font-weight: 600;
        }

        /* Complete button */
        .complete-button-container {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #e8f3f7;
            text-align: right;
        }

        .btn-complete {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 16px 36px;
            background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%);
            border: none;
            border-radius: 16px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(34, 197, 94, 0.3);
        }

        .btn-complete:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(34, 197, 94, 0.4);
        }

        .btn-complete i {
            font-size: 1.2rem;
        }

        .btn-complete:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
            background: #94a3b8;
            box-shadow: none;
        }

        /* Completion info */
        .completion-info {
            background: linear-gradient(135deg, #e8f7ed 0%, #f0fdf4 100%);
            border-radius: 16px;
            padding: 25px;
            margin-top: 25px;
            border: 1px solid #16a34a;
        }

        .completion-info h4 {
            color: #16a34a;
            font-size: 1.1rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .completion-info p {
            color: #475569;
            margin-bottom: 12px;
            font-size: 0.95rem;
        }

        .completion-info strong {
            color: #1e293b;
            width: 130px;
            display: inline-block;
        }

        /* Scrollable table */
        .scrollable-table {
            max-height: 450px;
            overflow-y: auto;
            border: 1px solid rgba(0, 98, 169, 0.1);
            border-radius: 16px;
            background: white;
        }

        .scrollable-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #e8f3f7;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #e8f3f7;
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: #0062a9;
            border-radius: 5px;
            border: 2px solid #e8f3f7;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #004d88;
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

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
        }
    </style>
    <script>
        function calculateBalance(input, index) {
            var approveQty = parseInt(input.value) || 0;
            var currentStock = parseInt(document.getElementById('current-stock-' + index).value) || 0;
            var balance = currentStock - approveQty;
            var balanceSpan = document.getElementById('balance-' + index);
            
            balanceSpan.textContent = balance;
            
            // Change color based on balance
            if (balance < 0) {
                balanceSpan.className = 'balance-negative';
            } else {
                balanceSpan.className = 'balance-positive';
            }
        }
        
        function validateBeforeComplete() {
            var inputs = document.querySelectorAll('.approve-input');
            var isValid = true;
            var errorMessages = [];
            
            for (var i = 0; i < inputs.length; i++) {
                var approveQty = parseInt(inputs[i].value) || 0;
                var requestQty = parseInt(inputs[i].getAttribute('data-request-qty')) || 0;
                var currentStock = parseInt(document.getElementById('current-stock-' + (i+1)).value) || 0;
                
                if (approveQty > requestQty) {
                    errorMessages.push('❌ Approve quantity cannot exceed request quantity for item #' + (i + 1));
                    isValid = false;
                }
                
                if (approveQty < 0) {
                    errorMessages.push('❌ Approve quantity cannot be negative for item #' + (i + 1));
                    isValid = false;
                }
                
                if (approveQty > currentStock) {
                    errorMessages.push('❌ Insufficient stock for item #' + (i + 1) + '. Available: ' + currentStock);
                    isValid = false;
                }
            }
            
            if (!isValid) {
                alert('Please fix the following errors:\n\n' + errorMessages.join('\n'));
                return false;
            }
            
            return confirm("⚠️ Are you sure you want to complete this request?\n\nThis will:\n✅ Save all approved quantities\n✅ Update inventory stock\n✅ Mark request as COMPLETED\n\nThis action cannot be undone.");
        }

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
<body onload="updateClock()">
    <?= generateNav($username) ?>
    
    <div id="main-content">
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <div class="logo-wrapper">
                        <div class="logo-container">
                            <img src="eog3.jpg" alt="EPIC OG Logo">
                        </div>
                        <div class="title-badge">View TECRF</div>
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

        <div class="container">
            <h1>
                <i class="fa fa-file-alt"></i>
                TECRF Details: <?= htmlspecialchars($tecrf_data['reference_number']) ?>
            </h1>
            
            <a href="approval.php" class="back-btn">
                <i class="fa fa-arrow-left"></i> Back to Request List
            </a>
            
            <?php if (isset($success_message)): ?>
                <div class="success-message">
                    <i class="fa fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fa fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="content-grid">
                <!-- Left Column - TECRF Details -->
                <div>
                    <div class="info-card">
                        <h2><i class="fa fa-info-circle"></i> Request Information</h2>
                        
                        <div class="info-row">
                            <span class="info-label">Reference No:</span>
                            <span class="info-value"><?php echo htmlspecialchars($tecrf_data['reference_number']); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Date Request:</span>
                            <span class="info-value"><?php echo htmlspecialchars($tecrf_data['date']); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Client:</span>
                            <span class="info-value"><?php echo htmlspecialchars($tecrf_data['client']); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Date Required:</span>
                            <span class="info-value"><?php echo htmlspecialchars($tecrf_data['date_required']); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Project:</span>
                            <span class="info-value"><?php echo htmlspecialchars($tecrf_data['project']); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Charge Code:</span>
                            <span class="info-value"><?php echo htmlspecialchars($tecrf_data['charge_code']); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Location:</span>
                            <span class="info-value"><?php echo htmlspecialchars($tecrf_data['location']); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span class="info-value">
                                <span class="status-badge <?php 
                                    echo $tecrf_data['status'] == 'Pending' ? 'status-pending' : 
                                        ($tecrf_data['status'] == 'In Progress' ? 'status-progress' : 'status-completed'); 
                                ?>">
                                    <?php echo htmlspecialchars($tecrf_data['status']); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Requested Items Table with Inventory Details -->
                <div>
                    <div class="info-card">
                        <h2><i class="fa fa-shopping-cart"></i> Requested Items with Inventory Details</h2>
                        
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Description</th>
                                        <th class="num-right">Qty</th>
                                        <th>UOM</th>
                                        <th>Size</th>
                                        <th>Floor</th>
                                        <th>Bay</th>
                                        <th>Location</th>
                                        <th>Current Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $counter = 1;
                                    foreach ($tecrf_items_data as $item): 
                                        $desc = $item['description'];
                                        $request_qty = $item['request_quantity'];
                                        
                                        // Get inventory details for this description
                                        $inventory_details = $grouped_products[$desc]['products'] ?? [];
                                    ?>
                                        <tr>
                                            <td rowspan="<?php echo max(1, count($inventory_details)); ?>" style="vertical-align: middle;">
                                                <?php echo $counter++; ?>
                                            </td>
                                            <td rowspan="<?php echo max(1, count($inventory_details)); ?>" style="vertical-align: middle;">
                                                <strong><?php echo htmlspecialchars($desc); ?></strong>
                                            </td>
                                            <td rowspan="<?php echo max(1, count($inventory_details)); ?>" class="num-right" style="vertical-align: middle;">
                                                <strong><?php echo $request_qty; ?></strong>
                                            </td>
                                            
                                            <?php if (!empty($inventory_details)): ?>
                                                <?php foreach ($inventory_details as $index => $product): ?>
                                                    <?php if ($index > 0): ?>
                                                        </tr><tr>
                                                    <?php endif; ?>
                                                    <td><?php echo htmlspecialchars($product['uom_id'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($product['size'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($product['floor_id'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($product['bay_id'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($product['location_code'] ?? 'N/A'); ?></td>
                                                    <td class="num-right"><?php echo $product['current_stock']; ?></td>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <td colspan="6" style="color: #dc2626; text-align: center;">
                                                    No inventory records found
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Approval Section -->
                    <div class="info-card">
                        <h2><i class="fa fa-check-circle"></i> Approval Management</h2>
                        
                        <?php if (!empty($grouped_products)): ?>
                            <form id="completeForm" action="" method="post" onsubmit="return validateBeforeComplete()">
                                <h3 style="color: #1e293b; font-size: 1rem; margin-bottom: 20px; font-weight: 600;">
                                    <i class="fa fa-boxes" style="color: #0062a9; margin-right: 8px;"></i>
                                    Available Stock & Approval
                                </h3>
                                
                                <!-- Scrollable Table -->
                                <div class="scrollable-table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Description</th>
                                                <th class="num-right">Request</th>
                                                <th class="num-right">Approve</th>
                                                <th class="num-right">Stock</th>
                                                <th class="num-right">Balance</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $counter = 1; 
                                            foreach ($tecrf_items_data as $item): 
                                                $desc = $item['description'];
                                                $request_qty = $item['request_quantity'];
                                                $total_stock = $grouped_products[$desc]['total_stock'] ?? 0;
                                                $approve_qty = $item['approve_quantity'] ?? 0;
                                                
                                                // Untuk COMPLETED items, balance = stock terkini
                                                if ($tecrf_data['status'] == 'Completed') {
                                                    $stock_display = $total_stock;  // stock terkini
                                                    $balance = $stock_display;      // balance = stock terkini
                                                } else {
                                                    $stock_display = $total_stock;  // stock sebelum tolak
                                                    $balance = $stock_display - $approve_qty;  // baki jika approve
                                                }
                                                
                                                $balance_class = $balance >= 0 ? 'balance-positive' : 'balance-negative';
                                                
                                                // Determine status
                                                if ($tecrf_data['status'] == 'Completed') {
                                                    $status = '<span style="color: #16a34a; font-weight: 600;">Completed</span>';
                                                } elseif ($balance < 0) {
                                                    $status = '<span style="color: #dc2626; font-weight: 600;">Insufficient</span>';
                                                } elseif ($approve_qty == 0) {
                                                    $status = '<span style="color: #f97316; font-weight: 600;">Pending</span>';
                                                } elseif ($approve_qty < $request_qty) {
                                                    $status = '<span style="color: #0062a9; font-weight: 600;">Partial</span>';
                                                } elseif ($approve_qty == $request_qty) {
                                                    $status = '<span style="color: #16a34a; font-weight: 600;">Approved</span>';
                                                } else {
                                                    $status = '<span style="color: #dc2626; font-weight: 600;">Exceeds</span>';
                                                }
                                            ?>
                                                <tr>
                                                    <td><?php echo $counter; ?></td>
                                                    <td><strong><?php echo htmlspecialchars($desc); ?></strong></td>
                                                    <td class="num-right"><strong><?php echo $request_qty; ?></strong></td>
                                                    <td class="num-right">
                                                        <input type="hidden" name="item_id[]" value="<?php echo $item['tecrf_item_id']; ?>">
                                                        <input type="hidden" name="description[]" value="<?php echo htmlspecialchars($desc); ?>">
                                                        <input type="number" 
                                                               name="approve_quantity[]" 
                                                               class="approve-input" 
                                                               value="<?php echo $approve_qty; ?>" 
                                                               min="0" 
                                                               max="<?php echo $request_qty; ?>"
                                                               data-request-qty="<?php echo $request_qty; ?>"
                                                               onchange="calculateBalance(this, <?php echo $counter; ?>)"
                                                               <?php echo ($tecrf_data['status'] == 'Completed') ? 'disabled' : ''; ?>>
                                                        <input type="hidden" id="current-stock-<?php echo $counter; ?>" value="<?php echo $stock_display; ?>">
                                                    </td>
                                                    <td class="num-right"><strong><?php echo $stock_display; ?></strong></td>
                                                    <td class="num-right">
                                                        <span id="balance-<?php echo $counter; ?>" class="<?php echo $balance_class; ?>">
                                                            <?php echo $balance; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $status; ?></td>
                                                </tr>
                                            <?php $counter++; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <input type="hidden" name="reference_number" value="<?php echo htmlspecialchars($reference_number); ?>">
                                
                                <!-- Complete Button - Only shown if status is not Completed -->
                                <?php if ($tecrf_data['status'] != 'Completed'): ?>
                                    <div class="complete-button-container">
                                        <button type="submit" name="complete" class="btn-complete">
                                            <i class="fas fa-check-double"></i> Complete Request & Update Stock
                                        </button>
                                        <p style="font-size: 0.85rem; color: #64748b; margin-top: 15px;">
                                            <i class="fas fa-info-circle" style="color: #0062a9;"></i> 
                                            This will save approved quantities and update inventory stock permanently
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </form>
                            
                            <!-- Completion Information -->
                            <?php if ($tecrf_data['status'] == 'Completed'): ?>
                                <div class="completion-info">
                                    <h4><i class="fas fa-check-circle"></i> Completion Details</h4>
                                    <p><strong>Completed By:</strong> <?php echo htmlspecialchars($tecrf_data['completed_by'] ?? 'N/A'); ?></p>
                                    <p><strong>Completed Date:</strong> <?php echo htmlspecialchars($tecrf_data['completed_date'] ?? 'N/A'); ?></p>
                                    <p style="margin-top: 15px; color: #16a34a;">
                                        <i class="fas fa-check-circle"></i> Inventory stock has been updated based on approved quantities.
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div style="text-align: center; padding: 50px; color: #64748b;">
                                <i class="fa fa-box-open" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;"></i>
                                <p style="font-size: 1.1rem;">No matching products found in inventory.</p>
                                <p style="font-size: 0.9rem; margin-top: 10px;">Please check inventory for these items.</p>
                            </div>
                        <?php endif; ?>  
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php 
$conn->close(); 
?>