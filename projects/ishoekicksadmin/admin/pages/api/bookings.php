<?php
require_once '../../includes/database.php';
//api/bookings.php

header('Content-Type: application/json');

// Enable CORS for mobile app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false];

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Fetch bookings (accept either customer_id or user_id from clients)
        if (isset($_GET['customer_id']) || isset($_GET['user_id'])) {
            $customerId = mysqli_real_escape_string($conn, isset($_GET['customer_id']) ? $_GET['customer_id'] : $_GET['user_id']);
            // Select all relevant fields from bookings table
            $query = "SELECT id, user_id, booking_reference, tracking_id, customer_name, customer_email, 
                            customer_phone, customer_address, arrival_date, shoe1_image, shoe2_image, 
                            branch, payment_method, total_amount, booking_date, booking_status, 
                            tracking_status, created_at, updated_at 
                     FROM bookings 
                     WHERE user_id = '$customerId' 
                     ORDER BY booking_date DESC";
        } else {
            $query = "SELECT id, user_id, booking_reference, tracking_id, customer_name, customer_email, 
                            customer_phone, customer_address, arrival_date, shoe1_image, shoe2_image, 
                            branch, payment_method, total_amount, booking_date, booking_status, 
                            tracking_status, created_at, updated_at 
                     FROM bookings 
                     ORDER BY booking_date DESC";
        }
        
        $result = mysqli_query($conn, $query);
        $bookings = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            // Map database fields to expected format for backward compatibility
            $row['status'] = $row['booking_status']; // Add 'status' field
            $row['service_type'] = $row['tracking_status']; // Map tracking_status to service_type
            $row['shoe_details'] = $row['customer_name'] . ' - ' . $row['branch']; // Create shoe_details from available data
            $bookings[] = $row;
        }
        
        $response = ['success' => true, 'data' => $bookings];
        break;

    case 'POST':
        // Create new booking
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ((isset($data['customer_id']) || isset($data['user_id'])) && isset($data['customer_name'])) {
            $userId = mysqli_real_escape_string($conn, isset($data['customer_id']) ? $data['customer_id'] : $data['user_id']);
            $customerName = mysqli_real_escape_string($conn, $data['customer_name']);
            $customerEmail = isset($data['customer_email']) ? mysqli_real_escape_string($conn, $data['customer_email']) : '';
            $customerPhone = isset($data['customer_phone']) ? mysqli_real_escape_string($conn, $data['customer_phone']) : '';
            $customerAddress = isset($data['customer_address']) ? mysqli_real_escape_string($conn, $data['customer_address']) : '';
            $branch = isset($data['branch']) ? mysqli_real_escape_string($conn, $data['branch']) : '';
            $paymentMethod = isset($data['payment_method']) ? mysqli_real_escape_string($conn, $data['payment_method']) : 'Cash';
            $totalAmount = isset($data['total_amount']) ? mysqli_real_escape_string($conn, $data['total_amount']) : 0;
            
            // Generate booking reference
            $bookingReference = 'BK' . date('Ymd') . rand(1000, 9999);
            
            $query = "INSERT INTO bookings (user_id, booking_reference, customer_name, customer_email, 
                                          customer_phone, customer_address, branch, payment_method, 
                                          total_amount, booking_status, tracking_status) 
                     VALUES ('$userId', '$bookingReference', '$customerName', '$customerEmail', 
                            '$customerPhone', '$customerAddress', '$branch', '$paymentMethod', 
                            '$totalAmount', 'Pending', 'Process')";
            
            if (mysqli_query($conn, $query)) {
                $response = [
                    'success' => true, 
                    'message' => 'Booking created successfully',
                    'booking_id' => mysqli_insert_id($conn),
                    'booking_reference' => $bookingReference
                ];
            } else {
                $response = ['success' => false, 'error' => mysqli_error($conn)];
            }
        } else {
            $response = ['success' => false, 'error' => 'Missing required fields'];
        }
        break;

    case 'PUT':
        // Update booking status
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['booking_id']) && isset($data['status'])) {
            $bookingId = mysqli_real_escape_string($conn, $data['booking_id']);
            $status = mysqli_real_escape_string($conn, $data['status']);
            
            // Update booking_status field
            $query = "UPDATE bookings SET booking_status = '$status' WHERE id = '$bookingId'";
            
            if (mysqli_query($conn, $query)) {
                $response = ['success' => true, 'message' => 'Status updated successfully'];
            } else {
                $response = ['success' => false, 'error' => mysqli_error($conn)];
            }
        } else {
            $response = ['success' => false, 'error' => 'Missing required fields'];
        }
        break;

    case 'DELETE':
        // Cancel booking
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['booking_id'])) {
            $bookingId = mysqli_real_escape_string($conn, $data['booking_id']);
            
            // Update booking_status to Cancelled
            $query = "UPDATE bookings SET booking_status = 'Cancelled' WHERE id = '$bookingId'";
            
            if (mysqli_query($conn, $query)) {
                $response = ['success' => true, 'message' => 'Booking cancelled successfully'];
            } else {
                $response = ['success' => false, 'error' => mysqli_error($conn)];
            }
        } else {
            $response = ['success' => false, 'error' => 'Missing booking ID'];
        }
        break;
}

echo json_encode($response);
mysqli_close($conn);
?>