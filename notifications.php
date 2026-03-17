<?php
// Assuming you have a PDO connection to your database
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC");
$stmt->bindParam(':user_id', $_SESSION['user_id']); // Assuming the logged-in admin staff has a user ID
$stmt->execute();

$notifications = $stmt->fetchAll();

foreach ($notifications as $notification) {
    // Display each notification
    echo "<div class='notification'>";
    echo "<p>" . htmlspecialchars($notification['message']) . "</p>";
    echo "<small>Received on: " . $notification['created_at'] . "</small>";
    echo "</div>";
}
?>
