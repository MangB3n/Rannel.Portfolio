<?php
session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";  
$password = "";      
$dbname = "medireg";

// Create connection
$conn = new mysqli("localhost", "root", "", "medireg");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    //prepared statement to prevent SQL injection
    $sql = "SELECT id, username, password FROM admin_users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Password is correct, start a new session
            session_regenerate_id(true); // Prevent session fixation
            
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['last_activity'] = time();
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password";
        }
    } else {
        $error = "Invalid username or password";
    }
    
    $stmt->close();
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MediReg</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" media="screen">
</head>
<style>

    body {
        background-color: #f5f5f5;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100vh;
    }
    .container {
        max-width: 400px;
        width: 100%;
        padding: 20px;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    p {
        text-align: center;
        color: #555;
    }
    label {
        font-weight: bold;
    }
    input[type="text"],
    input[type="password"] {
        width: 100%;
        padding: 10px;
        margin-top: 5px;
        margin-bottom: 15px;
        border-radius: 6px;
        border: 1px solid #ccc;
    }
    input[type="text"]:focus,
    input[type="password"]:focus {
        border-color: #0d6efd;
        outline: none;
    }
    .btn {
        width: 100%;
        padding: 10px;
        background-color: #0d6efd;
        color: white;
        border: none;
        border-radius: 20px;
        cursor: pointer;
    }
    .btn:hover {
        background-color: #0056b3;
    }
    .btn:active {
        background-color: #004085;
    }

    .login-link {
    text-align: center;
    color: gray;
    }

    .login-link a {
    color: #007bff; 
    text-decoration: none;
    }

    .login-link a:hover {
    text-decoration: underline;
    }


</style>
<body>
  
    <form action="#" method="POST">
    <div class="container">
    <img class="logo" alt="Medireg sample logo" src="../images/medlog.png" style="height: 60px; display: block; margin: 0 auto;">
    <p style="text-align: center;">Welcome to the MediReg Admin</p>
    <label for="">Username</label>
        <input type="text" name="username" id="username" placeholder="Enter your username">
        <br><br>

        <label for="">Password</label>
        <input type="password" name="password" id="password" placeholder="Enter your password">
        <br><br>
        <button class="btn" id="loginBtn">Login</button>
        <br><br>
        <p class="login-link">Don't have an account? <a href="register.php">Register</a></p>
        
    </div>
    </form>
</body>
</html>