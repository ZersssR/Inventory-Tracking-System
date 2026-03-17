<?php
// Include database connection
include('database.php');

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=export_assignLoadout_transaction.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Query to fetch data
$query = "
SELECT ulh.action_notice_no AS ulh_action_notice, 
           ulh.unit_id,
           GROUP_CONCAT(DISTINCT ulh.description ORDER BY ulh.description) AS ulh_description,
           GROUP_CONCAT(DISTINCT ulh.loadout_location ORDER BY ulh.loadout_location) AS ulh_location,
           GROUP_CONCAT(DISTINCT ulh.assigned_at ORDER BY ulh.assigned_at) AS assigned_at,
            loadout_date
    FROM unit_location_history ulh
    JOIN loadout_history lh ON ulh.action_notice_no = lh.action_notice_no
    GROUP BY ulh.unit_id, ulh.action_notice_no"; 

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

// Output table headers
echo "Action Notice No\tDescription\tUnit ID\tLoadout Location\tAssigned At\tLoadout Date\n"; // Tab-separated values for Excel

// Output rows
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['ulh_action_notice'] . "\t" . 
         $row['ulh_description'] .  "\t" . 
         $row['unit_id']. "\t" . 
         $row['ulh_location']. "\t" . 
         $row['assigned_at']. "\t" . 
         $row['loadout_date']."\n";
}

// Close connection
mysqli_close($conn);
?>
