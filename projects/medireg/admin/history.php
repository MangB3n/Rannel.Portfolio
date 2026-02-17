<?php
// Database connection

use function PHPSTORM_META\sql_injection_subst;

$servername = "localhost";
$username = "root";  
$password = "";      
$dbname = "medireg";

// Create connection
$conn = new mysqli("localhost", "root", "", "medireg");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT p.first_name, p.middle_name, p.last_name, p.date_of_birth, p.contact_number, p.priority_status, p.created_at
        FROM patients p";
$result = $conn->query($sql);
?>


<!DOCTYPE html>
<html>
<head>
  <title>Patient History - MediReg</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" media="screen">
  <style>
    .navbar {
            background-color: #0A2A4D;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 8vh;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
  
        .logo img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            height: 53px;
            width: 195px;
        }
        .nav-links {
            list-style: none;
            display: flex;
            gap: 20px;
        }
  
        .nav-links li a {
            font-size: 1.1rem;
            color: white;
            text-decoration: none;
            font-weight: bold;
            padding: 5px;
        }
  
        .nav-links li a:hover {
            text-decoration: underline;
        }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar">
    <div class="logo"> 
    <img src="../images/medlog.png" alt="Medireg Logo" style="height: 53px; width: 195px;">
    </div>
    <ul class="nav-links">
      <li><a href="../homepage.html">Home</a></li>
      <li><a href="../admin/dashboard.php">Dashboard</a></li>
      <li><a href="../admin/display.php">Queue Display</a></li>
      <li><a href="../admin/history.php">Patient History</a></li>
      <li><a href="../admin/logout.php">Logout</a></li>
    </ul>
  </nav>

  <!-- Page Content -->
  <div class="max-w-5xl mx-auto bg-white p-6 rounded shadow mt-6">
    <h1 class="text-3xl font-bold mb-6">Patient History</h1>
    <table class="w-full table-auto border-collapse text-sm">
      <thead class="bg-blue-200">
        <tr>
          <th class="border p-2">Name</th>
          <th class="border p-2">Date of Birth</th>
          <th class="border p-2">Contact #</th>
          <th class="border p-2">Priority</th>
          <th class="border p-2">Registered On</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr class="hover:bg-blue-50 text-center">
          <td class="border p-2">
            <?= htmlspecialchars($row['last_name']) . ', ' . htmlspecialchars($row['first_name']) . ' ' . htmlspecialchars($row['middle_name']) ?>
          </td>
          <td class="border p-2"><?= htmlspecialchars($row['date_of_birth']) ?></td>
          <td class="border p-2"><?= htmlspecialchars($row['contact_number']) ?></td>
          <td class="border p-2"><?= htmlspecialchars($row['priority_status']) ?></td>
          <td class="border p-2"><?= date("M d, Y H:i", strtotime($row['created_at'])) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</body>
</html>

<?php $conn->close(); ?>