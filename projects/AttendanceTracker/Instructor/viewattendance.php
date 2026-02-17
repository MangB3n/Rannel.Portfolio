<?php

session_start();
require_once('../dbconnection.php');

// Check if instructor is logged in
if (!isset($_SESSION['instructor_id'])) {
    header('Location: login.php');
    exit();
}

// Check if session_id is provided
if (!isset($_GET['session_id'])) {
    header('Location: viewattendance.php');
    exit();
}

$session_id = $_GET['session_id'];
$instructor_id = $_SESSION['instructor_id'];

// Get class session details
$stmt = $conn->prepare("
    SELECT * FROM class_sessions 
    WHERE id = ? AND instructor_id = ?
");
$stmt->bind_param("ii", $session_id, $instructor_id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();

if (!$session) {
    header('Location: manageclasses.php');
    exit();
}

// Handle attendance update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    $conn->begin_transaction();
    try {
        // First delete existing records for this session
        $delete_stmt = $conn->prepare("DELETE FROM attendance_records WHERE session_id = ?");
        $delete_stmt->bind_param("i", $session_id);
        $delete_stmt->execute();

        // Then insert new records with current system time
        $insert_stmt = $conn->prepare("
            INSERT INTO attendance_records (session_id, student_id, status, time_in) 
            VALUES (?, ?, ?, NOW())
        ");
        
        foreach ($_POST['attendance'] as $student_id => $status) {
            $insert_stmt->bind_param("iis", $session_id, $student_id, $status);
            $insert_stmt->execute();
        }

        $conn->commit();
        $success_message = "Attendance updated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error updating attendance: " . $e->getMessage();
    }
}

// Update the query to fetch students with their enrollment and attendance status
$stmt = $conn->prepare("
    SELECT 
        s.*,
        CASE 
            WHEN ar.status IS NOT NULL THEN ar.status
            WHEN cs.session_date > CURDATE() THEN 'upcoming'
            WHEN cs.session_date = CURDATE() AND cs.start_time > CURTIME() THEN 'upcoming'
            WHEN ar.status IS NULL THEN 'absent'
            ELSE ar.status 
        END as status,
        ar.time_in,
        ce.enrollment_date,
        cs.session_date,
        cs.start_time,
        cs.end_time
    FROM class_enrollments ce
    JOIN students s ON ce.student_id = s.id
    JOIN class_sessions cs ON ce.class_id = cs.id
    LEFT JOIN attendance_records ar ON (s.id = ar.student_id AND ar.session_id = ?)
    WHERE ce.class_id = ?
    ORDER BY s.full_name ASC
");
$stmt->bind_param("ii", $session_id, $session_id);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Add this after getting $students to show enrollment count
$enrolled_count = count($students);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Attendance</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 30px;
            background-color: #f5f5f5;
            min-height: 100vh;
        }
        .attendance-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-badge {
            width: 100px;
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
        
        .status-badge option[value="present"] {
            background-color: #28a745;
            color: white;
        }
        
        .status-badge option[value="late"] {
            background-color: #ffc107;
            color: black;
        }
        
        .status-badge option[value="absent"] {
            background-color: #dc3545;
            color: white;
        }
        
        .status-badge option[value="upcoming"] {
            background-color: #17a2b8;
            color: white;
        }
        
        .status-badge:disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
       
    </style>
</head>
<body>
    <?php include 'instructorsidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>View Attendance</h2>
                <a href="manageclasses.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Classes
                </a>
            </div>

            <div class="attendance-card">
                <div class="card-body">
                    <div class="mb-4">
                        <h4><?php echo htmlspecialchars($session['class_name']); ?></h4>
                        <p class="text-muted">
                            <i class="far fa-calendar-alt"></i> 
                            <?php echo date('F d, Y', strtotime($session['session_date'])); ?>
                            <span class="ml-3"><i class="far fa-clock"></i> 
                            <?php echo date('h:i A', strtotime($session['start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($session['end_time'])); ?></span>
                        </p>
                        <p class="text-info">
                            <i class="fas fa-users"></i> Enrolled Students: <?php echo $enrolled_count; ?>
                        </p>
                    </div>

                    <?php if ($enrolled_count === 0): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No students are currently enrolled in this class.
                        </div>
                    <?php else: ?>

                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php elseif (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Enrolled Date</th>
                                        <th>Status</th>
                                        <th>Time In</th>
                                        
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                                        <td>
                                            <select name="attendance[<?php echo $student['id']; ?>]" 
                                                    class="form-control status-badge" 
                                                <?php echo ($session['session_date'] > date('Y-m-d')) ?  : ''; ?>> // Disable after ? 'disabled' //
                                                
                                                <option value="present" <?php echo ($student['status'] === 'present') ? 'selected' : ''; ?>>
                                                    Present
                                                </option>
                                                <option value="late" <?php echo ($student['status'] === 'late') ? 'selected' : ''; ?>>
                                                    Late
                                                </option>
                                                <option value="absent" <?php echo ($student['status'] === 'absent') ? 'selected' : ''; ?>>
                                                    Absent
                                                </option>
                                                <?php if ($student['status'] === 'upcoming'): ?>
                                                    <option value="upcoming" selected disabled>Upcoming</option>
                                                <?php endif; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($student['time_in']) {
                                                $time_in = new DateTime($student['time_in']);
                                                echo $time_in->format('h:i:s A'); // Shows hours:minutes:seconds AM/PM
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

                        <div class="text-right mt-3">
                            <button type="submit" name="update_attendance" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Attendance
                            </button>
                        </div>
                    </form>
                    <?php endif; // End of enrolled_count check ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function viewStudentHistory(studentId) {
            // Implement student history view functionality
            window.location.href = `student_history.php?student_id=${studentId}`;
        }
    </script>
</body>
</html>