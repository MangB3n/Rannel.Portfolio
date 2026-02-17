<?php
require_once '../../includes/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$booking_reference = isset($_GET['booking_reference']) ? trim($_GET['booking_reference']) : '';

if (empty($booking_reference)) {
    echo json_encode(['success' => false, 'message' => 'Booking reference is required.']);
    exit;
}

$booking_reference_sql = mysqli_real_escape_string($conn, $booking_reference);

// Get booking ID
$booking_query = "SELECT id FROM bookings WHERE booking_reference = '$booking_reference_sql'";
$booking_result = mysqli_query($conn, $booking_query);
if (!$booking_result || mysqli_num_rows($booking_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}
$booking_id = mysqli_fetch_assoc($booking_result)['id'];

// Get services from the first shoe associated with the booking
$services_query = "SELECT * FROM booking_shoes WHERE booking_id = '$booking_id' LIMIT 1";
$services_result = mysqli_query($conn, $services_query);

$selected_services = [];
if ($services_result && mysqli_num_rows($services_result) > 0) {
    $shoe_row = mysqli_fetch_assoc($services_result);
    foreach ($shoe_row as $key => $value) {
        if ($value == 1 && $key !== 'id' && $key !== 'booking_id' && $key !== 'shoe_number') {
            $selected_services[] = $key;
        }
    }
}

echo json_encode([
    'success' => true,
    'services' => $selected_services
]);

mysqli_close($conn);