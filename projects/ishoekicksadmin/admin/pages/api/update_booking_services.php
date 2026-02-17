<?php
// This is my update_booking_services.php
require_once '../../includes/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$booking_reference = isset($_POST['booking_reference']) ? trim($_POST['booking_reference']) : '';

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

// Service map for prices
$service_map = [
    'regular_cleaning' => ['name' => 'Regular Cleaning', 'price' => 499],
    'vip_cleaning' => ['name' => 'VIP Cleaning', 'price' => 699],
    'repainting' => ['name' => 'Repainting', 'price' => 1699],
    'restitching' => ['name' => 'Restitching', 'price' => 1699],
    'restoring' => ['name' => 'Restoring', 'price' => 1699],
    'partial_regluing' => ['name' => 'Partial Regluing', 'price' => 1699],
    'single_sole' => ['name' => 'Single Sole', 'price' => 2199],
    'sole_pair' => ['name' => 'Sole Pair', 'price' => 2699],
    'multiple_sole' => ['name' => 'Multiple Sole', 'price' => 2899],
    'deodorizing' => ['name' => 'Deodorizing', 'price' => 200],
    'rush_fee' => ['name' => 'Rush Fee', 'price' => 149]
];

// Get all shoes for this booking
$shoes_query = "SELECT id, shoe_number FROM booking_shoes WHERE booking_id = '$booking_id' ORDER BY shoe_number ASC";
$shoes_result = mysqli_query($conn, $shoes_query);

if (!$shoes_result) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch shoes.']);
    exit;
}

$shoes = [];
while ($row = mysqli_fetch_assoc($shoes_result)) {
    $shoes[] = $row;
}

$new_total_amount = 0;
$updated_count = 0;

// Update each shoe's services
foreach ($shoes as $index => $shoe) {
    $shoe_num = $index + 1;
    $shoe_id = $shoe['id'];
    
    // Get services for this shoe
    $shoe_services = isset($_POST["shoe{$shoe_num}_services"]) ? $_POST["shoe{$shoe_num}_services"] : [];
    $other_specify = isset($_POST["shoe{$shoe_num}_other_specify"]) ? trim($_POST["shoe{$shoe_num}_other_specify"]) : '';
    $other_specify_sql = mysqli_real_escape_string($conn, $other_specify);
    
    // Build update fields
    $update_fields = [];
    
    // Reset all service fields to 0, then set selected ones to 1
    foreach (array_keys($service_map) as $service_key) {
        $service_key_sql = mysqli_real_escape_string($conn, $service_key);
        if (in_array($service_key, $shoe_services)) {
            $update_fields[] = "`{$service_key_sql}` = 1";
            $new_total_amount += $service_map[$service_key]['price'];
        } else {
            $update_fields[] = "`{$service_key_sql}` = 0";
        }
    }
    
    // Add other_specify field
    $update_fields[] = "`other_specify` = '{$other_specify_sql}'";
    
    // Update this shoe's services
    $update_shoe_query = "UPDATE booking_shoes SET " . implode(', ', $update_fields) . " WHERE id = '$shoe_id'";
    
    if (mysqli_query($conn, $update_shoe_query)) {
        $updated_count++;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update shoe ' . $shoe_num . ': ' . mysqli_error($conn)]);
        exit;
    }
}

// Handle manual services
$manual_services = isset($_POST['manual_services']) ? $_POST['manual_services'] : [];

// First, get all existing manual service IDs for this booking
$existing_ids_query = "SELECT id FROM manual_services WHERE booking_reference = '$booking_reference_sql'";
$existing_ids_result = mysqli_query($conn, $existing_ids_query);
$existing_ids = [];
while ($row = mysqli_fetch_assoc($existing_ids_result)) {
    $existing_ids[] = $row['id'];
}

$processed_ids = [];

foreach ($manual_services as $key => $service) {
    $service_name = isset($service['name']) ? trim($service['name']) : '';
    $service_price = isset($service['price']) ? floatval($service['price']) : 0;
    $shoe_number = isset($service['shoe_number']) ? intval($service['shoe_number']) : 1;
    
    if (empty($service_name) || $service_price <= 0) {
        continue; // Skip invalid entries
    }
    
    $service_name_sql = mysqli_real_escape_string($conn, $service_name);
    $service_price_sql = mysqli_real_escape_string($conn, $service_price);
    $shoe_number_sql = mysqli_real_escape_string($conn, $shoe_number);
    
    // Check if this is an update (has ID) or new entry
    if (isset($service['id']) && !empty($service['id']) && is_numeric($service['id'])) {
        $service_id = intval($service['id']);
        $processed_ids[] = $service_id;
        
        // Update existing manual service
        $update_manual = "UPDATE manual_services 
                         SET service_name = '$service_name_sql', 
                             service_price = '$service_price_sql',
                             shoe_number = '$shoe_number_sql'
                         WHERE id = '$service_id' AND booking_reference = '$booking_reference_sql'";
        mysqli_query($conn, $update_manual);
    } else {
        // Insert new manual service
        $insert_manual = "INSERT INTO manual_services (booking_reference, shoe_number, service_name, service_price) 
                         VALUES ('$booking_reference_sql', '$shoe_number_sql', '$service_name_sql', '$service_price_sql')";
        if (mysqli_query($conn, $insert_manual)) {
            $processed_ids[] = mysqli_insert_id($conn);
        }
    }
    
    $new_total_amount += $service_price;
}

// Delete manual services that were removed (exist in DB but not in processed list)
$ids_to_delete = array_diff($existing_ids, $processed_ids);
if (!empty($ids_to_delete)) {
    $ids_string = implode(',', array_map('intval', $ids_to_delete));
    $delete_query = "DELETE FROM manual_services WHERE id IN ($ids_string) AND booking_reference = '$booking_reference_sql'";
    mysqli_query($conn, $delete_query);
}

// Update total_amount in bookings table
$new_total_amount_sql = mysqli_real_escape_string($conn, $new_total_amount);
$update_booking_query = "UPDATE bookings SET total_amount = '$new_total_amount_sql' WHERE id = '$booking_id'";

if (mysqli_query($conn, $update_booking_query)) {
    echo json_encode([
        'success' => true, 
        'message' => 'Services and total amount updated successfully.',
        'updated_shoes' => $updated_count,
        'new_total' => $new_total_amount
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update total amount: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>