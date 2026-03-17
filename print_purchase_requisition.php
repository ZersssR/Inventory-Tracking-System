<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Purchase Requisition</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        table, th, td {
            
            border: 1px solid black;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            text-align: left;
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
        .signature-label {
            margin-top: 10px;
            display: block;
            text-align: center;
        }
        @media print {
            .no-print {
                display: none;
            }
            .avoid-page-break {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Purchase Requisition Form</h1>
        <img src="eog.JPG" alt="EPIC_OG Logo" class="mb-4" style="width: 150px;">
        
        <div class="box-container">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label"><strong>Client</strong></label>
                            <p><?= htmlspecialchars($_POST['client']); ?></p>
                        </div>
                        <div class="col">
                            <label class="form-label"><strong>PR Number</strong></label>
                            <p><?= htmlspecialchars($_POST['prNumber']); ?></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label"><strong>Project Title</strong></label>
                            <p><?= htmlspecialchars($_POST['projectTitle']); ?></p>
                        </div>
                        <div class="col">
                            <label class="form-label"><strong>Type of Purchase</strong></label>
                            <p><?= htmlspecialchars($_POST['typeOfPurchase']); ?></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label"><strong>Person in Charge</strong></label>
                            <p><?= htmlspecialchars($_POST['personInCharge']); ?></p>
                        </div>
                        <div class="col">
                            <label class="form-label"><strong>Delivery Point</strong></label>
                            <p><?= htmlspecialchars($_POST['deliveryPoint']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="box-container">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label"><strong>Priority Level Request</strong></label>
                            <p><?= htmlspecialchars($_POST['priorityLevel']); ?></p>
                        </div>
                        <div class="col">
                            <label class="form-label"><strong>Charge Code</strong></label>
                            <p><?= htmlspecialchars($_POST['chargeCode']); ?></p>
                        </div>
                        <div class="col">
                            <label class="form-label"><strong>AFS/WO</strong></label>
                            <p><?= htmlspecialchars($_POST['AFS/WO']); ?></p>
                        </div>
                        <div class="col">
                            <label class="form-label"><strong>Cost Centre</strong></label>
                            <p><?= htmlspecialchars($_POST['costCentre']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="box-container avoid-page-break">
            <div class="card">
                <div class="card-body">
                    <table class="table table-bordered">
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
            </div>
        </div>

        <div class="box-container avoid-page-break">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label"><strong>Prepared by</strong></label>
                            <div class="signature-box"></div>
                            <span class="signature-label"></span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><strong>Reviewed by</strong></label>
                            <div class="signature-box"></div>
                            <span class="signature-label"></span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><strong>Checked by</strong></label>
                            <div class="signature-box"></div>
                            <span class="signature-label"></span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><strong>Approved by</strong></label>
                            <div class="signature-box"></div>
                            <span class="signature-label"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="button" class="btn btn-primary no-print" onclick="window.print()">Print</button>
    </div>
</body>
</html>
