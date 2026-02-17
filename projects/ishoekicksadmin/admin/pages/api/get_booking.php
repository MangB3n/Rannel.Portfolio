<?php
require_once '../../includes/database.php';

header('Content-Type: application/json');

// The JS sends 'booking_reference', so we should check for that.
if (isset($_GET['booking_reference'])) {
    $booking_reference = mysqli_real_escape_string($conn, $_GET['booking_reference']);
    
    // Corrected SQL query syntax and logic
    $query = "SELECT * FROM bookings WHERE booking_reference = '$booking_reference'";

    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        // Handle SQL error
        echo json_encode(['success' => false, 'message' => 'Query failed: ' . mysqli_error($conn)]);
    } elseif ($row = mysqli_fetch_assoc($result)) {
        // Return a structured response that the frontend expects
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        // Return a structured error response
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
    }
} else {
    // Return a structured error response
    echo json_encode(['success' => false, 'message' => 'No booking reference provided']);
}

mysqli_close($conn);
?>