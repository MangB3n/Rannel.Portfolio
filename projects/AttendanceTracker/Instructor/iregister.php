

<?php
//Databse connection
include '../dbconnection.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $fullname = trim($_POST["fullname"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);

   // Capitalize the first letter of each word in fullname
    $fullname = ucwords(strtolower($fullname));

    // Check if passwords match
    if ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } else {
        
        // Prepare and bind
        $stmt = $conn->prepare("SELECT id FROM instructors WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
      


        if ($stmt->num_rows > 0) {
            $message = "Username already taken.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new instructor
            $stmt = $conn->prepare("INSERT INTO instructors (username, password, full_name, email) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $hashed_password, $fullname, $email);

            if ($stmt->execute()) {
                $message = "Registration successful. <a href='ilogin.php'>Login here</a>.";
            } else {
                $message = "Error: Could not register instructor.";
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
    <title>Instructor Registration</title>
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
            background: #007bff;
            
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
        }
        button:hover { background: #0056b3; }
        .message { color: green; text-align: center; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Instructor Registration</h2>
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        <form action="" method="POST">
            <label for="fullname">Full Name</label>
            <input type="text" id="fullname" name="fullname" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>

            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>

            <button type="submit">Register</button>
        </form>
        <p style="text-align:center;margin-top:10px;">
            Already have an account? <a href="./ilogin.php">Login here</a>
        </p>
    </div>
</body>
</html>