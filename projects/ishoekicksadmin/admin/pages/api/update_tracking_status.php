<?php
// update_tracking_status.php

// Disable error display to prevent HTML errors in JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON header
header('Content-Type: application/json');

// Start output buffering to catch any accidental output
ob_start();

try {
    // Helper function to ensure a tracking record exists
    function ensure_tracking_record($conn, $booking_id, $booking_data) {
        $check_query = "SELECT id FROM trackings WHERE booking_id = '$booking_id'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) === 0) {
            $customer_name = mysqli_real_escape_string($conn, $booking_data['customer_name']);
            $customer_email = mysqli_real_escape_string($conn, $booking_data['customer_email']);
            $tracking_status = mysqli_real_escape_string($conn, $booking_data['tracking_status'] ?? 'Process');
            
            $insert_query = "INSERT INTO trackings (booking_id, customer_name, customer_email, tracking_status, updated_time) 
                             VALUES ('$booking_id', '$customer_name', '$customer_email', '$tracking_status', NOW())";
            
            if (!mysqli_query($conn, $insert_query)) {
                error_log("Failed to create tracking record for booking ID $booking_id: " . mysqli_error($conn));
            }
        }
    }

    require_once '../../includes/database.php';
    
    $response = ['success' => false, 'message' => 'Unknown error'];
    
    // Debug logging
    error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Invalid request method. Use POST. Received: ' . $_SERVER['REQUEST_METHOD'];
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    // Get POST data
    $bookingReference = isset($_POST['booking_reference']) ? trim($_POST['booking_reference']) : '';
    $newStatus = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
    $adminEmail = isset($_POST['admin_email']) ? trim($_POST['admin_email']) : 'admin';
    $adminNotes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
    
    if (empty($bookingReference)) {
        $response['message'] = 'Booking reference is required';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    // Escape strings for database
    $bookingReference = mysqli_real_escape_string($conn, $bookingReference);
    $newStatus = mysqli_real_escape_string($conn, $newStatus);
    
    // Get the booking by reference (booking_id)
    $query = "SELECT * FROM bookings WHERE booking_reference = '$bookingReference' OR id = '$bookingReference'";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        $response['message'] = 'Booking not found';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    $booking = mysqli_fetch_assoc($result);
    $bookingId = $booking['id'] ?? $booking['booking_id'];

    // Ensure a corresponding record exists in the `trackings` table
    ensure_tracking_record($conn, $bookingId, $booking);
    
    // --- Handle file uploads ---
    $uploadDir = '../../uploads/';
    
    // Create uploads directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Define the ishoekicks upload directory
    $ishoekicksUploadDir = $_SERVER['DOCUMENT_ROOT'] . '/ishoekicks/uploads/';
    
    // Log the path for debugging
    error_log("ishoekicks upload directory: " . $ishoekicksUploadDir);
    error_log("Directory exists: " . (is_dir($ishoekicksUploadDir) ? 'YES' : 'NO'));
    
    $uploaded_files = [];
    $bookings_update_fields = [];
    $trackings_update_fields = [];

    // Receipt image
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $filename = time() . '_receipt_' . basename($_FILES['receipt_image']['name']);
        $adminUploadPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $adminUploadPath)) {
            error_log("Receipt image uploaded to: $adminUploadPath");
            
            // Copy to ishoekicks project
            if (is_dir($ishoekicksUploadDir)) {
                $destPath = $ishoekicksUploadDir . $filename;
                if (copy($adminUploadPath, $destPath)) {
                    error_log("Receipt image copied to: $destPath");
                } else {
                    error_log("Failed to copy receipt image to: $destPath");
                }
            } else {
                error_log("ishoekicks upload directory does not exist: $ishoekicksUploadDir");
            }
            
            $escapedFilename = mysqli_real_escape_string($conn, $filename);
            mysqli_query($conn, "UPDATE trackings SET receipt_image = '$escapedFilename' WHERE booking_id = '$bookingId'");
            mysqli_query($conn, "UPDATE bookings SET receipt_image = '$escapedFilename' WHERE id = '$bookingId'");
            $uploaded_files['receipt_image'] = $filename;
        }
    }

    // Proof of service before - FIXED variable name
    if (isset($_FILES['proof_of_service_before']) && $_FILES['proof_of_service_before']['error'] === UPLOAD_ERR_OK) {
        $filename = time() . '_proof_service_before_' . basename($_FILES['proof_of_service_before']['name']);
        $adminUploadPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['proof_of_service_before']['tmp_name'], $adminUploadPath)) {
            error_log("Proof of service before uploaded to: $adminUploadPath");
            
            // Copy to ishoekicks project
            if (is_dir($ishoekicksUploadDir)) {
                $destPath = $ishoekicksUploadDir . $filename;
                if (copy($adminUploadPath, $destPath)) {
                    error_log("Proof of service before copied to: $destPath");
                } else {
                    error_log("Failed to copy proof of service before to: $destPath");
                }
            }
            
            $escapedFilename = mysqli_real_escape_string($conn, $filename);
            mysqli_query($conn, "UPDATE trackings SET proof_of_service_before = '$escapedFilename' WHERE booking_id = '$bookingId'");
            // Update bookings table with generic proof_of_service column
            mysqli_query($conn, "UPDATE bookings SET proof_of_service = '$escapedFilename' WHERE id = '$bookingId'");
            $uploaded_files['proof_of_service_before'] = $filename;
        }
    }

    // Proof of service after - FIXED error_log string
    if (isset($_FILES['proof_of_service_after']) && $_FILES['proof_of_service_after']['error'] === UPLOAD_ERR_OK) {
        $filename = time() . '_proof_service_after_' . basename($_FILES['proof_of_service_after']['name']);
        $adminUploadPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['proof_of_service_after']['tmp_name'], $adminUploadPath)) {
            error_log("Proof of service after uploaded to: $adminUploadPath");

            // Copy to ishoekicks project
            if (is_dir($ishoekicksUploadDir)) {
                $destPath = $ishoekicksUploadDir . $filename;
                if (copy($adminUploadPath, $destPath)) {
                    error_log("Proof of service after copied to: $destPath");
                } else {
                    error_log("Failed to copy proof of service after to: $destPath");
                }
            }
            
            $escapedFilename = mysqli_real_escape_string($conn, $filename);
            mysqli_query($conn, "UPDATE trackings SET proof_of_service_after = '$escapedFilename' WHERE booking_id = '$bookingId'");
            // Update bookings table - check if column exists, otherwise use generic column
            $check_column = mysqli_query($conn, "SHOW COLUMNS FROM bookings LIKE 'proof_of_service_after'");
            if (mysqli_num_rows($check_column) > 0) {
                mysqli_query($conn, "UPDATE bookings SET proof_of_service_after = '$escapedFilename' WHERE id = '$bookingId'");
            }
            $uploaded_files['proof_of_service_after'] = $filename;
        }
    }

    // Details image - matching form field name "details_image"
    if (isset($_FILES['details_image']) && $_FILES['details_image']['error'] === UPLOAD_ERR_OK) {
        $filename = time() . '_details_' . basename($_FILES['details_image']['name']);
        $adminUploadPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['details_image']['tmp_name'], $adminUploadPath)) {
            error_log("Details image uploaded to: $adminUploadPath");
            
            // Copy to ishoekicks project
            if (is_dir($ishoekicksUploadDir)) {
                $destPath = $ishoekicksUploadDir . $filename;
                if (copy($adminUploadPath, $destPath)) {
                    error_log("Details image copied to: $destPath");
                } else {
                    error_log("Failed to copy details image to: $destPath");
                }
            }
            
            $escapedFilename = mysqli_real_escape_string($conn, $filename);
            mysqli_query($conn, "UPDATE trackings SET details_image = '$escapedFilename' WHERE booking_id = '$bookingId'");
            mysqli_query($conn, "UPDATE bookings SET details_image = '$escapedFilename' WHERE id = '$bookingId'");
            $uploaded_files['details_image'] = $filename;
        }
    }

    // Proof of delivery image
    if (isset($_FILES['proof_of_delivery']) && $_FILES['proof_of_delivery']['error'] === UPLOAD_ERR_OK) {
        $filename = time() . '_proof_delivery_' . basename($_FILES['proof_of_delivery']['name']);
        $adminUploadPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['proof_of_delivery']['tmp_name'], $adminUploadPath)) {
            error_log("Proof of delivery uploaded to: $adminUploadPath");
            
            // Copy to ishoekicks project
            if (is_dir($ishoekicksUploadDir)) {
                $destPath = $ishoekicksUploadDir . $filename;
                if (copy($adminUploadPath, $destPath)) {
                    error_log("Proof of delivery copied to: $destPath");
                } else {
                    error_log("Failed to copy proof of delivery to: $destPath");
                }
            }
            
            $escapedFilename = mysqli_real_escape_string($conn, $filename);
            mysqli_query($conn, "UPDATE trackings SET proof_of_delivery = '$escapedFilename' WHERE booking_id = '$bookingId'");
            mysqli_query($conn, "UPDATE bookings SET proof_of_delivery = '$escapedFilename' WHERE id = '$bookingId'");
            $uploaded_files['proof_of_delivery'] = $filename;
        }
    }

    // Build and execute UPDATE queries
    $bookings_update_fields[] = "tracking_status = '$newStatus'";
    $bookings_update_fields[] = "updated_at = NOW()";
    $trackings_update_fields[] = "tracking_status = '$newStatus'";
    $trackings_update_fields[] = "updated_time = NOW()";

    if (strtolower($newStatus) === 'completed') {
        $bookings_update_fields[] = "confirmation_accepted = 1";
        $bookings_update_fields[] = "confirmation_accepted_at = NOW()";
    }

    // Execute update for trackings table
    $trackings_query = "UPDATE trackings SET " . implode(', ', $trackings_update_fields) . " WHERE booking_id = '$bookingId'";
    error_log("Trackings update query: " . $trackings_query);
    mysqli_query($conn, $trackings_query);

    // Execute update for bookings table
    $updateQuery = "UPDATE bookings SET " . implode(', ', $bookings_update_fields) . " WHERE id = '$bookingId'";
    error_log("Bookings update query: " . $updateQuery);
    $query_success = mysqli_query($conn, $updateQuery);

    if ($query_success) {
        // Log the status change to the tracking_logs table
        $remarks = mysqli_real_escape_string($conn, $adminNotes);
        $changed_by = mysqli_real_escape_string($conn, $adminEmail);
        
        $log_query = "INSERT INTO tracking_logs (booking_id, new_status, remarks, changed_by, created_at) 
                      VALUES ('$bookingId', '$newStatus', '$remarks', '$changed_by', NOW())";
        
        error_log("Tracking log query: " . $log_query);
        mysqli_query($conn, $log_query);

        $response = [
            'success' => true,
            'message' => 'Tracking status updated successfully',
            'booking_id' => $bookingId,
            'new_status' => $newStatus,
            'uploaded_files' => $uploaded_files,
            'ishoekicks_path' => $ishoekicksUploadDir,
            'path_exists' => is_dir($ishoekicksUploadDir)
        ];
    } else {
        $response['message'] = 'Failed to update tracking status: ' . mysqli_error($conn);
    }
    
    // Clean buffer and output JSON
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