<?php
// get_tracking_details.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// === DATABASE CONFIGURATION ===
$host = 'localhost';
$dbname = 'ishoekicks_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// === CHECK REQUEST METHOD ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed']);
    exit;
}

// === INPUTS ===
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$booking_reference = isset($_POST['booking_reference']) ? trim($_POST['booking_reference']) : '';

// === SERVICE MAP ===
$service_map = [
    'regular_cleaning' => ['name' => 'Regular Cleaning', 'price' => 199],
    'vip_cleaning' => ['name' => 'VIP Cleaning', 'price' => 399],
    'repainting' => ['name' => 'Repainting', 'price' => 499],
    'restitching' => ['name' => 'Restitching', 'price' => 299],
    'restoring' => ['name' => 'Restoring', 'price' => 599],
    'partial_regluing' => ['name' => 'Partial Regluing', 'price' => 249],
    'single_sole' => ['name' => 'Single Sole', 'price' => 349],
    'sole_pair' => ['name' => 'Sole Pair', 'price' => 649],
    'multiple_sole' => ['name' => 'Multiple Sole', 'price' => 799],
    'deodorizing' => ['name' => 'Deodorizing', 'price' => 99],
    'rush_fee' => ['name' => 'Rush Fee', 'price' => 200]
];

// === DETERMINE ACCESS TYPE ===
// If admin session is active and booking_reference is given → ADMIN MODE
// Else if only email is given → CUSTOMER MODE
$is_admin = isset($_SESSION["admin_logged_in"]) && $_SESSION["admin_logged_in"] === true && !empty($booking_reference);

// --------------------
// CUSTOMER MODE
// --------------------
if (!$is_admin && !empty($email)) {
    try {
        // If a booking reference is provided, fetch that specific booking.
        // Otherwise, fetch all bookings for the email.
        if (!empty($booking_reference)) {
             $booking_sql = "SELECT * FROM bookings 
                            WHERE customer_email = :email AND booking_reference = :booking_reference
                            LIMIT 1";
            $stmt = $pdo->prepare($booking_sql);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':booking_reference', $booking_reference);
        } else {
            // Find all bookings for this email to return a list
            $booking_sql = "SELECT id, booking_reference, customer_name, booking_date, tracking_status, total_amount FROM bookings 
                            WHERE customer_email = :email 
                            ORDER BY booking_date DESC, id DESC";
            $stmt = $pdo->prepare($booking_sql);
            $stmt->bindParam(':email', $email);
        }
       $stmt->execute();

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'No bookings found for this email.']);
            exit;
        }

        // If no booking reference was given, return the list of all bookings.
        if (empty($booking_reference)) {
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'source' => 'customer_list',
                'bookings' => $bookings
            ]);
            exit;
        }

        // If a booking reference was provided, continue to fetch full details for that single booking.
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        // ✅ NEW: Get tracking data (proof images) from trackings table
        $tracking_sql = "SELECT * FROM trackings WHERE booking_id = :booking_id LIMIT 1";
        $tracking_stmt = $pdo->prepare($tracking_sql);
        $tracking_stmt->bindParam(':booking_id', $booking['id']);
        $tracking_stmt->execute();
    
        $tracking_data = null;
        if ($tracking_stmt->rowCount() > 0) {
            $tracking_data = $tracking_stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Get shoes
        $shoes_stmt = $pdo->prepare("SELECT * FROM booking_shoes WHERE booking_id = :booking_id ORDER BY shoe_number");
        $shoes_stmt->bindParam(':booking_id', $booking['id']);
        $shoes_stmt->execute();

        $shoes = [];
        while ($shoe = $shoes_stmt->fetch(PDO::FETCH_ASSOC)) {
            $services = [];
            foreach ($service_map as $key => $service) {
                if ($shoe[$key] == 1) {
                    $services[] = $service['name'] . ' – ₱' . $service['price'];
                }
            }
            if ($shoe['if_others'] == 1 && !empty($shoe['other_specify'])) {
                $services[] = $shoe['other_specify'] . ' – ₱' . number_format($shoe['shoe_price'], 0);
            }
            $shoes[] = [
                'shoe_number' => $shoe['shoe_number'],
                'shoe_info' => $shoe['shoe_brand'] . ', ' . $shoe['shoe_color'] . ', ' . 
                              $shoe['shoe_model'] . ', Size: ' . $shoe['shoe_size'],
                'services' => $services,
                'price' => $shoe['shoe_price']
            ];
        }

        // Response for customer
        echo json_encode([
            'success' => true,
            'source' => 'customer',
            'booking' => [
                'id' => $booking['id'],
                'booking_reference' => $booking['booking_reference'],
                'customer_name' => $booking['customer_name'],
                'customer_email' => $booking['customer_email'],
                'customer_phone' => $booking['customer_phone'],
                'booking_date' => $booking['booking_date'],
                'booking_status' => $booking['tracking_status'],
                'payment_method' => $booking['payment_method'],
                'total_amount' => $booking['total_amount'],
                'receipt_image' => $tracking_data['receipt_image'] ?? '',
                'proof_of_service_before' => $tracking_data['proof_of_service_before'] ?? '',

                // add rejection reason
                'rejection_reason' => $booking['rejection_reason'] ?? '',

                'proof_of_service_after' => $tracking_data['proof_of_service_after'] ?? '',
                'details_image' => $tracking_data['details_image'] ?? '',
                'proof_of_delivery' => $tracking_data['proof_of_delivery'] ?? ''

               
            ],
            'shoes' => $shoes
        ]);
        exit;

    
    

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// --------------------
// ADMIN MODE
// --------------------
if ($is_admin && !empty($booking_reference)) {
    try {
        // Booking
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_reference = :ref");
        $stmt->bindParam(':ref', $booking_reference);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
            exit;
        }

        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch corresponding tracking data
        $tracking_sql = "SELECT * FROM trackings WHERE booking_id = :booking_id LIMIT 1";
        $tracking_stmt = $pdo->prepare($tracking_sql);
        $tracking_stmt->bindParam(':booking_id', $booking['id']);
        $tracking_stmt->execute();
        $tracking_data = $tracking_stmt->rowCount() > 0 
            ? $tracking_stmt->fetch(PDO::FETCH_ASSOC) 
            : null;

        // Shoes
        $shoes_stmt = $pdo->prepare("SELECT * FROM booking_shoes WHERE booking_id = :booking_id ORDER BY shoe_number");
        $shoes_stmt->bindParam(':booking_id', $booking['id']);
        $shoes_stmt->execute();

        $shoes = [];
        while ($shoe = $shoes_stmt->fetch(PDO::FETCH_ASSOC)) {
            $services = [];
            foreach ($service_map as $key => $service) {
                if ($shoe[$key] == 1) {
                    $services[] = $service['name'] . ' – ₱' . $service['price'];
                }
            }
            if ($shoe['if_others'] == 1 && !empty($shoe['other_specify'])) {
                $services[] = $shoe['other_specify'] . ' – ₱' . number_format($shoe['shoe_price'], 0);
            }
            $shoes[] = [
                'shoe_number' => $shoe['shoe_number'],
                'shoe_info' => $shoe['shoe_brand'] . ', ' . $shoe['shoe_color'] . ', ' .
                              $shoe['shoe_model'] . ', Size: ' . $shoe['shoe_size'],
                'services' => $services,
                'price' => $shoe['shoe_price']
            ];
        }

        // Tracking history logs
        $history_stmt = $pdo->prepare("SELECT * FROM tracking_logs WHERE booking_id = :booking_id ORDER BY created_at DESC");
        $history_stmt->bindParam(':booking_id', $booking['id']);
        $history_stmt->execute();
        $history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Response for admin
        echo json_encode([
            'success' => true,
            'source' => 'admin',
            'data' => [
                'id' => $booking['id'],
                'booking_reference' => $booking['booking_reference'],
                'customer_name' => $booking['customer_name'],
                'customer_email' => $booking['customer_email'],
                'customer_phone' => $booking['customer_phone'],
                'customer_address' => $booking['customer_address'],
                'branch' => $booking['branch'],
                'booking_date' => $booking['booking_date'],
                'tracking_status' => $booking['tracking_status'] ?? 'Process',
                'payment_method' => $booking['payment_method'],
                'total_amount' => $booking['total_amount'],
                
                // ✅ UPDATED: Include proof images from trackings table
                'receipt_image' => $tracking_data['receipt_image'] ?? '',
                'proof_of_service_before' => $tracking_data['proof_of_service_before'] ?? '',
                'proof_of_service_after' => $tracking_data['proof_of_service_after'] ?? '',
                'details_image' => $tracking_data['details_image'] ?? '',
                'proof_of_delivery' => $tracking_data['proof_of_delivery'] ?? '',

                // ✅ Include rejection_reason from bookings table
                'rejection_reason' => $booking['rejection_reason'] ?? '',


                'confirmation_accepted' => $booking['confirmation_accepted'] ?? 0,
                'confirmation_accepted_at' => $booking['confirmation_accepted_at'] ?? null,
                'updated_at' => $booking['updated_at']

                
            ],
            'shoes' => $shoes,
            'history' => $history
        ]);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// --------------------
// INVALID REQUEST
// --------------------
echo json_encode([
    'success' => false,
    'message' => 'Invalid request: Please provide either an email (for customer) or booking_reference (for admin).'
]);
exit;
?>
