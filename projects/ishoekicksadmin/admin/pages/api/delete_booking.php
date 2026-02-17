<?php
session_start();
header('Content-Type: application/json');

require_once '../../includes/database.php';

// Check if admin is logged in
if(!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if booking reference is provided
if (!isset($_POST['booking_reference']) || empty($_POST['booking_reference'])) {
    echo json_encode(['success' => false, 'message' => 'Booking reference is required']);
    exit;
}

$booking_reference = mysqli_real_escape_string($conn, $_POST['booking_reference']);

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Get booking ID first
    $get_booking_query = "SELECT id FROM bookings WHERE booking_reference = '$booking_reference'";
    $booking_result = mysqli_query($conn, $get_booking_query);
    
    if (!$booking_result || mysqli_num_rows($booking_result) === 0) {
        throw new Exception('Booking not found');
    }
    
    $booking = mysqli_fetch_assoc($booking_result);
    $booking_id = $booking['id'];
    
    // Delete related records from booking_shoes table
    $delete_shoes_query = "DELETE FROM booking_shoes WHERE booking_id = '$booking_id'";
    if (!mysqli_query($conn, $delete_shoes_query)) {
        throw new Exception('Failed to delete shoe records: ' . mysqli_error($conn));
    }
    
    // Check and delete from manual_services table if it exists
    $check_manual_services = mysqli_query($conn, "SHOW TABLES LIKE 'manual_services'");
    if (mysqli_num_rows($check_manual_services) > 0) {
        $delete_manual_services_query = "DELETE FROM manual_services WHERE booking_reference = '$booking_reference'";
        mysqli_query($conn, $delete_manual_services_query);
    }
    
    // Check and delete from tracking_updates table if it exists
    $check_tracking = mysqli_query($conn, "SHOW TABLES LIKE 'tracking_updates'");
    if (mysqli_num_rows($check_tracking) > 0) {
        $delete_tracking_query = "DELETE FROM tracking_updates WHERE booking_reference = '$booking_reference'";
        mysqli_query($conn, $delete_tracking_query);
    }
    
    // Delete main booking record
    $delete_booking_query = "DELETE FROM bookings WHERE booking_reference = '$booking_reference'";
    if (!mysqli_query($conn, $delete_booking_query)) {
        throw new Exception('Failed to delete booking: ' . mysqli_error($conn));
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Booking deleted successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>