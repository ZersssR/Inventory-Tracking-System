<?php
include 'database.php';

// Check if database connection is working
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define the current date
$today = date("Y-m-d");

// SQL query to fetch the total quantity per loadout location and TEC expiry categories
$sql_offshore = "SELECT 
                    loadout_location,
                    COUNT(CASE 
                        WHEN STR_TO_DATE(tec_expiry, '%Y-%m-%d') < '$today' THEN description
                        ELSE NULL 
                    END) AS expired,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') < 28 
                          AND STR_TO_DATE(tec_expiry, '%Y-%m-%d') >= '$today' THEN description 
                        ELSE NULL 
                    END) AS less_than_4_weeks,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') >= 28 
                          AND DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') < 56 THEN description
                        ELSE NULL 
                    END) AS less_than_8_weeks,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') >= 56 
                          AND DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') < 84 THEN description 
                        ELSE NULL 
                    END) AS less_than_12_weeks,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') >= 84 THEN description 
                        ELSE NULL 
                    END) AS valid
                FROM product
                WHERE action_notice_no IS NOT NULL AND action_notice_no != ''
                  AND loadout_location IS NOT NULL AND loadout_location != ''
                  AND loadout_date IS NOT NULL AND loadout_date != ''
                GROUP BY loadout_location
                HAVING (expired + less_than_4_weeks + less_than_8_weeks + less_than_12_weeks + valid) > 0  
                ORDER BY loadout_location";

$result_offshore = $conn->query($sql_offshore);

// Fetch Onshore Summary (New Query)
$sql_onshore = "SELECT 
                    COUNT(CASE 
                        WHEN STR_TO_DATE(tec_expiry, '%Y-%m-%d') < '$today' THEN description
                        ELSE NULL 
                    END) AS expired,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') < 28 
                          AND STR_TO_DATE(tec_expiry, '%Y-%m-%d') >= '$today' THEN description 
                        ELSE NULL 
                    END) AS less_than_4_weeks,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') >= 28 
                          AND DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') < 56 THEN description
                        ELSE NULL 
                    END) AS less_than_8_weeks,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') >= 56 
                          AND DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') < 84 THEN description 
                        ELSE NULL 
                    END) AS less_than_12_weeks,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') >= 84 THEN description 
                        ELSE NULL 
                    END) AS valid
                FROM product
                WHERE (action_notice_no IS NULL OR action_notice_no = '') 
                  AND (loadout_location IS NULL OR loadout_location = '') 
                  AND (loadout_date IS NULL OR loadout_date = '')";

$result_onshore = $conn->query($sql_onshore);
$row_onshore = $result_onshore->fetch_assoc();

// Fetch data into an array
$data = [];
$total_less_4 = $total_less_8 = $total_less_12 = $total_valid = $grand_total = 0;
$total_expired = 0;

while ($row = $result_offshore->fetch_assoc()) {
    $row['total'] = $row['expired'] + $row['less_than_4_weeks'] + $row['less_than_8_weeks'] + $row['less_than_12_weeks'] + $row['valid'];
    $total_expired += $row['expired']; // Add this line
    $total_less_4 += $row['less_than_4_weeks'];
    $total_less_8 += $row['less_than_8_weeks'];
    $total_less_12 += $row['less_than_12_weeks'];
    $total_valid += $row['valid'];
    $grand_total += $row['total'];
    $data[] = $row;
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Summary of Items by TEC Expiry </title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to right, #e0eafc, #cfdef3);
            color: #333;
            padding: 20px;
            position: relative; /* For absolute positioning of back button */
            font-size: 12px;
            }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #1d3557;
            border-right: 1px solid #ddd; /* Add vertical border between columns */
        }

        th:last-child, td:last-child {
            border-right: #1d3557; /* Remove border from last column */
        }
        th {
            background-color:rgba(242, 242, 242, 0.43);
            text-align: center;
        }
        thead {
            position: sticky;
            top: 0;
            background-color: #345d9d;
            color: white;
            z-index: 2;
        }
        .total-row {
            color: black;
            font-weight: bold;
        }
        .container thead, th{
            position: sticky; /* Make the <thead> sticky */
            top: 0; /* Stick the header to the top of the scrollable area */
            background-color: #345d9d; /* Background color for the sticky header */
            color: #fff; 
            z-index: 2; 
            text-align: center;
        }
        #sidebar {
            height: 100%;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #1d3557; /* Formal dark blue */
            color: white;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
            z-index: 1;
            border-radius: 15px;  /* Rounded corners */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        #sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;  /* Rounded corners */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }


        #sidebar-header h2 {
            margin: 0;
            font-size: 12px;
            font-weight: bold;
            color: #a8dadc;
        }

        #sidebar ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        #sidebar ul li {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: background-color 0.3s ease;
        }

        #sidebar ul li:hover {
            background-color: #457b9d;
        }

        #sidebar ul li a {
            color: #f1faee;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 12px;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        #sidebar ul li a:hover {
            color: #a8dadc;
        }

        #sidebar ul li i {
            font-size: 12px;
            margin-right: 10px;
            color: #a8dadc;
            transition: color 0.3s ease;
        }

        #sidebar ul li:hover i {
            color: white;
        }
        .container {
            max-width: 1200px;
            margin-right: 30px;
            margin-left: 250px;
            padding: 60px;
            background-color: #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border-radius: 12px;
        }
        .btn {
            display: inline-block;
            padding: 8px 18px;
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

        .btn:hover {
            background-color: #2980b9;
            box-shadow: 0 4px 8px rgba(41, 128, 185, 0.2);
            transform: translateY(-2px);
        }
        .actions {
            margin-top: 40px;
            display: flex;
            justify-content: right;
            gap: 15px;
            margin-bottom: 20px;
        }
        .action-btn {
            padding: 10px 15px;
            font-size: 12px;
            border: none;
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .print-btn {
            background-color: #1c3b66;
        }
        .print-btn:hover {
            background-color: #2980b9;
        }
        .excel-btn {
            background-color: #1c3b66;
        }
        .excel-btn:hover {
            background-color: #2980b9;
        }
        .print-logo {
            display: none; /* Hide by default */
            text-align: left;
        }
        @media print {
            .actions, .back-button, #sidebar {
                display: none;
            }
            .print-logo {
                display: block; /* Show only in print view */
                margin-bottom: 20px; /* Space between the logo and the table */
            }

            .print-logo img {
                width: 120px; /* Adjust size as needed */
                height: auto;
            }
            .container {
            width: 90%;
            max-width: 1100px;
            padding: 30px; /* Reduced padding */
            margin: 0 auto; /* Centering */
            margin-right: 400px; /* Adjust according to sidebar width (sidebar is 250px, add some space) */
            max-height: max-content;
            page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <div id="sidebar">
        <div id="sidebar-header">
            <h2><i class="fa fa-bars"></i> Menu</h2>
        </div>
        <ul>
            <li><a href="adminStaff.php"><i class="fa fa-file-alt"></i> Dashboard</a></li> 
            <li><a href="inventory_list.php"><i class="fa fa-list"></i> Inventory List</a></li>
            <li><a href="summary.php"><i class="fa fa-file-alt"></i> Item Summary </a></li>            <li><a href="approval.php"><i class="fa fa-tasks"></i> Request List</a></li>
            <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="container">
        <div class="back-button">
            <a href="adminStaff.php" class="btn">Back</a>
        </div><div class="print-logo">
        <img src="eog.png" alt="Company Logo">
    </div>
        <div class="actions">
            <button class="action-btn print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Page
            </button>
            <a href="export_summary.php" class="action-btn excel-btn">
                <i class="fas fa-file-excel"></i> Download Excel
            </a>
        </div>
        <h2>Summary of Items by TEC Expiry (Onshore)</h2>
        <table>
            <thead>
                <tr>
                    <th>Expired</th>
                    <th>Less than 4 Weeks</th>
                    <th>Less than 8 Weeks</th>
                    <th>Less than 12 Weeks</th>
                    <th>Valid (More than 12 Weeks)</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= $row_onshore['expired'] ?></td>
                    <td><?= $row_onshore['less_than_4_weeks'] ?></td>
                    <td><?= $row_onshore['less_than_8_weeks'] ?></td>
                    <td><?= $row_onshore['less_than_12_weeks'] ?></td>
                    <td><?= $row_onshore['valid'] ?></td>
                    <td><strong><?= array_sum($row_onshore) ?></strong></td>
                </tr>
            </tbody>
        </table>

        <h2>Summary of Items by TEC Expiry (Offshore)</h2>
        <div style="width: 30%; margin: 0 auto;">
            <canvas id="offshorePieChart"></canvas>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Loadout Location</th>
                    <th>Expired</th>
                    <th>Less than 4 Weeks</th>
                    <th>Less than 8 Weeks</th>
                    <th>Less than 12 Weeks</th>
                    <th>Valid (More than 12 Weeks)</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($data)): ?>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['loadout_location']) ?></td>
                            <td><?= htmlspecialchars($row['expired']) ?></td>
                            <td><?= htmlspecialchars($row['less_than_4_weeks']) ?></td>
                            <td><?= htmlspecialchars($row['less_than_8_weeks']) ?></td>
                            <td><?= htmlspecialchars($row['less_than_12_weeks']) ?></td>
                            <td><?= htmlspecialchars($row['valid']) ?></td>
                            <td><strong><?= htmlspecialchars($row['total']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <!-- Total row -->
                    <tr class="total-row">
                        <td><strong>Total</strong></td>
                        <td><strong><?= $total_expired ?></strong></td>
                        <td><strong><?= $total_less_4 ?></strong></td>
                        <td><strong><?= $total_less_8 ?></strong></td>
                        <td><strong><?= $total_less_12 ?></strong></td>
                        <td><strong><?= $total_valid ?></strong></td>
                        <td><strong><?= $grand_total ?></strong></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script>
        // Data from PHP
        const offshoreData = {
            labels: ['Expired', 'Less than 4 Weeks', 'Less than 8 Weeks', 'Less than 12 Weeks', 'Valid'],
            datasets: [{
                label: 'TEC Expiry',
                data: [
                    <?= $total_expired ?>,
                    <?= $total_less_4 ?>,
                    <?= $total_less_8 ?>,
                    <?= $total_less_12 ?>,
                    <?= $total_valid ?>
                ],
                backgroundColor: [
                    '#D30000', // Expired - Red
                    '#8c4a44', // Less than 4 Weeks - chocolate
                    '#ff4000', // Less than 8 Weeks - orange
                    '#f1c40f', // Less than 12 Weeks - yellow
                    '#74d649'  // Valid - green
                ],
                borderColor: '#fff',
                borderWidth: 1
            }]
        };

        // Config
        const config = {
            type: 'pie',
            data: offshoreData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (tooltipItem) {
                                let value = tooltipItem.raw;
                                let total = offshoreData.datasets[0].data.reduce((acc, val) => acc + val, 0);
                                let percentage = ((value / total) * 100).toFixed(2);
                                return `${value} items (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        };

        // Render the chart
        const ctx = document.getElementById('offshorePieChart').getContext('2d');
        new Chart(ctx, config);
    </script>
</body>
</html>