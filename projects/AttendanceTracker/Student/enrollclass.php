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

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
    $class_id = $_POST['class_id'];
    
    // First verify the class exists and is available
    $verify_stmt = $conn->prepare("
        SELECT id FROM class_sessions 
        WHERE id = ? AND session_date >= CURDATE()
    ");
    $verify_stmt->bind_param("i", $class_id);
    $verify_stmt->execute();
    $verify_stmt->store_result();

    if ($verify_stmt->num_rows === 0) {
        $message = "<div class='alert alert-danger'>This class is not available for enrollment.</div>";
    } else {
        // Check if already enrolled
        $check_stmt = $conn->prepare("
            SELECT id FROM class_enrollments 
            WHERE student_id = ? AND class_id = ?
        ");
        $check_stmt->bind_param("ii", $student_id, $class_id);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $message = "<div class='alert alert-warning'>You are already enrolled in this class.</div>";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Enroll the student
                $enroll_stmt = $conn->prepare("
                    INSERT INTO class_enrollments (student_id, class_id, enrollment_date) 
                    VALUES (?, ?, NOW())
                ");
                $enroll_stmt->bind_param("ii", $student_id, $class_id);
                
                if ($enroll_stmt->execute()) {
                    $conn->commit();
                    $message = "<div class='alert alert-success'>Successfully enrolled in the class!</div>";
                } else {
                    throw new Exception("Error enrolling in class");
                }
            } catch (Exception $e) {
                $conn->rollback();
                $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// Fetch available classes
$stmt = $conn->prepare("
    SELECT cs.*, 
           COUNT(ce.student_id) as enrolled_students,
           CASE WHEN ce2.student_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled
    FROM class_sessions cs
    LEFT JOIN class_enrollments ce ON cs.id = ce.class_id
    LEFT JOIN class_enrollments ce2 ON cs.id = ce2.class_id AND ce2.student_id = ?
    WHERE cs.session_date >= CURDATE()
    GROUP BY cs.id
    ORDER BY cs.session_date ASC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$classes = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enroll in Class</title>
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
        .enrolled-badge {
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
    </style>
</head>
<body>
    <?php include 'studentsidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">Available Classes</h2>
            
            <?php echo $message; ?>

            <div class="row">
                <?php foreach ($classes as $class): ?>
                    <div class="col-md-4">
                        <div class="card class-card">
                            <?php if ($class['is_enrolled']): ?>
                                <div class="enrolled-badge">
                                    <span class="badge badge-success">Enrolled</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($class['class_name']); ?></h5>
                                <p class="card-text">
                                    <i class="far fa-calendar-alt"></i> 
                                    <?php echo date('F d, Y', strtotime($class['session_date'])); ?>
                                </p>
                                <p class="card-text">
                                    <i class="far fa-clock"></i> 
                                    <?php echo date('h:i A', strtotime($class['start_time'])); ?> - 
                                    <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-users"></i> 
                                    <?php echo $class['enrolled_students']; ?> students enrolled
                                </p>
                                
                                <?php if (!$class['is_enrolled']): ?>
                                    <form method="POST">
                                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                        <button type="submit" name="enroll" class="btn btn-primary btn-block">
                                            <i class="fas fa-plus-circle"></i> Enroll
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-success btn-block" disabled>
                                        <i class="fas fa-check"></i> Enrolled
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>