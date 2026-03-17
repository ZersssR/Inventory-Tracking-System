<?php
include 'session.php';
include('database.php');

// Get the form data
$revision = $_POST['revision'];
$date = $_POST['date'];
$referenceNumber = $_POST['referenceNumber'];
$date_request = $_POST['date_request'];
$date_required = $_POST['date_required'];
$charge_code = $_POST['charge_code'];
$client = $_POST['client'];
$project = $_POST['project'];
$location = $_POST['location'];
$description = $_POST['description'];
$request_quantity = $_POST['request_quantity'];
$uom = $_POST['uom'];
$available_quantity = $_POST['available_quantity'];
$remarks = $_POST['remarks'];
$date_return = $_POST['date_return'];
$code = $_POST['code'];

// Insert the data into the database
$sql = "INSERT INTO tecrf_forms (revision, date, reference_number, date_request, date_required, charge_code, client, project, location, description, request_quantity, uom, available_quantity, remarks, date_return, code)
VALUES ('$revision', '$date', '$referenceNumber', '$date_request', '$date_required', '$charge_code', '$client', '$project', '$location', '$description', '$request_quantity', '$uom', '$available_quantity', '$remarks', '$date_return', '$code')";

if (mysqli_query($conn, $sql)) {
    echo "Form saved successfully!";
} else {
    echo "Error: " . $sql . "<br>" . mysqli_error($conn);
}

// Close the database connection
mysqli_close($conn);
?>
