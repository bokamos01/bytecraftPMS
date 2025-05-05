<?php
require_once 'config.php';

// Require login to access this page
requireLogin();

// Get counts from database for dashboard display
$staffCount = 0;
$customerCount = 0;
$bookingCount = 0;
$feedbackCount = 0;

try {
    // Get staff count
    $result = $dataAccess->GetDataSQL("SELECT COUNT(*) as count FROM tbl_staff");
    $staffCount = $result[0]['count'];
    
    // Get customer count
    $result = $dataAccess->GetDataSQL("SELECT COUNT(*) as count FROM tbl_customers");
    $customerCount = $result[0]['count'];
    
    // Get booking count
    $result = $dataAccess->GetDataSQL("SELECT COUNT(*) as count FROM tbl_booking");
    $bookingCount = $result[0]['count'];
    
    // Get feedback count
    $result = $dataAccess->GetDataSQL("SELECT COUNT(*) as count FROM tbl_feedback");
    $feedbackCount = $result[0]['count'];
} catch (Exception $ex) {
    error_log($ex->getMessage());
}

// Start output buffer for master template
ob_start();
?>

<h2 class="page-title">DASHBOARD</h2>

<div class="welcome-section">
    <h3>Welcome, <?php echo htmlspecialchars($_SESSION['staff_name'] ?? 'User'); ?></h3>
    <p>Role: <?php echo htmlspecialchars($_SESSION['role']); ?></p>
</div>

<div class="dashboard-grid">
    <!-- Staff Card -->
    <div class="dashboard-card">
        <div class="card-header">Staff</div>
        <div class="card-count"><?php echo $staffCount; ?></div>
        <div class="card-footer">
            <a href="manageStaff.php">Manage Staff</a>
        </div>
    </div>
    
    <!-- Customers Card -->
    <div class="dashboard-card">
        <div class="card-header">Customers</div>
        <div class="card-count"><?php echo $customerCount; ?></div>
        <div class="card-footer">
            <a href="manageCustomer.php">Manage Customers</a>
        </div>
    </div>
    
    <!-- Bookings Card -->
    <div class="dashboard-card">
        <div class="card-header">Bookings</div>
        <div class="card-count"><?php echo $bookingCount; ?></div>
        <div class="card-footer">
            <a href="manageBooking.php">Manage Bookings</a>
        </div>
    </div>
    
    <!-- Feedback Card -->
    <div class="dashboard-card">
        <div class="card-header">Feedback</div>
        <div class="card-count"><?php echo $feedbackCount; ?></div>
        <div class="card-footer">
            <a href="manageFeedback.php">View Feedback</a>
        </div>
    </div>
</div>

<div class="dashboard-actions">
    <h3>Quick Actions</h3>
    <div class="action-buttons">
        <a href="manageParking.php" class="action-button">Manage Parking</a>
        <a href="Reports.php" class="action-button">View Reports</a>
        <a href="manageFaq.php" class="action-button">Manage FAQs</a>
    </div>
</div>

<?php
$content = ob_get_clean();

include 'master.php';
?>
