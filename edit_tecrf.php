<?php
include 'session.php';
include 'database.php';

// Check if reference_number is passed via GET
if (isset($_GET['reference_number'])) {
    $reference_number = $_GET['reference_number'];
} else {
    echo "Error: Reference number not provided!";
    exit; // Stop script execution if no reference_number is provided
}

// SQL Query: Joining tecrf1 and tecrf2 based on reference_number
$sql = "
    SELECT 
        t1.reference_number, 
        t1.date_request, 
        t1.client, 
        t1.project, 
        t2.description, 
        t2.request_quantity, 
        t2.uom,
        t2.location
    FROM tecrf1 t1
    JOIN tecrf2 t2 ON t1.reference_number = t2.reference_number
    WHERE t1.reference_number = ?";

// Prepare the statement
$stmt = $conn->prepare($sql);

// Check if the prepare() was successful
if ($stmt === false) {
    echo "Error: Could not prepare the SQL statement. " . $conn->error; // Display the actual SQL error
    exit; // Stop script execution if there's an issue with the SQL
}

// Bind the reference number parameter and execute the query
$stmt->bind_param("s", $reference_number);
$stmt->execute();
$result = $stmt->get_result();

// Handle deletion of rows if 'delete' is clicked
if (isset($_GET['delete']) && isset($_GET['description'])) {
    $description = $_GET['description'];

    // SQL query to delete the row from tecrf2 based on reference_number and description
    $delete_sql = "DELETE FROM tecrf2 WHERE reference_number = ? AND description = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    
    if ($delete_stmt === false) {
        echo "Error: " . $conn->error;
        exit;
    }

    $delete_stmt->bind_param("ss", $reference_number, $description);
    $delete_stmt->execute();

    // Redirect back to the edit page after deletion
    header('Location: edit_tecrf.php?reference_number=' . urlencode($reference_number));
    exit;
}



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" type="image/x-icon" href="its2.png">
    <title>Request Details</title>
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
            font-size: 30px;
            text-align: center;
            color: #345d9d;
            margin-bottom: 20px;
            border-bottom: 3px solid #345d9d;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 12px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            font-size: 10px;
            color: #555;
        }

        th {
            background-color: #cbcdd1;
        }

        .counter {
    display: flex;
    justify-content: center;
    align-items: center;
    border: 1px solid #ddd;
    border-radius: 6px;
    overflow: hidden;
    width: 100px;
}

.counter button {
    background-color: #dedcdc;
    border: none;
    padding: 10px 15px;
    font-size: 18px;
    cursor: pointer;
    color: #333;
    transition: all 0.3s ease;
    width: 10px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.counter button:hover {
    background-color: #e0e0e0;
}

.counter input {
    text-align: center;
    border: none;
    font-size: 14px;
    width: 45px;
    color: #333;
    outline: none;
    background-color: #fff;
}

.counter button:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}

        .btn-container {
            text-align: right;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            color: #ffffff;
            background-color: #1c3b66;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #2e62b0;
        }
        .back-btn{
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
        .popup {
            display: none; /* Keep it hidden by default */
            position: fixed;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .popup-content {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .popup button {
            background-color: #1c3b66;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
            margin: 10px;
        }

        .popup button:hover {
            background-color: #2e62b0;
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
            }

    #sidebar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
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
        border-radius: 15px;  /* Rounded corners */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    #sidebar ul li {
        padding: 15px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        transition: background-color 0.3s ease;
        border-radius: 15px;  /* Rounded corners */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
        border-radius: 15px;  /* Rounded corners */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
        border-radius: 35px;  /* Rounded corners */
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
    #main-content {
        margin-left: 250px; /* Align the main content next to the sidebar */
        padding: 20px;
        transition: margin-left 0.3s ease;
        border-radius: 15px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    </style>
    <script>
    function incrementQuantity(inputId) {
        const input = document.getElementById(inputId);
        input.value = parseInt(input.value) + 1;
    }

    function decrementQuantity(inputId) {
        const input = document.getElementById(inputId);
        if (parseInt(input.value) > 1) { // Prevent going below 1
            input.value = parseInt(input.value) - 1;
        }
    }
    // Confirm update popup
    function confirmUpdate(event) {
        event.preventDefault(); // Prevent form submission
        const popup = document.getElementById('update-popup');
        popup.style.display = 'flex';

        // Ensure form submission after confirmation
        const yesButton = popup.querySelector('#update-confirm-btn');
        yesButton.onclick = function () {
            document.forms[0].submit(); // Submit form
        };
    }

    function confirmDelete(event, deleteUrl) {
        event.preventDefault(); // Prevent default navigation
        const popup = document.getElementById('delete-popup');
        popup.style.display = 'flex';

        // Set the action for the 'Yes' button dynamically
        const yesButton = popup.querySelector('#delete-confirm-btn');
        yesButton.onclick = function () {
            window.location.href = deleteUrl; // Redirect to the delete URL
        };
    }

    // Close the popups
    function closePopup(popupId) {
        document.getElementById(popupId).style.display = 'none';
    }
        // Success message for update
        function showSuccessPopup() {
            const popup = document.getElementById('success-popup');
            popup.style.display = 'flex';
        }
    </script>
</head>
<body>
<div id="sidebar">
        <div id="sidebar-header">
        <h2><i class="fa fa-bars"></i> Menu</h2>
           
        </div>
        <ul>
            <li><a href="tecrf.php"><i class="fa-solid fa-file-signature"></i>create TECRF</a></li>
            <li><a href="list_tecrf.php"><i class="fas fa-box"></i>My Requests</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    <div class="container">
        <h1>Request Details for Reference Number: <?php echo htmlspecialchars($reference_number); ?></h1>
        <a href="list_tecrf.php" class="back-btn">Back</a>
        <form action="update_request.php" method="POST" onsubmit="confirmUpdate(event)">
        <form action="update_request.php" method="POST" onsubmit="confirmDelete(event)">
            <input type="hidden" name="reference_number" value="<?php echo htmlspecialchars($reference_number); ?>">
            <table>
                <thead>
                    <tr>
                        <th>Reference Number</th>
                        <th>Date Request</th>
                        <th>Client</th>
                        <th>Project</th>
                        <th>Description</th>
                        <th>Request Quantity</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0) : ?>
                        <?php $rowIndex = 0; ?>
                        <?php while ($row = $result->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['reference_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['date_request']); ?></td>
                                <td><?php echo htmlspecialchars($row['client']); ?></td>
                                <td><?php echo htmlspecialchars($row['project']); ?></td>
                                <td>
                                    <input type="hidden" name="description[]" value="<?php echo htmlspecialchars($row['description']); ?>">
                                    <?php echo htmlspecialchars($row['description']); ?>
                                </td>
                                <td>
                                    <div class="counter">
                                        <button type="button" onclick="decrementQuantity('quantity-<?php echo $rowIndex; ?>')">-</button>
                                        <input type="number" name="request_quantity[]" id="quantity-<?php echo $rowIndex; ?>" value="<?php echo htmlspecialchars($row['request_quantity']); ?>" readonly>
                                        <button type="button" onclick="incrementQuantity('quantity-<?php echo $rowIndex; ?>')">+</button>
                                    </div>
                                </td>
                                <td>
                                    <a href="#" onclick="confirmDelete(event, 'edit_tecrf.php?reference_number=<?php echo urlencode($row['reference_number']); ?>&description=<?php echo urlencode($row['description']); ?>&delete=true')">
                                        <i class="fa fa-trash" style="font-size: 20px; cursor: pointer; color: #c22525;" title="Delete this item"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php $rowIndex++; ?>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7">No data found for the given reference number.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="btn-container">
                <button type="submit" class="btn">Update</button>
            </div>
        </form>
    </div>

    <!-- Update confirmation popup -->
    <div id="update-popup" class="popup">
        <div class="popup-content">
            <p>Are you sure you want to update this request?</p>
            <button onclick="document.forms[0].submit()">Yes</button>
            <button onclick="closePopup('update-popup')">No</button>
        </div>
    </div>

    <!-- Delete confirmation popup -->
    <div id="delete-popup" class="popup">
    <div class="popup-content">
        <p>Are you sure you want to delete this item request?</p>
        <button id="delete-confirm-btn">Yes</button>
        <button onclick="closePopup('delete-popup')">No</button>
    </div>
</div>
    <!-- Success message popup -->
    <div id="success-popup" class="popup" style="display: none;">
        <div class="popup-content">
            <p>You have successfully updated your request!</p>
            <button onclick="closePopup('success-popup')">Close</button>
        </div>
    </div>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>


