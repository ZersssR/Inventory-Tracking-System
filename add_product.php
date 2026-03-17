<?php
include 'session.php';

// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "inventory_tracking";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch unique values from the database for dropdowns
function fetchOptions($conn, $column) {
    $sql = "SELECT DISTINCT $column FROM product WHERE $column IS NOT NULL AND $column != ''";
    $result = $conn->query($sql);
    $options = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $options[] = $row[$column];
        }
    }
    return $options;
}

$certificate_validity_options = fetchOptions($conn, 'certificate_validity');
$category_options = fetchOptions($conn, 'category');
$type_options = fetchOptions($conn, 'type');
$tec_group_options = fetchOptions($conn, 'tec_group');
$description_options = fetchOptions($conn, 'description');

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$error = '';

// Handle form submission based on the page number
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    switch ($page) {
        case 1:
            $unit_id = $_POST['unit_id'];

            // Check if unit_id already exists in the product table
            $check_sql = "SELECT COUNT(*) AS count FROM product WHERE unit_id = ?";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->bind_param("s", $unit_id);
            $stmt_check->execute();
            $stmt_check->bind_result($count);
            $stmt_check->fetch();
            $stmt_check->close();

            if ($count > 0) {
                // Duplicate found, set error message and do not proceed to the next page
                $error = "The Unit ID already exists. Please enter a unique Unit ID.";
                break;
            }

            // Store data in session
            $_SESSION['tec_id'] = $_POST['tec_id'];
            $_SESSION['tec_expiry'] = $_POST['tec_expiry'];
            $_SESSION['unit_id'] = $unit_id;
            break;

            case 2:
                $description = $_POST['description'];
                $custom_description = isset($_POST['custom_description']) ? trim($_POST['custom_description']) : '';
                
                // If "others" is selected, save the custom description as the description
                if ($description === 'others') {
                    if (!empty($custom_description)) {
                        $_SESSION['description'] = 'others'; // Save 'others' as the selected value
                        $_SESSION['custom_description'] = $custom_description; // Save the custom description
                    } else {
                        $error = "Please provide a custom description.";
                        break;
                    }
                } else {
                    // Save the selected description in the session
                    $_SESSION['description'] = $description;
                    unset($_SESSION['custom_description']); // Clear custom description if not 'others'
                }
                $_SESSION['size'] = $_POST['size'];
                $_SESSION['swl'] = $_POST['swl'];
                break;

                case 3:
                    $_SESSION['category'] = $_POST['category'];
                    $_SESSION['type'] = $_POST['type'];
                    $_SESSION['tec_group'] = $_POST['tec_group'];
                
                    // Check if custom_description is set and assign it to description
                    $description_to_save = ($_SESSION['description'] === 'others' && isset($_SESSION['custom_description'])) 
                                           ? $_SESSION['custom_description'] 
                                           : $_SESSION['description'];
                
                    // Insert data into database
                    $insert_sql = "INSERT INTO product (
                        tec_id, tec_expiry, unit_id,
                        description, size, swl,
                        category, type, tec_group
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                    $stmt = $conn->prepare($insert_sql);
                    if ($stmt === false) {
                        die('Prepare failed: ' . htmlspecialchars($conn->error));
                    }
                
                    $stmt->bind_param(
                        "sssssssss",
                        $_SESSION['tec_id'], $_SESSION['tec_expiry'], $_SESSION['unit_id'],
                        $description_to_save, $_SESSION['size'], $_SESSION['swl'],
                        $_SESSION['category'], $_SESSION['type'], $_SESSION['tec_group']
                    );
                
                    $stmt->execute();
                    if ($stmt->errno) {
                        die('Execute failed: ' . htmlspecialchars($stmt->error));
                    }
                
                    $stmt->close();
                
                    // Clear session variables after successful submission
                    unset($_SESSION['tec_id'], $_SESSION['tec_expiry'], $_SESSION['unit_id']);
                    unset($_SESSION['description'], $_SESSION['size'], $_SESSION['swl']);
                    unset($_SESSION['category'], $_SESSION['type'], $_SESSION['tec_group']);
                    unset($_SESSION['custom_description']); // Clear custom description after submission
                
                    // Trigger success message and redirect to list_items.php
                    echo "<script>alert('Item added successfully!'); window.location.href = 'list_iitems.php';</script>";
                    exit();
                
    }

    // If no error, proceed to the next page
    if (empty($error)) {
        $page++;
        header("Location: add_product.php?page=$page");
        exit();
    }
}
?>

<!DOCTYPE html> 
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" type="x-icon" href="its2.png">
<title>Add New Item</title>
<h1>Add New Item</h1>
<a href="list_iitems.php" class="back-btn">Back</a> 
<style>
       body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to right, #e0eafc, #cfdef3);
            color: #333;
            padding: 20px;
            position: relative;
        }

        h1 {
            font-size: 28px;
            color: #2a5298;
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2a5298;
            padding-bottom: 10px;
            font-weight: 700;
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
        .content {
            display: flex;
            justify-content: center;
            background-color: #84aae0;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Add a shadow for depth */
            padding: 20px;
            gap: 20px; /* Add space between the sidebar and form */
            width: 80%; /* Adjust width as needed */
            height: 48%;
            max-width: 3000px; /* Limit the maximum width of the content */
            margin-top: 45px;
            }
            .sidebar {
            width: 250px;
            padding: 20px;
            background-color: #d3dbe6;
            border-right: 1px solid #486999;
            }

            .sidebar a {
            display: block;
            padding: 12px;
            margin-bottom: 10px;
            text-decoration: none;
            background-color: #e9ecef;
            color: #007bff;
            border-radius: 4px;
            transition: background-color 0.3s;
            }

            .sidebar a.active {
                background-color: #486999;
                color: white;
            }

            .sidebar a:hover {
                background-color: #0056b3;
                color: white;
            }

            form {
                flex-grow: 1;
                max-width: 800px;
                background-color: #d3dbe6;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }

            form input[type="text"], 
            form input[type="date"], 
            form select {
                padding: 12px;
                margin: 12px 0;
                width: 100%;
                border: 1px solid #ccc;
                border-radius: 4px;
                box-sizing: border-box;
            }

            form input[type="text"]:focus, 
            form input[type="date"]:focus, 
            form select:focus {
                border-color: #007bff;
                outline: none;
            }

            .button-container {
                display: flex;
                justify-content: flex-start;
                margin-top: 20px;
            }

            button {
                padding: 12px 20px;
                background-color: #1d3557;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                transition: background-color 0.3s;
            }

            button:hover {
                background-color: #1d3557;
            }
            .next-btn, .submit-btn{
                margin-left: auto;
        
            }
            .back-button{
                padding: 12px 20px;
                background-color: #1d3557;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                transition: background-color 0.3s;
                text-decoration: none; /* Removes underline */
            }
</style>
</head>
<body>
    <div class="content">
        <div class="sidebar">
            <a href="add_product.php?page=1" class="<?php echo $page == 1 ? 'active' : ''; ?>">TEC Information</a>
            <a href="add_product.php?page=2" class="<?php echo $page == 2 ? 'active' : ''; ?>">Item Details</a>
            <a href="add_product.php?page=3" class="<?php echo $page == 3 ? 'active' : ''; ?>">Certification Info</a>
        </div>
        <form method="post" action="">
            <!-- Display error message if unit_id already exists -->
            <?php if (!empty($error)): ?>
                <div style="color: red; font-weight: bold; margin-bottom: 20px;">
                    <?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Form fields for different pages -->
            <?php if ($page == 1): ?>
            <label for="tec_id">TEC ID:</label>
            <input type="text" name="tec_id" value="<?php echo $_SESSION['tec_id'] ?? ''; ?>" required><br>
            
            <label for="tec_expiry">TEC Expiry:</label>
            <input type="date" name="tec_expiry" value="<?php echo $_SESSION['tec_expiry'] ?? ''; ?>" required><br>
            
            <label for="unit_id">Unit ID:</label>
            <input type="text" name="unit_id" value="<?php echo $_SESSION['unit_id'] ?? ''; ?>" required><br>

        <?php elseif ($page == 2): ?>
            <label for="description">Description:</label>
<select name="description" id="description" onchange="toggleCustomDescription(this)" required>
    <option value="" disabled <?php echo empty($_SESSION['description']) ? 'selected' : ''; ?>>Select Description</option>
    <?php foreach ($description_options as $option): ?>
        <option value="<?php echo htmlspecialchars($option); ?>" 
                <?php echo (isset($_SESSION['description']) && $_SESSION['description'] == $option) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($option); ?>
        </option>
    <?php endforeach; ?>
    <option value="others" <?php echo (isset($_SESSION['description']) && $_SESSION['description'] == 'others') ? 'selected' : ''; ?>>Others</option>
</select><br>

<div id="customDescription" style="display: <?php echo (isset($_SESSION['description']) && $_SESSION['description'] == 'others') ? 'block' : 'none'; ?>;">
<label for="custom_description">Custom Description:</label>
<input type="text" name="custom_description" value="<?php echo htmlspecialchars($_SESSION['custom_description'] ?? ''); ?>" id="custom_description">

</div>

<label for="size">Size:</label>
<input type="text" name="size" value="<?php echo $_SESSION['size'] ?? ''; ?>" required><br>

<label for="swl">SWL:</label>
<input type="text" name="swl" value="<?php echo $_SESSION['swl'] ?? ''; ?>" required><br>

        <?php elseif ($page == 3): ?>
            <label for="category">Category:</label>
            <select name="category" required>
                <option value="" disabled selected>Select Category</option>
                <?php foreach ($category_options as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                <?php endforeach; ?>
            </select><br>

            <label for="type">Type:</label>
            <select name="type" required>
                <option value="" disabled selected>Select Type</option>
                <?php foreach ($type_options as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                <?php endforeach; ?>
            </select><br>

            <label for="tec_group">TEC Group:</label>
            <select name="tec_group" required>
                <option value="" disabled selected>Select TEC Group</option>
                <?php foreach ($tec_group_options as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                <?php endforeach; ?>
            </select><br>
            <?php endif; ?>
      

            <!-- Navigation buttons -->
            <div class="button-container">
                <?php if ($page > 1): ?>
                    <a href="add_product.php?page=<?= $page - 1; ?>" class="back-button">Back</a>
                <?php endif; ?>

                <?php if ($page < 3): ?>
                    <button type="submit" class="next-btn">Next</button>
                <?php else: ?>
                    <button type="submit" class="submit-btn">Submit</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

        </form>
    </div>
    <script>
       function toggleCustomDescription(selectElement) {
    var customDescriptionDiv = document.getElementById('customDescription');
    if (selectElement.value === 'others') {
        customDescriptionDiv.style.display = 'block';  // Show the custom description field
    } else {
        customDescriptionDiv.style.display = 'none';   // Hide the custom description field
    }
}

    </script>
</body>
</html>