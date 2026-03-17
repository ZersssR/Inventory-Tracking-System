<?php
include 'database.php';

if (isset($_POST['description'])) {
    $description = $_POST['description'];
    
    // Semak jika ada item tanpa size
    $check_no_size_sql = "SELECT COUNT(*) as count FROM inventory_product 
                          WHERE description = ? AND (size IS NULL OR size = '')";
    $check_stmt = $conn->prepare($check_no_size_sql);
    $check_stmt->bind_param("s", $description);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $no_size_count = $check_result->fetch_assoc()['count'];
    
    // Ambil semua size yang ada
    $sql = "SELECT DISTINCT size FROM inventory_product 
            WHERE description = ? AND size IS NOT NULL AND size != '' 
            ORDER BY size";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $description);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo '<option value="">Select Size</option>';
    
    // Jika ada item tanpa size, tambah option khas
    if ($no_size_count > 0) {
        echo '<option value="">-- Items without size --</option>';
    }
    
    while ($row = $result->fetch_assoc()) {
        echo '<option value="' . htmlspecialchars($row['size']) . '">' . htmlspecialchars($row['size']) . '</option>';
    }
}
?>