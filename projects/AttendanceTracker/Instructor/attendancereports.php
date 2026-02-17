<?php

session_start();
require_once('../dbconnection.php');

// Check if instructor is logged in
if (!isset($_SESSION['instructor_id'])) {
    header('Location: login.php');
    exit();
}

$instructor_id = $_SESSION['instructor_id'];

// Get filter parameters
$class_name = isset($_GET['class_name']) ? $_GET['class_name'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Base query
$query = "
    SELECT 
        cs.class_name,
        cs.session_date,
        COUNT(DISTINCT ar.student_id) as total_students,
        COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
        COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count,
        COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count
    FROM class_sessions cs
    LEFT JOIN attendance_records ar ON cs.id = ar.session_id
    WHERE cs.instructor_id = ?
";

$params = array($instructor_id);
$types = "i";

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

$query .= " GROUP BY cs.id ORDER BY cs.session_date DESC";

// Execute query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$reports = $result->fetch_all(MYSQLI_ASSOC);

// Get unique class names for filter
$stmt = $conn->prepare("SELECT DISTINCT class_name FROM class_sessions WHERE instructor_id = ?");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 30px;
            background-color: #f5f5f5;
            min-height: 100vh;
        }
        .stats-card {
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .report-filters {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
    <?php include './instructorsidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">Attendance Reports</h2>

            <!-- Filters -->
            <div class="report-filters">
                <form method="GET" class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="class_name">Class Name</label>
                            <select class="form-control" name="class_name">
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
                            <label for="date_from">From Date</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="date_to">To Date</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
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

            <!-- Summary Stats -->
            <div class="row">
                <div class="col-md-4">
                    <div class="card stats-card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Classes</h5>
                            <h2><?php echo count($reports); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Average Attendance</h5>
                            <h2>
                                <?php
                                $total_present = array_sum(array_column($reports, 'present_count'));
                                $total_students = array_sum(array_column($reports, 'total_students'));
                                echo $total_students > 0 ? 
                                    round(($total_present / $total_students) * 100, 0.1) . '%' : '0%';
                                ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Students</h5>
                            <h2><?php echo $total_students; ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Report -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Class Name</th>
                                    <th>Date</th>
                                    <th>Total Students</th>
                                    <th>Present</th>
                                    <th>Late</th>
                                    <th>Absent</th>
                                    <th>Attendance Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($report['class_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($report['session_date'])); ?></td>
                                        <td><?php echo $report['total_students']; ?></td>
                                        <td class="text-success"><?php echo $report['present_count']; ?></td>
                                        <td class="text-warning"><?php echo $report['late_count']; ?></td>
                                        <td class="text-danger"><?php echo $report['absent_count']; ?></td>
                                        <td>
                                            <?php
                                            $attendance_rate = $report['total_students'] > 0 ? 
                                                round(($report['present_count'] / $report['total_students']) * 100, 0.1) : 0;
                                            echo $attendance_rate . '%';
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