<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Purchase Requisition Form</title>
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 20px;
        background-color: #fff;
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
    margin: 0 auto;
    width: 100%;
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
    .btn-container {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    .btn-container2 {
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
        height: 100px;
    }
    @media print {
    .btn, .btn-container, .action-column {
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
<div class="btn-container">
    <button type="button" class="btn" onclick="window.history.back()">Back</button>
</div>

<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
<div class="container">
    <div class="header-container">
        <img src="eog.jpg" alt="Logo">
        <h1>Purchase Requisition</h1>
    </div>

    <table class="details">
        <tr>
            <td>Client:</td>
            <td><input type="text" name="client" class="input-field"></td>
            <td>SR Number</td>
            <td><input type="text" name="pr_no" class="input-field"></td>
        </tr>
        <tr>
            <td>Project Title:</td>
            <td><input type="text" name="project_title" class="input-field"></td>
            <td>Type of Purchase:</td>
            <td><input type="text" name="type_purchase" class="input-field"></td>
        </tr>
        <tr>
            <td>Person in Charge:</td>
            <td><input type="text" name="person_charge" class="input-field"></td>
            <td>Delivery Point:</td>
            <td><input type="text" name="delivery_point" class="input-field"></td>
        </tr>
    </table>

               
    <table class="details">
        <tr>
        <td>Priority Level Request:</td>
        <td>
            <input type="radio" name="priority_request" value="normal" id="normal_request">
            <label for="normal_request">Normal Request</label>
            <input type="radio" name="priority_request" value="urgent" id="urgent_request">
            <label for="urgent_request">Urgent Request</label>
        </td>
            <td>Charge Code</td>
            <td><input type="text" name="charge_code" class="input-field"></td>
        </tr>
        <tr>
            <td>AFS/WO:</td>
            <td><input type="text" name="afs" class="input-field"></td>
            <td>Cost Centre:</td>
            <td><input type="text" name="cost_centre:" class="input-field"></td>
        </tr>
    </table>


    <table class="item-list">
        <thead>
            <tr>
                <th>No</th>
                <th>Part ID</th>
                <th>Item Description</th>
                <th>Size</th>
                <th>Quantity</th>
                <th>UOM</th>
                <th>PG</th>
                <th>Remark</th>
                <th class="action-column">Action</th>
            </tr>
        </thead>
        <tbody id="item-body">
            <tr>
                <td><input type="number" name="no[]" class="input-field no-field" value="1" readonly></td>
                <td><input type="text" name="partID[]" class="input-field"></td>
                <td><input type="text" name="item_description[]" class="input-field"></td>
                <td><input type="text" name="size[]" class="input-field"></td>
                <td><input type="text" name="duration[]" class="input-field"></td>
                <td><input type="number" name="quantity[]" class="input-field"></td>
                <td><input type="text" name="uom[]" class="input-field"></td>
                <td><input type="text" name="pg[]" class="input-field"></td>
                <td><input type="text" name="remarks[]" class="input-field"></td>
                <td class="action-column"><button type="button" class="btn" onclick="removeRow(this)">Remove</button></td> <!-- Add class here -->
            </tr>
        </tbody>
    </table>

    <div class="btn-container2">
        <button type="button" class="btn" onclick="addRow()">Add Row</button>
        <button type="submit" class="btn">Save</button>
        <button type="button" class="btn" onclick="printForm()">Print</button>
    </div>
</form>

<table class="approvals">
    <tr>
        <td>
            <strong>PREPARED BY:</strong>
        </td>
        <td>
            <strong>REVIEWED BY:</strong>
        </td>
        <td>
            <strong>CHECKED BY:</strong>
        </td>
        <td>
            <strong>APPROVED BY:</strong>
        </td>
    </tr>
    <tr>
        <td class="signature">
            <div>
               <input type="text" name="request_by_signature" class="input-field signature-field">
            </div>
        </td>
        <td class="signature">
            <div>
             <input type="text" name="verified_by_signature" class="input-field signature-field">
            </div>
        </td>
        <td class="signature">
            <div>
              <input type="text" name="approved_by_signature" class="input-field signature-field">
            </div>
        </td>
        <td class="signature">
            <div>
             <input type="text" name="approved_by_signature" class="input-field signature-field">
            </div>
        </td>
    </tr>
</table>

<script>
    var currentRowNumber = 1;

    function addRow() {
        var tableBody = document.getElementById('item-body');
        var newRow = document.createElement('tr');
        currentRowNumber++;

        newRow.innerHTML = `
                <td><input type="number" name="no[]" class="input-field no-field" value="${currentRowNumber}" readonly></td>
                <td><input type="text" name="partID[]" class="input-field"></td>
                <td><input type="text" name="item_description[]" class="input-field"></td>
                <td><input type="text" name="size[]" class="input-field"></td>s
                <td><input type="number" name="quantity[]" class="input-field"></td>
                <td><input type="text" name="uom[]" class="input-field"></td>
                <td><input type="text" name="pg[]" class="input-field"></td>
                <td><input type="text" name="remarks[]" class="input-field"></td>
                <td class="action-column"><button type="button" class="btn" onclick="removeRow(this)">Remove</button></td>
        `;
        tableBody.appendChild(newRow);
    }

    function removeRow(button) {
        var row = button.parentElement.parentElement;
        row.parentElement.removeChild(row);
        currentRowNumber--; // Decrement currentRowNumber when a row is removed
        updateRowNumbers(); // Reorder the row numbers
    }

    function updateRowNumbers() {
        var noFields = document.querySelectorAll('.no-field');
        noFields.forEach((field, index) => {
            field.value = index + 1;
        });
    }

    function printForm() {
        window.print();
    }
</script>

</body>
</html>
