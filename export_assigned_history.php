<?php
// Include the database connection
include('database.php');

// Set headers to force download as Excel file
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=HistoryAssigned.xls");
header("Pragma: no-cache");
header("Expires: 0");

$query = "
    SELECT 
        description,
        unit_id,
        loadout_location,
        action_notice_no,
        assigned_at
    FROM unit_location_history";
    
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

// Output the column headers for Excel
echo "Description\tUnit ID\tLoadout Location\tReference Number\tDate Assigned\n";

// Output the data
while ($row = mysqli_fetch_assoc($result)) {
    echo 
        $row['description'] . "\t" .
         $row['unit_id'] . "\t" .
         $row['loadout_location'] . "\t" .
         $row['action_notice_no'] . "\t" .
         $row['assigned_at'] . "\n";
}

// Close the database connection
mysqli_close($conn);
?>
