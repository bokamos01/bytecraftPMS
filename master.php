<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$userLoggedIn = isset($_SESSION['customer_id']) || isset($_SESSION['staff_id']);
$userName = '';
$isAdmin = false;
$isCustomer = false;

if (isset($_SESSION['customer_name'])) {
    $userName = $_SESSION['customer_name'];
    $isCustomer = true;
} elseif (isset($_SESSION['staff_name'])) {
    $userName = $_SESSION['staff_name'];
    
    // Check if user is admin (roleid = 1)
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
        $isAdmin = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carpark Management System</title>

    <!-- External CSS (only for reference; using inline CSS for the wireframe matching) -->
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    
    <!-- External JavaScript -->
    <script src="../assets/js/script.js" defer></script> 

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #e9e9e9;
        }
        
        .header-container {
            background-color: #2d3741;
            color: white;
            padding: 15px 0;
            text-align: center;
            width: 100%;
        }
        
        .header-title {
            font-size: 22px;
            margin: 0;
            padding: 5px 0;
        }
        
        .header-links {
            margin-top: 5px;
        }
        
        .header-links a {
            color: white;
            text-decoration: none;
            margin: 0 10px;
            font-size: 14px;
        }
        
        .main-content {
            padding: 20px;
            max-width: 1000px;
            margin: 0 auto;
            flex: 1;
            width: 100%;
        }
        
        .footer {
            background-color: #2d3741;
            color: white;
            text-align: center;
            padding: 15px 0;
            margin-top: auto;
            width: 100%;
        }
        
        .logout-link {
            background-color: #b91c1c;
            padding: 3px 8px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="header-container">
        <h1 class="header-title">CARPARK MANAGEMENT SYSTEM</h1>
        <div class="header-links">
            <a href="bookParking2.php">Book parking</a>
            
            <?php if ($isCustomer || !$userLoggedIn): ?>
                <a href="faq.php">FAQ</a>
                <a href="feedback.php">Feedback</a>
            <?php endif; ?>
            
            <?php if ($isCustomer): ?>
                <a href="customerManageBooking.php">My Bookings</a>
            <?php endif; ?>
            
            <?php if ($isAdmin): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="manageStaff.php">Manage Staff</a>
                <a href="manageCustomer.php">Manage Customers</a>
                <a href="manageBooking.php">Manage Bookings</a>
                <a href="manageParking.php">Manage Parking</a>
                <a href="manageFaq.php">Manage FAQs</a>
                <a href="Reports.php">Reports</a>
            <?php endif; ?>
            
            <?php if ($userLoggedIn): ?>
                <a href="UpdateProfile.php" style="text-decoration: none;">
                    <span class="user-info"><?php echo htmlspecialchars($userName); ?></span>
                </a>
                <a href="logout.php" class="logout-link">Log-out</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="main-content">
        <?php 
        // Display flash login notifications if any
        if (isset($_SESSION['flash_login_notifications']) && is_array($_SESSION['flash_login_notifications']) && count($_SESSION['flash_login_notifications']) > 0) {
            echo '<div class="flash-notifications">';
            foreach ($_SESSION['flash_login_notifications'] as $notification) {
                $message = htmlspecialchars($notification['message']);
                echo "<div class=\"message-container success-message\" style=\"margin-bottom: 10px; padding: 10px; border-left: 4px solid #28a745; background-color: #d4edda; color: #155724;\">";
                echo $message;
                echo "</div>";
            }
            echo '</div>';
            // Clear notifications after displaying once
            unset($_SESSION['flash_login_notifications']);
        }
        ?>
        <?php echo $content ?? ''; ?>
    </div>
    <!-- Footer -->
    <div class="footer">
        <p>&copy; 2025 Carpark Management System. All rights reserved.</p>
    </div>
</body>
</html>
