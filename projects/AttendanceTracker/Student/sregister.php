<?php
//Database connection
include '../dbconnection.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = isset($_POST["username"]) ? trim($_POST["username"]) : '';
    $fullname = isset($_POST["fullname"]) ? trim($_POST["fullname"]) : '';
    $email = isset($_POST["email"]) ? trim($_POST["email"]) : '';
    $student_id = isset($_POST["student_id"]) ? trim($_POST["student_id"]) : '';
    $password = isset($_POST["password"]) ? trim($_POST["password"]) : '';
    $confirm_password = isset($_POST["confirm_password"]) ? trim($_POST["confirm_password"]) : '';

    // capitalize the first letter of each word in fullname
    $fullname = ucwords(strtolower($fullname));

    // Validate input
    if (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } else {
        // Check if username, email or student ID already exists
        $stmt = $conn->prepare("SELECT id FROM students WHERE username = ? OR email = ? OR student_id = ?");
        $stmt->bind_param("sss", $username, $email, $student_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $message = "Username, email or student ID already registered.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new student
            $stmt = $conn->prepare("INSERT INTO students (username, password, full_name, email, student_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $hashed_password, $fullname, $email, $student_id);

            if ($stmt->execute()) {
                $message = "<span style='color: green;'>Registration successful. <a href='slogin.php'>Login here</a>.</span>";
            } else {
                $message = "Error: Could not register student.";
            }
        }
        $stmt->close();
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Registration</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; }
        .register-container {
            width: 400px;
            margin: 60px auto;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%;
            padding: 10px;
            margin: 8px 0 16px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #28a745;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
        }
        button:hover { background: #218838; }
        .message { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; }
        .success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Student Registration</h2>
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successful') !== false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <form action="" method="POST" onsubmit="return validateForm()">
            <label for="fullname">Full Name</label>
            <input type="text" id="fullname" name="fullname" required>

            <label for="student_id">Student ID</label>
            <input type="text" id="student_id" name="student_id" required pattern="[A-Za-z0-9]+" title="Only letters and numbers allowed">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>

            <label for="username">Username</label>
            <input type="text" id="username" name="username" required pattern="[A-Za-z0-9]+" title="Only letters and numbers allowed">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="6">

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>

            <button type="submit">Register</button>
        </form>
        <p style="text-align:center;margin-top:10px;">
            Already have an account? <a href="slogin.php">Login here</a>
        </p>
    </div>

    <script>
    function validateForm() {
        var password = document.getElementById("password").value;
        var confirm_password = document.getElementById("confirm_password").value;
        
        if (password !== confirm_password) {
            alert("Passwords do not match!");
            return false;
        }
        return true;
    }
    </script>
</body>
</html>