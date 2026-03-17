<?php
// Include database connection
include('database.php');

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=monthly_report.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Get the filter values from the URL (default to current month and year)
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Query to fetch Loadout Items
$loadout_query = "
    SELECT description, unit_id, loadout_date 
    FROM loadout_history 
    WHERE MONTH(loadout_date) = ? AND YEAR(loadout_date) = ?
";
$loadout_stmt = $conn->prepare($loadout_query);
$loadout_stmt->bind_param('ii', $filter_month, $filter_year);
$loadout_stmt->execute();
$loadout_result = $loadout_stmt->get_result();

// Query to fetch Backload Items
$backload_query = "
    SELECT description, unit_id, backload_date 
    FROM backload_product 
    WHERE MONTH(backload_date) = ? AND YEAR(backload_date) = ?
";
$backload_stmt = $conn->prepare($backload_query);
$backload_stmt->bind_param('ii', $filter_month, $filter_year);
$backload_stmt->execute();
$backload_result = $backload_stmt->get_result();

// Output the Loadout Items section headers
echo "Loadout Items (" . date('F Y', strtotime("$filter_year-$filter_month-01")) . ")\n";
echo "No\tDescription\tUnit ID\tLoadout Date\n"; // Tab-separated headers

// Output Loadout Items data
$no = 1;
while ($row = $loadout_result->fetch_assoc()) {
    echo $no++ . "\t" . htmlspecialchars($row['description']) . "\t" . htmlspecialchars($row['unit_id']) . "\t" . htmlspecialchars($row['loadout_date']) . "\n";
}

// Add a section for Backload Items
echo "\nBackload Items (" . date('F Y', strtotime("$filter_year-$filter_month-01")) . ")\n";
echo "No\tDescription\tUnit ID\tBackload Date\n"; // Tab-separated headers

// Output Backload Items data
$no = 1;
while ($row = $backload_result->fetch_assoc()) {
    echo $no++ . "\t" . htmlspecialchars($row['description']) . "\t" . htmlspecialchars($row['unit_id']) . "\t" . htmlspecialchars($row['backload_date']) . "\n";
}

// Close database connections
$loadout_stmt->close();
$backload_stmt->close();
$conn->close();
?>
