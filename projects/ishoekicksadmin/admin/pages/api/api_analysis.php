<?php
header('Content-Type: application/json');
require_once '../../includes/database.php';


// Check for POST request
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action == 'save_analysis') {
    saveAnalysis($conn);
} elseif ($action == 'save_recommendation') {
    saveRecommendation($conn);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function saveAnalysis($conn) {
    // Get parameters
    $email = $_POST['user_email'] ?? '';
    $shoeType = $_POST['shoe_type'] ?? '';
    $confidence = $_POST['confidence'] ?? 0;
    $method = $_POST['analysis_method'] ?? 'local';
    $imageData = $_POST['image_data'] ?? ''; // Base64 string

    // Basic validation
    if (empty($email) || empty($imageData)) {
        echo json_encode(['success' => false, 'message' => 'Missing email or image']);
        return;
    }

    // Generate a unique filename for the image
    $imageName = "analysis_" . time() . "_" . uniqid() . ".jpg";
    $uploadDir = "uploads/analysis/"; // Make sure this folder exists
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $imagePath = $uploadDir . $imageName;
    
    // Decode and save image
    if (file_put_contents($imagePath, base64_decode($imageData))) {
        
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO shoe_analysis (user_email, shoe_type, confidence_score, analysis_method, image_path, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssdss", $email, $shoeType, $confidence, $method, $imageName);
        
        if ($stmt->execute()) {
            $analysisId = $stmt->insert_id;
            echo json_encode([
                'success' => true, 
                'message' => 'Analysis saved',
                'data' => ['analysis_id' => $analysisId]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save image file']);
    }
}

function saveRecommendation($conn) {
    $email = $_POST['user_email'] ?? '';
    $analysisId = $_POST['analysis_id'] ?? 0;
    $shoeType = $_POST['shoe_type'] ?? '';
    $condition = $_POST['condition_status'] ?? '';
    $servicesJson = $_POST['services_json'] ?? '[]';
    $issuesJson = $_POST['issues_json'] ?? '[]';
    $totalPrice = $_POST['total_price'] ?? 0;
    $estimatedDays = $_POST['estimated_days'] ?? '';

    $stmt = $conn->prepare("INSERT INTO analysis_recommendations (analysis_id, user_email, verified_shoe_type, condition_status, detected_issues, recommended_services, total_price, estimated_days, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->bind_param("isssssds", $analysisId, $email, $shoeType, $condition, $issuesJson, $servicesJson, $totalPrice, $estimatedDays);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Recommendation saved']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
}
?>