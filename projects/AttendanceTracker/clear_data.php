<?php
session_start();
require_once('dbconnection.php');



// Handle data clearing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_data'])) {
    $conn->begin_transaction();
    
    try {
        // Clear data in the correct order to maintain referential integrity
        $tables = [
            'attendance_records',
            'class_enrollments',
            'class_sessions',
            'students',
            'instructors'
        ];

        foreach ($tables as $table) {
            $stmt = $conn->prepare("DELETE FROM $table");
            $stmt->execute();
            
            // Reset auto-increment
            $stmt = $conn->prepare("ALTER TABLE $table AUTO_INCREMENT = 1");
            $stmt->execute();
        }

        $conn->commit();
        $success_message = "All data has been cleared successfully.";
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error clearing data: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clear System Data</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container {
            max-width: 600px;
            margin-top: 50px;
        }
        .warning-card {
            border: 2px solid #dc3545;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card warning-card">
            <div class="card-body">
                <h3 class="card-title text-danger">
                    <i class="fas fa-exclamation-triangle"></i> Clear System Data
                </h3>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="alert alert-warning">
                    <strong>Warning!</strong> This action will permanently delete all:
                    <ul>
                        <li>Attendance Records</li>
                        <li>Class Enrollments</li>
                        <li>Class Sessions</li>
                        <li>Student Accounts</li>
                        <li>Instructor Accounts</li>
                    </ul>
                    This action cannot be undone!
                </div>

                <form method="POST" onsubmit="return confirmClear()">
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="confirm" required>
                            <label class="custom-control-label" for="confirm">
                                I understand that this action will permanently delete all data
                            </label>
                        </div>
                    </div>
                    <button type="submit" name="clear_data" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Clear All Data
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </form>
            </div>
        </div>
    </div>

    <script>
    function confirmClear() {
        return confirm('Are you absolutely sure you want to clear all system data? This cannot be undone!');
    }
    </script>
</body>
</html>