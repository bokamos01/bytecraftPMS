<?php
require_once 'config.php';

// Initialize message
$message = '';

// Check if user is logged in as a customer
$isCustomerLoggedIn = isset($_SESSION['customer_id']) && isset($_SESSION['customer_email']);
$emailAddress = '';

// If customer is logged in, get their email
if ($isCustomerLoggedIn) {
    $emailAddress = $_SESSION['customer_email'];
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // If customer is logged in, use the session email
    if ($isCustomerLoggedIn) {
        $emailAddress = $_SESSION['customer_email'];
    } else {
        $emailAddress = sanitizeInput($_POST['emailAddress']);
    }
    
    $feedbackText = sanitizeInput($_POST['feedbackText']);
    
    // Validate inputs
    $errors = [];
    if (empty($emailAddress)) {
        $errors[] = 'Email address is required';
    } elseif (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address format';
    }
    if (empty($feedbackText)) $errors[] = 'Feedback is required';
    
    if (empty($errors)) {
        try {
            // Get customer ID if logged in, else null
            $customerId = $isCustomerLoggedIn ? $_SESSION['customer_id'] : null;
            
            // Submit feedback using DataAccess
            $result = $dataAccess->SubmitFeedback($feedbackText, '', '', $emailAddress, '');
            
            if ($result > 0) {
                $message = displaySuccess('Your feedback has been submitted successfully. Thank you!');
                // Reset form fields
                if (!$isCustomerLoggedIn) {
                    $emailAddress = '';
                }
                $feedbackText = '';
            } else {
                $message = displayError('Failed to submit feedback. Please try again.');
            }
        } catch (Exception $ex) {
            $message = displayError('Error: ' . $ex->getMessage());
        }
    } else {
        $message = displayError('Please correct the following errors: ' . implode(', ', $errors));
    }
}

// Start output buffer for master template
ob_start();
?>

<h2 class="page-title">FEEDBACK</h2>

<div class="feedback-container">
    <p class="feedback-intro">
        We value your feedback! Please let us know how we can improve our parking management system.
    </p>
    
    <?php echo $message; ?>
    
    <div class="form-container">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="emailAddress">Email Address <span class="required">*</span></label>
                <input type="email" id="emailAddress" name="emailAddress" 
                    value="<?php echo htmlspecialchars($emailAddress); ?>" 
                    <?php echo $isCustomerLoggedIn ? 'readonly' : 'required'; ?>
                    <?php echo $isCustomerLoggedIn ? 'style="background-color: #f0f0f0;"' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="feedbackText">Your Feedback <span class="required">*</span></label>
                <textarea id="feedbackText" name="feedbackText" rows="5" required><?php echo isset($feedbackText) ? htmlspecialchars($feedbackText) : ''; ?></textarea>
            </div>
            
            <button type="submit">Submit Feedback</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();

include 'master.php';
?>
