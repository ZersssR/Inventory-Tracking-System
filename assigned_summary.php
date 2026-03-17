<?php
session_start();
include 'database.php';

// Fetch the reference number from GET
$reference_number = filter_input(INPUT_GET, 'reference_number', FILTER_SANITIZE_STRING);

// Fetch details from tecrf1 based on the reference number
$sql1 = $conn->prepare("SELECT * FROM tecrf1 WHERE reference_number = ?");
$sql1->bind_param('s', $reference_number);
$sql1->execute();
$result1 = $sql1->get_result();

if ($result1 && $result1->num_rows > 0) {
    $tecrf1_data = $result1->fetch_assoc();
} else {
    echo "<p>No data found for the provided reference number.</p>";
    exit;
}

// Fetch unique assigned unit_ids from the session
$assigned_unit_ids = array_unique($_SESSION['assigned_unit_ids'] ?? []);

if (empty($assigned_unit_ids)) {
    echo "<p>No items were assigned.</p>";
    exit;
}

// Fetch all assigned items from product based on unique assigned unit_ids
$assigned_items = [];
if (!empty($assigned_unit_ids)) {
    $placeholders = implode(',', array_fill(0, count($assigned_unit_ids), '?'));
    $types = str_repeat('s', count($assigned_unit_ids));

    // Update the SQL query to select distinct items
    $sql2 = $conn->prepare("SELECT DISTINCT unit_id, description, tec_expiry, loadout_location FROM product WHERE unit_id IN ($placeholders)");
    $sql2->bind_param($types, ...$assigned_unit_ids);
    $sql2->execute();
    $result2 = $sql2->get_result();

    if ($result2 && $result2->num_rows > 0) {
        // Use an associative array to avoid duplicates
        $unique_items = [];
        while ($row = $result2->fetch_assoc()) {
            $key = $row['unit_id'] . '_' . $row['description']; // Create a unique key
            if (!isset($unique_items[$key])) {
                $unique_items[$key] = $row; // Add to unique items array
            }
        }
        // Re-index the unique items array to get it back into a standard array
        $assigned_items = array_values($unique_items);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Summary Page</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            background: linear-gradient(to right, #e0eafc, #cfdef3);
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            background-color: #ffffff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        h1 {
            font-size: 30px;
            text-align: center;
            color: #345d9d;
            margin-bottom: 20px;
            border-bottom: 3px solid #345d9d;
            padding-bottom: 10px;
        }
        
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            color: #ffffff;
            background-color: #1c3b66;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        .tecrf-details-container {
            background-color: #f9f9f9;
            padding: 15px;
           
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .tecrf-details-container h3 {
            margin-bottom: 15px;
            font-size: 22px;
            color: #345d9d;
            border-bottom: 2px solid #d3d3d3;
            padding-bottom: 5px;
        }
        .tecrf-details-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .tecrf-detail {
            width: calc(50% - 10px);
            font-size: 16px;
            color: #555;
        }
        .detail-title {
            font-weight: bold;
            color: #333;
        }
        .detail-value {
            margin-left: 10px;
            color: #666;
            font-style: italic;
        }
        .items-table table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th, .items-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .items-table th {
            background-color: #f4f4f4;
        }
        .items-table tr:hover {
            background-color: #f1f1f1;
        }
        .print-button {
            display: block;
            margin-bottom: 20px;
            display: inline-block; /* Change to inline-block for centering */
            text-align: center;
            font-size: 12px;
            color: #ffffff;
            background-color: #1c3b66;
            text-decoration: none;
            border-radius: 8px;
            width: 80px; /* Set a fixed width */
            height: 40px; /* Set a fixed height */
            line-height: 40px; /* Center text vertically */
            font-size: 12px; /* Further reduce font size */
            text-align: center;
            
        }
        .button-container {
        text-align: center; /* Center the button */
        margin-top: 20px;
}

        .print-button:hover {
            background-color: #0056b3;
        }
        .print-logo {
            display: none; /* Hide by default */
            text-align: left;
        }
        @media print {
            .print-button, .back-btn {
                display: none; /* Hide these elements during printing */
            }
            .print-logo {
                        display: block; /* Show only in print view */
                        margin-bottom: 20px; /* Space between the logo and the table */
                    }

                    .print-logo img {
                        width: 120px; /* Adjust size as needed */
                        height: auto;
                    }
        }
    </style>
</head>
<body>

<div class="container">
<div class="print-logo">
        <img src="eog.png" alt="Company Logo">
    </div>
    <h1>Summary of Assigned Items</h1>
    <a href="approval.php" class="back-btn">Back</a>

    <div class="tecrf-details-container">
        <h3>TECRF Details:</h3>
        <div class="tecrf-details-grid">
            <div class="tecrf-detail">
                <span class="detail-title">Reference Number:</span>
                <span class="detail-value"><?php echo htmlspecialchars($tecrf1_data['reference_number']); ?></span>
            </div>
            <div class="tecrf-detail">
                <span class="detail-title">Project:</span>
                <span class="detail-value"><?php echo htmlspecialchars($tecrf1_data['project'] ); ?></span>
            </div>
            <div class="tecrf-detail">
                <span class="detail-title">Client:</span>
                <span class="detail-value"><?php echo htmlspecialchars($tecrf1_data['client'] ); ?></span>
            </div>
            <div class="tecrf-detail">
                <span class="detail-title">Location:</span>
                <span class="detail-value"><?php echo htmlspecialchars($tecrf1_data['location']); ?></span>
            </div>
        </div>
    </div>

    <div class="items-table">
        <h3>Assigned Items:</h3>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Description</th>
                    <th>Unit ID</th>
                    <th>Expiry Date</th>
                    <th>Loadout Location</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $counter = 1;
                foreach ($assigned_items as $item): ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td><?php echo htmlspecialchars($item['unit_id']); ?></td>
                        <td><?php echo htmlspecialchars($item['tec_expiry']); ?></td>
                        <td><?php echo htmlspecialchars($item['loadout_location']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="button-container">
    <a href="#" class="print-button" onclick="window.print()">Print</a>
</div>

</body>
</html>