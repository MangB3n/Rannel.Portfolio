<?php
// api/update_confirmation_status.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ishoekicks_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// Get POST data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$booking_reference = isset($_POST['booking_reference']) ? trim($_POST['booking_reference']) : '';

// Validate inputs
if (empty($email) || empty($booking_reference)) {
    echo json_encode([
        'success' => false,
        'message' => 'Email and booking reference are required'
    ]);
    exit();
}

// First, get the booking_id
$booking_sql = "SELECT id FROM bookings WHERE customer_email = ? AND booking_reference = ?";
$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param("ss", $email, $booking_reference);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();

if ($booking_result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking not found for this user'
    ]);
    exit();
}

$booking = $booking_result->fetch_assoc();
$booking_id = $booking['id'];

// Update booking status to "Confirmation"
$sql = "UPDATE bookings 
        SET tracking_status = 'Confirmation', 
            updated_at = NOW() 
        WHERE customer_email = ? 
        AND booking_reference = ? 
        AND tracking_status = 'Ready'";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to prepare statement: ' . $conn->error
    ]);
    exit();
}

$stmt->bind_param("ss", $email, $booking_reference);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Also update trackings table
        $update_tracking = "UPDATE trackings SET tracking_status = 'Confirmation', updated_time = NOW() WHERE booking_id = ?";
        $tracking_stmt = $conn->prepare($update_tracking);
        $tracking_stmt->bind_param("i", $booking_id);
        $tracking_stmt->execute();
        $tracking_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Status updated to Confirmation successfully'
        ]);
    } else {
        // Check if booking exists and get current status
        $check_sql = "SELECT tracking_status FROM bookings WHERE customer_email = ? AND booking_reference = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $email, $booking_reference);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $row = $check_result->fetch_assoc();
            echo json_encode([
                'success' => false,
                'message' => 'Booking status is currently: ' . $row['tracking_status'] . '. Must be in Ready status to confirm.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Booking not found for this user'
            ]);
        }
        $check_stmt->close();
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update status: ' . $stmt->error
    ]);
}

$stmt->close();
$booking_stmt->close();
$conn->close();
?>