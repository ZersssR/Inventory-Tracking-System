<?php
// Start the session at the beginning of the file
session_start();

// Check if the user is logged in
if (isset($_SESSION['email'])) {
    // Include the database connection
    require 'database.php';

    // Prepare and execute the query to set the login_status to 0
    $stmt = $conn->prepare("UPDATE users SET login_status = 0 WHERE email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();

    // Check if the query executed successfully
    if ($stmt->execute()) {
        // Query successful, login_status updated
        // echo "Login status updated"; // For debugging purposes
    } else {
        echo "<div class='alert alert-danger'>Error updating login status.</div>";
    }

    // Close the statement
    $stmt->close();
} else {
    // If no session username is found, redirect to login page
    header("location: index.php");
    exit();
}

// Wipe out all session data
$_SESSION = array();

// Destroy the session
session_destroy();


// Redirect to login page after logging out
header("location: index.php");
exit;
?>
