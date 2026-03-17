<?php
include 'user_redirect.php';
include 'session.php';
include('database.php');

// Fetch distinct reference numbers from the tecrf1 table
$sql_reference_numbers = "SELECT DISTINCT reference_number FROM tecrf1";
$result_reference_numbers = $conn->query($sql_reference_numbers);

$reference_numbers = [];
if ($result_reference_numbers && $result_reference_numbers->num_rows > 0) {
    while ($row = $result_reference_numbers->fetch_assoc()) {
        $reference_numbers[] = $row['reference_number'];
    }
}

// Fetch latest backload_dates for each reference number from the product table
$backload_dates = [];
if (!empty($reference_numbers)) {
    foreach ($reference_numbers as $ref_number) {
        $sql_backload_date = "SELECT backload_date FROM product WHERE action_notice_no = ? ORDER BY backload_date DESC LIMIT 1";
        $stmt_backload_date = $conn->prepare($sql_backload_date);
        $stmt_backload_date->bind_param("s", $ref_number);
        $stmt_backload_date->execute();
        $result_backload_date = $stmt_backload_date->get_result();

        if ($result_backload_date && $result_backload_date->num_rows > 0) {
            $row = $result_backload_date->fetch_assoc();
            $backload_dates[$ref_number] = $row['backload_date'];
        } else {
            $backload_dates[$ref_number] = "Not available"; // Handle case when no backload_date is found
        }
        $stmt_backload_date->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Received Items List</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 900px;
            margin: 20px auto;
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 16px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            color: #333;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .back-btn {
            display: inline-block;
            margin: 20px 0;
            padding: 12px 24px;
            font-size: 18px;
            background-color: #007bff; /* Bootstrap Primary Color */
            color: white;
            text-decoration: none;
            border-radius: 8px;
            text-align: center;
            transition: background-color 0.3s ease;
        }
        .back-btn:hover {
            background-color: #0056b3;
        }
        @media screen and (max-width: 768px) {
            table, th, td {
                font-size: 14px;
            }
            .back-btn {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Received Items List</h1>

    <a href="<?php echo $backUrl; ?>" class="back-button">Back</a>

    <table>
        <thead>
            <tr>
                <th>Reference Number</th>
                <th>Latest Backload Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($reference_numbers)): ?>
                <?php foreach ($reference_numbers as $ref_number): ?>
                    <tr>
                        <td>
                            <a href="receive_items.php?reference_number=<?php echo urlencode($ref_number); ?>">
                                <?php echo htmlspecialchars($ref_number); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($backload_dates[$ref_number]); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="2">No received items available.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

</body>
</html>
