<?php

include 'session.php';
include 'database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['description']) && isset($_POST['request_quantity']) && isset($_POST['uom']) && isset($_POST[''])) {

        // Clean and validate inputs
        $description = $_POST['description'];
        $req_qty = $_POST['request_quantity'];
        $uom = $_POST['uom'];
        
        // Check that all arrays have values (in case the form was submitted with empty fields)
        if (!empty($description) && !empty($req_qty) && !empty($uom)) {

            $date = date('Y-m-d');
            $reference_number = $_POST['reference_number'];
            $date_request = $_POST['date_request'];
            $date_required = $_POST['date_required'];
            $client = $_POST['client'];
            $project = $_POST['project'];
            $charge_code = $_POST['charge_code'];
            $location = $_POST['location'];
            $user_id = 1; // For demo purposes. Set this based on session

            // Begin a transaction
            $conn->begin_transaction();

            try {
                // Insert into tecrf1
                $stmt_user = $conn->prepare("INSERT INTO tecrf1 (date, reference_number, date_request, date_required, client, project, charge_code, location, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_user->bind_param("ssssssssi", $date, $reference_number, $date_request, $date_required, $client, $project, $charge_code, $location, $user_id);

                if (!$stmt_user->execute()) {
                    throw new Exception("Error inserting into the 'tecrf1' table: " . $stmt_user->error);
                }

                // Prepare statement for tecrf2
                if ($stmt_business_code = $conn->prepare("INSERT INTO tecrf2 (description, request_quantity, uom, user_id, reference_number, location) VALUES (?, ?, ?, ?, ?, ?)")) {

                    // Loop through all items
                    for ($i = 0; $i < count($description); $i++) {
                        if (!empty($description[$i]) && !empty($req_qty[$i]) && !empty($uom[$i])) {
                            // Bind parameters and execute statement for each row
                            $stmt_business_code->bind_param("sississ", $description[$i], $req_qty[$i], $uom[$i], $user_id, $reference_number, $location);
                
                            // Check if the execution fails
                            if (!$stmt_business_code->execute()) {
                                throw new Exception("Error inserting into 'tecrf2': " . $stmt_business_code->error);
                            }
                        }
                    }
                
                    // Close statement after the loop
                    $stmt_business_code->close();
                
                } else {
                    throw new Exception("Failed to prepare the statement: " . $conn->error);
                }
                
                // Commit the transaction
                $conn->commit();

                // Close statements
                $stmt_business_code->close();
                $stmt_user->close();

                // Redirect after success
                header("Location: tecrf.php");
                exit();

            } catch (Exception $e) {
                $conn->rollback(); // Roll back the transaction in case of error
                echo "Transaction failed: " . $e->getMessage();
            }

        } else {
            echo "Error: Please fill out all required fields.";
        }
    } else {
        echo "Error: Form fields are missing.";
    }

    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TECRF Request Form</title>
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 20px;
        background: white;
    }
    .container {
        width: 210mm;
        margin: 0 auto;
        padding: 20px;
        border: 1px solid #000;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .header-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }
    .header-container img {
        height: 50px;
    }
    h1 {
        text-align: center;
        font-size: 18px;
        margin: 0;
        padding: 0;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    th, td {
        border: 1px solid #000;
        padding: 8px;
        text-align: left;
        font-size: 12px;
    }
    th {
        background-color: #f2f2f2;
    }
    .header td {
        padding: 8px;
    }
    .note {
        font-size: 12px;
        color: #000;
        margin-bottom: 20px;
    }
    .btn-container {
        text-align: right;
        margin-bottom: 10px;
    }
    .btn {
        background-color: #007bff;
        color: #fff;
        border: none;
        padding: 8px 12px;
        border-radius: 5px;
        cursor: pointer;
        margin: 5px;
        font-size: 14px;
    }
    .btn:hover {
        background-color: #0056b3;
    }
    .input-field {
        width: 100%;
        padding: 5px;
        box-sizing: border-box;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 12px;
    }
    .signature .signature-field {
        height: 60px;
    }
    @media print {
        .btn, .btn-container {
            display: none;
        }
    }
    
</style>
</head>
<body>

<?php
include 'session.php';
include 'database.php';
?>

<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
<div class="container">
    <div class="header-container">
        <img src="eog.jpg" alt="Logo">
        <h1>Tools, Equipment & Consumables Request Form (TECRF)</h1>
        <table>
            <tr>
                <td>Form:</td>
                <td>Tools, Equipment & Consumables Request (TECRF)</td>
            </tr>
            <tr>
                <td>Doc. No.:</td>
                <td>WI/EOG/LOG/10(FM/02)</td>
            </tr>
            <tr>
                <td>Revision:</td>
                <td><input type="text" name="revision" class="input-field" value="0"></td>
            </tr>
            <tr>
                <td>Date:</td>
                <td><label for="date" id="dateLabel">Current date</label></td>
            </tr>
            <tr>
                <td>No of Pages:</td>
                <td>1</td>
            </tr>
            <tr>
    <td>Reference Number:</td>
    <td><label id="referenceNumberLabel"></label></td>
    <input type="hidden" name="reference_number" id="reference_number">
</tr>

        </table>
    </div>

    <table class="details">
        <tr>
            <td>Date Request:</td>
            <td><input type="date" name="date_request" class="input-field"></td>
            <td>Client:</td>
            <td><input type="text" name="client" class="input-field"></td>
        </tr>
        <tr>
            <td>Date Required:</td>
            <td><input type="date" name="date_required" class="input-field"></td>
            <td>Project:</td>
            <td><input type="text" name="project" class="input-field"></td>
        </tr>
        <tr>
            <td>Charge Code:</td>
            <td><input type="text" name="charge_code" class="input-field"></td>
            <td>Location:</td>
            <td><input type="text" name="location" class="input-field"></td>
        </tr>
    </table>

    <table class="item-list">
    <thead>
        <tr>
            <th>NO</th>
            <th>Description</th>
            <th>Request Quantity</th>
            <th>UOM</th>
            <th>Remarks</th>
            <th>Date Return</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody id="item-body">
        <tr>
            <td><input type="number" name="item_no[]" class="input-field"></td>
            <td><select style="width:100%;" name="description[]">
                <option value="">Select Description</option>
                <?php
                // Fetch descriptions from the 'product' table and order them alphabetically
                $desc_sql = "SELECT DISTINCT description FROM product WHERE (description IS NOT NULL AND TRIM(REPLACE(REPLACE(description, '<', ''), '>', '')) != '') ORDER BY description ASC";
                $desc_result = $conn->query($desc_sql);

                if ($desc_result->num_rows > 0) {
                // Loop through results and create options
                while ($desc_row = $desc_result->fetch_assoc()) {
                    echo '<option value="' . htmlspecialchars($desc_row['description']) . '">' . htmlspecialchars($desc_row['description']) . '</option>';
                }
                } 
                else {
                    echo '<option value="">No products available</option>';
                }
                ?>
                </select></td>

            <td><input type="number" name="request_quantity[]" class="input-field"></td>
            <td><input type="text" name="uom[]" class="input-field"></td>
            <td><input type="text" name="remarks[]" class="input-field"></td>
            <td><input type="date" name="[]" class="input-field"></td>
            <td><button type="button" class="btn" onclick="removeRow(this)">Remove</button></td>
        </tr>
    </tbody>
</table>


    <div class="btn-container">
        <button type="button" class="btn" onclick="addRow()">Add Row</button>
        <button type="submit" class="btn">Save</button>
        <button type="button" class="btn" onclick="printForm()">Print</button>
    </div>
    </form>

    <p class="note">For project request, please submit 5 days before the required date.</p>

    <table class="approvals">
    <tr>
        <td>
            <strong>REQUEST BY</strong><br>(*FOREMAN & ABOVE)
        </td>
        <td>
            <strong >VERIFIED BY</strong><br>(*SUPERVISOR)
        </td>
        <td>
            <strong>APPROVED BY</strong><br>(*HOD LOGISTIC)
        </td>
    </tr>
    <tr>
        <td class="signature">
            <div>
                Signature: <input type="text" name="request_by_signature" class="input-field signature-field">
            </div>
        </td>
        <td class="signature">
            <div>
                Signature: <input type="text" name="verified_by_signature" class="input-field signature-field">
            </div>
        </td>
        <td class="signature">
            <div>
                Signature: <input type="text" name="approved_by_signature" class="input-field signature-field">
            </div>
        </td>
    </tr>
    <tr>
        <td>
            <div>
                Name: <input type="text" name="request_by_name" class="input-field">
            </div>
            <div>
                Designation: <input type="text" name="request_by_designation" class="input-field">
            </div>
            <div>
                Date: <input type="date" name="request_by_date" class="input-field">
            </div>
        </td>
        <td>
            <div>
                Name: <input type="text" name="verified_by_name" class="input-field">
            </div>
            <div>
                Designation: <input type="text" name="verified_by_designation" class="input-field">
            </div>
            <div>
                Date: <input type="date" name="verified_by_date" class="input-field">
            </div>
        </td>
        <td>
            <div>
                Name: <input type="text" name="approved_by_name" class="input-field">
            </div>
            <div>
                Designation: <input type="text" name="approved_by_designation" class="input-field">
            </div>
            <div>
                Date: <input type="date" name="approved_by_date" class="input-field">
            </div>
        </td>
    </tr>
</table>

<script>
var descriptionOptions = 
`<?php
$desc_sql = "SELECT DISTINCT description FROM product WHERE (description IS NOT NULL AND TRIM(REPLACE(REPLACE(description, '<', ''), '>', '')) != '') ORDER BY description ASC";
$desc_result = $conn->query($desc_sql);
    while ($desc_row = $desc_result->fetch_assoc()) {
        echo '<option value="' . htmlspecialchars($desc_row['description']) . '">' . htmlspecialchars($desc_row['description']) . '</option>';
    }
?>`;

function addRow() {
    var tableBody = document.getElementById('item-body');
    var newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td><input type="number" name="item_no[]" class="input-field"></td>
        <td><select style="width:100%;" name="description[]">` + descriptionOptions + `</select></td>
        <td><input type="number" name="request_quantity[]" class="input-field"></td>
        <td><input type="text" name="uom[]" class="input-field"></td>
        <td><input type="text" name="remarks[]" class="input-field"></td>
        <td><input type="date" name="[]" class="input-field"></td>
        <td><button type="button" class="btn" onclick="removeRow(this)">Remove</button></td>
    `;
    tableBody.appendChild(newRow);
}

function removeRow(button) {
    var row = button.parentNode.parentNode;
    row.parentNode.removeChild(row);
}

    function printForm() {
        window.print();
    }

        // Function to format the date in YYYY-MM-DD format
        function getCurrentDate() {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0'); // Months are 0-based, so +1
            const day = String(today.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        // Set the current date to the label
        document.getElementById('dateLabel').textContent = getCurrentDate();

        function generateReferenceNumber() {
    const currentDate = new Date();
    const year = currentDate.getFullYear();
    const month = String(currentDate.getMonth() + 1).padStart(2, '0');

    // Retrieve the last reference number from localStorage or start from 1
    let lastNumber = localStorage.getItem('lastReferenceNumber') || '0000';
    lastNumber = String(parseInt(lastNumber) + 1).padStart(4, '0');

    const referenceNumber = `TECRF/${year}/${month}/${lastNumber}`;

    // Store the last used reference number in localStorage
    localStorage.setItem('lastReferenceNumber', lastNumber);

    // Display the reference number as a label
    document.getElementById('referenceNumberLabel').textContent = referenceNumber;

    // Store the reference number in the hidden input field for form submission
    document.getElementById('reference_number').value = referenceNumber;
}

// Generate the reference number on page load
window.onload = generateReferenceNumber;
</script>
</body>
</html>