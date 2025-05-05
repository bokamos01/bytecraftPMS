<?php
if (!isset($_SESSION)) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['staff_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    // Redirect to login page
    header("Location: login.php");
    exit;
}

// Include the data access layer
include_once './PMSDataAccess.php'; 
$da = new DataAccess();

// Get all FAQs
$faqs = $da->GetAllFAQs();

// Initialize variables
$faqDetails = null;

if (isset($_GET['id'])) {
    $faqId = $_GET['id'];
    
    $sql = "SELECT * FROM tbl_faq WHERE FAQID = ?";
    $result = $da->GetData($sql, array($faqId));
    if (count($result) > 0) {
        $faqDetails = $result[0];
    }
}

// Handle form submissions
try {
    if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] === "POST") {
        if (isset($_POST['btnadd'])) {
            // Add new FAQ validation
            $question = trim($_POST["question"] ?? "");
            $answer = trim($_POST["answer"] ?? "");
            
            $errors = [];
            
            if (empty($question)) {
                $errors[] = "Question is required";
            }
            
            if (empty($answer)) {
                $errors[] = "Answer is required";
            }
            
            if (empty($errors)) {
                // Use the AddFAQ method from DataAccess
                $result = $da->AddFAQ($question, $answer);
                
                if ($result > 0) {
                    $_SESSION['faq_message'] = "FAQ added successfully!";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $_SESSION['faq_error'] = "FAQ addition failed!";
                }
            } else {
                $_SESSION['faq_error'] = implode("<br>", $errors);
            }
        } else if (isset($_POST['btnupdate'])) {
            // Update FAQ validation
            $faqId = $_POST["faqid"] ?? 0;
            $question = trim($_POST["question"] ?? "");
            $answer = trim($_POST["answer"] ?? "");
            
            $errors = [];
            
            if (empty($faqId) || $faqId <= 0) {
                $errors[] = "Invalid FAQ ID";
            }
            
            if (empty($question)) {
                $errors[] = "Question is required";
            }
            
            if (empty($answer)) {
                $errors[] = "Answer is required";
            }
            
            if (empty($errors)) {
                // Use the UpdateFAQ method from DataAccess
                $result = $da->UpdateFAQ($faqId, $question, $answer);
                
                if ($result > 0) {
                    $_SESSION['faq_message'] = "FAQ updated successfully!";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $_SESSION['faq_error'] = "No changes were made or FAQ update failed!";
                }
            } else {
                $_SESSION['faq_error'] = implode("<br>", $errors);
            }
        } else if (isset($_POST['btndelete'])) {
            // Delete FAQ
            $faqId = $_POST["faqid"] ?? 0;
            
            if (!$faqId) {
                $_SESSION['faq_error'] = "Please select a FAQ to delete!";
            } else {
                // Use the DeleteFAQ method from DataAccess
                $result = $da->DeleteFAQ($faqId);
                
                if ($result > 0) {
                    $_SESSION['faq_message'] = "FAQ deleted successfully!";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $_SESSION['faq_error'] = "FAQ deletion failed!";
                }
            }
        }
    }
} catch (Exception $ex) {
    $msg = $ex->getMessage();
    $_SESSION['faq_error'] = $msg;
}

// Start output buffering to capture content for master template
ob_start();
?>

<div class="manage-faq-container">
    <h1 class="page-title">Manage FAQs</h1>
    
    <?php if (isset($_SESSION['faq_message'])): ?>
        <div class="success-message">
            <p>
                <?php 
                    echo $_SESSION['faq_message'];
                    unset($_SESSION['faq_message']);
                ?>
            </p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['faq_error'])): ?>
        <div class="error-message">
            <p>
                <?php 
                    echo $_SESSION['faq_error'];
                    unset($_SESSION['faq_error']);
                ?>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="faq-management-layout">
        <!-- Left Side: FAQ List -->
        <div class="faq-list-container">
            <!-- FAQ List -->
            <div class="faq-table-container">
                <div class="table-header">
                    <h2>All FAQs</h2>
                </div>
                
                <?php if (count($faqs) > 0): ?>
                    <div class="table-content">
                        <table class="faq-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Question</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($faqs as $faq): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($faq['FAQID']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($faq['FAQUESTION']); ?>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="?id=<?php echo htmlspecialchars($faq['FAQID']); ?>" class="edit-link">Edit</a>
                                        
                                        <form method="POST" id="delete-faq-<?php echo $faq['FAQID']; ?>" class="delete-form">
                                            <input type="hidden" name="faqid" value="<?php echo htmlspecialchars($faq['FAQID']); ?>">
                                            <button type="button" onclick="confirmDeleteFAQ('<?php echo $faq['FAQID']; ?>')" class="delete-button">Delete</button>
                                            <input type="hidden" name="btndelete" value="1">
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-faqs-message">
                        No FAQs found. Add your first FAQ using the form.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Right Side: Add/Edit FAQ Form -->
        <div class="faq-form-container">
            <h2 class="form-title" id="faq-form-title">
                <?php echo isset($faqDetails) ? 'Edit FAQ' : 'Add New FAQ'; ?>
            </h2>
            <form name="faq" id="faqForm" method="POST">
                <input type="hidden" name="faqid" id="faq_id" value="<?php echo htmlspecialchars($faqDetails['FAQID'] ?? ''); ?>" />

                <div class="form-group">
                    <label for="question">
                        Question <span class="required">*</span>
                    </label>
                    <input type="text" name="question" id="question" required placeholder="Enter FAQ question"
                        value="<?php echo htmlspecialchars($faqDetails['FAQUESTION'] ?? ''); ?>" />
                </div>

                <div class="form-group">
                    <label for="answer">
                        Answer <span class="required">*</span>
                    </label>
                    <textarea name="answer" id="answer" required placeholder="Enter FAQ answer" rows="6"
                    ><?php echo htmlspecialchars($faqDetails['FAQANSWER'] ?? ''); ?></textarea>
                </div>

                <div class="form-buttons">
                    <button type="button" id="reset-form" class="reset-button">
                        Reset
                    </button>
                    <?php if (isset($faqDetails)): ?>
                        <button type="submit" name="btnupdate" id="edit-faq-btn" class="submit-button">
                            Update FAQ
                        </button>
                    <?php else: ?>
                        <button type="submit" name="btnadd" id="add-faq-btn" class="submit-button">
                            Add FAQ
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .manage-faq-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .page-title {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 20px;
    }
    
    .success-message {
        background-color: #e6f7e6;
        border-left: 4px solid #28a745;
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .error-message {
        background-color: #f8d7da;
        border-left: 4px solid #dc3545;
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .faq-management-layout {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    @media (min-width: 992px) {
        .faq-management-layout {
            flex-direction: row;
        }
        
        .faq-list-container {
            width: 66%;
        }
        
        .faq-form-container {
            width: 33%;
        }
    }
    
    .faq-table-container, .faq-form-container {
        background-color: white;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    
    .table-header {
        padding: 15px;
        border-bottom: 1px solid #ddd;
        background-color: #f5f5f5;
    }
    
    .table-header h2 {
        font-size: 18px;
        font-weight: 500;
        margin: 0;
    }
    
    .table-content {
        max-height: 600px;
        overflow-y: auto;
    }
    
    .faq-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .faq-table th, .faq-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    
    .faq-table th {
        background-color: #f5f5f5;
        font-weight: 500;
    }
    
    .faq-table tr:hover {
        background-color: #f9f9f9;
    }
    
    .action-buttons {
        text-align: right;
        white-space: nowrap;
    }
    
    .edit-link {
        color: #1a73e8;
        text-decoration: none;
        margin-right: 10px;
    }
    
    .edit-link:hover {
        text-decoration: underline;
    }
    
    .delete-form {
        display: inline;
    }
    
    .delete-button {
        color: #dc3545;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
    }
    
    .delete-button:hover {
        text-decoration: underline;
    }
    
    .no-faqs-message {
        padding: 20px;
        text-align: center;
        color: #666;
    }
    
    .faq-form-container {
        padding: 20px;
    }
    
    .form-title {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 15px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 5px;
    }
    
    .required {
        color: #dc3545;
    }
    
    .form-group input, .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .form-group input:focus, .form-group textarea:focus {
        outline: none;
        border-color: #1a73e8;
        box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
    }
    
    .form-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .reset-button, .submit-button {
        padding: 8px 16px;
        border-radius: 4px;
        font-weight: 500;
        cursor: pointer;
        border: none;
    }
    
    .reset-button {
        background-color: #e0e0e0;
    }
    
    .reset-button:hover {
        background-color: #d0d0d0;
    }
    
    .submit-button {
        background-color: #2d3741;
        color: white;
    }
    
    .submit-button:hover {
        background-color: #3d4751;
    }
</style>

<script>
    function confirmDeleteFAQ(faqId) {
        if (confirm('Are you sure you want to delete this FAQ?')) {
            document.getElementById('delete-faq-' + faqId).submit();
        }
    }

    // Reset form button
    document.getElementById('reset-form').addEventListener('click', function() {
        document.getElementById('faqForm').reset();
        
        // If in edit mode, redirect to add mode
        <?php if (isset($faqDetails)): ?>
        window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>';
        <?php endif; ?>
    });
</script>

<?php
$content = ob_get_clean();

require_once 'master.php';
?>
