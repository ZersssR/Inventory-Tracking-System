<?php
include 'session.php';
include 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference_number = $_POST['reference_number']; // The reference number passed from the form
    $descriptions = $_POST['description']; // The array of descriptions
    $quantities = $_POST['request_quantity']; // The updated quantities array

    foreach ($descriptions as $index => $description) {
        $quantity = $quantities[$index]; // Match each description with the quantity

        // Update query for tecrf2 table
        $sql = "UPDATE tecrf2 
                SET request_quantity = ? 
                WHERE reference_number = ? AND description = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            echo "Error: " . $conn->error;
            exit;
        }

        $stmt->bind_param("iss", $quantity, $reference_number, $description);
        $stmt->execute();
    }

    
    header('Location: edit_tecrf.php?reference_number=' . urlencode($reference_number));
    exit;
}
?>

