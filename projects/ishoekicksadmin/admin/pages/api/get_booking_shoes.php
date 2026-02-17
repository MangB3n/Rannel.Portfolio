<?php
require_once '../../includes/database.php';

header('Content-Type: application/json');

$booking_reference = $_GET['booking_reference'] ?? '';

if (empty($booking_reference)) {
    echo json_encode(['success' => false, 'message' => 'No booking reference provided']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM bookings WHERE booking_reference = ?");
$stmt->bind_param("s", $booking_reference);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

$booking_id = $booking['id'];
$stmt = $conn->prepare("SELECT * FROM booking_shoes WHERE booking_id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

$shoes = [];
while ($row = $result->fetch_assoc()) {
    $shoes[] = $row;
}

echo json_encode(['success' => true, 'shoes' => $shoes]);
?>