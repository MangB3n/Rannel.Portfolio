<?php
// admin/pages/api/assign_crew.php
include('../../includes/database.php');
session_start();

header('Content-Type: application/json');

try {
    // Check if admin is logged in
    if(!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true){
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }

    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    // Check database connection
    if (!isset($conn) || !$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }

    // Get and validate inputs
    $booking_reference = isset($_POST['booking_reference']) ? trim($_POST['booking_reference']) : '';
    $crew_name = isset($_POST['crew_name']) ? trim($_POST['crew_name']) : '';

    if (empty($booking_reference)) {
        echo json_encode(['success' => false, 'message' => 'Booking reference is required']);
        exit;
    }

    if (empty($crew_name)) {
        echo json_encode(['success' => false, 'message' => 'Crew name is required']);
        exit;
    }

    // Validate crew name against enum values
    $valid_crew = ['Jason', 'Michael', 'Bea', 'Janisa', 'Mike'];
    if (!in_array($crew_name, $valid_crew)) {
        echo json_encode(['success' => false, 'message' => 'Invalid crew name: ' . $crew_name]);
        exit;
    }

    // Clean the inputs
    $booking_reference = mysqli_real_escape_string($conn, $booking_reference);
    $crew_name = mysqli_real_escape_string($conn, $crew_name);

    // First, get the booking ID from booking_reference
    $get_booking_id = "SELECT id FROM bookings WHERE booking_reference = '$booking_reference'";
    $booking_result = mysqli_query($conn, $get_booking_id);

    if (!$booking_result || mysqli_num_rows($booking_result) == 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Booking not found with reference: ' . $booking_reference
        ]);
        exit;
    }

    $booking_row = mysqli_fetch_assoc($booking_result);
    $booking_id = $booking_row['id'];

    // Update the trackings table with crew name
    $update_query = "UPDATE trackings 
                     SET crew_name = '$crew_name'
                     WHERE booking_id = $booking_id";

    $result = mysqli_query($conn, $update_query);

    if ($result) {
        if (mysqli_affected_rows($conn) > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Crew member assigned successfully',
                'crew_name' => $crew_name,
                'booking_reference' => $booking_reference,
                'booking_id' => $booking_id
            ]);
        } else {
            // Check if tracking record exists
            $check_tracking = "SELECT id, crew_name FROM trackings WHERE booking_id = $booking_id";
            $tracking_result = mysqli_query($conn, $check_tracking);
            
            if (!$tracking_result || mysqli_num_rows($tracking_result) == 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'No tracking record found for this booking. Please ensure tracking is initialized.',
                    'debug' => [
                        'booking_id' => $booking_id,
                        'booking_reference' => $booking_reference
                    ]
                ]);
            } else {
                $tracking_row = mysqli_fetch_assoc($tracking_result);
                echo json_encode([
                    'success' => false, 
                    'message' => 'No changes made. Crew may already be assigned.',
                    'debug' => [
                        'booking_id' => $booking_id,
                        'booking_reference' => $booking_reference,
                        'current_crew' => $tracking_row['crew_name'],
                        'requested_crew' => $crew_name
                    ]
                ]);
            }
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . mysqli_error($conn)
        ]);
    }

    mysqli_close($conn);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>