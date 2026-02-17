<?php
//Databse connection
include '../dbconnection.php';
// Start session
session_start();


if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // Prepare and bind
    $stmt = $conn->prepare("SELECT id, password FROM instructors WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password);
        $stmt->fetch();

        // Verify password
        if (password_verify($password, $hashed_password)) {
            // Set session variables
            $_SESSION['instructor_id'] = $id;
            $_SESSION['username'] = $username;

            // Redirect to instructor dashboard
            header("Location: idashboard.php");
            exit();
        } else {
            echo "<script>alert('Invalid password. Please try again.');</script>";
        }
    } else {
        echo "<script>alert('Username not found. Please register first.');</script>";
    }
    $stmt->close();
}



?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Instructor Login</title>
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
        <h2>Instructor Login</h2>
        <form action="#" method="POST">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>


            <button type="submit">Login</button>
            <p class="login">Don't have an Account? <a href="iregister.php">Register</a></p>
            
        </form>
    </div>
</body>
</html>