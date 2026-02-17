<?php
session_start();
require_once('../dbconnection.php');

// Check if instructor is logged in
if (!isset($_SESSION['instructor_id'])) {
    header('Location: ilogin.php');
    exit();
}

$instructor_id = $_SESSION['instructor_id'];

// Get class session ID from query parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manageclasses.php');
    exit();
}
$class_id = intval($_GET['id']);

// Fetch class session details
$stmt = $conn->prepare("SELECT * FROM class_sessions WHERE id = ? AND instructor_id = ?");
$stmt->bind_param("ii", $class_id, $instructor_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();

if (!$class) {
    header('Location: manageclasses.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_class'])) {
    $class_name = trim($_POST['class_name']);
    $session_date = trim($_POST['session_date']);
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);
    

    // Basic validation
    if (empty($class_name) || empty($session_date) || empty($start_time) || empty($end_time)) {
        $error_message = "All fields marked with * are required.";
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $error_message = "End time must be after start time.";
    } else {
        $stmt = $conn->prepare("UPDATE class_sessions SET class_name = ?, session_date = ?, start_time = ?, end_time = ? WHERE id = ? AND instructor_id = ?");
        $stmt->bind_param("sssssi", $class_name, $session_date, $start_time, $end_time, $class_id, $instructor_id);
        if ($stmt->execute()) {
            $success_message = "Class session updated successfully!";
            // Refresh class data
            $stmt = $conn->prepare("SELECT * FROM class_sessions WHERE id = ? AND instructor_id = ?");
            $stmt->bind_param("ii", $class_id, $instructor_id);
            $stmt->execute();
            $class = $stmt->get_result()->fetch_assoc();
        } else {
            $error_message = "Failed to update class session.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Class Session</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }
        .card {
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
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
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Edit Class Session</h2>
                <a href="manageclasses.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Classes
                </a>
            </div>
            <div class="card">
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="form-group">
                            <label for="class_name">Class Name *</label>
                            <input type="text" class="form-control" id="class_name" name="class_name"
                                   value="<?php echo htmlspecialchars($class['class_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="session_date">Session Date *</label>
                            <input type="date" class="form-control" id="session_date" name="session_date"
                                   value="<?php echo htmlspecialchars($class['session_date']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="start_time">Start Time *</label>
                            <input type="time" class="form-control" id="start_time" name="start_time"
                                   value="<?php echo htmlspecialchars($class['start_time']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="end_time">End Time *</label>
                            <input type="time" class="form-control" id="end_time" name="end_time"
                                   value="<?php echo htmlspecialchars($class['end_time']); ?>" required>
                        </div>
                        
                        <button type="submit" name="update_class" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Class Session
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>