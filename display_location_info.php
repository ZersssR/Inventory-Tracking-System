<?php
session_start();
include 'database.php';

// Fetch all data from the unit_location_info table
$sql = "SELECT * FROM unit_location_history ORDER BY assigned_at DESC"; // You can order by any column as needed
$result = $conn->query($sql);

// Check if the query was successful
if (!$result) {
    // Handle error if query fails
    die("Query failed: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Items History</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="styleInfo.css"> 
</head>
<body>
<a href="adminStaff.php" class="back-btn">Back to Dashboard</a>
<div class="container">
    <h1>Assigned Items History</h1>
    <div class="actions">
        <button class="action-btn print-btn" onclick="window.print()">
            <i class="fa fa-print"></i> Print Page
        </button>
        <a href="export_assigned_history.php ?>" class="action-btn excel-btn">
            <i class="fa fa-download"></i> Download Excel
        </a>
    </div>
    <?php if ($result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Description</th>
                    <th>Unit ID</th>
                    <th>Loadout Location</th>
                    <th>Reference Number</th>
                    <th>Date Assigned</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td><?php echo htmlspecialchars($row['unit_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['loadout_location']); ?></td>
                        <td><?php echo htmlspecialchars($row['action_notice_no']); ?></td>
                        <td><?php echo htmlspecialchars($row['assigned_at']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No unit location information available.</p>
    <?php endif; ?>
</div>

</body>
</html>

<?php
$conn->close();
?>
