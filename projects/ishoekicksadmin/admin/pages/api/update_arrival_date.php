<?php
// api/update_arrival_date.php
include('../../includes/database.php');
session_start();

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate input
if (!isset($_POST['booking_reference']) || !isset($_POST['arrival_date'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$booking_reference = mysqli_real_escape_string($conn, $_POST['booking_reference']);
$arrival_date = mysqli_real_escape_string($conn, $_POST['arrival_date']);

// If arrival_date is empty or 0000-00-00, set it to 7 days from booking date
if (empty($arrival_date) || $arrival_date == '0000-00-00') {
    // Get the booking date
    $booking_query = "SELECT booking_date FROM bookings WHERE booking_reference = ?";
    $stmt_check = mysqli_prepare($conn, $booking_query);
    mysqli_stmt_bind_param($stmt_check, "s", $booking_reference);
    mysqli_stmt_execute($stmt_check);
    $result = mysqli_stmt_get_result($stmt_check);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $arrival_date = date('Y-m-d', strtotime($row['booking_date'] . ' +7 days'));
    } else {
        $arrival_date = date('Y-m-d', strtotime('+7 days'));
    }
    mysqli_stmt_close($stmt_check);
}
// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrival_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Update the arrival date in the database
$query = "UPDATE bookings SET arrival_date = ? WHERE booking_reference = ?";
$stmt = mysqli_prepare($conn, $query);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, "ss", $arrival_date, $booking_reference);

if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Arrival date updated successfully',
            'new_date' => $arrival_date
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No booking found or date unchanged']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating arrival date: ' . mysqli_error($conn)]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>