<?php
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match("/[A-Z]/", $password)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match("/[a-z]/", $password)) {
        $error = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match("/[0-9]/", $password)) {
        $error = "Password must contain at least one number.";
    } elseif (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $password)) {
        $error = "Password must contain at least one special character (!@#$%^&*()-_=+{};:,<.>).";
    } else {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if username or email already exists
        $check_sql = "SELECT * FROM admin_users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Username or email already exists.";
        } else {
            // Insert into database with hashed password
            $sql = "INSERT INTO admin_users (username, email, password) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $username, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $success = "Account created successfully. Redirecting to login page...";
                header('Location: login.php');
                exit();
            } else {
                $error = "Failed to register account.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MediReg</title>
</head>

<style>
    body {
        background-color: #f5f5f5;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
        font-family: Arial, sans-serif;
    }

    form {
        background-color: white;
        padding: 30px 40px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        width: 350px;
    }

    label {
        display: block;
        font-weight: bold;
        color: #333;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    .form-control,
    .form-select {
        width: 100%;
        padding: 12px 15px;
        margin-bottom: 10px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 14px;
        box-sizing: border-box;
        transition: border-color 0.3s;
    }

    input:focus,
    .form-control:focus,
    .form-select:focus {
        border-color: #007bff;
        outline: none;
    }

    button {
        width: 100%;
        padding: 12px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        font-size: 16px;
        transition: background-color 0.3s ease;
    }

    p {
        text-align: center;
        color: #555;
    }

    button:hover {
        background-color: #0056b3;
    }

    .register-link {
    text-align: center;
    color: gray;
    }

    .register-link a {
    color: #007bff; 
    text-decoration: none;
    }

    .register-link a:hover {
    text-decoration: underline;
    }

    .form-select {
        height: 50px;
        border: 1px solid black;
        border-radius: 5px;
        padding: 0 1rem;
    }
    .form-control {
        height: 50px;
        border: 1px solid black;
        border-radius: 5px;
        padding: 0 1rem;
    }

</style>
<body>
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <?php if (isset($success)) echo "<p style='color:green;'>$success</p>"; ?>
    <form method="POST" action="">
    <img class="logo" alt="Medireg sample logo" src="../images/medlog.png" style="height: 60px; display: block; margin: 0 auto;">
    <p>Welcome to the MediReg Admin</p>
    <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" required><br><br>
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br><br>
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required 
               pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$"
               title="Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character."><br>
        <small style="color: #666; font-size: 12px;">Password must be at least 8 characters long and contain uppercase, lowercase, number, and special character.</small><br><br>
        <label for="confirm_password">Confirm Password:</label><br>
        <input type="password" id="confirm_password" name="confirm_password" required><br><br>
        <button type="submit">Register</button>
        <br><br>
        <p class="register-link">Already have an account? <a href="login.php">Login</a></p>
        </form>
</body>
</html>