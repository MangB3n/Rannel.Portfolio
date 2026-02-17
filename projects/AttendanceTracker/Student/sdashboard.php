<?php
session_start();
require_once('../dbconnection.php');

// Verify student session
if (!isset($_SESSION['student_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: slogin.php');
    exit();
}

// Check session timeout
$timeout = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header("Location: slogin.php?msg=timeout");
    exit();
}

$_SESSION['last_activity'] = time();

// Get student information
$student_id = $_SESSION['student_id'];
$stmt = $conn->prepare("SELECT full_name FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Get student's attendance records for enrolled classes only
$query = "SELECT cs.*, ar.status, ar.time_in 
          FROM class_sessions cs 
          JOIN class_enrollments ce ON cs.id = ce.class_id AND ce.student_id = ?
          LEFT JOIN attendance_records ar ON cs.id = ar.session_id AND ar.student_id = ?
          ORDER BY cs.session_date DESC, cs.start_time DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
$sessions = $result->fetch_all(MYSQLI_ASSOC);

// Update the attendance summary calculations to check for enrollment
$present_count = array_reduce($sessions, function($carry, $item) {
    return $carry + ($item['status'] === 'present' ? 1 : 0);
}, 0);

$late_count = array_reduce($sessions, function($carry, $item) {
    return $carry + ($item['status'] === 'late' ? 1 : 0);
}, 0);

$absent_count = array_reduce($sessions, function($carry, $item) {
    return $carry + (($item['status'] === 'absent' || $item['status'] === null) ? 1 : 0);
}, 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
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
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
    
        .user-info {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .status-present { color: #28a745; }
        .status-absent { color: #dc3545; }
        .status-late { color: #ffc107; }
    </style>
</head>
<body>
    <!-- Include your sidebar here -->
    <?php include 'studentsidebar.php'; ?>
    

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Welcome, <?php echo htmlspecialchars($student['full_name']); ?>!</h4>
                        </div>
                        <div class="card-body">
                            <!-- Attendance Summary -->
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="card text-white bg-success">
                                        <div class="card-body">
                                            <h5 class="card-title">Present</h5>
                                            <p class="card-text h2">
                                                <?php 
                                                echo $present_count;
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card text-white bg-warning">
                                        <div class="card-body">
                                            <h5 class="card-title">Late</h5>
                                            <p class="card-text h2">
                                                <?php 
                                                echo $late_count;
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card text-white bg-danger">
                                        <div class="card-body">
                                            <h5 class="card-title">Absent</h5>
                                            <p class="card-text h2">
                                                <?php 
                                                echo $absent_count;
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Class Sessions Table -->
                            <h5>Attendance Records</h5>
                            <div class="table-responsive">
                                <?php if (empty($sessions)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> You are not enrolled in any classes yet. 
                                        <a href="enrollclass.php" class="alert-link">Click here to enroll in classes</a>.
                                    </div>
                                <?php else: ?>
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Class Name</th>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Status</th>
                                                <th>Time In</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sessions as $session): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($session['class_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($session['session_date'])); ?></td>
                                                <td><?php echo date('h:i A', strtotime($session['start_time'])) . ' - ' . 
                                                         date('h:i A', strtotime($session['end_time'])); ?></td>
                                                <td>
                                                    <span class="status-<?php echo $session['status'] ?? 'absent'; ?>">
                                                        <?php echo ucfirst($session['status'] ?? 'absent'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $session['time_in'] ? date('h:i A', strtotime($session['time_in'])) : '-'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
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