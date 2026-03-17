<?php
include "session.php";
include "database.php";

// Function to calculate days offshore
function calculateDaysOffshore($loadout_date) {
    if (!$loadout_date || $loadout_date == '0000-00-00') {
        return '-'; // Return '-' if the loadout_date is empty or invalid
    }

    try {
        $loadout_date = new DateTime($loadout_date); // Attempt to parse the date
        $current_date = new DateTime(); // Get the current date
        $interval = $loadout_date->diff($current_date); // Calculate the difference
        return $interval->days; // Return the number of days
    } catch (Exception $e) {
        return '-'; // In case of error, return '-'
    }
}

// Handle form submission for updating product
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['unit_id'])) {
    $unit_id = $_POST['unit_id'];
    $tec_expiry = $_POST['tec_expiry'];
    $action_notice_no = $_POST['action_notice_no'];
    $loadout_date = $_POST['loadout_date'];
    $loadout_location = $_POST['loadout_location'];
    $backload_date = $_POST['backload_date'];
    $backload_sheet_no = $_POST['backload_sheet_no'];
    $description = $_POST['description'];
    $size = $_POST['size'];
    $swl = $_POST['swl'];
    $remarks = $_POST['remarks'];
    $status = $_POST['status'];
    $certificate_validity = $_POST['certificate_validity'];
    $days_offshore = calculateDaysOffshore($loadout_date);
    $category = isset($_POST['category']) ? $_POST['category'] : null;
    $type = $_POST['type'];
    $tec_group = $_POST['tec_group'];
    $po_number = $_POST['po_number'];
    $serial_no = $_POST['serial_no'];
    $qty_inhouse = $_POST['qty_inhouse'];
    $qty_use = $_POST['qty_use'];
    $qty_damage = $_POST['qty_damage'];
    $date_damage = $_POST['date_damage'];
    $qty_dispose = $_POST['qty_dispose'];
    $date_dispose = $_POST['date_dispose'];
    $qty_valid = $_POST['qty_valid'];
    $storage_location = isset($_POST['storage_location']) ? $_POST['storage_location'] : null;

    // Prepare the update SQL query
    $update_sql = "UPDATE product SET 
        tec_expiry=?, action_notice_no=?, loadout_date=?, loadout_location=?,
        backload_date=?, backload_sheet_no=?, description=?, size=?, swl=?, remarks=?, 
        status=?, certificate_validity=?, days_offshore=?, category=?, type=?, tec_group=?, 
        po_number=?, serial_no=?, qty_inhouse=?, qty_use=?, qty_damage=?, date_damage=?, 
        qty_dispose=?, date_dispose=?, qty_valid=?, storage_location=? 
        WHERE unit_id=?";

    $stmt = $conn->prepare($update_sql);

    // Bind parameters, handle NULL values correctly
    $stmt->bind_param("ssssssssssssisssssiiisisiss", 
    $tec_expiry, $action_notice_no, $loadout_date, $loadout_location, 
    $backload_date, $backload_sheet_no, $description, $size, $swl, $remarks, 
    $status, $certificate_validity, $days_offshore, $category, $type, $tec_group, 
    $po_number, $serial_no, $qty_inhouse, $qty_use, $qty_damage, $date_damage, 
    $qty_dispose, $date_dispose, $qty_valid, $storage_location, $unit_id
);

    if ($stmt->execute()) {
        $message = "success";
    } else {
        $message = "error";
    }
    $stmt->close();
}

// Get the product details for editing
$product = null;
if (isset($_GET['unit_id']) && !isset($_POST['unit_id'])) {
    $unit_id = $_GET['unit_id'];
    $sql = "SELECT * FROM product WHERE unit_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $unit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Items</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <link rel="stylesheet" href="styleListItems.css">
    <style>
        .container {
            max-width: 1500px;
            margin: 0 auto;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select {
            padding: 8px;
            font-size: 14px;
        }
        .form-actions {
            margin-top: 20px;
            text-align: center;
        }
        .form-actions button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            color: #ffffff;
            background-color: #1c3b66;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            gap: 10px;
        }

        .btn:hover {
            background-color: #2980b9;
            box-shadow: 0 4px 8px rgba(41, 128, 185, 0.2);
            transform: translateY(-2px);
        }


        .btn-danger {
            background-color: #e74c3c;
        }

        .btn-danger:hover {
            background-color: #c0392b;
            box-shadow: 0 4px 8px rgba(192, 57, 43, 0.2);
        }
        
        .button-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            gap: 15px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .show {
            display: flex;
        }
    </style>
</head>
<body>
    <div class="back-button">
        <a href="list_iitems.php" class="btn">Back</a>
    </div>
    <div class="container">
        <h1>Edit List</h1>
        <form method="post" action="" id="editForm"> 
            <input type="hidden" name="tec_id" value="<?php echo $product['unit_id']; ?>">          
            <div class="form-grid">
                <!-- Column 1 (Fields 1-13) -->
                <div>
                    <div class="form-group">
                        <label>Certification Expiry Date:</label>
                        <input type="date" name="tec_expiry" value="<?php echo $product['tec_expiry']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Unit ID:</label>
                        <input type="text" name="unit_id" value="<?php echo $product['unit_id']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Action Notice No:</label>
                        <input type="text" name="action_notice_no" value="<?php echo $product['action_notice_no']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Loadout Date:</label>
                        <input type="date" name="loadout_date" value="<?php echo $product['loadout_date']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Loadout Location:</label>
                        <input type="text" name="loadout_location" value="<?php echo $product['loadout_location']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Backload Date:</label>
                        <input type="date" name="backload_date" value="<?php echo $product['backload_date']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Backload Sheet No:</label>
                        <input type="text" name="backload_sheet_no" value="<?php echo $product['backload_sheet_no']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Description:</label>
                        <input type="text" name="description" value="<?php echo $product['description']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Size:</label>
                        <input type="text" name="size" value="<?php echo $product['size']; ?>">
                    </div>
                    <div class="form-group">
                        <label>SWL:</label>
                        <input type="text" name="swl" value="<?php echo $product['swl']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Remarks:</label>
                        <input type="text" name="remarks" value="<?php echo $product['remarks']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status" class="form-control">
                            <option value="Active" <?php echo ($product['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Damage" <?php echo ($product['status'] === 'Damage') ? 'selected' : ''; ?>>Damage</option>
                            <option value="Dispose" <?php echo ($product['status'] === 'Dispose') ? 'selected' : ''; ?>>Dispose</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Certificate Validity:</label>
                        <input type="text" name="certificate_validity" value="<?php echo $product['certificate_validity']; ?>">
                    </div>
                </div>

                <!-- Column 2 (Fields 14-27) -->
                <div>
                    <div class="form-group">
                        <label>Days Offshore:</label>
                        <input type="text" name="days_offshore" value="<?php echo calculateDaysOffshore($product['loadout_date']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Category:</label>
                        <input type="text" name="category" value="<?php echo $product['category']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Type:</label>
                        <input type="text" name="type" value="<?php echo $product['type']; ?>">
                    </div>
                    <div class="form-group">
                        <label>TEC Group:</label>
                        <input type="text" name="tec_group" value="<?php echo $product['tec_group']; ?>">
                    </div>
                    <div class="form-group">
                        <label>PO Number:</label>
                        <input type="text" name="po_number" value="<?php echo $product['po_number']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Serial No:</label>
                        <input type="text" name="serial_no" value="<?php echo $product['serial_no']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Quantity Inhouse:</label>
                        <input type="number" name="qty_inhouse" value="<?php echo $product['qty_inhouse']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Quantity In Use:</label>
                        <input type="number" name="qty_use" value="<?php echo $product['qty_use']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Quantity Damage:</label>
                        <input type="number" name="qty_damage" value="<?php echo $product['qty_damage']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Date Damage:</label>
                        <input type="date" name="date_damage" value="<?php echo $product['date_damage']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Quantity Dispose:</label>
                        <input type="number" name="qty_dispose" value="<?php echo $product['qty_dispose']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Date Dispose:</label>
                        <input type="date" name="date_dispose" value="<?php echo $product['date_dispose']; ?>">
                    </div>
                    <div class="form-group">
                        <label> Quantity Valid:</label>
                        <input type="number" name="qty_valid" value="<?php echo $product['qty_valid']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Storage Location:</label>
                        <input type="text" name="storage_location" value="<?php echo $product['storage_location']; ?>">
                    </div>
                </div>
            </div>

            <div class="button-container">
                <button type="button" class="btn" onclick="confirmUpdate()">Update Items</button>                
                <a href="list_iitems.php" class="btn btn-danger">Cancel</a>
            </div>
        </form>
    </div>
    <!-- Modal 1: Confirmation -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Update</h3>
            <p>Are you sure you want to update this item?</p>
            <button onclick="document.getElementById('editForm').submit();" class="btn">Yes</button>
            <button onclick="closeModal()" class="btn btn-danger">No</button>
        </div>
    </div>

    <!-- Modal 2: Success/Error -->
    <?php if ($message === "success"): ?>
    <div id="resultModal" class="modal show">
        <div class="modal-content">
            <h3>Success!</h3>
            <p>Item updated successfully.</p>
            <a href="list_iitems.php" class="btn">OK</a>
        </div>
    </div>
    <?php elseif ($message === "error"): ?>
    <div id="resultModal" class="modal show">
        <div class="modal-content">
            <h3>Error</h3>
            <p>Failed to update item. Please try again.</p>
            <button onclick="closeModal()" class="btn btn-danger">Close</button>
        </div>
    </div>
    <?php endif; ?>
<script>
        function confirmUpdate() {
            document.getElementById('confirmationModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('confirmationModal').style.display = 'none';
            document.getElementById('resultModal').style.display = 'none';
        }
    </script>
</body>
</html>