<?php
include '../includes/database.php';

// --- CONFIGURATION ---    // <--- to create a admin account 
$new_username = "lokihan";
$new_email    = "lokihan@gmail.com";    // <---- input this if you create a new account the middle of ""
$new_password = "123456789";
// ---------------------

// 1. Hash the password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// 2. Insert username, EMAIL, and password
// Notice we added 'email' to the query below
$sql = "INSERT INTO admin_users (username, email, password) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt) {
    // "sss" means String, String, String (username, email, password)
    $stmt->bind_param("sss", $new_username, $new_email, $hashed_password);
    
    try {
        if ($stmt->execute()) {
            echo "Success! Admin user '<strong>$new_username</strong>' created.";
            echo "<br>Please delete this file now.";
        }
    } catch (mysqli_sql_exception $e) {
        // Handle the specific duplicate error cleanly
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo "<h3 style='color:red'>Error: User already exists.</h3>";
            echo "A user with this username OR email already exists in the database.<br>";
            echo "Try changing the username and email in the code and refreshing.";
        } else {
            echo "Error: " . $e->getMessage();
        }
    }
    $stmt->close();
} else {
    echo "Error preparing statement: " . $conn->error;
}

$conn->close();
?>