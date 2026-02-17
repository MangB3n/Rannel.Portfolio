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

$userEmail = $_POST['user_email'] ?? '';

if (empty($userEmail)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User email is required'
    ]);
    exit;
}

try {
    // Delete all archived messages for this user
    $sql = "DELETE FROM chat_messages_archive WHERE user_email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $userEmail);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Archive deleted successfully',
            'deleted_count' => $stmt->affected_rows
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to delete archive'
        ]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>