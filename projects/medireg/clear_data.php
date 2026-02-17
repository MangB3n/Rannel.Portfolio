<?php
// Database connection
$servername = "localhost";
$username = "root";  
$password = "";      
$dbname = "medireg";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start transaction for data consistency
$conn->begin_transaction();

try {
    // Delete all records from queue table first 
    $sql_clear_queue = "DELETE FROM queue";
    $conn->query($sql_clear_queue);
    $queue_count = $conn->affected_rows;

    // Now delete all records from patients table
    $sql_clear_patients = "DELETE FROM patients";
    $conn->query($sql_clear_patients);
    $patients_count = $conn->affected_rows;

    // Reset auto-increment values
    $conn->query("ALTER TABLE queue AUTO_INCREMENT = 1");
    $conn->query("ALTER TABLE patients AUTO_INCREMENT = 1");

    // Delete all images in the uploads folder
    $uploads_folder = __DIR__ . '/uploads/';
    $files = glob($uploads_folder . '*'); // Get all files in the uploads folder
    $deleted_files_count = 0;

    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file); // Delete the file
            $deleted_files_count++;
        }
    }

    // Commit the transaction
    $conn->commit();

    echo "<h2>Database Cleared Successfully</h2>";
    echo "<p>Removed $queue_count queue entries</p>";
    echo "<p>Removed $patients_count patient records</p>";
    echo "<p>Deleted $deleted_files_count files from the uploads folder</p>";
    echo "<p>Auto-increment counters have been reset.</p>";
    echo "<p><a href='patient/index.php'>Return to Registration Form</a> | <a href='admin/dashboard.php'>Go to Admin Dashboard</a></p>";

} catch (Exception $e) {
    // An error occurred, rollback the transaction
    $conn->rollback();
    echo "<h2>Error Clearing Database</h2>";
    echo "<p>Error message: " . $e->getMessage() . "</p>";
    echo "<p><a href='patient/index.php'>Return to Registration Form</a></p>";
}

// Close connection
$conn->close();
?>