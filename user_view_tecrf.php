<?php
include 'session.php';
include 'database.php';

// Get reference number from URL
$ref_no = $_GET['ref_no'] ?? '';

if (!$ref_no) {
    header('Location: list_tecrf.php');
    exit();
}

// Fetch request details
$sql = "SELECT t1.*, u.full_name 
        FROM tecrf1 t1 
        LEFT JOIN users u ON t1.user_id = u.user_id 
        WHERE t1.reference_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $ref_no);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

if (!$request) {
    echo "Request not found";
    exit();
}

// Fetch request items
$items_sql = "SELECT * FROM tecrf2 WHERE reference_number = ?";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("s", $ref_no);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TECRF Request Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .detail-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .detail-table th, .detail-table td { border: 1px solid #ddd; padding: 8px; }
        .detail-table th { background-color: #f2f2f2; }
        .print-btn { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>TECRF Request Details</h2>
            <h4>Reference: <?php echo $ref_no; ?></h4>
        </div>
        
        <table class="detail-table">
            <tr>
                <th>Reference Number</th>
                <td><?php echo $request['reference_number']; ?></td>
                <th>Date Required</th>
                <td><?php echo $request['date_required']; ?></td>
            </tr>
            <tr>
                <th>Client</th>
                <td><?php echo $request['client']; ?></td>
                <th>Project</th>
                <td><?php echo $request['project']; ?></td>
            </tr>
            <tr>
                <th>Charge Code</th>
                <td><?php echo $request['charge_code']; ?></td>
                <th>Location</th>
                <td><?php echo $request['location']; ?></td>
            </tr>
            <tr>
                <th>Requested By</th>
                <td><?php echo $request['full_name']; ?></td>
                <th>Status</th>
                <td><?php echo $request['status']; ?></td>
            </tr>
        </table>
        
        <h4>Requested Items</h4>
        <table class="detail-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Size</th>
                    <th>UOM</th>
                    <th>Floor</th>
                    <th>Bay</th>
                    <th>Location</th>
                    <th>Current Stock</th>
                    <th>Request Qty</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php while($item = $items_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                    <td><?php echo htmlspecialchars($item['size']); ?></td>
                    <td><?php echo htmlspecialchars($item['uom']); ?></td>
                    <td><?php echo htmlspecialchars($item['floor']); ?></td>
                    <td><?php echo htmlspecialchars($item['bay']); ?></td>
                    <td><?php echo htmlspecialchars($item['location_code']); ?></td>
                    <td><?php echo $item['current_stock']; ?></td>
                    <td><?php echo $item['request_quantity']; ?></td>
                    <td><?php echo htmlspecialchars($item['remarks']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <div class="text-center">
            <button class="btn btn-primary print-btn" onclick="window.print()">Print</button>
            <a href="list_tecrf.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>
</body>
</html>
<?php
$conn->close();
?>