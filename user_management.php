<?php
session_start();
include 'database.php'; // Ensure database connection is included

// Ensure session variables exist before using them
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

// Use $_SESSION['username'] safely
$username = isset($_SESSION['username']) ? $_SESSION['username'] : "Unknown User"; 

// Fetch username from database
$query = "SELECT username FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    $username = $user['username']; // Assign username
} else {
    $username = "Unknown User"; // Fallback if user not found
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="shortcut icon" type="x-icon" href="its2.png">
    <title>Admin IT Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to right, #e0eafc, #cfdef3);
            color: #333;
            padding: 20px;
            font-size: 12px;
        }
        .container {
            width: 80%;
            max-width: 1100px;
            padding: 30px; /* Reduced padding */
            margin: 0 auto; /* Centering */
            margin-left: 300px; /* Adjust according to sidebar width (sidebar is 250px, add some space) */
            max-height: max-content;
            background-color: rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border-radius: 10px;
        }
        #sidebar {
            height: 100vh; /* Ensures full viewport height */
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #1d3557;
            color: white;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 1000; /* Ensures it stays above other content */
            border-radius: 12px;
        }

        #sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;  /* Rounded corners */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }


        #sidebar-header h2 {
        margin: 0;
        font-size: 12px;
        font-weight: bold;
        color: #a8dadc;
        }

        #sidebar ul {
        list-style-type: none;
        padding: 0;
        margin: 0;
        }

        #sidebar ul li {
        padding: 15px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        transition: background-color 0.3s ease;
        }

        #sidebar ul li:hover {
        background-color: #457b9d;
        }

        #sidebar ul li a {
        color: #f1faee;
        text-decoration: none;
        display: flex;
        align-items: center;
        font-size: 12px;
        font-weight: 500;
        transition: color 0.3s ease;
        }

        #sidebar ul li a:hover {
        color: #a8dadc;
        }

        #sidebar ul li i {
        font-size: 12px;
        margin-right: 10px;
        color: #a8dadc;
        transition: color 0.3s ease;
        }

        #sidebar ul li:hover i {
        color: white;
        }
        .card {
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            background: #fff;
        }

        th, td {
            border: 1px solid #1f1f1f;
            padding: 15px;
            text-align: left;
            font-size: 12px;
            color: #555;
            border: 1px solid #000; 
        }

        th {
            background-color: #345d9d;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 500;
        }
        td {
            background-color: #f9f9f9;
        }
        tr:nth-child(even) td {
            background-color: #deeafa
        }
        tr:hover td {
            background-color:#8fa3bf; /* Highlight color on hover */
        }
        a {
            color: #000000; /* Black color */
            text-decoration: none;
        }

        .btn-primary, .btn-danger {
            border-radius: 5px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 40px 20px;
            background: #f0e896;
            color: black;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .logo img {
            height: 50px;
            margin-right: 15px;
        }
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            color: #ffffff;
            background-color: #1c3b66;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background-color: #2980b9;
            box-shadow: 0 4px 8px rgba(41, 128, 185, 0.2);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div id="sidebar">
        <div id="sidebar-header">
            <h2><i class="fa fa-bars"></i> Menu</h2>
        </div>
            <ul>
                <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="user_management.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>            
            </ul>
        </div>

    <div class="container">
        <a href="adminIT.php" class="back-btn">Back</a>
            <h2 class="text-center mb-4">User Management</h2>
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    include 'database.php';
                    $query = "SELECT * FROM users";
                    $result = $conn->query($query);

                    $counter = 1; // Start counting from 1

                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $userType = $row['userType'] === 'adminIT' ? 'Admin IT' : ($row['userType'] === 'adminStaff' ? 'Admin Staff' : 'User');
                            echo "<tr>";
                            echo "<td>" . $counter . "</td>"; // Display incremental number
                            echo "<td>" . $row['username'] . "</td>";
                            echo "<td>" . $row['email'] . "</td>";
                            echo "<td>" . $userType . "</td>";
                            echo "<td>
                                    <button class='btn btn-primary btn-sm' onclick='openEditModal(" . $row['user_id'] . ", \"" . $row['username'] . "\", \"" . $row['userType'] . "\")'>Edit</button>
                                    <button class='btn btn-danger btn-sm' onclick='deleteUser(" . $row['user_id'] . ")'>Delete</button>
                                </td>";
                            echo "</tr>";
                    
                            $counter++; // Increment the counter
                        }
                    } else {
                        echo "<tr><td colspan='4'>No users found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="editUserId">
                        <div class="mb-3">
                            <label for="editUsername" class="form-label">Username:</label>
                            <input type="text" id="editUsername" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="editRole" class="form-label">Role:</label>
                            <select id="editRole" class="form-select">
                                <option value="adminIT">Admin IT</option>
                                <option value="adminStaff">Admin Staff</option>
                                <option value="client">User</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="saveUser()">Save</button>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openEditModal(userId, username, userType) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUsername').value = username;
            document.getElementById('editRole').value = userType;
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }

        function saveUser() {
            const userId = document.getElementById('editUserId').value;
            const username = document.getElementById('editUsername').value;
            const userType = document.getElementById('editRole').value;

            fetch('update_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `user_id=${userId}&username=${username}&userType=${userType}`
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                location.reload(); // Refresh the page after updating
            })
            .catch(error => console.error('Error:', error));

            closeEditModal();
        }

        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                fetch('delete_user.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `user_id=${userId}`
                })
                .then(response => response.text())
                .then(data => {
                    alert(data);
                    location.reload(); // Refresh the page after deletion
                })
                .catch(error => console.error('Error:', error));
            }
        }
        function updateClock() {
            let now = new Date();
            let hours = now.getHours().toString().padStart(2, '0');
            let minutes = now.getMinutes().toString().padStart(2, '0');
            let seconds = now.getSeconds().toString().padStart(2, '0');
            document.getElementById('clock').innerText = `${hours}:${minutes}:${seconds}`;
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>
