<?php
include 'database.php';

if (isset($_POST['description'])) {
    $description = $_POST['description'];
    $size = isset($_POST['size']) ? $_POST['size'] : '';
    
    // Jika size dihantar dan tidak kosong
    if (!empty($size)) {
        $sql = "SELECT ip.*, f.floor_name, b.bay_name, s.store_name as location_name, uom.uom_name
                FROM inventory_product ip
                LEFT JOIN floor f ON ip.floor_id = f.floor_id
                LEFT JOIN bay b ON ip.bay_id = b.bay_id
                LEFT JOIN store s ON ip.store_id = s.store_id
                LEFT JOIN unit_of_measurement uom ON ip.uom_id = uom.uom_id
                WHERE ip.description = ? AND ip.size = ?
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $description, $size);
    } else {
        // Jika size kosong, cari rekod dengan size NULL atau empty string
        $sql = "SELECT ip.*, f.floor_name, b.bay_name, s.store_name as location_name, uom.uom_name
                FROM inventory_product ip
                LEFT JOIN floor f ON ip.floor_id = f.floor_id
                LEFT JOIN bay b ON ip.bay_id = b.bay_id
                LEFT JOIN store s ON ip.store_id = s.store_id
                LEFT JOIN unit_of_measurement uom ON ip.uom_id = uom.uom_id
                WHERE ip.description = ? AND (ip.size IS NULL OR ip.size = '')
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $description);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'description' => $row['description'],
            'size' => $row['size'] ?? '', // Jika NULL, return empty string
            'uom_name' => $row['uom_name'] ?? '',
            'floor' => $row['floor_name'] ?? '',
            'bay' => $row['bay_name'] ?? '',
            'location' => $row['location_name'] ?? '',
            'current_stock' => $row['current_stock'] ?? 0
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
}
?>