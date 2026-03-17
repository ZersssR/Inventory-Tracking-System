<?php
// Include database connection
include('database.php');

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=export_backload_transaction.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Query to fetch data
$query = "
    SELECT 
       backload_sheet_no,
       description,
       unit_id,
       backload_date
    FROM backload_product

";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

// Output table headers
echo "Backload Sheet No\tDescription\tUnit ID\tBackload Date\n"; // Tab-separated values for Excel

// Output rows
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['backload_sheet_no'] . "\t" . 
         $row['description'] .  "\t" . 
         $row['unit_id']. "\t" . 
         $row['backload_date']."\n";
}

// Close connection
mysqli_close($conn);
?>
