<?php
session_start();
include 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference_number = trim($_POST['reference_number'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $unit_ids = $_POST['unit_id'] ?? [];

    if (empty($reference_number) || empty($location) || empty($unit_ids)) {
        die("Invalid input data.");
    }

    // Sanitize unit IDs
    $unit_ids = array_map('trim', $unit_ids);

    // Start transaction
    $conn->begin_transaction();
    try {
        // Update status in tecrf1
        $status = 'Assigned';
        $items_assigned = 1;
        $reassigned = isset($_POST['reassign']) && $_POST['reassign'] === '1' ? 1 : 0;

        $sql = $conn->prepare("UPDATE tecrf1 SET status = ?, items_assigned = ?, reassigned = ? WHERE reference_number = ?");
        $sql->bind_param('siis', $status, $items_assigned, $reassigned, $reference_number);
        if (!$sql->execute()) {
            throw new Exception("Failed to update TECRF1 status.");
        }

        // Fetch descriptions for unit_ids
        $unit_ids_placeholder = implode(',', array_fill(0, count($unit_ids), '?'));
        $stmt_desc = $conn->prepare("SELECT unit_id, description FROM product WHERE unit_id IN ($unit_ids_placeholder)");
        $stmt_desc->bind_param(str_repeat('s', count($unit_ids)), ...$unit_ids);
        $stmt_desc->execute();
        $result = $stmt_desc->get_result();
        $units = $result->fetch_all(MYSQLI_ASSOC);

        // Update product table in chunks for efficiency
        foreach ($unit_ids as $unit_id) {
            $stmt_update = $conn->prepare("UPDATE product SET loadout_location = ?, action_notice_no = ? WHERE unit_id = ?");
            $stmt_update->bind_param('sss', $location, $reference_number, $unit_id);
            if (!$stmt_update->execute()) {
                throw new Exception("Failed to update product for Unit ID: $unit_id");
            }
        }

        // Insert into unit_location_history
        $stmt_history = $conn->prepare("INSERT INTO unit_location_history (description, unit_id, loadout_location, action_notice_no, assigned_at) VALUES (?, ?, ?, ?, NOW())");
        foreach ($units as $unit) {
            $stmt_history->bind_param('ssss', $unit['description'], $unit['unit_id'], $location, $reference_number);
            if (!$stmt_history->execute()) {
                throw new Exception("Failed to insert history record for Unit ID: " . $unit['unit_id']);
            }
        }

        // Commit transaction
        $conn->commit();

        // Store assigned unit_ids in session and redirect
        $_SESSION['assigned_unit_ids'] = $unit_ids;
        header("Location: assigned_summary.php?reference_number=" . urlencode($reference_number));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}

$conn->close();
?>
