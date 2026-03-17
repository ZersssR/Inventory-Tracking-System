<?php
include 'session.php';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "inventory_tracking";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST['user_id'];
    $reference_number = $_POST['reference_number']; //guna reference number untuk approve atau reject mengikut form
    $action = $_POST['action'];

    if ($action == 'approve') {
        // Update status to approved for the specific form
        $sql = "UPDATE tecrf1 SET status='Approved' WHERE user_id=? AND reference_number=?";
        $stmt = $conn->prepare($sql);
        
        // Check if prepare() was successful
        if ($stmt === false) {
            die("Error preparing statement: " . $conn->error);
        }

        $stmt->bind_param("is", $user_id, $reference_number);
        $stmt->execute();
        echo "Request approved successfully!";
    } elseif ($action == 'reject') {
        // Update status to rejected for the specific form
        $sql = "UPDATE tecrf1 SET status='Rejected' WHERE user_id=? AND reference_number=?";
        $stmt = $conn->prepare($sql);

        // Check if prepare() was successful
        if ($stmt === false) {
            die("Error preparing statement: " . $conn->error);
        }

        $stmt->bind_param("is", $user_id, $reference_number);
        $stmt->execute();
        echo "Request rejected successfully!";
    } elseif ($action == 'view') {
        // Redirect to a detailed view page
        header("Location: view_tecrf.php?id=" . $user_id . "&reference_number=" . $reference_number);
        exit();
    }
}

$conn->close();
?>
