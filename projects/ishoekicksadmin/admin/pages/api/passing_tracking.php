<?php

// passing_tracking.php
include '../../includes/database.php';

if (isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];

    // Insert booking info into tracking table
    $sql = "INSERT INTO tracking (booking_id, customer_name, customer_email, tracking_status, updated_time)
            SELECT id, customer_name, customer_email, tracking_status, NOW()
            FROM bookings
            WHERE id = '$booking_id'";

    if (mysqli_query($conn, $sql)) {
        echo json_encode(["success" => true, "message" => "Tracking record created successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . mysqli_error($conn)]);
    }
}
?>
    