<?php
session_start();
include 'database.php';

// Fetch the reference number from GET
$reference_number = filter_input(INPUT_GET, 'reference_number', FILTER_SANITIZE_STRING);

// Process removal of selected items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_part_id'])) {
    $remove_part_ids = $_POST['remove_part_id'];
    $placeholders = implode(',', array_fill(0, count($remove_part_ids), '?'));
    $types = str_repeat('s', count($remove_part_ids));
    
    // Prepare SQL query to remove assignment from inventory_product table
    $sql_delete = $conn->prepare("UPDATE inventory_product SET status = 1 WHERE part_id IN ($placeholders)");
    $sql_delete->bind_param($types, ...$remove_part_ids);
    $sql_delete->execute();

    // Update session to remove the removed part IDs
    $_SESSION['assigned_part_ids'] = array_diff($_SESSION['assigned_part_ids'] ?? [], $remove_part_ids);
}

// Fetch details from tecrf based on the reference number
$sql1 = $conn->prepare("SELECT * FROM tecrf WHERE reference_number = ?");
$sql1->bind_param('s', $reference_number);
$sql1->execute();
$result1 = $sql1->get_result();

if ($result1 && $result1->num_rows > 0) {
    $tecrf_data = $result1->fetch_assoc();
} else {
    echo "<p>No data found for the provided reference number.</p>";
    exit;
}

// Fetch unique assigned part_ids from the session
$assigned_part_ids = array_unique($_SESSION['assigned_part_ids'] ?? []);

// Fetch all assigned items from inventory_product based on unique assigned part_ids
$assigned_items = [];
if (!empty($assigned_part_ids)) {
    $placeholders = implode(',', array_fill(0, count($assigned_part_ids), '?'));
    $types = str_repeat('s', count($assigned_part_ids));

    $sql2 = $conn->prepare("SELECT DISTINCT part_id, description, expire_date, current_stock FROM inventory_product WHERE part_id IN ($placeholders)");
    $sql2->bind_param($types, ...$assigned_part_ids);
    $sql2->execute();
    $result2 = $sql2->get_result();

    if ($result2 && $result2->num_rows > 0) {
        $unique_items = [];
        while ($row = $result2->fetch_assoc()) {
            $key = $row['part_id'] . '_' . $row['description'];
            if (!isset($unique_items[$key])) {
                $unique_items[$key] = $row;
            }
        }
        $assigned_items = array_values($unique_items);
    }
}

// Fetch all items from tecrf_items based on the tecrf_id
$sql3 = $conn->prepare("SELECT * FROM tecrf_items WHERE tecrf_id = ?");
$sql3->bind_param('i', $tecrf_data['tecrf_id']);
$sql3->execute();
$result3 = $sql3->get_result();

$tecrf_items_data = [];
if ($result3 && $result3->num_rows > 0) {
    while ($row = $result3->fetch_assoc()) {
        $tecrf_items_data[] = $row;
    }
}

// Fetch available product details based on description from tecrf_items
$product_data = [];
foreach ($tecrf_items_data as $item) {
    $description = $item['description'];
    
    $removed_part_ids = $_POST['remove_part_id'] ?? [];
    
    $sql4 = $conn->prepare("SELECT * FROM inventory_product WHERE description = ? AND status = 1");
    $sql4->bind_param('s', $description);
    $sql4->execute();
    $result4 = $sql4->get_result();

    while ($product = $result4->fetch_assoc()) {
        // Skip the removed items from the product list
        if (!in_array($product['part_id'], $removed_part_ids)) {
            $product_data[] = $product;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Edit Assigned Items</title>
  <style>
    /* Overall Page Styling */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-size: 12px;
        background: linear-gradient(to right, #e0eafc, #cfdef3);
        color: #333;
        margin: 0;
        padding: 0;
    }

    .container {
        max-width: 1000px;
        margin: 50px auto;
        background-color: #ffffff;
        padding: 40px; /* Increased padding for cleaner look */
        border-radius: 8px;
        box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
    }

    h1{
        font-size: 28px;
            color: #2a5298;
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2a5298;
            padding-bottom: 10px;
            font-weight: 700;
    }
    
    h3, h4 {
        color: #004085;
        margin-bottom: 20px;
    }

    h1 {
        font-size: 2em;
    }

    h3 {
        font-size: 1.5em;
        margin-bottom: 30px;
    }

    h4 {
        font-size: 1.3em;
        color: #004085;
    }

    /* Table Styling */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 40px; /* Spacing above tables */
        margin-bottom: 70px;
    }

    th, td {
        padding: 15px; /* Increased padding for better spacing */
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    th {
        background-color: #e9ecef; /* Light gray for standout headers */
        font-weight: bold;
        color: #333;
        text-transform: uppercase;
        font-size: 1em;
    }

    tr:nth-child(even) {
        background-color: #f9f9f9; /* Light stripe effect for even rows */
    }

    tbody tr:hover {
        background-color: #f1f1f1; /* Subtle hover effect */
    }
    
    .available-details-container {
    background-color: #f9f9f9; /* Light gray background */
    padding: 50px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    margin-top: 40px;
}
    
    .assign-details-container {
    background-color: #f9f9f9; /* Light gray background */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    margin-bottom: 50px; /* Add spacing below */
    margin-bottom: 20px; 
    margin-top: 40px;
}
    
    .tecrf-details-container {
    background-color: #f9f9f9; /* Light gray background */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    margin-bottom:20px; /* Add spacing below */
}

.tecrf-details-container h3 {
    margin-bottom: 35px; 
    font-size: 22px; /* Adjust title size */
    color: #345d9d; /* Match theme color */
    border-bottom: 2px solid #d3d3d3; /* Subtle underline */
    padding-bottom: 5px;

}

.tecrf-details-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 20px; /* Add spacing between items */
    margin-bottom: 40px; 
}

.tecrf-detail {
    width: calc(50% - 10px); /* Two items per row with spacing */
    font-size: 16px;
    color: #555; /* Slightly darker text */
}

.detail-title {
    font-weight: bold;
    color: #333;
}

.detail-value {
    margin-left: 10px;
    color: #666;
    font-style: italic; /* Optional, for emphasis */
}
 
input[type="checkbox"] {
        transform: scale(2.0); /* Adjust scale for desired size */
        margin-right: -20px; /* Optional: spacing */
        margin: auto;
        display: block; /* Ensures proper centering in the table cell */
}
    /* Button Styling */
    button {
        background-color: #0056b3;
        color: #ffffff;
        border: none;
        padding: 12px 20px; /* Enhanced padding for better usability */
        font-size: 1.1em;
        cursor: pointer;
        border-radius: 8px;
        transition: background-color 0.3s;
        display: inline-block;
    }

    button:hover {
        background-color: #004085;
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

    .back-btn:hover {
        background-color: #2980b9;
        box-shadow: 0 4px 8px rgba(41, 128, 185, 0.2);
        transform: translateY(-2px);
    }
    .remove-btn{
    padding: 10px 20px;
    color: #ffffff;
    background-color: #1c3b66;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    font-size: 12px;
    transition: all 0.3s ease;
    float: left;
    margin-top: -30px;
    position: relative;
    right: -750px;
    }

    .assign-btn{
    padding: 10px 20px;
    color: #ffffff;
    background-color: #1c3b66;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    font-size: 12px;
    transition: all 0.3s ease;
    float: left;
    margin-top: 10px;
    position: relative;
    right: -780px;
    }

    /* Expiry Date Styling */
    .expiry-date {
        font-weight: bold;
    }

    .expiry-date.valid {
        color: #28a745;
    }

    .expiry-date.expired {
        color: #dc3545;
    }
    .scrollable-table {
max-height: 400px; /* Set the height of the scrollable area */
overflow-y: auto; /* Enable vertical scrolling */
border: 1px solid #ddd;
margin-top: 10px;
}

.scrollable-table table {
width: 100%;
border-collapse: collapse;
}

.scrollable-table thead th {
position: sticky; /* Make the <thead> sticky */
top: 0; /* Stick the header to the top of the scrollable area */
background-color: #345d9d; /* Background color for the sticky header */
color: #fff; /* Header text color */
z-index: 2; /* Ensure it stays above other content */

}

.scrollable-table th, .scrollable-table td {
padding: 10px;
border-bottom: 1px solid #ddd;
}

.scrollable-table tr:nth-child(even) {
background-color: #deeafa;
}

.scrollable-table tr:hover {
background-color: #8fa3bf; /* Highlight color on hover */
}
</style>
</head>
<body>
<div class="container">
    <h1>Edit Assigned Items</h1>
    <a href="approval.php" class="back-btn">Back</a>
  
    <div class="tecrf-details-container">
        <h3>TECRF Details:</h3>
        <div class="tecrf-details-grid">
            <div class="tecrf-detail">
                <strong>Reference Number:</strong> 
                <span><?php echo htmlspecialchars($tecrf_data['reference_number'] ?? 'N/A'); ?></span>
            </div>
            <div class="tecrf-detail">
                <strong>Project:</strong>
                <span><?php echo htmlspecialchars($tecrf_data['project'] ?? 'N/A'); ?></span>
            </div>
            <div class="tecrf-detail">
                <strong>Client:</strong>
                <span><?php echo htmlspecialchars($tecrf_data['client'] ?? 'N/A'); ?></span>
            </div>
            <div class="tecrf-detail">
                <strong>Location:</strong>
                <span><?php echo htmlspecialchars($tecrf_data['location'] ?? 'N/A'); ?></span>
            </div>
        </div>
    </div>
    
    <div class="assign-details-container">
        <div class="items-table">
            <h3>Currently Assigned Items:</h3>
            <form method="post">
                <div class="scrollable-table">
                    <table>
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Description</th>
                                <th>Part ID</th>
                                <th>Expiry Date</th>
                                <th>Current Stock</th>
                                <th>Remove</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; foreach ($assigned_items as $item): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                <td><?php echo htmlspecialchars($item['part_id']); ?></td>
                                <td>
                                    <?php
                                    $expire_date = $item['expire_date'];
                                    if (!empty($expire_date)) {
                                        $expiry_date = DateTime::createFromFormat('Y-m-d', $expire_date);
                                        if ($expiry_date) {
                                            echo htmlspecialchars($expiry_date->format('d-m-Y'));
                                        } else {
                                            echo htmlspecialchars($expire_date);
                                        }
                                    } else {
                                        echo 'No Expiry Date';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['current_stock']); ?></td>
                                <td>
                                    <input type="checkbox" name="remove_part_id[]" value="<?php echo htmlspecialchars($item['part_id']); ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="remove-btn">Remove Selected Items</button>
            </form>
        </div>
    </div>
    
    <div class="available-details-container">
        <!-- Color Label Section -->
        <div style="margin-bottom: 10px; font-size: 16px;">
            <strong>Color Label for the Expiry Date</strong><br>
            <span style="color: red;">&#9679;</span> Expired <br>
            <span style="color: orange;">&#9679;</span> Expiring in the next 3 months <br>
            <span style="color: green;">&#9679;</span> More than 3 months remaining
        </div>

        <h3>Items Currently Available:</h3>
        <?php if (!empty($product_data)): ?>
            <form id="assignForm" action="update_location.php" method="post">
                <div class="scrollable-table">
                    <table style="width: 100%; text-align: center;">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Description</th>
                                <th>Part ID</th>
                                <th>Expiry Date</th>
                                <th>Current Stock</th>
                                <th>Assign</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; foreach ($product_data as $product): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($product['description']); ?></td>
                                <td><?php echo htmlspecialchars($product['part_id']); ?></td>
                                <td>
                                    <?php
                                    $expire_date = $product['expire_date'];
                                    $current_date = new DateTime();
                                    
                                    if (!empty($expire_date)) {
                                        $expiry_date = false;
                                        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y'];
                                        
                                        foreach ($formats as $format) {
                                            $expiry_date = DateTime::createFromFormat($format, $expire_date);
                                            if ($expiry_date && $expiry_date->format($format) === $expire_date) {
                                                break;
                                            }
                                            $expiry_date = false;
                                        }
                                        
                                        if ($expiry_date) {
                                            $three_months_later = (new DateTime())->add(new DateInterval('P3M'));
                                            
                                            $color = 'black';
                                            if ($expiry_date < $current_date) {
                                                $color = 'red';
                                            } elseif ($expiry_date >= $current_date && $expiry_date <= $three_months_later) {
                                                $color = 'orange';
                                            } else {
                                                $color = 'green';
                                            }
                                            
                                            echo '<span style="color: ' . $color . ';">';
                                            echo htmlspecialchars($expiry_date->format('d-m-Y'));
                                            echo '</span>';
                                        } else {
                                            echo '<span style="color: red;">Invalid Date</span>';
                                        }
                                    } else {
                                        echo '<span style="color: red;">No Expiry Date</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['current_stock']); ?></td>
                                <td>
                                    <input type="checkbox" name="part_id[]" value="<?php echo htmlspecialchars($product['part_id']); ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <input type="hidden" name="location" value="<?php echo htmlspecialchars($tecrf_data['location']); ?>">
                <input type="hidden" name="reference_number" value="<?php echo htmlspecialchars($reference_number); ?>">
                <button type="submit" class="assign-btn">Assign New Items</button>
            </form>
        <?php else: ?>
            <p>No matching products found.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>