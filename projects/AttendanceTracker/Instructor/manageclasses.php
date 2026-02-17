<?php

session_start();
require_once('../dbconnection.php');

// Check if instructor is logged in
if (!isset($_SESSION['instructor_id'])) {
    header('Location: login.php');
    exit();
}

$instructor_id = $_SESSION['instructor_id'];

// Handle class deletion
if (isset($_POST['delete_session']) && isset($_POST['session_id'])) {
    $session_id = $_POST['session_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First delete attendance records
        $stmt = $conn->prepare("DELETE FROM attendance_records WHERE session_id = ?");
        $stmt->bind_param("i", $session_id);
        $stmt->execute();
        
        // Then delete class enrollments
        $stmt = $conn->prepare("DELETE FROM class_enrollments WHERE class_id = ?");
        $stmt->bind_param("i", $session_id);
        $stmt->execute();
        
        // Finally delete the class session
        $stmt = $conn->prepare("DELETE FROM class_sessions WHERE id = ? AND instructor_id = ?");
        $stmt->bind_param("ii", $session_id, $instructor_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        $success_message = "Class session deleted successfully!";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error deleting class session: " . $e->getMessage();
    }
}

// Fetch all classes for this instructor
$stmt = $conn->prepare("
    SELECT cs.*, 
           COUNT(DISTINCT ar.student_id) as student_count,
           COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
           COUNT(DISTINCT ce.student_id) as enrolled_count
    FROM class_sessions cs
    LEFT JOIN attendance_records ar ON cs.id = ar.session_id
    LEFT JOIN class_enrollments ce ON cs.id = ce.class_id
    WHERE cs.instructor_id = ?
    GROUP BY cs.id
    ORDER BY cs.session_date DESC, cs.start_time DESC
");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$result = $stmt->get_result();
$classes = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 30px;
            background-color: #f5f5f5;
            min-height: 100vh;
        }
        .class-card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-badge {
            font-size: 0.9rem;
            margin-right: 10px;
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
    <?php include 'instructorsidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Manage Classes</h2>
                <a href="./idashboard.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create New Class
                </a>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if (empty($classes)): ?>
                <div class="alert alert-info">No classes found. Create your first class!</div>
            <?php else: ?>
                <?php foreach ($classes as $class): ?>
                    <div class="card class-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($class['class_name']); ?></h5>
                                <div>
                                    <button onclick="viewEnrolledStudents(<?php echo $class['id']; ?>)" 
                                            class="btn btn-primary btn-sm">
                                        <i class="fas fa-users"></i> Enrolled (<?php echo $class['enrolled_count']; ?>)
                                    </button>
                                    <a href="viewattendance.php?session_id=<?php echo $class['id']; ?>" 
                                       class="btn btn-info btn-sm">
                                        <i class="fas fa-clipboard-check"></i> Attendance
                                    </a>
                                    <a href="editclass.php?id=<?php echo $class['id']; ?>" 
                                       class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" class="d-inline" 
                                          onsubmit="return confirm('Are you sure you want to delete this class?');">
                                        <input type="hidden" name="session_id" value="<?php echo $class['id']; ?>">
                                        <button type="submit" name="delete_session" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="badge badge-primary stats-badge">
                                    <i class="far fa-calendar"></i> 
                                    <?php echo date('F d, Y', strtotime($class['session_date'])); ?>
                                </span>
                                <span class="badge badge-info stats-badge">
                                    <i class="far fa-clock"></i> 
                                    <?php 
                                        $start = new DateTime($class['start_time']);
                                        $end = new DateTime($class['end_time']);
                                        echo $start->format('g:i A') . ' - ' . $end->format('g:i A'); 
                                    ?>
                                </span>
                                <span class="badge badge-success stats-badge">
                                    <i class="fas fa-user-check"></i> 
                                    <?php 
                                    $attendance_rate = $class['enrolled_count'] > 0 ? 
                                        round(($class['present_count'] / $class['enrolled_count']) * 100) : 0;
                                    echo $class['present_count'] . ' Present (' . $attendance_rate . '%)';
                                    ?>
                                </span>
                                <span class="badge badge-secondary stats-badge">
                                    <i class="fas fa-clipboard-check"></i> 
                                    <?php echo $class['student_count']; ?> Attended
                                </span>
                                <span class="badge badge-info stats-badge">
                                    <i class="fas fa-user-plus"></i> 
                                    <?php echo $class['enrolled_count']; ?> Enrolled
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Enrolled Students Modal -->
<div class="modal fade" id="enrolledStudentsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enrolled Students</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="enrolledStudentsList"></div>
            </div>
        </div>
    </div>
</div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
function viewEnrolledStudents(classId) {
    $.ajax({
        url: 'get_enrolled_students.php',
        type: 'POST',
        data: { class_id: classId },
        success: function(response) {
            $('#enrolledStudentsList').html(response);
            $('#enrolledStudentsModal').modal('show');
        }
    });
}
</script>
</body>
</html>