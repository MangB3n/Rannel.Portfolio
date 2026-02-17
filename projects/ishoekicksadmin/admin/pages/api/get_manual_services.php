<?php
require_once '../../includes/database.php';

header('Content-Type: application/json');

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

// Get manual services for this booking
$services_query = "SELECT id, service_name, service_price FROM manual_services WHERE booking_id = '$booking_id' ORDER BY id ASC";
$services_result = mysqli_query($conn, $services_query);

$services = [];
if ($services_result) {
    while ($row = mysqli_fetch_assoc($services_result)) {
        $services[] = [
            'id' => $row['id'],
            'service_name' => $row['service_name'],
            'service_price' => floatval($row['service_price'])
        ];
    }
}

echo json_encode([
    'success' => true,
    'services' => $services
]);

mysqli_close($conn);
?>