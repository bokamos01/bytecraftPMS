<?php
// Start the session
session_start();

$pageTitle = 'LOGIN - CARPARK MANAGEMENT SYSTEM';
$custom_layout = true;

// --- Include the data access layer ---
// Ensure this path is correct and $da/$dataAccess is instantiated
if (!class_exists('DataAccess')) {
    include_once './PMSDataAccess.php';
}
if (!isset($dataAccess) && !isset($da)) { // Check both common names
    $da = new DataAccess(); // Instantiate if not already done
} elseif (isset($dataAccess) && !isset($da)) {
    $da = $dataAccess; // Use the one from config.php if it exists
}
// --- End DataAccess setup ---


// Check if user is already logged in
if (isset($_SESSION['customer_id']) || isset($_SESSION['staff_id'])) {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'staff') {
        $redirect = 'dashboard.php';
    } else {
        $redirect = 'bookParking2.php';
    }
    $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : $redirect;
    header("Location: " . $redirect);
    exit;
}

$loginError = $_SESSION['login_error'] ?? null;
$loginMessage = $_SESSION['login_message'] ?? null;
unset($_SESSION['login_error']);
unset($_SESSION['login_message']);

$redirectParam = isset($_GET['redirect']) ? $_GET['redirect'] : '';
if (!empty($redirectParam)) {
    $_SESSION['redirect_after_login'] = $redirectParam;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = "Email and password are required";
            header("Location: login.php");
            exit;
        }

        $hashedPassword = hash('sha256', $password);

        // Try staff login first
        $sqlStaff = "SELECT a.STAFFID, a.FIRSTNAME, a.SURNAME, a.GENDER,
                       a.EMAILADDRESS, a.ROLEID, b.ROLE,
                       a.DATEREGISTERED
                FROM tbl_staff a
                JOIN tbl_roles b ON a.ROLEID = b.ROLEID
                WHERE a.EMAILADDRESS = ? AND a.PASSWORD = ?";
        $staffUser = $da->GetData($sqlStaff, [$email, $hashedPassword]);

        if (count($staffUser) > 0) {
            // Login successful for staff
            $_SESSION['staff_id'] = $staffUser[0]['STAFFID'];
            $_SESSION['staff_name'] = $staffUser[0]['FIRSTNAME'] . ' ' . $staffUser[0]['SURNAME'];
            $_SESSION['staff_email'] = $staffUser[0]['EMAILADDRESS'];
            $_SESSION['role_id'] = $staffUser[0]['ROLEID'];
            $_SESSION['role'] = $staffUser[0]['ROLE'];
            $_SESSION['user_type'] = 'staff';

            $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'dashboard.php';
            unset($_SESSION['redirect_after_login']);
            header("Location: " . $redirect);
            exit;
        }

        // If not staff, check customers
        $sqlCustomer = "SELECT * FROM tbl_customers WHERE EMAILADDRESS = ? AND PASSWORD = ?";
        $customer = $da->GetData($sqlCustomer, [$email, $hashedPassword]);

        if (count($customer) > 0) {
            // Login successful for customer
            $customerId = $customer[0]['CUSTOMERID'];
            $_SESSION['customer_id'] = $customerId;
            $_SESSION['customer_name'] = $customer[0]['FIRSTNAME'] . ' ' . $customer[0]['SURNAME'];
            $_SESSION['customer_email'] = $customer[0]['EMAILADDRESS'];
            $_SESSION['user_type'] = 'customer';

            // --- REVISED: Fetch and store notifications for flash display ---
            $_SESSION['flash_login_notifications'] = null; // Ensure it's reset/exists
            try {
                // Check if method exists before calling
                if (method_exists($da, 'GetUnreadNotifications')) {
                     $unreadNotifications = $da->GetUnreadNotifications($customerId);
                     if (!empty($unreadNotifications)) {
                         // Store the actual notification data
                         $_SESSION['flash_login_notifications'] = $unreadNotifications;
                     }
                } else {
                    error_log("ERROR login.php: GetUnreadNotifications method does not exist in DataAccess.");
                }
            } catch (Exception $ex) {
                error_log("ERROR login.php: Failed to fetch notifications during login for customer ID " . $customerId . ": " . $ex->getMessage());
            }
            // --- END REVISED ---

            // Redirect to customer dashboard or appropriate page (defaulting to bookParking2)
            $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'bookParking2.php';
            unset($_SESSION['redirect_after_login']);

            header("Location: " . $redirect);
            exit;
        }

        // Login failed if neither staff nor customer matched
        $_SESSION['login_error'] = "Invalid email or password";
        header("Location: login.php");
        exit;

    } catch (Exception $ex) {
        error_log("ERROR login.php: Login process failed: " . $ex->getMessage());
        $_SESSION['login_error'] = "Login failed due to a system error.";
        header("Location: login.php");
        exit;
    }
}

// Start output buffering to capture page content
ob_start();
?>

<style>
    body { background-color: #e9e9e9; font-family: Arial, sans-serif; }
    .login-container { max-width: 400px; margin: 50px auto; background-color: #fff; border-radius: 3px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); padding: 20px; }
    .login-title { text-align: center; font-size: 24px; margin-bottom: 20px; color: #333; }
    .error-message { background-color: #fef2f2; color: #b91c1c; padding: 10px; margin-bottom: 20px; border-left: 4px solid #ef4444; display: flex; align-items: center; }
    .message-container.success-message { background-color: #d4edda; color: #155724; border-left: 4px solid #28a745; padding: 10px; margin-bottom: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-label { display: block; text-align: left; margin-bottom: 5px; font-weight: bold; }
    .required { color: #ef4444; }
    .form-input { width: 100%; padding: 8px; border: 1px solid #ddd; background-color: #ffffe0; box-sizing: border-box; }
    .login-button { width: 100%; background-color: #2d3741; color: white; border: none; padding: 10px; font-size: 16px; cursor: pointer; margin-bottom: 10px; }
    .secondary-button { width: 100%; background-color: #95a5a6; color: white; border: none; padding: 10px; font-size: 16px; cursor: pointer; margin-bottom: 20px; }
    .register-link { text-align: center; font-size: 14px; margin-top: 10px; }
    .register-link a { color: #3498db; text-decoration: none; }
</style>

<div class="login-container">
    <h1 class="login-title">User Login</h1>

    <?php if ($loginError): ?>
        <div class="error-message">
            <span>❌</span>&nbsp;&nbsp;<?php echo htmlspecialchars($loginError); ?>
        </div>
    <?php endif; ?>
     <?php if ($loginMessage): ?>
        <div class="message-container success-message">
            <?php echo htmlspecialchars($loginMessage); ?>
        </div>
    <?php endif; ?>

    <form id="loginForm" action="login.php" method="POST">
        <div class="form-group">
            <label for="email" class="form-label">Email Address <span class="required">*</span></label>
            <input type="email" id="email" name="email" class="form-input" required>
        </div>
        <div class="form-group">
            <label for="password" class="form-label">Password <span class="required">*</span></label>
            <input type="password" id="password" name="password" class="form-input" required>
        </div>
        <button type="submit" class="login-button">Log In</button>
        <button type="button" class="secondary-button" onclick="window.location.href='bookParking2.php'">Continue as guest</button>
        <div class="register-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require_once 'master.php';
?>
