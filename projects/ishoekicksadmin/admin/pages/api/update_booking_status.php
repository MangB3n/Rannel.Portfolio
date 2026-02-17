<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require_once '../../includes/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

try {
    $booking_reference = isset($_POST['booking_reference']) ? trim($_POST['booking_reference']) : '';
    
    if (empty($booking_reference)) {
        echo json_encode(['success' => false, 'message' => 'Booking reference is required']);
        exit();
    }
    
    // Escape strings
    $booking_reference = mysqli_real_escape_string($conn, $booking_reference);
    
    $valid_booking_statuses = ['Pending', 'Accepted', 'Completed', 'Cancelled'];
    $valid_tracking_statuses = ['Process', 'Ready', 'Confirmation', 'Completed'];
    
    $update_fields = [];
    $response_data = [
        'booking_reference' => $booking_reference
    ];

    // --- Handle Total Amount Update ---
    if (isset($_POST['total_amount'])) {
        $total_amount = trim($_POST['total_amount']);
        if (!is_numeric($total_amount) || $total_amount < 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid total amount. Must be a non-negative number.']);
            exit();
        }
        $update_fields[] = "total_amount = '" . mysqli_real_escape_string($conn, $total_amount) . "'";
        $response_data['total_amount'] = $total_amount;
    }

    // --- Handle Booking Status Update ---
    $booking_status = null;
    if (isset($_POST['booking_status']) && !empty($_POST['booking_status'])) {
        $booking_status = trim($_POST['booking_status']);
        if (!in_array($booking_status, $valid_booking_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid booking status']);
            exit();
        }
        $update_fields[] = "booking_status = '" . mysqli_real_escape_string($conn, $booking_status) . "'";
        $response_data['booking_status'] = $booking_status;
    }

    // --- Handle Tracking Status Update ---
    if (isset($_POST['tracking_status']) && !empty($_POST['tracking_status'])) {
        $tracking_status = trim($_POST['tracking_status']);
        if (!in_array($tracking_status, $valid_tracking_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid tracking status']);
            exit();
        }
        $update_fields[] = "tracking_status = '" . mysqli_real_escape_string($conn, $tracking_status) . "'";
        $response_data['tracking_status'] = $tracking_status;
    }

    // --- Handle Receipt Image Update ---
    if (isset($_POST['receipt_image']) && !empty($_POST['receipt_image'])) {
        $receipt_image = trim($_POST['receipt_image']);
        
        // Validate it's a valid path/filename
        if (strlen($receipt_image) > 500) {
            echo json_encode(['success' => false, 'message' => 'Receipt image path is too long']);
            exit();
        }
        
        $update_fields[] = "receipt_image = '" . mysqli_real_escape_string($conn, $receipt_image) . "'";
        $response_data['receipt_image'] = $receipt_image;
    }

    // --- Handle File Uploads (if file is provided) ---
    $upload_dir = '../../uploads/';
    
    if (isset($_FILES['receipt_image_file']) && $_FILES['receipt_image_file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['receipt_image_file'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        
        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, GIF, and PDF are allowed.']);
            exit();
        }
        
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
            exit();
        }
        
        // Create upload directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = 'receipt_image_' . $booking_reference . '_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $update_fields[] = "receipt_image = '" . mysqli_real_escape_string($conn, $file_name) . "'";
            $response_data['receipt_image'] = $file_name;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload receipt image']);
            exit();
        }
    }

    // --- Execute Update ---
    if (empty($update_fields)) {
        echo json_encode(['success' => false, 'message' => 'No valid fields provided for update.']);
        exit();
    }
    
    // Add updated timestamp
    $update_fields[] = "updated_at = NOW()";
    
    // Build and execute query
    $query = "UPDATE bookings SET " . implode(', ', $update_fields) . " WHERE booking_reference = '$booking_reference'";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Booking updated successfully',
            'data' => $response_data
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating booking: ' . mysqli_error($conn)]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

mysqli_close($conn);
?>