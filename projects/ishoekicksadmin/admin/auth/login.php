<?php
session_start();
include '../includes/database.php';

$error = '';

// 1. SECURITY: Anti-Brute Force Init
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

// 2. SECURITY: CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    // 2. SECURITY: Check CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Validation Failed.");
    }

    // 1. SECURITY: Check Brute Force Limit (Max 5 tries)
    if ($_SESSION['login_attempts'] >= 5) {
        $error = "Too many failed attempts. Please close your browser and try again.";
    } else {
        $username = trim($_POST["username"]);
        $password = trim($_POST["password"]);

        // Prepare and bind
        $stmt = $conn->prepare("SELECT id, password FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $hashed_password);
            $stmt->fetch();

            // Verify password
            if (password_verify($password, $hashed_password)) {
                // 3. SECURITY: Prevent Session Fixation
                session_regenerate_id(true);

                // Reset attempts
                $_SESSION['login_attempts'] = 0;

                // Set session variables
                $_SESSION['admin_id'] = $id;
                $_SESSION['username'] = $username;
                $_SESSION['admin_logged_in'] = true;

                // Redirect to admin dashboard
                header("Location: ../pages/dashboard.php");
                exit();
            } else {
                $_SESSION['login_attempts']++;
                $error = "Invalid username or password.";
            }
        } else {
            $_SESSION['login_attempts']++;
            $error = "Invalid username or password.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISoeKicks Admin Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    
    <style>
        /* BASE RESET */
        * {
            box-sizing: border_box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            /* Dark Streetwear Gradient Background */
            background: linear-gradient(135deg, #121212 0%, #1f1f1f 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        /* CARD STYLING */
        .login-container {
            width: 100%;
            max-width: 400px;
            background: rgba(30, 30, 30, 0.95);
            padding: 40px;
            border-radius: 20px;
            /* Sophisticated Shadow */
            box-shadow: 0 20px 50px rgba(0,0,0,0.5); 
            border: 1px solid rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
        }

        /* Top Accent Line */
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #9a7d36, #cfaa48);
        }

        /* Logo Styling */
        .logo-area {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .logo-area img {
            max-width: 120px;
            margin-bottom: 15px;
            /* Add a drop shadow to the logo so it pops */
            filter: drop-shadow(0 5px 5px rgba(0,0,0,0.3)); 
        }

        h2 {
            font-weight: 600;
            font-size: 1.5rem;
            color: #ffffff;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .subtitle {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 30px;
            display: block;
            text-align: center;
        }

        /* FORM ELEMENTS */
        form {
            width: 100%;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        label {
            display: block;
            font-size: 0.85rem;
            color: #bbb;
            margin-bottom: 8px;
            margin-left: 5px;
            font-weight: 400;
        }

        input[type="text"], 
        input[type="password"] {
            width: 100%;
            padding: 14px 15px;
            background: #252525;
            border: 2px solid #333;
            border-radius: 12px;
            color: #fff;
            font-size: 0.95rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            box-sizing: border-box; /* Fixes width issues */
        }

        /* Focus Effects (The Glow) */
        input[type="text"]:focus, 
        input[type="password"]:focus {
            outline: none;
            border-color: #9a7d36;
            background: #2a2a2a;
            box-shadow: 0 0 10px rgba(154, 125, 54, 0.2);
        }

        /* Button Styling */
        input[type="submit"] {
            width: 100%;
            padding: 15px;
            margin-top: 10px;
            background: linear-gradient(45deg, #9a7d36, #b89644);
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        input[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(154, 125, 54, 0.3);
        }

        input[type="submit"]:active {
            transform: translateY(1px);
        }

        /* Error Message */
        div.error {
            background: rgba(220, 53, 69, 0.1);
            color: #ff6b6b;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-size: 0.9rem;
            margin-bottom: 20px;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

    </style>
</head>
<body>
    
    <div class="login-container">
        <div class="logo-area">
            <img src="../images/logo.png" alt="ISoeKicks Logo">
            <h2>Admin Portal</h2>
            <span class="subtitle">Secure Login System</span>
        </div>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <?php if($error): ?>
                <div class="error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" placeholder="Enter ID" required autocomplete="off">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="••••••••" required>
            </div>
            
            <input type="submit" value="Sign In">
        </form>
    </div>

</body>
</html>