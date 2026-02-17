<?php
//Database connection
include '../dbconnection.php';
// Start session
session_start();


if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $student_id = trim($_POST["student_id"]);
    $password = trim($_POST["password"]);

    // Prepare and bind with student information using student_id
    $stmt = $conn->prepare("
        SELECT id, password, full_name, student_id, email 
        FROM students 
        WHERE student_id = ?
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $student['password'])) {
            // Set comprehensive session variables
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_number'] = $student['student_id'];
            $_SESSION['full_name'] = $student['full_name'];
            $_SESSION['email'] = $student['email'];
            $_SESSION['user_type'] = 'student';
            $_SESSION['last_activity'] = time();

            // Redirect to dashboard
            header("Location: sdashboard.php");
            exit();
        } else {
            $error_message = "Invalid password. Please try again.";
        }
    } else {
        $error_message = "Student ID not found. Please register first.";
    }
    $stmt->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Login</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; }
        .login-container {
            width: 350px;
            margin: 100px auto;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            margin: 8px 0 16px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
        }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Student Login</h2>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <form method="POST">
            <label for="student_id">Student ID</label>
            <input type="text" id="student_id" name="student_id" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Login</button>
            <p class="login">Don't have an Account? <a href="sregister.php">Register</a></p>
        </form>
    </div>
</body>
</html>