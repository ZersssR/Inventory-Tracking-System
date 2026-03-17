<?php
include('database.php');

// Get the reference number from the URL
$reference_number = $_GET['reference_number'] ?? null;

if (!$reference_number) {
    echo "<p>Reference number is missing.</p>";
    exit;
}

// Fetch all assigned items for this reference number where loadout_date is NULL
$sql = "SELECT description, unit_id, loadout_location FROM product WHERE action_notice_no = ? AND loadout_date IS NULL";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $reference_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assigned_items = [];
    while ($row = $result->fetch_assoc()) {
        $assigned_items[] = $row;
    }
} else {
    echo "Error preparing statement: " . $conn->error;
    exit;
}

$update_success = false;
$date = '';

// Handle "Issue Out" button submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['loadout_date'];
    $selected_items = $_POST['selected_items'] ?? [];

    if (DateTime::createFromFormat('Y-m-d', $date) && !empty($selected_items)) {
        $all_updates_successful = true;

        foreach ($selected_items as $unit_id) {
            $update_sql = "UPDATE product SET loadout_date = ? WHERE unit_id = ? AND action_notice_no = ?";
            $update_stmt = $conn->prepare($update_sql);

            if ($update_stmt) {
                $update_stmt->bind_param("sss", $date, $unit_id, $reference_number);
                if (!$update_stmt->execute()) {
                    $all_updates_successful = false;
                }

                $fetch_sql = "SELECT description, loadout_location FROM product WHERE unit_id = ?";
                $fetch_stmt = $conn->prepare($fetch_sql);
                $fetch_stmt->bind_param("s", $unit_id);
                $fetch_stmt->execute();
                $fetch_result = $fetch_stmt->get_result();
                $item = $fetch_result->fetch_assoc();

                if ($item) {
                    // Insert action_notice_no in loadout_history
                    $insert_sql = "INSERT INTO loadout_history (unit_id, description, loadout_location, loadout_date, action_notice_no) VALUES (?, ?, ?, NOW(), ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    if ($insert_stmt) {
                        $insert_stmt->bind_param("ssss", $unit_id, $item['description'], $item['loadout_location'], $reference_number);
                        $insert_stmt->execute();
                    }
                }
            }
        }

        if ($all_updates_successful) {
            $update_success = true;
        }
    } else {
        echo "<p>Invalid date format or no items selected. Please select a valid date and items to issue out.</p>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Assigned Items for <?php echo htmlspecialchars($reference_number); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="styleLoadout.css">
    <style>
        /* Reset and Body Styling */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Arial', sans-serif;
}
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    font-size: 12px;
    background: linear-gradient(to right, #e0eafc, #cfdef3);
    color: #333;
    margin: 0;
    padding: 0;
}

.container {
    width: 80%;
    max-width: 1100px;
    padding: 30px; /* Reduced padding */
    margin: 0 auto; /* Centering */
    margin-left: 300px; /* Adjust according to sidebar width (sidebar is 250px, add some space) */
    max-height: max-content;
    background-color: rgba(255, 255, 255, 0.9);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); 
}
h1 {
    font-size: 28px;
    color: #333;
    text-align: center;
    margin-bottom: 20px;
    border-bottom: 2px solid #2a5298; /* This creates the line below the heading */
    text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.1);
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
    font-size: 10px;
    
}
table, th, td {
    border: 1px solid #ddd;
    text-align: center; /* Center align the text */
    padding: 15px;


}
th, td {
     text-align: center; /* Ensure center alignment */
     vertical-align: middle; /* Center vertically as well */
     font-size: 10px;

}
th {
    background: #345d9d;
    color: #ffffff;
    font-size: 10px;
    letter-spacing: 0.6px;
    text-transform: uppercase;
}
tr:nth-child(even) {
    background-color: #f8f9fc;
}
tr:hover {
    background-color: #bfcfdb;
    cursor: pointer;
}
.issue-out-btn {
    display: inline-block;
    padding: 12px 24px;
    font-size: 12px;
    background-color: #4078a3;
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    text-align: center;
    transition: background-color 0.3s ease;
    border: none;
    cursor: pointer;
    margin-top: 20px;
}
.issue-out-btn:hover {
    background-color: #218838;
}
.issue-out-btn:focus {
    outline: none;
}

modal-content {
    background-color: #fefefe;
    margin: 15% auto; /* 15% from the op and centered */
    padding: 20px;
    border: 1px solid #888;
    width: 40%; 
    border-radius: 8px;
    text-align: center;
}
.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
}
.close:hover,
.close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}
@media screen and (max-width: 768px) {
    .container {
        padding: 20px;
    }
    table, th, td {
        font-size: 14px;
    }
    .issue-out-btn {
        font-size: 16px;
    }
}

label {
    font-weight: 500;
    margin-top: 20px;
    display: block;
}
input[type="date"] {
    width: calc(100% - 24px);
    padding: 12px;
    margin-top: 8px;
    border: 1px solid #ced4da;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s ease;
}
input[type="date"]:focus {
    border-color: #007bff;
    outline: none;
    box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
}
input[type="checkbox"] {
    transform: scale(1.0); /* Adjust the scale value to make it bigger */
    width: 20px;  /* Optional: explicitly set width */
    height: 20px; /* Optional: explicitly set height */
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
    margin-bottom: 60px;
}
.back-btn:hover {
    background-color: #2980b9;
    box-shadow: 0 4px 8px rgba(41, 128, 185, 0.2);
    transform: translateY(-2px);
}
.back-btn:focus {
    outline: none;
}
        .modal {
            display: <?php echo $update_success ? 'block' : 'none'; ?>;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4); 
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            border-radius: 8px;
            text-align: center;
        }
        /* Static Sidebar Styles */
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
font-size: 1.2em;
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
font-size: 1em;
font-weight: 500;
transition: color 0.3s ease;
}

#sidebar ul li a:hover {
color: #a8dadc;
}

#sidebar ul li i {
font-size: 20px;
margin-right: 10px;
color: #a8dadc;
transition: color 0.3s ease;
}

#sidebar ul li:hover i {
color: white;
}

/* Adjust the main content margin to accommodate the static sidebar */
#main-content {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    min-height: 100vh; /* Ensures full height */
}

    </style>
</head>
<body>
<div id="sidebar">
        <div id="sidebar-header">
            <h2><i class="fa fa-bars"></i> Menu</h2>
        </div>
        <ul>
            <li><a href="adminStaff.php"><i class="fa fa-list"></i> Dashboard</a></li>
            <li><a href="list_iitems.php"><i class="fa fa-list"></i> List Items</a></li>
            <li><a href="approval.php"><i class="fa fa-tasks"></i> Request List</a></li>
            <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
<div class="container">

    <h1>Assigned Items for Reference: <?php echo htmlspecialchars($reference_number); ?></h1>
   
    <a href="view_all_assigned_items.php" class="back-btn">Back</a>
    <?php if (!empty($assigned_items)): ?>
        <form method="POST">
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Unit ID</th>
                        <th>Loadout Location</th>
                        <th>Select</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assigned_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                            <td><?php echo htmlspecialchars($item['unit_id']); ?></td>
                            <td><?php echo htmlspecialchars($item['loadout_location']); ?></td>
                            <td><input type="checkbox" name="selected_items[]" value="<?php echo htmlspecialchars($item['unit_id']); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <label for="loadout_date">Select Loadout Date:</label>
            <input type="date" id="loadout_date" name="loadout_date" required style="font-size: 10px;">
            <button type="submit" class="issue-out-btn">Issue Out</button>
        </form>
    <?php else: ?>
        <p>All items were load out.</p>
    <?php endif; ?>
</div>

<div class="modal" id="myModal">
    <div class="modal-content">
        <span class="close" id="closeModal">&times;</span>
        <h2>Success!</h2>
        <p>Loadout Date Issued: <?php echo htmlspecialchars($date); ?></p>
    </div>
</div>

<script>
    var modal = document.getElementById("myModal");
    var span = document.getElementById("closeModal");

    span.onclick = function() {
        modal.style.display = "none";
        window.location.href = "view_all_assigned_items.php?reference_number=<?php echo urlencode($reference_number); ?>";
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
            window.location.href = "view_assigned_items.php?reference_number=<?php echo urlencode($reference_number); ?>";
        }
    }
</script>

</body>
</html>
