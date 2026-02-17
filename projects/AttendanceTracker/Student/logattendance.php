<?php

session_start();
require_once('../dbconnection.php');

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: slogin.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$message = '';

// Handle attendance logging
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_attendance'])) {
    $session_id = $_POST['session_id'];
    $device_time = $_POST['device_time'];
    $timezone_offset = isset($_POST['timezone_offset']) ? intval($_POST['timezone_offset']) : 0;
    
    // Convert device time to server timezone if needed
    $time_in = date('Y-m-d H:i:s', strtotime($device_time));
    
    // Verify student is enrolled and class is today
    $verify_stmt = $conn->prepare("
        SELECT cs.* FROM class_sessions cs
        JOIN class_enrollments ce ON cs.id = ce.class_id
        WHERE cs.id = ? AND ce.student_id = ? AND cs.session_date = DATE(?)
    ");
    $verify_stmt->bind_param("iis", $session_id, $student_id, $time_in);
    $verify_stmt->execute();
    $session = $verify_stmt->get_result()->fetch_assoc();

    if (!$session) {
        $message = "<div class='alert alert-danger'>You are not enrolled in this class or the class is not scheduled for today.</div>";
    } else {
        // Check if already logged attendance
        $check_stmt = $conn->prepare("
            SELECT id, status FROM attendance_records 
            WHERE session_id = ? AND student_id = ?
        ");
        $check_stmt->bind_param("ii", $session_id, $student_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();

        // Determine attendance status based on device time
        $start_time = strtotime($session['session_date'] . ' ' . $session['start_time']);
        $current_time = strtotime($device_time);
        $late_threshold = $start_time + (15 * 60); // 15 minutes grace period

        $status = ($current_time <= $late_threshold) ? 'present' : 'late';

        if ($existing) {
            $message = "<div class='alert alert-warning'>You have already logged attendance for this class.</div>";
        } else {
            $log_stmt = $conn->prepare("
                INSERT INTO attendance_records (session_id, student_id, status, time_in)
                VALUES (?, ?, ?, ?)
            ");
            $log_stmt->bind_param("iiss", $session_id, $student_id, $status, $time_in);
            
            if ($log_stmt->execute()) {
                $message = "<div class='alert alert-success'>Attendance logged successfully! Status: " . ucfirst($status) . "</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error logging attendance.</div>";
            }
        }
    }
}

// Fetch today's enrolled classes
$stmt = $conn->prepare("
    SELECT cs.*, 
           ar.status,
           ar.time_in
    FROM class_sessions cs
    JOIN class_enrollments ce ON cs.id = ce.class_id
    LEFT JOIN attendance_records ar ON cs.id = ar.session_id AND ar.student_id = ?
    WHERE ce.student_id = ? 
    AND cs.session_date = CURDATE()
    ORDER BY cs.start_time ASC
");
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Attendance</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .class-card {
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .class-card:hover {
            transform: translateY(-5px);
        }
        .attendance-status {
            position: absolute;
            top: 10px;
            right: 10px;
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
        .current-time {
            font-size: 1.2rem;
            color: #6c757d;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .current-time i {
            margin-right: 5px;
            color: #007bff;
        }
    </style>
</head>
<body>
    <?php include 'studentsidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Log Attendance</h2>
                <div class="current-time">
                    <i class="fas fa-clock"></i>
                    Current Time: <span id="current_time"></span>
                </div>
            </div>
            
            <?php echo $message; ?>

            <?php if (empty($classes)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> You have no classes scheduled for today.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($classes as $class): ?>
                        <div class="col-md-4">
                            <div class="card class-card">
                                <?php if ($class['status']): ?>
                                    <div class="attendance-status">
                                        <span class="badge badge-<?php echo $class['status'] === 'present' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($class['status']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($class['class_name']); ?></h5>
                                    <p class="card-text">
                                        <i class="far fa-clock"></i> 
                                        <?php echo date('h:i A', strtotime($class['start_time'])); ?> - 
                                        <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                    </p>
                                    
                                    <?php if ($class['status']): ?>
                                        <p class="text-muted">
                                            <i class="fas fa-clock"></i> Logged at: 
                                            <?php echo date('h:i A', strtotime($class['time_in'])); ?>
                                        </p>
                                    <?php else: ?>
                                        <form method="POST" onsubmit="return setDeviceTime(this)">
                                            <input type="hidden" name="session_id" value="<?php echo $class['id']; ?>">
                                            <input type="hidden" name="device_time" id="device_time_<?php echo $class['id']; ?>">
                                            <input type="hidden" name="timezone_offset" value="">
                                            <button type="submit" name="log_attendance" class="btn btn-primary btn-block">
                                                <i class="fas fa-check-circle"></i> Log Attendance
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
function setDeviceTime(form) {
    const now = new Date();
    // Format date and time in MySQL format
    const deviceTime = now.getFullYear() + '-' + 
                      String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                      String(now.getDate()).padStart(2, '0') + ' ' + 
                      String(now.getHours()).padStart(2, '0') + ':' + 
                      String(now.getMinutes()).padStart(2, '0') + ':' + 
                      String(now.getSeconds()).padStart(2, '0');
    
    const deviceTimeInput = form.querySelector('input[name="device_time"]');
    deviceTimeInput.value = deviceTime;
    
    // Store timezone offset for server-side adjustment if needed
    const timezoneOffset = form.querySelector('input[name="timezone_offset"]');
    timezoneOffset.value = now.getTimezoneOffset();
    
    return true;
}

// Update current time display with device time
function updateCurrentTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true 
    });
    document.getElementById('current_time').textContent = timeString;
}

// Update time every second
setInterval(updateCurrentTime, 1000);

// Start the clock immediately
updateCurrentTime();
</script>
</body>
</html>