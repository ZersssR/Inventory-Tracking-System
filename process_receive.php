<?php
// Connect to database
include('database.php');

// Get form data
$location = $_POST['location'];
$backload_sheet_no = $_POST['backload_sheet_no'];
$selected_items = $_POST['items'];

// Generate current date and time
$backload_date_time = date('Y-m-d H:i:s'); // TIMESTAMP for backload_product
$backload_date_only = date('Y-m-d');       // DATE for product

// Process each selected item
foreach ($selected_items as $unit_id) {
    // Fetch the details of the product to be archived
    $query = "SELECT * FROM product WHERE unit_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $unit_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);

    // Insert the product's details into the new table `backload_product`
    $insert_backlog_query = "INSERT INTO backload_product (unit_id, description, backload_sheet_no, backload_date) 
                             VALUES (?, ?, ?, ?)";
    $stmt_backlog = mysqli_prepare($conn, $insert_backlog_query);
    mysqli_stmt_bind_param($stmt_backlog, "ssss", 
                           $unit_id, $product['description'], $backload_sheet_no, $backload_date_time);
    mysqli_stmt_execute($stmt_backlog);

    // Update the product table by setting action_notice_no, loadout_date, and loadout_location to NULL
    $update_query = "UPDATE product 
                     SET action_notice_no = NULL, loadout_date = NULL, loadout_location = NULL, 
                         backload_sheet_no = ?, backload_date = ? 
                     WHERE unit_id = ?";
    $stmt_update = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt_update, "sss", $backload_sheet_no, $backload_date_only, $unit_id);
    mysqli_stmt_execute($stmt_update);
}

// Redirect the user to the confirmation page or show a popup
echo "<script>alert('Items have been successfully received!'); window.location.href = 'received_items.php?date=$backload_date_only';</script>";
?>
