<?php
session_start();

// Check if user is logged in as staff
if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Include the data access layer
include_once './PMSDataAccess.php';
$da = new DataAccess();

// Check if ID parameter exists
if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parking ID']);
    exit;
}

$parkingId = intval($_GET['id']);

try {
    // Get the parking space details
    $sql = "SELECT PARKINGIMAGE FROM tbl_parkingspace WHERE PARKINGID = ?";
    $result = $da->GetData($sql, [$parkingId]);
    
    if (count($result) == 0) {
        echo json_encode(['success' => false, 'message' => 'Parking space not found']);
        exit;
    }
    
    // Get images from JSON string
    $imageString = $result[0]['PARKINGIMAGE'];
    $images = [];
    
    if (!empty($imageString)) {
        $images = json_decode($imageString, true) ?: [];
        
        // Filter out non-existent images
        $images = array_filter($images, function($path) {
            return file_exists($path);
        });
    }
    
    // Return success response with images
    echo json_encode([
        'success' => true, 
        'images' => array_values($images)  // Re-index array to ensure it's properly serialized
    ]);
    
} catch (Exception $ex) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $ex->getMessage()]);
}
