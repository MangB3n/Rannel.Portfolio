<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow requests
require_once '../../includes/database.php'; // Adjust path to your db_connect.php

// This query groups all messages by user_email
// It gets the user's name, email, the last message, unread count,
// and sorts the list by the most recent conversation.
$query = "SELECT 
            user_email, 
            (SELECT user_name FROM chat_messages ci WHERE ci.user_email = c.user_email ORDER BY ci.id DESC LIMIT 1) as user_name,
            MAX(timestamp) as last_timestamp,
            (SELECT message FROM chat_messages ci WHERE ci.user_email = c.user_email ORDER BY ci.id DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM chat_messages ci WHERE ci.user_email = c.user_email AND ci.is_read = 0 AND ci.is_admin_reply = 0) as unread_count
          FROM 
            chat_messages c
          GROUP BY 
            user_email
          ORDER BY 
            last_timestamp DESC";

$result = mysqli_query($conn, $query);

if ($result) {
    $chats = array();
    while ($row = mysqli_fetch_assoc($result)) {
        // If user_name is null or empty, use a placeholder
        if (empty($row['user_name'])) {
            $row['user_name'] = 'Guest User (' . $row['user_email'] . ')';
        }
        $chats[] = $row;
    }
    echo json_encode(['status' => 'success', 'chats' => $chats]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch chat list: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>