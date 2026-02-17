<?php
session_start();
require_once('../dbconnection.php');

// Check if instructor is logged in
if (!isset($_SESSION['instructor_id'])) {
    header('Location: login.php');
    exit();
}

// Handle session creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_session'])) {
    $class_name = $_POST['class_name'];
    $session_date = $_POST['session_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $instructor_id = $_SESSION['instructor_id'];

    $query = "INSERT INTO class_sessions (instructor_id, class_name, session_date, start_time, end_time) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issss", $instructor_id, $class_name, $session_date, $start_time, $end_time);
    
    if ($stmt->execute()) {
        $success_message = "Class session created successfully!";
    } else {
        $error_message = "Error creating class session.";
    }
}



// Fetch existing sessions for the instructor
$instructor_id = $_SESSION['instructor_id'];
$query = "SELECT * FROM class_sessions WHERE instructor_id = ? ORDER BY session_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$result = $stmt->get_result();
$sessions = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
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
            position: sticky;
            top: 0;
            height: 100vh;
            padding-top: 20px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
       
        .user-info {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        @media screen and (max-width: 768px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }
            .main-content {
                margin-left: 0;
            }

            .sidebar-sticky {
                height: auto;
                padding-top: 0;
            }
            .nav-link {
                padding: 10px;
            }
        }


    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'instructorsidebar.php' ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Create Session Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Create New Class Session</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label for="class_name">Class Name</label>
                            <input type="text" class="form-control" id="class_name" name="class_name" required>
                        </div>
                        <div class="form-group">
                            <label for="session_date">Date</label>
                            <input type="date" class="form-control" id="session_date" name="session_date" required>
                        </div>
                        <div class="form-group">
                            <label for="start_time">Start Time</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                        </div>
                        <div class="form-group">
                            <label for="end_time">End Time</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                        </div>
                        <button type="submit" name="create_session" class="btn btn-primary">Create Session</button>
                    </form>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <h4>Your Class Sessions</h4>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Class Name</th>
                                <th>Date</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($session['class_name']); ?></td>
                                <td><?php echo htmlspecialchars($session['session_date']); ?></td>
                                <td><?php echo htmlspecialchars($session['start_time']); ?></td>
                                <td><?php echo htmlspecialchars($session['end_time']); ?></td>
                                <td>
                                    <a href="viewattendance.php?session_id=<?php echo $session['id']; ?>" 
                                       class="btn btn-sm btn-info">View Attendance</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
     <!-- Existing Sessions Table -->
            <script>
                // Capitalize first and second word's first letter in class name input
                document.addEventListener('DOMContentLoaded', function() {
                    var classNameInput = document.getElementById('class_name');
                    classNameInput.addEventListener('blur', function() {
                        let words = classNameInput.value.trim().split(' ');
                        if (words.length > 0) {
                            words[0] = words[0].charAt(0).toUpperCase() + words[0].slice(1).toLowerCase();
                        }
                        if (words.length > 1) {
                            words[1] = words[1].charAt(0).toUpperCase() + words[1].slice(1).toLowerCase();
                        }
                        classNameInput.value = words.join(' ');
                    });
                });
            </script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</body>
</html>