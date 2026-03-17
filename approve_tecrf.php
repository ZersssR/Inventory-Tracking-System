<?php
include 'user_redirect.php';
include 'session.php';
// Database connection
$conn = new mysqli("localhost", "root", "", "inventory_tracking");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the ID from the URL
$id = $_GET['id'];

// Update the status in the database (you may need to add a 'status' column to the table)
$sql = "UPDATE tecrf1 SET status = 'Approved' WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo "Request approved successfully.";
    // Redirect back to the approval page
    header("Location: approval.php");
    exit();
} else {
    echo "Error approving request: " . $conn->error;
}

$stmt->close();
$conn->close();
?>
<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "inventory_tracking");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the ID from the URL
$id = $_GET['id'];

// Update the status in the database (you may need to add a 'status' column to the table)
$sql = "UPDATE tecrf1 SET status = 'Approved' WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo "Request approved successfully.";
    // Redirect back to the approval page
    header("Location: approval.php");
    exit();
} else {
    echo "Error approving request: " . $conn->error;
}

$stmt->close();
$conn->close();
?>
