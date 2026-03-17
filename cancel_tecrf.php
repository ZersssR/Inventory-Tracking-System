<?php
include 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference_number = $_POST['reference_number'] ?? '';

    if (!empty($reference_number)) {
        // Update the status in the tecrf1 table
        $sql = $conn->prepare("UPDATE tecrf1 SET status = 'Cancel' WHERE reference_number = ?");
        $sql->bind_param('s', $reference_number);

        if ($sql->execute()) {
            echo "success";
        } else {
            echo "error";
        }
        $sql->close();
    }
}
$conn->close();
?>
