<?php
session_start();
header('Content-Type: application/json');

require_once '../../includes/database.php';

// Check if admin is logged in
if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit;
}

try {
    // Get list of archived conversations
    $sql = "SELECT 
                user_name,
                user_email,
                MAX(archived_at) as archived_at,
                COUNT(*) as message_count
            FROM chat_messages_archive
            GROUP BY user_email, user_name
            ORDER BY MAX(archived_at) DESC";
    
    $result = $conn->query($sql);
    
    $archives = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $archives[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'archives' => $archives
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>