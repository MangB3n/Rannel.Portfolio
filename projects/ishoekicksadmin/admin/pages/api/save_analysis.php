<?php
// API endpoint to save image analysis
// Place this in: /ishoekicks/api/save_analysis.php

require_once '../../pages/api/db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method. Use POST.');
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['user_email']) || !isset($data['shoe_type']) || 
    !isset($data['confidence']) || !isset($data['analysis_method'])) {
    sendResponse(false, 'Missing required fields');
}

$user_email = sanitizeInput($data['user_email']);
$shoe_type = sanitizeInput($data['shoe_type']);
$confidence = floatval($data['confidence']);
$analysis_method = sanitizeInput($data['analysis_method']);
$image_base64 = isset($data['image_base64']) ? $data['image_base64'] : null;

// Validate email
if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email format');
}

// Validate confidence range
if ($confidence < 0 || $confidence > 1) {
    sendResponse(false, 'Confidence must be between 0 and 1');
}

// Validate analysis method
if (!in_array($analysis_method, ['gemini', 'local'])) {
    sendResponse(false, 'Invalid analysis method');
}

$conn = getDbConnection();

// Insert image analysis
$stmt = $conn->prepare("INSERT INTO image_analysis (user_email, shoe_type, confidence, analysis_method, image_base64) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssdss", $user_email, $shoe_type, $confidence, $analysis_method, $image_base64);

if ($stmt->execute()) {
    $analysis_id = $conn->insert_id;
    
    // Update statistics
    $update_stats = $conn->prepare("INSERT INTO analysis_statistics (shoe_type, total_analyses, last_analyzed) 
                                     VALUES (?, 1, NOW()) 
                                     ON DUPLICATE KEY UPDATE total_analyses = total_analyses + 1, last_analyzed = NOW()");
    $update_stats->bind_param("s", $shoe_type);
    $update_stats->execute();
    $update_stats->close();
    
    sendResponse(true, 'Analysis saved successfully', [
        'analysis_id' => $analysis_id,
        'shoe_type' => $shoe_type,
        'confidence' => $confidence
    ]);
} else {
    sendResponse(false, 'Failed to save analysis: ' . $stmt->error);
}

$stmt->close();
$conn->close();
?>
