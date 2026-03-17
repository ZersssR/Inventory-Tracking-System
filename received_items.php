<?php
// Connect to database
include('database.php');

// Get the backload_date from the URL
$backload_date = $_GET['date'];

// Fetch the items received on the selected backload_date
$query = "SELECT description, unit_id FROM product WHERE backload_date = '$backload_date'";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Received Items</title>
    <style>
        /* Add some basic styling */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .item-list {
            list-style-type: none;
            padding: 0;
        }
        .item-list li {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .item-list li:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Items Received on <?php echo $backload_date; ?></h2>
        <ul class="item-list">
            <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                <li>
                    <?php echo $row['description']; ?> (Unit ID: <?php echo $row['unit_id']; ?>)
                </li>
            <?php } ?>
        </ul>
    </div>
</body>
</html>
