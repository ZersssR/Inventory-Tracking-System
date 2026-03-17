<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Requisition</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none;
            }
        }
        table, th, td {
            border: 1px solid black;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        .remove-row {
            color: red;
            cursor: pointer;
        }
        .box-container {
            margin-bottom: 20px;
            padding: 15px;
        }
        .box-container .card-body {
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
        .signature-label {
            margin-top: 10px;
            display: block;
            text-align: center;
        }
        .priority-section {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .priority-section .form-check {
            margin-top: 10px;
        }
        .priority-section .form-check-input {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Service Requisition Form</h1>
        <img src="eog.JPG" alt="EPIC_OG Logo" class="mb-4" style="width: 150px;">
        <form id="serviceRequisitionForm" action="print_service_requisition.php" method="POST">
            
            <!-- BOX CONTAINER 1 -->
            <div class="box-container">
                <div class="card">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col">
                                <label for="client" class="form-label">Client</label>
                                <input type="text" class="form-control" id="client" name="client" >
                            </div>
                            <div class="col">
                                <label for="srNumber" class="form-label">SR Number</label>
                                <input type="text" class="form-control" id="srNumber" name="srNumber" >
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label for="projectTitle" class="form-label">Project Title</label>
                                <input type="text" class="form-control" id="projectTitle" name="projectTitle">
                            </div>
                            <div class="col">
                                <label for="typeOfPurchase" class="form-label">Type of Purchase</label>
                                <input type="text" class="form-control" id="typeOfPurchase" name="typeOfPurchase">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label for="personInCharge" class="form-label">Person in Charge</label>
                                <input type="text" class="form-control" id="personInCharge" name="personInCharge">
                            </div>
                            <div class="col">
                                <label for="deliveryPoint" class="form-label">Delivery Point</label>
                                <input type="text" class="form-control" id="deliveryPoint" name="deliveryPoint">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- BOX CONTAINER 2 -->
            <div class="box-container">
                <div class="card">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col priority-section">
                                <label class="form-label">Priority Level Request</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="priorityLevel" id="normalRequest" value="Normal Request">
                                    <label class="form-check-label" for="normalRequest">
                                        Normal Request
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="priorityLevel" id="urgentRequest" value="Urgent Request">
                                    <label class="form-check-label" for="urgentRequest">
                                        Urgent Request
                                    </label>
                                </div>
                            </div>
                            <div class="col">
                                <label for="chargeCode" class="form-label">Charge Code</label>
                                <input type="text" class="form-control" id="chargeCode" name="chargeCode">
                            </div>
                            <div class="col">
                                <label for="AFS/WO" class="form-label">AFS/WO</label>
                                <input type="text" class="form-control" id="AFS/WO" name="AFS/WO">
                            </div>
                            <div class="col">
                                <label for="costCentre" class="form-label">Cost Centre</label>
                                <input type="text" class="form-control" id="costCentre" name="costCentre">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BOX CONTAINER 3 -->
            <div class="box-container">
                <div class="card">
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Item Description</th>
                                    <th>Size</th>
                                    <th>Duration</th>
                                    <th>Qty</th>
                                    <th>UOM</th>
                                    <th>PG</th>
                                    <th>Remark</th>
                                    <th class="no-print">Remove</th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody">
                                <tr>
                                    <td><input type="text" class="form-control" name="no[]"></td>
                                    <td><input type="text" class="form-control" name="itemDescription[]"></td>
                                    <td><input type="text" class="form-control" name="size[]"></td>
                                    <td><input type="text" class="form-control" name="duration[]"></td>
                                    <td><input type="number" class="form-control" name="qty[]"></td>
                                    <td><input type="text" class="form-control" name="uom[]"></td>
                                    <td><input type="text" class="form-control" name="pg[]"></td>
                                    <td><input type="text" class="form-control" name="remark[]"></td>
                                    <td class="no-print text-center"><span class="remove-row">&times;</span></td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-secondary no-print" onclick="addItemRow()">Add Row</button>
                    </div>
                </div>
            </div>

            <!-- BOX CONTAINER 4 -->
            <div class="box-container">
                <div class="card">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="preparedBy" class="form-label">Prepared by</label>
                                <div class="signature-box" id="preparedBy"></div>
                                <span class="signature-label"></span>
                            </div>
                            <div class="col-md-3">
                                <label for="reviewedBy" class="form-label">Reviewed by</label>
                                <div class="signature-box" id="reviewedBy"></div>
                                <span class="signature-label"></span>
                            </div>
                            <div class="col-md-3">
                                <label for="checkedBy" class="form-label">Checked by</label>
                                <div class="signature-box" id="checkedBy"></div>
                                <span class="signature-label"></span>
                            </div>
                            <div class="col-md-3">
                                <label for="approvedBy" class="form-label">Approved by</label>
                                <div class="signature-box" id="approvedBy"></div>
                                <span class="signature-label"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-section">
                <button type="submit" class="btn btn-primary no-print">Print</button>
                <button type="submit" class="btn btn-success no-print">Save</button>
                <button type="button" class="btn btn-warning no-print" onclick="editForm()">Edit</button>
                <button type="reset" class="btn btn-danger no-print">Reset</button>
            </div>
        </form>
    </div>

    <script>
        function addItemRow() {
            const tableBody = document.getElementById('itemsTableBody');
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><input type="text" class="form-control" name="no[]"></td>
                <td><input type="text" class="form-control" name="itemDescription[]"></td>
                <td><input type="text" class="form-control" name="size[]"></td>
                <td><input type="text" class="form-control" name="duration[]"></td>
                <td><input type="number" class="form-control" name="qty[]"></td>
                <td><input type="text" class="form-control" name="uom[]"></td>
                <td><input type="text" class="form-control" name="pg[]"></td>
                <td><input type="text" class="form-control" name="remark[]"></td>
                <td class="no-print text-center"><span class="remove-row">&times;</span></td>
            `;
            tableBody.appendChild(newRow);
        }

        document.getElementById('itemsTableBody').addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-row')) {
                e.target.closest('tr').remove();
            }
        });

        function editForm() {
            const form = document.getElementById('serviceRequisitionForm');
            const formElements = form.elements;
            for (let i = 0; i < formElements.length; i++) {
                formElements[i].disabled = false;
            }
        }

        window.addEventListener('beforeunload', function (event) {
            // Trigger AJAX request to log the user out
            navigator.sendBeacon('logout.php');
        });
    </script>
</body>
</html>
