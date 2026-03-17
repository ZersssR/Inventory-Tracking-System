<?php
include 'session.php'; // This should start session and check login

// Check if we're coming back from view page and session data exists
$form_data = [];
if (isset($_SESSION['tecrf_data']) && !isset($_POST['save_to_session']) && !isset($_POST['final_submit'])) {
    $form_data = $_SESSION['tecrf_data'];
}

// Database connection setup
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "inventory_tracking";

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the logged-in user ID from session
if (!isset($_SESSION['user_id'])) {
    die("Error: User not logged in. Please login first.");
}
$logged_in_user_id = $_SESSION['user_id']; // This gets the actual logged-in user ID (33 for Amir Syazwan)

// Generate reference number
if (!isset($_SESSION['reference_number'])) {
    $year = date('Y');
    $month = date('m');
    
    $sql = "SELECT reference_number FROM tecrf 
            WHERE reference_number LIKE 'TECRF/$year/$month/%' 
            ORDER BY reference_number DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $last_ref = $result->fetch_assoc()['reference_number'];
        $parts = explode('/', $last_ref);
        
        if (count($parts) === 4) {
            $last_num = intval($parts[3]);
            $new_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $new_num = '0001';
        }
    } else {
        $new_num = '0001';
    }
    
    $_SESSION['reference_number'] = "TECRF/$year/$month/$new_num";
}

$reference_number = $_SESSION['reference_number'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if this is the first form submission (saving to session)
    if (isset($_POST['save_to_session'])) {
        // Process "other" values correctly
        $client_actual = ($_POST['client'] == 'other') ? $_POST['client_other'] : $_POST['client'];
        $project_actual = ($_POST['project'] == 'other') ? $_POST['project_other'] : $_POST['project'];
        $location_actual = ($_POST['location'] == 'other') ? $_POST['location_other'] : $_POST['location'];
        
        // Save form data to session
        $_SESSION['tecrf_data'] = [
            'date_required' => $_POST['date_required'],
            'client' => $client_actual,
            'project' => $project_actual,
            'charge_code' => $_POST['charge_code'],
            'location' => $location_actual,
            'original_client' => $_POST['client'],
            'original_project' => $_POST['project'],
            'original_location' => $_POST['location'],
            'client_other' => $_POST['client_other'] ?? '',
            'project_other' => $_POST['project_other'] ?? '',
            'location_other' => $_POST['location_other'] ?? '',
            'descriptions' => $_POST['description'] ?? [],
            'sizes' => $_POST['size'] ?? [],
            'req_qtys' => $_POST['request_quantity'] ?? [],
            'uoms' => $_POST['uom'] ?? [],
            'floors' => $_POST['floor'] ?? [],
            'bays' => $_POST['bay'] ?? [],
            'location_codes' => $_POST['location_code'] ?? [],
            'current_stocks' => $_POST['current_stock'] ?? [],
            'remarks' => $_POST['remarks'] ?? []
        ];
        
        // Redirect to view page
        header("Location: view_tecrf_.php");
        exit();
    }
    
    // Check if this is the final submission from view page
    if (isset($_POST['final_submit'])) {
        if (!isset($_SESSION['tecrf_data'])) {
            die("Error: No request data found. Please start over.");
        }
        
        $tecrf_data = $_SESSION['tecrf_data'];
        
        $descriptions = $_POST['description'] ?? [];
        $sizes = $_POST['size'] ?? [];
        $req_qtys = $_POST['request_quantity'] ?? [];
        $uoms = $_POST['uom'] ?? [];
        $remarks = $_POST['remarks'] ?? [];
        
        if (!empty($descriptions) && !empty($req_qtys)) {
            $reference_number = $_SESSION['reference_number'];
            
            // Check if reference number already exists
            $check_sql = "SELECT COUNT(*) FROM tecrf WHERE reference_number = ?";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->bind_param("s", $reference_number);
            $stmt_check->execute();
            $stmt_check->bind_result($count);
            $stmt_check->fetch();
            $stmt_check->close();
            
            // If it exists, generate a new one
            if ($count > 0) {
                $parts = explode('/', $reference_number);
                if (count($parts) === 4) {
                    $last_num = intval($parts[3]);
                    $new_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
                    $reference_number = "TECRF/{$parts[1]}/{$parts[2]}/$new_num";
                    $_SESSION['reference_number'] = $reference_number;
                }
            }
            
            // Prepare data for insertion
            $date = date('Y-m-d');
            $date_required = $tecrf_data['date_required'];
            $client = $tecrf_data['client'];
            $project = $tecrf_data['project'];
            $location = $tecrf_data['location'];
            $charge_code = $tecrf_data['charge_code'];
            
            // FIXED: Use the logged-in user ID from session instead of hardcoded 1
            $user_id = $logged_in_user_id;

            // Handle "Others" options
            if (isset($_POST['client_other']) && !empty($_POST['client_other'])) {
                $client = $_POST['client_other'];
            }
            
            if (isset($_POST['project_other']) && !empty($_POST['project_other'])) {
                $project = $_POST['project_other'];
            }
            
            if (isset($_POST['location_other']) && !empty($_POST['location_other'])) {
                $location = $_POST['location_other'];
            }

            // Insert into "other" tables if needed
            if (isset($tecrf_data['original_client']) && $tecrf_data['original_client'] == 'other' && !empty($client)) {
                $check_client = $conn->prepare("SELECT client_id FROM client WHERE client_name = ?");
                $check_client->bind_param("s", $client);
                $check_client->execute();
                $check_client->store_result();
                
                if ($check_client->num_rows == 0) {
                    $stmt_client = $conn->prepare("INSERT INTO client (client_name, is_other) VALUES (?, 1)");
                    $stmt_client->bind_param("s", $client);
                    $stmt_client->execute();
                    $stmt_client->close();
                }
                $check_client->close();
            }

            if (isset($tecrf_data['original_project']) && $tecrf_data['original_project'] == 'other' && !empty($project)) {
                $check_project = $conn->prepare("SELECT project_id FROM project WHERE project_name = ?");
                $check_project->bind_param("s", $project);
                $check_project->execute();
                $check_project->store_result();
                
                if ($check_project->num_rows == 0) {
                    $stmt_project = $conn->prepare("INSERT INTO project (project_name, is_other) VALUES (?, 1)");
                    $stmt_project->bind_param("s", $project);
                    $stmt_project->execute();
                    $stmt_project->close();
                }
                $check_project->close();
            }

            if (isset($tecrf_data['original_location']) && $tecrf_data['original_location'] == 'other' && !empty($location)) {
                $check_location = $conn->prepare("SELECT location_id FROM location WHERE location_name = ?");
                $check_location->bind_param("s", $location);
                $check_location->execute();
                $check_location->store_result();
                
                if ($check_location->num_rows == 0) {
                    $stmt_location = $conn->prepare("INSERT INTO location (location_name, is_other) VALUES (?, 1)");
                    $stmt_location->bind_param("s", $location);
                    $stmt_location->execute();
                    $stmt_location->close();
                }
                $check_location->close();
            }
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert into tecrf (main table) with the correct user_id
                $stmt_tecrf = $conn->prepare("INSERT INTO tecrf (date, reference_number, date_required, client, project, charge_code, location, user_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
                $stmt_tecrf->bind_param("sssssssi", $date, $reference_number, $date_required, $client, $project, $charge_code, $location, $user_id);
                
                if (!$stmt_tecrf->execute()) {
                    throw new Exception("Error inserting into tecrf: " . $stmt_tecrf->error);
                }
                
                $tecrf_id = $stmt_tecrf->insert_id;
                
                // Insert into tecrf_items for each item
                $stmt_items = $conn->prepare("INSERT INTO tecrf_items (tecrf_id, description, size, request_quantity, uom, floor, bay, location_code, current_stock, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $floors = $tecrf_data['floors'] ?? [];
                $bays = $tecrf_data['bays'] ?? [];
                $location_codes = $tecrf_data['location_codes'] ?? [];
                $current_stocks = $tecrf_data['current_stocks'] ?? [];

                foreach ($descriptions as $index => $description) {
                    if (!empty($description) && !empty($req_qtys[$index])) {
                        $remark = isset($remarks[$index]) ? $remarks[$index] : '';
                        $current_stock = isset($current_stocks[$index]) ? $current_stocks[$index] : 0;
                        $floor = isset($floors[$index]) ? $floors[$index] : '';
                        $bay = isset($bays[$index]) ? $bays[$index] : '';
                        $location_code_item = isset($location_codes[$index]) ? $location_codes[$index] : '';
                        
                        $size_value = isset($sizes[$index]) ? $sizes[$index] : '';
                        
                        $stmt_items->bind_param("ississssis", 
                            $tecrf_id,
                            $description, 
                            $size_value,
                            $req_qtys[$index], 
                            $uoms[$index], 
                            $floor, 
                            $bay, 
                            $location_code_item,
                            $current_stock,
                            $remark
                        );
                        
                        if (!$stmt_items->execute()) {
                            throw new Exception("Error inserting into tecrf_items: " . $stmt_items->error);
                        }
                    }
                }
                
                // Commit transaction
                $conn->commit();

                // Send email notification
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'epicgroup-com-my.mail.protection.outlook.com';
                    $mail->Username = 'itms.epic-og@epicgroup.com.my';
                    $mail->Password = 'Epic12345'; 
                    $mail->SMTPAuth = false; 
                    $mail->SMTPSecure = false; 
                    $mail->Port = 25;
                    $mail->SMTPAutoTLS = false;

                    $mail->setFrom('itms.epic-og@epicgroup.com.my', 'ITMS');
                    $mail->addAddress('fathiahizzti@gmail.com');
                    $mail->addAddress('noornajwaamirah@gmail.com');

                    $mail->isHTML(true);
                    $mail->Subject = 'New TECRF Request Submitted';
                    $mail->Body = '
                        <h2>New TECRF Request</h2>
                        <table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th>Reference Number</th>
                                    <th>Date Required</th>
                                    <th>Client</th>
                                    <th>Project</th>
                                    <th>Description</th>
                                    <th>Size</th>
                                    <th>Request Quantity</th>
                                </tr>
                            </thead>
                            <tbody>';
                            for ($i = 0; $i < count($descriptions); $i++) {
                                if (!empty($descriptions[$i])) {
                                    $size_display = !empty($sizes[$i]) ? htmlspecialchars($sizes[$i]) : '-';
                                    $mail->Body .= '
                                        <tr>
                                            <td>' . htmlspecialchars($reference_number) . '</td>
                                            <td>' . htmlspecialchars($date_required) . '</td>
                                            <td>' . htmlspecialchars($client) . '</td>
                                            <td>' . htmlspecialchars($project) . '</td>
                                            <td>' . htmlspecialchars($descriptions[$i]) . '</td>
                                            <td>' . $size_display . '</td>
                                            <td>' . htmlspecialchars($req_qtys[$i]) . '</td>
                                        </tr>';
                                }
                            }

                    $mail->Body .= '
                            </tbody>
                        </table>';

                    $mail->send();
                    
                    // Clear session data
                    unset($_SESSION['tecrf_data']);
                    unset($_SESSION['reference_number']);
                    
                    ?>
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Email Sent</title>
                        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                        <style>
                            .modal-content {
                                border-radius: 15px;
                                border: none;
                                text-align: center;
                                padding: 20px;
                                box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.2);
                            }
                            .modal-header {
                                border-bottom: none;
                                justify-content: center;
                            }
                            .modal-body {
                                font-size: 18px;
                                color: #555;
                            }
                            .modal-footer {
                                border-top: none;
                                justify-content: center;
                            }
                            .icon-container {
                                font-size: 60px;
                                color: #28a745;
                                margin-bottom: 10px;
                            }
                            .btn-custom {
                                background-color: #28a745;
                                color: white;
                                border-radius: 30px;
                                padding: 10px 25px;
                                font-size: 16px;
                            }
                            .btn-custom:hover {
                                background-color: #218838;
                            }
                        </style>
                    </head>
                    <body onload="showSuccessModal()">
                    
                    <!-- Custom Success Modal -->
                    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold text-success">Success!</h5>
                                </div>
                                <div class="modal-body">
                                    <div class="icon-container">✅</div>
                                    <p>Email has been successfully sent to Admin Staff.</p>
                                    <p><strong>Reference Number:</strong> <?php echo $reference_number; ?></p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-custom" onclick="redirectToDashboard()">OK</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                    <script>
                        function showSuccessModal() {
                            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                            successModal.show();
                        }
                    
                        function redirectToDashboard() {
                            window.location.href = 'customer_dashboard.php';
                        }
                    </script>
                    
                    </body>
                    </html>
                    <?php
                    exit();                                             
                } catch (Exception $e) {
                    throw new Exception("Email error: " . $mail->ErrorInfo);
                }
            } catch (Exception $e) {
                $conn->rollback();
                echo "Transaction failed: " . $e->getMessage();
            }
        } else {
            echo "Error: Please fill out all required fields.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Create TECRF | EPIC OG</title>
    <!-- Fonts and Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            height: 100%;
            width: 100%;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #d9eaef 0%, #e8f3f7 100%);
            color: #1e293b;
        }

        /* Modern Sidebar - Sama macam customer_dashboard */
        #sidebar {
            height: 100vh;
            width: 280px;
            position: fixed;
            top: 0;
            left: 0;
            background: #04548d;
            box-shadow: 4px 0 30px rgba(0, 98, 169, 0.3);
            z-index: 10;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 30px 24px;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-area i {
            font-size: 2rem;
            color: white;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 12px;
        }

        .brand {
            color: white;
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .sidebar-menu {
            flex: 1;
            padding: 0 16px;
            overflow-y: auto;
        }

        .sidebar-menu ul {
            list-style: none;
        }

        .sidebar-menu ul li {
            margin-bottom: 6px;
        }

        .sidebar-menu ul li a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 14px 20px;
            font-size: 0.95rem;
            font-weight: 500;
            border-radius: 12px;
            transition: all 0.2s ease;
            gap: 14px;
        }

        .sidebar-menu ul li a i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
        }

        .sidebar-menu ul li.active a {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar-menu ul li.active a i {
            color: white;
        }

        .sidebar-menu ul li:not(.logout):hover a {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-menu ul li:not(.logout):hover a i {
            color: white;
        }

        .sidebar-menu ul li.logout {
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }

        .sidebar-menu ul li.logout a {
            color: rgba(255, 255, 255, 0.7);
        }

        .sidebar-menu ul li.logout:hover a {
            background: rgba(239, 68, 68, 0.2);
            color: #fff;
        }

        /* Main Content */
        #main-content {
            margin-left: 280px; /* ruang untuk sidebar */
            padding: 30px 35px;
            min-height: 100vh;
            width: calc(100% - 280px); /* penting! */
        }

        /* Header - Sama macam customer_dashboard */
        .header {
            background: #0062a9;
            border-radius: 24px;
            padding: 20px 30px;
            margin-bottom: 35px;
            box-shadow: 0 15px 35px rgba(0, 98, 169, 0.2);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: -80%;
            left: -5%;
            width: 350px;
            height: 350px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            pointer-events: none;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-container {
            background: white;
            padding: 8px 15px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .logo-wrapper img {
            height: 35px;
            width: auto;
            display: block;
        }

        .title-badge {
            font-size: 1rem;
            font-weight: 600;
            color: white;
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 18px;
            border-radius: 40px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
        }

        .header-right {
            text-align: right;
        }

        .welcome-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
            justify-content: flex-end;
        }

        .welcome-row h4 {
            font-size: 1rem;
            font-weight: 500;
            color: white;
        }

        .welcome-row span {
            color: white;
            font-size: 1.2rem;
        }

        .header-right p {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .clock-container {
            background: rgba(255, 255, 255, 0.12);
            padding: 10px 20px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
        }

        .clock {
            font-size: 0.9rem;
            color: white;
            font-weight: 600;
            text-align: center;
            line-height: 1.4;
        }

        /* Container - sama tapi dengan style yang lebih modern */
        .container {
            width: 70%;
            max-width: 1300px;
            padding: 30px;
            margin: 0 auto; /* auto margin untuk tengah */
            background-color: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 98, 169, 0.15);
            border: 1px solid rgba(0, 98, 169, 0.1);
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid #e9ecef;
        }

        .header-container img {
            height: 50px;
        }

        h1 {
            text-align: center;
            font-size: 18px;
            margin: 0;
            padding: 0;
            color: #0062a9;
            font-weight: 600;
        }

        /* Modern Table Styles - Rounded rectangle, border kemas, shadow halus */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 25px;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #dee2e6;
        }

        th, td {
            padding: 14px 12px;
            text-align: left;
            font-size: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            background-color: white;
            color: #212529;
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* Hover effect pada rows */
        tbody tr:hover td {
            background-color: #f1f3f5;
            transition: background-color 0.2s ease;
        }

        /* Style untuk action/delete column */
        .action-delete {
            text-align: center;
            background-color: #fff5f5;
        }

        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .btn-remove:hover {
            background: #c82333;
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
        }

        /* Input fields dalam table */
        .input-field {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 12px;
            transition: all 0.2s ease;
            background: white;
            font-family: 'Inter', sans-serif;
        }

        .input-field:focus {
            outline: none;
            border-color: #0062a9;
            box-shadow: 0 0 0 3px rgba(0, 98, 169, 0.1);
        }

        /* Details table */
        .details td {
            padding: 15px 12px;
        }

        .details select.input-field {
            cursor: pointer;
            background-color: white;
        }

        /* Back button */
        .back-btn {
            display: inline-block;
            padding: 10px 22px;
            color: #ffffff;
            background: #0062a9;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 10px rgba(0, 98, 169, 0.2);
        }

        .back-btn:hover {
            background: #004d88;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 98, 169, 0.3);
        }

        .back-btn i {
            margin-right: 8px;
        }

        /* Buttons */
        .btn {
            color: #ffffff;
            background: #0062a9;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            margin: 5px;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 4px 8px rgba(0, 98, 169, 0.2);
        }

        .btn:hover {
            background: #004d88;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 98, 169, 0.3);
        }

        /* Add Item Section */
        .add-item-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 25px;
            border: 2px dashed #adb5bd;
        }

        .add-item-section h5 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .add-item-section h5 i {
            color: #0062a9;
            margin-right: 8px;
        }

        .form-control {
            border: 1px solid #ced4da;
            border-radius: 10px;
            padding: 10px 15px;
            font-size: 12px;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
        }

        .form-control:focus {
            border-color: #0062a9;
            box-shadow: 0 0 0 3px rgba(0, 98, 169, 0.1);
            outline: none;
        }

        /* Other input */
        .other-input {
            margin-top: 8px;
            display: none;
            background: #fff9e6;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 12px;
            width: 100%;
        }

        .required {
            color: #dc3545;
            font-weight: bold;
        }

        .note {
            font-size: 12px;
            color: #dc3545;
            margin-top: 20px;
            padding: 15px 20px;
            background: #fff5f5;
            border-radius: 12px;
            border-left: 4px solid #dc3545;
        }

        .note i {
            margin-right: 8px;
        }

        .btn-container {
            text-align: right;
            margin-top: 20px;
        }

        /* Header info table */
        .header-container table {
            width: auto;
            margin-left: 20px;
            box-shadow: none;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            margin-bottom: 0;
        }

        .header-container table td {
            padding: 8px 15px;
            background: white;
            border-bottom: 1px solid #e9ecef;
        }

        .header-container table tr:last-child td {
            border-bottom: none;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .container {
                width: 85%; /* lebih besar sikit untuk skrin sederhana */
            }
        }

        @media (max-width: 992px) {
            #main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
            
            .container {
                width: 95%; /* guna lebih ruang untuk mobile */
                margin: 0 auto;
            }
        }

        @media (max-width: 992px) {
            .container {
                width: 90%;
                margin-left: 0;
                margin-top: 20px;
            }
            #sidebar {
                transform: translateX(-100%);
            }
            #main-content {
                margin-left: 0;
            }
        }

        /* Print styles */
        @media print {
            .btn, .btn-container, .action-delete, .back-btn, #sidebar, .add-item-section, .header {
                display: none;
            }
            #main-content {
                margin-left: 0;
                padding: 20px;
            }
            .container {
                width: 100%;
                margin: 0;
                box-shadow: none;
                border: 1px solid #000;
            }
        }

        select option[value="other"] {
            font-style: italic;
            color: #fd7e14;
            background-color: #fff9e6;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #e9ecef;
        }

        ::-webkit-scrollbar-thumb {
            background: #adb5bd;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #6c757d;
        }
    </style>
    <script>
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const time = `${hours}:${minutes}:${seconds}`;
            
            const day = String(now.getDate()).padStart(2, '0');
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const year = now.getFullYear();
            const date = `${day}/${month}/${year}`;
            
            document.getElementById('clock').innerHTML = `${time}<br>${date}`;
        }
        
        setInterval(updateClock, 1000);
    </script>
</head>
<body>
    <div id="sidebar">
        <div class="sidebar-header">
            <div class="logo-area">
                <i class="fa fa-cube"></i>
                <span class="brand">EPIC OG</span>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li>
                    <a href="customer_dashboard.php">
                        <i class="fa fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="active">
                    <a href="tecrf.php">
                        <i class="fa fa-file-signature"></i>
                        <span>Create TECRF</span>
                    </a>
                </li>
                <li>
                    <a href="list_tecrf.php">
                        <i class="fa fa-list"></i>
                        <span>My Requests</span>
                    </a>
                </li>
                <li class="logout">
                    <a href="logout.php">
                        <i class="fa fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>    

    <div id="main-content">
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <div class="logo-wrapper">
                        <div class="logo-container">
                            <img src="eog3.jpg" alt="EPIC OG Logo">
                        </div>
                        <div class="title-badge">Create TECRF</div>
                    </div>
                </div>
                <div class="header-right">
                    <div class="welcome-row">
                        <h4>Welcome back, <?php 
                            // Fetch user name for display
                            include 'database.php';
                            $user_id = $_SESSION['user_id'] ?? 0;
                            $user_sql = "SELECT full_name FROM users WHERE user_id = ?";
                            $stmt = $conn->prepare($user_sql);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $user_result = $stmt->get_result();
                            $user_data = $user_result->fetch_assoc();
                            echo htmlspecialchars($user_data['full_name'] ?? 'User');
                        ?></h4>
                        <span>👋</span>
                    </div>
                    <p>EPIC OG Inventory Tracking System</p>
                </div>
                <div class="clock-container">
                    <div class="clock" id="clock"></div>
                </div>
            </div>
        </div>

        <a href="customer_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
        
<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" onsubmit="return validateForm()">
    <div class="container" style="transform: scale(1.05); transform-origin: top center;">
        <div class="header-container">
            <img src="eog.jpg" alt="Logo" style="transform: scale(1.1);">
            <h1 style="font-size: 20px;">Tools, Equipment & Consumables Request Form (TECRF)</h1>
            <table style="transform: scale(1.05);">
                <tr>
                    <td style="font-size: 13px;">Form:</td>
                    <td style="font-size: 13px;">TECRF</td>
                </tr>
                <tr>
                    <td style="font-size: 13px;">Doc. No.:</td>
                    <td style="font-size: 13px;">WI/EOG/LOG/10(FM/02)</td>
                </tr>
                <tr>
                    <td style="font-size: 13px;">Date:</td>
                    <td style="font-size: 13px;"><label for="date" id="dateLabel">Current date</label></td>
                </tr>
                <tr>
                    <td style="font-size: 13px;">No of Pages:</td>
                    <td style="font-size: 13px;">1</td>
                </tr>
                <tr>
                    <td style="font-size: 13px;">Reference No.:</td>
                    <td style="font-size: 14px;"><strong style="color: #0062a9;"><?php echo $_SESSION['reference_number']; ?></strong></td>
                    <input type="hidden" name="reference_number" id="reference_number" value="<?php echo $_SESSION['reference_number']; ?>">
                </tr>
            </table>
        </div>

        <table class="details" style="transform: scale(1.03);">
            <tr>
                <td style="font-size: 13px;"><strong>Date Required:</strong> <span class="required">*</span></td>
                <td><input type="date" name="date_required" class="input-field" style="font-size: 13px; padding: 10px;" required value="<?php echo isset($form_data['date_required']) ? htmlspecialchars($form_data['date_required']) : ''; ?>"></td>
                <td style="font-size: 13px;"><strong>Client:</strong> <span class="required">*</span></td>
                <td>
                    <select name="client" id="client" class="input-field" style="font-size: 13px; padding: 10px;" required onchange="toggleOtherInput(this, 'client_other')">
                        <option value="" disabled selected>Select Client</option>
                        <?php
                        $client_sql = "SELECT * FROM client WHERE client_name != 'other' ORDER BY client_name";
                        $client_result = $conn->query($client_sql);
                        while ($client_row = $client_result->fetch_assoc()) {
                            $selected = (isset($form_data['original_client']) && $form_data['original_client'] == $client_row['client_name']) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($client_row['client_name']) . '" ' . $selected . '>' . htmlspecialchars($client_row['client_name']) . '</option>';
                        }
                        ?>
                        <option value="other" <?php echo (isset($form_data['original_client']) && $form_data['original_client'] == 'other') ? 'selected' : ''; ?>>Other: (to fill blank)</option>
                    </select>
                    <input type="text" name="client_other" id="client_other" class="other-input" style="font-size: 13px; padding: 10px; margin-top: 10px;" placeholder="Enter new client name" value="<?php echo isset($form_data['client_other']) ? htmlspecialchars($form_data['client_other']) : ''; ?>">
                </td>
            </tr>
            <tr>
                <td style="font-size: 13px;"><strong>Charge Code:</strong> <span class="required">*</span></td>
                <td><input type="text" name="charge_code" class="input-field" style="font-size: 13px; padding: 10px;" required value="<?php echo isset($form_data['charge_code']) ? htmlspecialchars($form_data['charge_code']) : ''; ?>"></td>
                <td style="font-size: 13px;"><strong>Project:</strong> <span class="required">*</span></td>
                <td>
                    <select name="project" id="project" class="input-field" style="font-size: 13px; padding: 10px;" required onchange="toggleOtherInput(this, 'project_other')">
                        <option value="" disabled selected>Select Project</option>
                        <?php
                        $project_sql = "SELECT * FROM project WHERE project_name != 'other' ORDER BY project_name";
                        $project_result = $conn->query($project_sql);
                        if ($project_result->num_rows > 0) {
                            while ($project_row = $project_result->fetch_assoc()) {
                                $selected = (isset($form_data['original_project']) && $form_data['original_project'] == $project_row['project_name']) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($project_row['project_name']) . '" ' . $selected . '>' . htmlspecialchars($project_row['project_name']) . '</option>';
                            }
                        }
                        ?>
                        <option value="other" <?php echo (isset($form_data['original_project']) && $form_data['original_project'] == 'other') ? 'selected' : ''; ?>>Other: (to fill blank)</option>
                    </select>
                    <input type="text" name="project_other" id="project_other" class="other-input" style="font-size: 13px; padding: 10px; margin-top: 10px;" placeholder="Enter new project name" value="<?php echo isset($form_data['project_other']) ? htmlspecialchars($form_data['project_other']) : ''; ?>">
                </td>
            </tr>
            <tr>
                <td style="font-size: 13px;"><strong>Location:</strong> <span class="required">*</span></td>
                <td colspan="3">
                    <select name="location" id="location" class="input-field" style="width: 50%; font-size: 13px; padding: 10px;" required onchange="toggleOtherInput(this, 'location_other')">
                        <option value="" disabled selected>Select Location</option>
                        <?php
                        $location_sql = "SELECT * FROM location WHERE location_name != 'other' ORDER BY location_name";
                        $location_result = $conn->query($location_sql);
                        if ($location_result->num_rows > 0) {
                            while ($location_row = $location_result->fetch_assoc()) {
                                $selected = (isset($form_data['original_location']) && $form_data['original_location'] == $location_row['location_name']) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($location_row['location_name']) . '" ' . $selected . '>' . htmlspecialchars($location_row['location_name']) . '</option>';
                            }
                        }
                        ?>
                        <option value="other" <?php echo (isset($form_data['original_location']) && $form_data['original_location'] == 'other') ? 'selected' : ''; ?>>Other: (to fill blank)</option>
                    </select>
                    <input type="text" name="location_other" id="location_other" class="other-input" style="width: 50%; font-size: 13px; padding: 10px; margin-top: 10px;" placeholder="Enter new location" value="<?php echo isset($form_data['location_other']) ? htmlspecialchars($form_data['location_other']) : ''; ?>">
                </td>
            </tr>
        </table>

        <!-- Add Item Section -->
        <div class="add-item-section" style="transform: scale(1.02); padding: 30px;">
            <h5 style="font-size: 16px;"><i class="fas fa-plus-circle"></i> Add New Item</h5>
            <div class="row">
                <div class="col-md-5">
                    <label style="font-size: 14px;"><strong>Description:</strong></label>
                    <select id="item_description" class="form-control" style="font-size: 13px; padding: 12px;" onchange="fetchSizes()">
                        <option value="">Select Description</option>
                        <?php
                        $desc_sql = "SELECT DISTINCT description FROM inventory_product WHERE description IS NOT NULL AND description != '' ORDER BY description";
                        $desc_result = $conn->query($desc_sql);
                        while ($desc_row = $desc_result->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($desc_row['description']) . '">' . htmlspecialchars($desc_row['description']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label style="font-size: 14px;"><strong>Size:</strong></label>
                    <select id="item_size" class="form-control" style="font-size: 13px; padding: 12px;">
                        <option value="">Select Size</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-primary" onclick="addItem()" style="width: 100%; background: #0062a9; font-size: 14px; padding: 12px;">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="item-list" style="transform: scale(1.02); font-size: 13px;">
            <thead>
                <tr>
                    <th style="font-size: 12px; padding: 15px 12px;">NO</th>
                    <th style="font-size: 12px; padding: 15px 12px;">Description</th>
                    <th style="font-size: 12px; padding: 15px 12px;">Size</th>
                    <th style="font-size: 12px; padding: 15px 12px;">UOM</th>
                    <th style="font-size: 12px; padding: 15px 12px;">Floor</th>
                    <th style="font-size: 12px; padding: 15px 12px;">Bay</th>
                    <th style="font-size: 12px; padding: 15px 12px;">Location</th>
                    <th style="font-size: 12px; padding: 15px 12px;">Current Stock</th>
                    <th style="font-size: 12px; padding: 15px 12px;">Request Qty</th>
                    <th style="font-size: 12px; padding: 15px 12px;">Remarks</th>
                    <th class="action-delete" style="font-size: 12px; padding: 15px 12px;">Action</th>
                </tr>
            </thead>
            <tbody id="item-body">
                <?php if (!empty($form_data) && !empty($form_data['descriptions'])): ?>
                    <?php foreach ($form_data['descriptions'] as $index => $description): ?>
                    <tr>
                        <td style="font-size: 13px; padding: 12px;"><strong><?php echo $index + 1; ?></strong></td>
                        <td style="font-size: 13px; padding: 12px;"><?php echo htmlspecialchars($description); ?></td>
                        <td style="font-size: 13px; padding: 12px;"><?php echo htmlspecialchars($form_data['sizes'][$index] ?? '-'); ?></td>
                        <td style="font-size: 13px; padding: 12px;"><?php echo htmlspecialchars($form_data['uoms'][$index] ?? ''); ?></td>
                        <td style="font-size: 13px; padding: 12px;"><?php echo htmlspecialchars($form_data['floors'][$index] ?? ''); ?></td>
                        <td style="font-size: 13px; padding: 12px;"><?php echo htmlspecialchars($form_data['bays'][$index] ?? ''); ?></td>
                        <td style="font-size: 13px; padding: 12px;"><?php echo htmlspecialchars($form_data['location_codes'][$index] ?? ''); ?></td>
                        <td style="font-size: 13px; padding: 12px;"><span style="font-weight: 600; color: #0062a9;"><?php echo htmlspecialchars($form_data['current_stocks'][$index] ?? 0); ?></span></td>
                        <td style="padding: 12px;"><input type="number" name="request_quantity[]" class="input-field" style="font-size: 13px; padding: 10px;" min="1" max="<?php echo $form_data['current_stocks'][$index] ?? 0; ?>" required value="<?php echo htmlspecialchars($form_data['req_qtys'][$index] ?? ''); ?>"></td>
                        <td style="padding: 12px;"><input type="text" name="remarks[]" class="input-field" style="font-size: 13px; padding: 10px;" placeholder="-" value="<?php echo htmlspecialchars($form_data['remarks'][$index] ?? ''); ?>"></td>
                        <td class="action-delete" style="padding: 12px;">
                            <button type="button" class="btn-remove" style="width: 36px; height: 36px; font-size: 16px;" onclick="removeRow(this)">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                    <input type="hidden" name="description[]" value="<?php echo htmlspecialchars($description); ?>">
                    <input type="hidden" name="size[]" value="<?php echo htmlspecialchars($form_data['sizes'][$index] ?? ''); ?>">
                    <input type="hidden" name="uom[]" value="<?php echo htmlspecialchars($form_data['uoms'][$index] ?? ''); ?>">
                    <input type="hidden" name="floor[]" value="<?php echo htmlspecialchars($form_data['floors'][$index] ?? ''); ?>">
                    <input type="hidden" name="bay[]" value="<?php echo htmlspecialchars($form_data['bays'][$index] ?? ''); ?>">
                    <input type="hidden" name="location_code[]" value="<?php echo htmlspecialchars($form_data['location_codes'][$index] ?? ''); ?>">
                    <input type="hidden" name="current_stock[]" value="<?php echo htmlspecialchars($form_data['current_stocks'][$index] ?? 0); ?>">
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="btn-container" style="transform: scale(1.05);">
            <button type="button" class="btn" style="font-size: 14px; padding: 12px 25px;" onclick="printForm()">
                <i class="fas fa-print"></i> Print
            </button>
            <button type="submit" class="btn" name="save_to_session" value="1" style="background: #28a745; font-size: 14px; padding: 12px 25px;">
                <i class="fas fa-eye"></i> Review Request
            </button>
        </div>
        
        <p class="note" style="font-size: 13px; padding: 18px 25px;">
            <i class="fas fa-exclamation-circle"></i>
            **For project request, please submit 5 days before the required date.
        </p>
    </div>
</form>
    </div>

    <script>
        let currentRowNumber = <?php echo !empty($form_data) ? count($form_data['descriptions'] ?? []) : 0; ?>;
        let items = <?php echo json_encode($form_data['descriptions'] ?? []); ?>;

        $(document).ready(function() {
            <?php if (!empty($form_data)): ?>
            // Show other inputs if they were selected
            <?php if (isset($form_data['original_client']) && $form_data['original_client'] == 'other'): ?>
            document.getElementById('client_other').style.display = 'block';
            <?php endif; ?>
            
            <?php if (isset($form_data['original_project']) && $form_data['original_project'] == 'other'): ?>
            document.getElementById('project_other').style.display = 'block';
            <?php endif; ?>
            
            <?php if (isset($form_data['original_location']) && $form_data['original_location'] == 'other'): ?>
            document.getElementById('location_other').style.display = 'block';
            <?php endif; ?>
            <?php endif; ?>
        });

        function fetchSizes() {
            const description = $('#item_description').val();
            if (description) {
                $.ajax({
                    url: 'fetch_sizes.php',
                    type: 'POST',
                    data: { description: description },
                    success: function(response) {
                        $('#item_size').html(response);
                    }
                });
            }
        }

        function addItem() {
            const description = $('#item_description').val();
            const size = $('#item_size').val();
            
            if (!description) {
                alert('Please select description');
                return;
            }
            
            const selectedSize = size ? size : '';
            
            $.ajax({
                url: 'fetch_item_details.php',
                type: 'POST',
                data: { description: description, size: selectedSize },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.success) {
                        currentRowNumber++;
                        
                        const displaySize = data.size ? data.size : '-';
                        
                        const newRow = `
                            <tr>
                                <td><strong>${currentRowNumber}</strong></td>
                                <td>${data.description}</td>
                                <td>${displaySize}</td>
                                <td>${data.uom_name}</td>
                                <td>${data.floor}</td>
                                <td>${data.bay}</td>
                                <td>${data.location}</td>
                                <td><span style="font-weight: 600; color: #0062a9;">${data.current_stock}</span></td>
                                <td><input type="number" name="request_quantity[]" class="input-field" min="1" max="${data.current_stock}" required></td>
                                <td><input type="text" name="remarks[]" class="input-field" placeholder="-"></td>
                                <td class="action-delete">
                                    <button type="button" class="btn-remove" onclick="removeRow(this)">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        
                        const hiddenFields = `
                            <input type="hidden" name="description[]" value="${data.description}">
                            <input type="hidden" name="size[]" value="${data.size ? data.size : ''}">
                            <input type="hidden" name="uom[]" value="${data.uom_name}">
                            <input type="hidden" name="floor[]" value="${data.floor}">
                            <input type="hidden" name="bay[]" value="${data.bay}">
                            <input type="hidden" name="location_code[]" value="${data.location}">
                            <input type="hidden" name="current_stock[]" value="${data.current_stock}">
                        `;
                        
                        $('#item-body').append(newRow + hiddenFields);
                        
                        items.push({
                            description: data.description,
                            size: data.size,
                            uom: data.uom_name,
                            floor: data.floor,
                            bay: data.bay,
                            location: data.location,
                            current_stock: data.current_stock
                        });
                        
                        $('#item_description').val('');
                        $('#item_size').html('<option value="">Select Size</option>');
                    } else {
                        alert('Item not found in inventory');
                    }
                }
            });
        }

        function removeRow(button) {
            const row = $(button).closest('tr');
            const rowIndex = row.index() / 2; // Karena ada hidden fields
            
            items.splice(rowIndex, 1);
            row.nextAll('input[type="hidden"]:lt(7)').remove(); // Remove hidden fields
            row.remove(); // Remove the visible row
            
            $('#item-body tr').each(function(index) {
                if ($(this).find('td').length > 0) { // Only renumber visible rows
                    $(this).find('td:first').html(`<strong>${index + 1}</strong>`);
                }
            });
            
            currentRowNumber = items.length;
        }

        function printForm() {
            window.print();
        }

        function toggleOtherInput(selectElement, inputId) {
            const otherInput = document.getElementById(inputId);
            if (selectElement.value === 'other') {
                otherInput.style.display = 'block';
                otherInput.required = true;
            } else {
                otherInput.style.display = 'none';
                otherInput.required = false;
                otherInput.value = '';
            }
        }

        function validateForm() {
            if ($('#item-body tr').length === 0) {
                alert('Please add at least one item');
                return false;
            }
            
            const requiredFields = ['date_required', 'charge_code', 'client', 'project', 'location'];
            for (const field of requiredFields) {
                const element = document.querySelector(`[name="${field}"]`);
                if (!element.value.trim()) {
                    alert(`Please fill in the ${field.replace('_', ' ')} field`);
                    element.focus();
                    return false;
                }
            }
            
            if (document.getElementById('client').value === 'other') {
                const otherField = document.getElementById('client_other');
                if (!otherField.value.trim()) {
                    alert('Please enter the new client name');
                    otherField.focus();
                    return false;
                }
            }
            
            if (document.getElementById('project').value === 'other') {
                const otherField = document.getElementById('project_other');
                if (!otherField.value.trim()) {
                    alert('Please enter the new project name');
                    otherField.focus();
                    return false;
                }
            }
            
            if (document.getElementById('location').value === 'other') {
                const otherField = document.getElementById('location_other');
                if (!otherField.value.trim()) {
                    alert('Please enter the new location');
                    otherField.focus();
                    return false;
                }
            }
            
            let validQuantities = true;
            
            $('input[name="request_quantity[]"]').each(function() {
                const qty = parseInt($(this).val());
                const max = parseInt($(this).attr('max'));
                
                if (!qty || qty < 1) {
                    alert('Please enter valid request quantities for all items (minimum 1)');
                    validQuantities = false;
                    return false;
                }
                
                if (qty > max) {
                    alert(`Request quantity cannot exceed current stock (max: ${max})`);
                    validQuantities = false;
                    return false;
                }
            });
            
            if (!validQuantities) {
                return false;
            }
            
            return true;
        }

        function getCurrentDate() {
            const today = new Date();
            const day = String(today.getDate()).padStart(2, '0');
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const year = today.getFullYear();
            return `${day}/${month}/${year}`;
        }

        document.getElementById('dateLabel').textContent = getCurrentDate();
    </script>
</body>
</html>