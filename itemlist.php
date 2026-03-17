<?php
include 'session.php';
include 'database.php';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the user has clicked on a description
if (isset($_GET['description']) && isset($_GET['tec_group'])) {
    $selected_description = $_GET['description'];
    $selected_tec_group = $_GET['tec_group'];

    // Fetch all items with the same description and tec_group
    $item_sql = "SELECT * FROM product WHERE description = ? AND tec_group = ? ORDER BY unit_id";
    $stmt = $conn->prepare($item_sql);
    $stmt->bind_param("ss", $selected_description, $selected_tec_group);
    $stmt->execute();
    $items_result = $stmt->get_result();
    
    $items = [];
    while ($row = $items_result->fetch_assoc()) {
        $items[] = $row;
    }

    $stmt->close();
} else {
    die("Description or TEC group not provided.");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Item List for <?= htmlspecialchars($selected_description); ?></title>
    <style>
       body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to right, #e0eafc, #cfdef3);
            color: #333;
            padding: 20px;
            position: relative; /* For absolute positioning of back button */
            font-size: 0.9em; /* Smaller font size */
        }
        h1, h2 {
            color: #4A90E2; /* First tone */
            text-align: center;
            font-weight: bold;
        }
        h1 {
            font-size: 30px;
            text-align: center;
            color: #345d9d;
            margin-bottom: 20px;
            border-bottom: 3px solid #345d9d;
            padding-bottom: 10px;
        }
        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            background: #fff;
        }
        th, td {
            border: 1px solid #1f1f1f;
            padding: 15px;
            text-align: left;
            font-size: 14px;
            color: #555;
        }
        th {
            background-color: #345d9d;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 500;
        }
        td {
            background-color: #fafafa;
        }
        tr:hover td {
            background-color: #f0f8ff;
        }
        .back-button {
            display: inline-block;
            padding: 12px 24px;
            font-size: 18px;
            background-color: #000; /* Bootstrap Primary Color */
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            text-align: center;
            transition: background-color 0.3s ease;
            margin-bottom: 20px; /* Spacing below the button */
        }
        .btn {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
            display: inline-block;
        }
        .btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>Items for <?= htmlspecialchars($selected_description); ?> (TEC Group: <?= htmlspecialchars($selected_tec_group); ?>)</h1>
            <a href="masterlist.php" class="back-button">Back</a>
            <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Unit ID</th>
                    <th>Description</th>
                    <th>Loadout Date</th>
                    <th>Loadout Location</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td><?= $index + 1; ?></td>
                        <td><?= htmlspecialchars($item['unit_id']); ?></td>
                        <td><?= htmlspecialchars($item['description']); ?></td>
                        <td><?= htmlspecialchars($item['loadout_date']); ?></td>
                        <td><?= htmlspecialchars($item['loadout_location']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>


    </div>

</body>
</html>

<?php
$conn->close();
?>
