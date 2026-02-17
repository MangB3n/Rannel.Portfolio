<?php

session_start();
require_once('../dbconnection.php');

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: slogin.php');
    exit();
}

$student_id = $_SESSION['student_id'];

// Get filter parameters
$class_name = isset($_GET['class_name']) ? $_GET['class_name'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Base query to get attendance records
$query = "
    SELECT 
        cs.class_name,
        cs.session_date,
        cs.start_time,
        cs.end_time,
        ar.status,
        ar.time_in
    FROM class_sessions cs
    JOIN class_enrollments ce ON cs.id = ce.class_id
    LEFT JOIN attendance_records ar ON cs.id = ar.session_id AND ar.student_id = ?
    WHERE ce.student_id = ?
";

$params = array($student_id, $student_id);
$types = "ii";

// Add filters
if (!empty($class_name)) {
    $query .= " AND cs.class_name LIKE ?";
    $params[] = "%$class_name%";
    $types .= "s";
}
if (!empty($date_from)) {
    $query .= " AND cs.session_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if (!empty($date_to)) {
    $query .= " AND cs.session_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$query .= " ORDER BY cs.session_date DESC, cs.start_time DESC";

// Execute query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate attendance statistics
$total_classes = count($records);
$present_count = array_reduce($records, function($carry, $item) {
    return $carry + ($item['status'] === 'present' ? 1 : 0);
}, 0);
$late_count = array_reduce($records, function($carry, $item) {
    return $carry + ($item['status'] === 'late' ? 1 : 0);
}, 0);
$absent_count = array_reduce($records, function($carry, $item) {
    return $carry + (($item['status'] === 'absent' || $item['status'] === null) ? 1 : 0);
}, 0);

// Get unique class names for filter
$stmt = $conn->prepare("
    SELECT DISTINCT cs.class_name 
    FROM class_sessions cs
    JOIN class_enrollments ce ON cs.id = ce.class_id
    WHERE ce.student_id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Attendance Records</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .stats-card {
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
          .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100%;
            background-color: #343a40;
            color: #fff;
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php include 'studentsidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">My Attendance Records</h2>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Class</label>
                                <select name="class_name" class="form-control">
                                    <option value="">All Classes</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo htmlspecialchars($class['class_name']); ?>"
                                                <?php echo $class_name === $class['class_name'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row">
                <div class="col-md-3">
                    <div class="card stats-card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Classes</h5>
                            <h2><?php echo $total_classes; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Present</h5>
                            <h2><?php echo $present_count; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-warning text-white">
                        <div class="card-body">
                            <h5 class="card-title">Late</h5>
                            <h2><?php echo $late_count; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-danger text-white">
                        <div class="card-body">
                            <h5 class="card-title">Absent</h5>
                            <h2><?php echo $absent_count; ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Records Table -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Time In</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['class_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($record['session_date'])); ?></td>
                                        <td>
                                            <?php 
                                            echo date('h:i A', strtotime($record['start_time'])) . ' - ' . 
                                                 date('h:i A', strtotime($record['end_time'])); 
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($record['time_in']) {
                                                // Student has logged attendance
                                                $status_class = $record['status'] === 'present' ? 'success' : 
                                                              ($record['status'] === 'late' ? 'warning' : 'danger');
                                                $status_text = ucfirst($record['status']);
                                            } else {
                                                // Check if class is in the future
                                                if (strtotime($record['session_date'] . ' ' . $record['start_time']) > time()) {
                                                    $status_class = 'info';
                                                    $status_text = 'Upcoming';
                                                } else {
                                                    $status_class = 'secondary';
                                                    $status_text = 'Not Logged';
                                                }
                                            }
                                            ?>
                                            <span class="badge badge-<?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($record['time_in']) {
                                                $time_in = new DateTime($record['time_in']);
                                                echo $time_in->format('h:i:s A');
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>