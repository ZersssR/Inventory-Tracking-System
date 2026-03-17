<?php
// Include the database connection
include 'database.php';

// Include PHPMailer files
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['selected_items']) && !empty($_POST['new_location'])) {
    $selected_items = $_POST['selected_items'];
    $new_location = htmlspecialchars(trim($_POST['new_location']));

    // Start a transaction for data integrity
    $conn->begin_transaction();

    try {
        // Prepare SQL statement to update loadout location for selected items
        $stmt = $conn->prepare("UPDATE product SET loadout_location = ? WHERE unit_id = ?");

        if ($stmt) {
            $stmt->bind_param("ss", $new_location, $unit_id);

            // Collect details for email body
            $updated_items = [];

            foreach ($selected_items as $unit_id) {
                // Sanitize each unit_id before use
                $unit_id = htmlspecialchars(trim($unit_id));
                if ($stmt->execute()) {
                    // Collect the item for email body if update is successful
                    $updated_items[] = $unit_id;
                } else {
                    throw new Exception("Failed to update item with Unit ID: " . $unit_id);
                }
            }

            // Commit the transaction after all updates succeed
            $conn->commit();

            // Prepare the list of updated items for email body
            $item_list_html = '<ul>';
            foreach ($updated_items as $item) {
                $item_list_html .= '<li>' . htmlspecialchars($item) . '</li>';
            }
            $item_list_html .= '</ul>';

            // Send email notification after successful update
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


                // Email content
                $mail->isHTML(true);
                $mail->Subject = 'Loadout Location Updated for Selected Items';
                $mail->Body = '
                    <p>The loadout location has been successfully updated to: <strong>' . $new_location . '</strong> for the following items:</p>
                    ' . $item_list_html;

                // Send the email
                $mail->send();
                        ?>
                        <!DOCTYPE html>
                        <html lang="en">
                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <title>Email Sent</title>
                            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                            <style>
                                /* Custom Styling */
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
                                        <p>The email has been successfully sent to admin staff to notify about the location changes. </p>
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
                // Roll back the transaction if email fails
                $conn->rollback();
                
                // Display error if email fails
                echo "<script>
                        alert('Loadout location updated, but email notification failed: " . addslashes($mail->ErrorInfo) . "');
                        window.location.href = 'customer_dashboard.php';
                      </script>";
            }
        } else {
            throw new Exception("Error preparing the SQL statement: " . $conn->error);
        }
    } catch (Exception $e) {
        // Roll back the transaction if any error occurs
        $conn->rollback();

        // Display an error message and redirect
        echo "<script>
                alert('An error occurred: " . addslashes($e->getMessage()) . "');
                window.location.href = 'updateLocation.php';
              </script>";
    } finally {
        // Close the prepared statement
        if (isset($stmt)) {
            $stmt->close();
        }
    }
} else {
    // Prompt user to select at least one item and a location if form submission is invalid
    echo "<script>
            alert('Please select at least one item and a new location.');
            window.location.href = 'updateLocation.php';
          </script>";
}

// Close database connection
$conn->close();
?>
