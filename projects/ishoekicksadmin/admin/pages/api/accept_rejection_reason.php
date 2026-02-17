<?php
// api/accept_rejection_reason.php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
ob_start();

try {
    require_once '../../includes/database.php';
    
    $response = ['success' => false, 'message' => 'Unknown error'];
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Invalid request method';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    $bookingReference = isset($_POST['booking_reference']) ? trim($_POST['booking_reference']) : '';
    
    if (empty($bookingReference)) {
        $response['message'] = 'Booking reference is required';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    $bookingReference = mysqli_real_escape_string($conn, $bookingReference);
    
    // Get booking details
    $query = "SELECT b.* 
              FROM bookings b
              WHERE b.booking_reference = '$bookingReference' OR b.id = '$bookingReference'";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        $response['message'] = 'Booking not found';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    $booking = mysqli_fetch_assoc($result);
    $bookingId = $booking['id'];
    
    // First, check if confirm_rejection column exists, if not add it
    $checkColumnQuery = "SHOW COLUMNS FROM bookings LIKE 'confirm_rejection'";
    $columnResult = mysqli_query($conn, $checkColumnQuery);
    
    if (mysqli_num_rows($columnResult) == 0) {
        // Add the column if it doesn't exist
        $alterQuery = "ALTER TABLE bookings ADD COLUMN confirm_rejection TINYINT(1) DEFAULT 0";
        mysqli_query($conn, $alterQuery);
    }
    
    // Update booking - set confirm_rejection to 1
    $updateQuery = "UPDATE bookings 
                   SET confirm_rejection = 1,
                       updated_at = NOW()
                   WHERE id = '$bookingId'";
    
    if (mysqli_query($conn, $updateQuery)) {
        // Also update trackings table if needed
        $updateTrackingQuery = "UPDATE trackings 
                               SET updated_time = NOW()
                               WHERE booking_id = '$bookingId'";
        mysqli_query($conn, $updateTrackingQuery);
        
        $response = [
            'success' => true,
            'message' => 'Rejection reason acknowledged successfully',
            'booking_id' => $bookingId
        ];
    } else {
        $response['message'] = 'Failed to update booking: ' . mysqli_error($conn);
    }
    
    ob_clean();
    echo json_encode($response);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
    ob_end_flush();
}
?>