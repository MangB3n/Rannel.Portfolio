<?php
require_once 'session_check.php';

// Database connection
$servername = "localhost";
$username = "root";  
$password = "";      
$dbname = "medireg";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get filter parameters
$department_filter = isset($_GET['department']) ? intval($_GET['department']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';

// Update queue status if action is performed
if (isset($_GET['action']) && isset($_GET['queue_id'])) {
    $action = $_GET['action'];
    $queue_id = intval($_GET['queue_id']);
    
    if ($action === 'serving') {
        $sql = "UPDATE queue SET status = 'Serving' WHERE id = ?";
    } elseif ($action === 'complete') {
        $sql = "UPDATE queue SET status = 'Completed' WHERE id = ?";
    } elseif ($action === 'noshow') {
        $sql = "UPDATE queue SET status = 'No-show' WHERE id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $queue_id);
    $stmt->execute();
    
    // Redirect to remove action from URL
    header("Location: " . $_SERVER['PHP_SELF'] . "?department=" . $department_filter . "&status=" . $status_filter . "&date=" . $date_filter . "&priority=" . $priority_filter);
    exit();
}

// Get departments for filter dropdown
$departments = [];
$sql = "SELECT id, department_name FROM departments";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $departments[$row["id"]] = $row["department_name"];
    }
}

// Fetch queue data with filters
$sql = "SELECT q.id, q.queue_number, q.status, q.created_at, 
        p.first_name, p.middle_name, p.last_name, p.contact_number, p.priority_status, p.id_image_path,
        d.department_name
        FROM queue q
        JOIN patients p ON q.patient_id = p.id
        JOIN departments d ON q.department_id = d.id
        WHERE 1=1";

// Apply filters
if ($department_filter > 0) {
    $sql .= " AND q.department_id = " . $department_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND q.status = '" . $conn->real_escape_string($status_filter) . "'";
}

if (!empty($date_filter)) {
    $sql .= " AND DATE(q.created_at) = '" . $conn->real_escape_string($date_filter) . "'";
}

if (!empty($priority_filter)) {
    $sql .= " AND p.priority_status = '" . $conn->real_escape_string($priority_filter) . "'";
}

$sql .= " ORDER BY 
          FIELD(q.status, 'Waiting', 'Serving', 'Completed', 'No-show'),
          FIELD(p.priority_status, 'Person With Disability (PWD)', 'Senior Citizen', 'None'),
          q.created_at ASC";

$result = $conn->query($sql);
$queue_items = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $queue_items[] = $row;
    }
}

// Queue statistics
$stats = [
    'total' => 0,
    'waiting' => 0,
    'serving' => 0,
    'completed' => 0,
    'noshow' => 0
];

$sql = "SELECT status, COUNT(*) as count FROM queue";
if (!empty($date_filter)) {
    $sql .= " WHERE DATE(created_at) = '" . $conn->real_escape_string($date_filter) . "'";
}
$sql .= " GROUP BY status";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $status = strtolower(str_replace('-', '', $row['status']));
        $stats[$status] = $row['count'];
        $stats['total'] += $row['count'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Medical Registration System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta http-equiv="refresh" content="10">
    <style>
        body {
            background-color: #f5f5f5;
            padding-bottom: 50px;
        }
        .navbar {
            background-color: #0A2A4D;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 10vh;
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
  
        .dashboard-container {
            margin-top: 20px;
        }
        .stats-card {
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .stat-item {
            padding: 15px;
            
            text-align: center;
            border-right: 1px solid #e0e0e0;
        }
        .stat-item:last-child {
            border-right: none;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
        }
        .stat-label {
            font-size: 14px;
            
            color: #666;
        }
    
        
        
        .queue-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-waiting {
            background-color: #ffeeba;
            color: #856404;
        }
        .status-serving {
            background-color: #b8daff;
            color: #004085;
        }
        .status-completed {
            background-color: #c3e6cb;
            color: #155724;
        }
        .status-noshow {
            background-color: #f5c6cb;
            color: #721c24;
            
        }
        .priority-pwd {
            background-color: red;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        .priority-senior {
            background-color:red; 
            color:white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        .priority-regular {
            background-color:rgb(0, 221, 255);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        .nav-pills .nav-link.active {
            background-color: #0d6efd;
        }
        
        .navbar {
        background-color: #0A2A4D;
        }



    </style>
</head>
<body>
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

    <div class="container dashboard-container">
        <div class="row mb-4">
            <div class="col-md-6">
                <h2>Queue Management</h2>
            </div>
            <div class="col-md-6 text-end">
                <a href="display.php" target="_blank" class="btn btn-success">Open Queue Display</a>
            </div>
        </div> 
        
        <!-- Statistics Cards -->
        <div class="row stats-card bg-white">
            <div class="col stat-item">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="col stat-item">
                <div class="stat-number"><?php echo $stats['waiting']; ?></div>
                <div class="stat-label">Waiting</div>
            </div>
            <div class="col stat-item">
                <div class="stat-number"><?php echo $stats['serving']; ?></div>
                <div class="stat-label">Serving</div>
            </div>
            <div class="col stat-item">
                <div class="stat-number"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="col stat-item">
                <div class="stat-number"><?php echo $stats['noshow']; ?></div>
                <div class="stat-label">No-show</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
                            <div class="col-md-3">
                                <label for="department" class="form-label">Department</label>
                                <select class="form-select" id="department" name="department">
                                    <option value="0">All Departments</option>
                                    <?php foreach($departments as $id => $name): ?>
                                    <option value="<?php echo $id; ?>" <?php echo ($department_filter == $id) ? 'selected' : ''; ?>>
                                        <?php echo $name; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="Waiting" <?php echo ($status_filter == 'Waiting') ? 'selected' : ''; ?>>Waiting</option>
                                    <option value="Serving" <?php echo ($status_filter == 'Serving') ? 'selected' : ''; ?>>Serving</option>
                                    <option value="Completed" <?php echo ($status_filter == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="No-show" <?php echo ($status_filter == 'No-show') ? 'selected' : ''; ?>>No-show</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="">Priorities</option>
                                    <option value="Person With Disability (PWD)" <?php echo ($priority_filter == 'Person With Disability (PWD)') ? 'selected' : ''; ?>>PWD</option>
                                    <option value="Senior Citizen" <?php echo ($priority_filter == 'Senior Citizen') ? 'selected' : ''; ?>>Senior Citizen</option>
                                    <option value="None" <?php echo ($priority_filter == 'None') ? 'selected' : ''; ?>>Regular</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo $date_filter; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Queue Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card queue-table">
                    <div class="card-body">
                        <?php if (count($queue_items) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Queue #</th>
                                        <th>Patient Name</th>
                                        <th>Contact</th>
                                        <th>Priority</th>                               
                                        <th>Department</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($queue_items as $item): ?>
                                    <tr>
                                        <td><strong><?php echo $item['queue_number']; ?></strong></td>
                                        <td><?php echo $item['first_name'] . ' ' . $item['middle_name'] . ' ' . $item['last_name']; ?></td>
                                        <td><?php echo $item['contact_number']; ?></td>
                                        <td>
                                            <?php if($item['priority_status'] == 'Person With Disability (PWD)'): ?>
                                                <span class="priority-pwd">PWD</span>
                                            <?php elseif($item['priority_status'] == 'Senior Citizen'): ?>
                                                <span class="priority-senior">Senior</span>
                                            <?php else: ?>
                                                <span class="priority-regular">Regular</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $item['department_name']; ?></td>
                                        <td><?php echo date('h:i A', strtotime($item['created_at'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace('-', '', $item['status'])); ?>">
                                                <?php echo $item['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['priority_status']) && !empty($item['id_image_path'])): ?>
                                                <a href="../uploads/<?php echo $item['id_image_path']; ?>" target="_blank" class="btn btn-sm btn-info">View ID</a>
                                            <?php endif; ?>
                                            <?php if($item['status'] == 'Waiting'): ?>
                                                <a href="?action=serving&queue_id=<?php echo $item['id']; ?>&department=<?php echo $department_filter; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&priority=<?php echo $priority_filter; ?>" class="btn btn-sm btn-primary">Start</a>
                                                <a href="?action=noshow&queue_id=<?php echo $item['id']; ?>&department=<?php echo $department_filter; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&priority=<?php echo $priority_filter; ?>" class="btn btn-sm btn-danger">No-show</a>
                                            <?php elseif($item['status'] == 'Serving'): ?>
                                                <a href="?action=complete&queue_id=<?php echo $item['id']; ?>&department=<?php echo $department_filter; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&priority=<?php echo $priority_filter; ?>" class="btn btn-sm btn-success">Complete</a>
                                            <?php else: ?>
                                                <span class="text-muted">No actions</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">No queue entries found with the selected filters.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>