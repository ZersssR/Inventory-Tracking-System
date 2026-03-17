<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Purchase Requisition</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .header img {
            position: absolute;
            left: 0;
            width: 150px;
        }

        .header h1 {
            margin: 0 auto;
            text-align: center;
            flex-grow: 1;
            font-size: 24px;
            font-weight: bold;
        }

        table, th, td {
            border: 1px solid black;
            border-collapse: collapse;
        }

        th, td {
            padding: 8px;
            text-align: left;
        }

        .box-container {
            margin-bottom: 20px;
            padding: 15px;
        }

        .signature-box {
            border: 1px solid #000;
            width: 100%;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #aaa;
            margin-top: 5px;
        }

        .form-section {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <div class="container mt-4">
        <div class="header mb-5">
            <img src="eog.JPG" alt="EPIC_OG Logo">
            <h1>Purchase Requisition Form</h1>
        </div>

        <!-- Request Details Section -->
        <div class="form-section">
            <table class="table">
                <tr>
                    <th>Client</th>
                    <td><?= htmlspecialchars($_POST['client']); ?></td>
                    <th>PR Number</th>
                    <td><?= htmlspecialchars($_POST['prNumber']); ?></td>
                </tr>
                <tr>
                    <th>Project Title</th>
                    <td><?= htmlspecialchars($_POST['projectTitle']); ?></td>
                    <th>Type of Purchase</th>
                    <td><?= htmlspecialchars($_POST['typeOfPurchase']); ?></td>
                </tr>
                <tr>
                    <th>Person in Charge</th>
                    <td><?= htmlspecialchars($_POST['personInCharge']); ?></td>
                    <th>Delivery Point</th>
                    <td><?= htmlspecialchars($_POST['deliveryPoint']); ?></td>
                </tr>
                <tr>
                    <th>Charge Code</th>
                    <td><?= htmlspecialchars($_POST['chargeCode']); ?></td>
                    <th>AFS/WO</th>
                    <td><?= htmlspecialchars($_POST['AFS/WO']); ?></td>
                </tr>
                <tr>
                    <th>Priority Level Request</th>
                    <td><?= htmlspecialchars($_POST['priorityLevel']); ?></td>
                    <th>Cost Centre</th>
                    <td><?= htmlspecialchars($_POST['costCentre']); ?></td>
                </tr>
            </table>
        </div>

        <!-- Items Section -->
        <div class="form-section">
            <table class="table">
                <thead>
                    <tr>
                        <th>Part ID</th>
                        <th>No</th>
                        <th>Item Description</th>
                        <th>Size</th>
                        <th>Qty</th>
                        <th>UOM</th>
                        <th>PG</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $partIds = $_POST['partId'];
                    $nos = $_POST['no'];
                    $itemDescriptions = $_POST['itemDescription'];
                    $sizes = $_POST['size'];
                    $qtys = $_POST['qty'];
                    $uoms = $_POST['uom'];
                    $pgs = $_POST['pg'];
                    $remarks = $_POST['remark'];

                    for ($i = 0; $i < count($partIds); $i++) {
                        echo "<tr>
                            <td>" . htmlspecialchars($partIds[$i]) . "</td>
                            <td>" . htmlspecialchars($nos[$i]) . "</td>
                            <td>" . htmlspecialchars($itemDescriptions[$i]) . "</td>
                            <td>" . htmlspecialchars($sizes[$i]) . "</td>
                            <td>" . htmlspecialchars($qtys[$i]) . "</td>
                            <td>" . htmlspecialchars($uoms[$i]) . "</td>
                            <td>" . htmlspecialchars($pgs[$i]) . "</td>
                            <td>" . htmlspecialchars($remarks[$i]) . "</td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Approval Section -->
        <div class="form-section">
            <table class="table">
                <tr>
                    <th>Prepared by</th>
                    <th>Reviewed by</th>
                    <th>Checked by</th>
                    <th>Approved by</th>
                </tr>
                <tr>
                    <td><div class="signature-box"></div></td>
                    <td><div class="signature-box"></div></td>
                    <td><div class="signature-box"></div></td>
                    <td><div class="signature-box"></div></td>
                </tr>
            </table>
        </div>

        <button type="button" class="btn btn-primary no-print" onclick="window.print()">Print</button>
    </div>

</body>
</html>
