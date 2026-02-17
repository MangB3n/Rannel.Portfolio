<?php
require_once '../../includes/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false];

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Fetch specific customer - check both tables
            $id = mysqli_real_escape_string($conn, $_GET['id']);
            
            // Try users table first
            $query = "SELECT id, name, email, contact as phone, created_at FROM users WHERE id = '$id'";
            $result = mysqli_query($conn, $query);
            
            if ($customer = mysqli_fetch_assoc($result)) {
                $response = ['success' => true, 'data' => $customer];
            } else {
                // Try users_facebook table
                $query = "SELECT id, name, email, contact as phone, facebook_id, created_at FROM users_facebook WHERE id = '$id'";
                $result = mysqli_query($conn, $query);
                
                if ($customer = mysqli_fetch_assoc($result)) {
                    $response = ['success' => true, 'data' => $customer];
                } else {
                    $response = ['success' => false, 'error' => 'Customer not found'];
                }
            }
        } else {
            // Fetch all customers from both tables
            $query = "
                SELECT id, name, email, contact as phone, NULL as facebook_id, created_at, 'regular' as account_type
                FROM users
                UNION
                SELECT id, name, email, contact as phone, facebook_id, created_at, 'facebook' as account_type
                FROM users_facebook
                ORDER BY created_at DESC
            ";
            $result = mysqli_query($conn, $query);
            $customers = [];

            while ($row = mysqli_fetch_assoc($result)) {
                $customers[] = $row;
            }

            $response = ['success' => true, 'data' => $customers];
        }
        break;

    case 'POST':
        // Create new user (customer) - only in users table
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);

        // Check if email already exists in both tables
        $checkQuery = "
            SELECT id FROM users WHERE email = '$email' 
            UNION 
            SELECT id FROM users_facebook WHERE email = '$email'
        ";
        $checkResult = mysqli_query($conn, $checkQuery);

        if (mysqli_num_rows($checkResult) > 0) {
            $response = ['success' => false, 'error' => 'Email already exists'];
        } else {
            // Insert into users table
            $query = "INSERT INTO users (name, email, contact, created_at) 
                     VALUES ('$name', '$email', '$phone', NOW())";

            if (mysqli_query($conn, $query)) {
                $response = [
                    'success' => true,
                    'message' => 'Customer created successfully',
                    'id' => mysqli_insert_id($conn)
                ];
            } else {
                $response = ['success' => false, 'error' => mysqli_error($conn)];
            }
        }
        break;

    case 'PUT':
        // Update customer
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['id'])) {
            $id = mysqli_real_escape_string($conn, $data['id']);
            $name = mysqli_real_escape_string($conn, $data['name']);
            $email = mysqli_real_escape_string($conn, $data['email']);
            $phone = mysqli_real_escape_string($conn, $data['phone']);
            
            // Check which table the customer is in
            $checkTable = "SELECT id FROM users WHERE id = '$id'";
            $checkResult = mysqli_query($conn, $checkTable);
            
            if (mysqli_num_rows($checkResult) > 0) {
                // Customer is in users table
                $checkEmailQuery = "
                    SELECT id FROM users WHERE email = '$email' AND id != '$id'
                    UNION
                    SELECT id FROM users_facebook WHERE email = '$email'
                ";
                $checkEmailResult = mysqli_query($conn, $checkEmailQuery);
                
                if (mysqli_num_rows($checkEmailResult) > 0) {
                    $response = ['success' => false, 'error' => 'Email already exists'];
                } else {
                    $query = "UPDATE users SET 
                             name = '$name',
                             email = '$email',
                             contact = '$phone'
                             WHERE id = '$id'";
                    
                    if (mysqli_query($conn, $query)) {
                        $response = ['success' => true, 'message' => 'Customer updated successfully'];
                    } else {
                        $response = ['success' => false, 'error' => mysqli_error($conn)];
                    }
                }
            } else {
                // Try users_facebook table
                $checkTable = "SELECT id FROM users_facebook WHERE id = '$id'";
                $checkResult = mysqli_query($conn, $checkTable);
                
                if (mysqli_num_rows($checkResult) > 0) {
                    $checkEmailQuery = "
                        SELECT id FROM users WHERE email = '$email'
                        UNION
                        SELECT id FROM users_facebook WHERE email = '$email' AND id != '$id'
                    ";
                    $checkEmailResult = mysqli_query($conn, $checkEmailQuery);
                    
                    if (mysqli_num_rows($checkEmailResult) > 0) {
                        $response = ['success' => false, 'error' => 'Email already exists'];
                    } else {
                        $query = "UPDATE users_facebook SET 
                                 name = '$name',
                                 email = '$email',
                                 contact = '$phone'
                                 WHERE id = '$id'";
                        
                        if (mysqli_query($conn, $query)) {
                            $response = ['success' => true, 'message' => 'Customer updated successfully'];
                        } else {
                            $response = ['success' => false, 'error' => mysqli_error($conn)];
                        }
                    }
                } else {
                    $response = ['success' => false, 'error' => 'Customer not found'];
                }
            }
        } else {
            $response = ['success' => false, 'error' => 'Customer ID is required'];
        }
        break;

    case 'DELETE':
        // Delete customer with all related data (CASCADE DELETE)
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['id'])) {
            $id = mysqli_real_escape_string($conn, $data['id']);
            
            // Start transaction for safe deletion
            mysqli_begin_transaction($conn);
            
            try {
                // Check if customer is in users table
                $checkQuery = "SELECT email FROM users WHERE id = '$id'";
                $checkResult = mysqli_query($conn, $checkQuery);
                
                if (mysqli_num_rows($checkResult) > 0) {
                    $customer = mysqli_fetch_assoc($checkResult);
                    $userEmail = $customer['email'];
                    
                    // Delete related records in order
                    // 1. Delete bookings
                    $deleteBookings = mysqli_query($conn, "DELETE FROM bookings WHERE user_id = '$id'");
                    if (!$deleteBookings) {
                        throw new Exception('Failed to delete bookings: ' . mysqli_error($conn));
                    }
                    
                    // 2. Delete chat messages
                    $deleteChatMessages = mysqli_query($conn, "DELETE FROM chat_messages WHERE user_email = '$userEmail'");
                    if (!$deleteChatMessages) {
                        throw new Exception('Failed to delete chat messages: ' . mysqli_error($conn));
                    }
                    
                    // 3. Delete archived chat messages (if table exists)
                    $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'chat_messages_archive'");
                    if (mysqli_num_rows($tableCheck) > 0) {
                        $deleteArchived = mysqli_query($conn, "DELETE FROM chat_messages_archive WHERE user_email = '$userEmail'");
                        if (!$deleteArchived) {
                            throw new Exception('Failed to delete archived messages: ' . mysqli_error($conn));
                        }
                    }
                    
                    // 4. Delete the customer from users table
                    $deleteCustomer = mysqli_query($conn, "DELETE FROM users WHERE id = '$id'");
                    if (!$deleteCustomer) {
                        throw new Exception('Failed to delete customer: ' . mysqli_error($conn));
                    }
                    
                } else {
                    // Check in users_facebook table
                    $checkQuery = "SELECT email FROM users_facebook WHERE id = '$id'";
                    $checkResult = mysqli_query($conn, $checkQuery);
                    
                    if (mysqli_num_rows($checkResult) > 0) {
                        $customer = mysqli_fetch_assoc($checkResult);
                        $userEmail = $customer['email'];
                        
                        // Delete related records
                        // 1. Delete bookings
                        $deleteBookings = mysqli_query($conn, "DELETE FROM bookings WHERE user_id = '$id'");
                        if (!$deleteBookings) {
                            throw new Exception('Failed to delete bookings: ' . mysqli_error($conn));
                        }
                        
                        // 2. Delete chat messages
                        $deleteChatMessages = mysqli_query($conn, "DELETE FROM chat_messages WHERE user_email = '$userEmail'");
                        if (!$deleteChatMessages) {
                            throw new Exception('Failed to delete chat messages: ' . mysqli_error($conn));
                        }
                        
                        // 3. Delete archived chat messages
                        $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'chat_messages_archive'");
                        if (mysqli_num_rows($tableCheck) > 0) {
                            $deleteArchived = mysqli_query($conn, "DELETE FROM chat_messages_archive WHERE user_email = '$userEmail'");
                            if (!$deleteArchived) {
                                throw new Exception('Failed to delete archived messages: ' . mysqli_error($conn));
                            }
                        }
                        
                        // 4. Delete the customer from users_facebook table
                        $deleteCustomer = mysqli_query($conn, "DELETE FROM users_facebook WHERE id = '$id'");
                        if (!$deleteCustomer) {
                            throw new Exception('Failed to delete customer: ' . mysqli_error($conn));
                        }
                        
                    } else {
                        throw new Exception('Customer not found in either table');
                    }
                }
                
                // Commit the transaction
                mysqli_commit($conn);
                
                $response = [
                    'success' => true, 
                    'message' => 'Customer and all related data deleted successfully'
                ];
                
            } catch (Exception $e) {
                // Rollback on error
                mysqli_rollback($conn);
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            
        } else {
            $response = ['success' => false, 'error' => 'Customer ID is required'];
        }
        break;
}

echo json_encode($response);
mysqli_close($conn);
?>