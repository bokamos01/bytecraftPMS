<?php
session_start();
header('Content-Type: application/json'); // Set header for JSON response

require_once 'config.php'; // Includes $dataAccess

$response = ['success' => false, 'message' => 'An error occurred.'];

// Check if customer is logged in
if (!isset($_SESSION['customer_id'])) {
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit;
}

// Check if notification IDs were sent
if (!isset($_POST['notification_ids'])) {
    $response['message'] = 'No notification IDs provided.';
    echo json_encode($response);
    exit;
}

// Decode the JSON array of IDs
$notificationIdsJson = $_POST['notification_ids'];
$notificationIds = json_decode($notificationIdsJson, true);

if (!is_array($notificationIds) || empty($notificationIds)) {
    $response['message'] = 'Invalid notification IDs format.';
    echo json_encode($response);
    exit;
}

// Optional: CSRF validation if implemented
// if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
//     $response['message'] = 'Invalid request token.';
//     echo json_encode($response);
//     exit;
// }

try {
    $customerId = $_SESSION['customer_id'];
    $result = $dataAccess->MarkNotificationsAsRead($customerId, $notificationIds);

    if ($result >= 0) { // Allow 0 if no notifications needed updating but request was valid
        $response['success'] = true;
        $response['message'] = 'Notifications marked as read.';
        unset($_SESSION['unread_notifications']); // Clear the session variable after successful update
    } else {
        $response['message'] = 'Failed to update notification status.';
    }

} catch (Exception $ex) {
    error_log("Error in mark_notifications_read.php: " . $ex->getMessage());
    $response['message'] = 'Database error: ' . $ex->getMessage();
}

echo json_encode($response);
exit;
?>
