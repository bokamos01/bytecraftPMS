<?php
require_once 'config.php';

requireAdmin();

// Initialize variables
$message = '';
$formData = [
    'staffId' => '',
    'firstName' => '',
    'lastName' => '',
    'gender' => '',
    'email' => '',
    'password' => '',
    'roleId' => '',
    'dateRegistered' => date('Y-m-d') // Set default to today's date
];

// Get all roles for dropdown (excluding Customer role)
try {
    $roles = $dataAccess->GetDataSQL("SELECT * FROM tbl_roles WHERE ROLE != 'Customer' ORDER BY ROLE");
} catch (Exception $ex) {
    $message = displayError('Error loading roles: ' . $ex->getMessage());
    $roles = [];
}

// Handle Edit request from URL parameter
if (isset($_GET['id'])) {
    $staffId = $_GET['id'];
    
    // Prevent editing of staff with ID 1
    if ($staffId == 1) {
        $message = displayError('System administrator account cannot be modified');
    } else {
        try {
            $sql = "SELECT * FROM tbl_staff WHERE STAFFID = ?";
            $result = $dataAccess->GetData($sql, array($staffId));
            if (count($result) > 0) {
                $staffDetails = $result[0];
                $formData = [
                    'staffId' => $staffDetails['STAFFID'],
                    'firstName' => $staffDetails['FIRSTNAME'],
                    'lastName' => $staffDetails['SURNAME'],
                    'gender' => $staffDetails['GENDER'],
                    'email' => $staffDetails['EMAILADDRESS'],
                    'password' => '',
                    'roleId' => $staffDetails['ROLEID'],
                    'dateRegistered' => isset($staffDetails['DATEREGISTERED']) ? $staffDetails['DATEREGISTERED'] : date('Y-m-d')
                ];
            }
        } catch (Exception $ex) {
            $message = displayError('Error loading staff details: ' . $ex->getMessage());
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Add/Update staff
    if (isset($_POST['action'])) {
        // Get form data
        $formData = [
            'staffId' => isset($_POST['staffId']) ? sanitizeInput($_POST['staffId']) : '',
            'firstName' => sanitizeInput($_POST['firstName']),
            'lastName' => sanitizeInput($_POST['lastName']),
            'gender' => sanitizeInput($_POST['gender']),
            'email' => sanitizeInput($_POST['email']),
            'password' => isset($_POST['password']) ? sanitizeInput($_POST['password']) : '',
            'confirmPassword' => isset($_POST['confirmPassword']) ? sanitizeInput($_POST['confirmPassword']) : '',
            'roleId' => sanitizeInput($_POST['roleId']),
            'dateRegistered' => sanitizeInput($_POST['dateRegistered']),
        ];
        
        // Check if trying to update staff ID 1
        if ($_POST['action'] === 'update' && $formData['staffId'] == 1) {
            $message = displayError('System administrator account cannot be modified');
        } else {
            // Validate form data
            $errors = [];
            if (empty($formData['firstName'])) $errors[] = 'First name is required';
            if (empty($formData['lastName'])) $errors[] = 'Last name is required';
            if (empty($formData['gender'])) $errors[] = 'Gender is required';
            if (empty($formData['email'])) $errors[] = 'Email is required';
            if (empty($formData['roleId'])) $errors[] = 'Role is required';
            if (empty($formData['dateRegistered'])) $errors[] = 'Date registered is required';
            
            // Date validation for registration date
            if (!empty($formData['dateRegistered'])) {
                $registrationDate = strtotime($formData['dateRegistered']);
                $today = strtotime(date('Y-m-d'));
                
                if ($registrationDate > $today) {
                    $errors[] = 'Registration date cannot be in the future';
                }
            }
            
            // Validate password for new staff or when updating password
            if ($_POST['action'] === 'add' || !empty($formData['password'])) {
                if (empty($formData['password'])) {
                    $errors[] = 'Password is required';
                } elseif (strlen($formData['password']) < 6) {
                    $errors[] = 'Password must be at least 6 characters long';
                }
                
                if ($formData['password'] !== $formData['confirmPassword']) {
                    $errors[] = 'Passwords do not match';
                }
            }
            
            if (empty($errors)) {
                try {
                    if ($_POST['action'] === 'add') {
                        // Hash the password
                        $hashedPassword = hash('sha256', $formData['password']);
                        
                        // Add new staff
                        $result = $dataAccess->AddStaffMember(
                            $formData['firstName'],
                            $formData['lastName'],
                            $formData['gender'],
                            $formData['email'],
                            $hashedPassword,
                            $formData['roleId'],
                            $formData['dateRegistered'] // Add date registered
                        );
                        
                        if ($result > 0) {
                            $message = displaySuccess('Staff member added successfully.');
                            // Reset form
                            $formData = [
                                'staffId' => '',
                                'firstName' => '',
                                'lastName' => '',
                                'gender' => '',
                                'email' => '',
                                'password' => '',
                                'roleId' => '',
                                'dateRegistered' => date('Y-m-d') // Reset to today's date
                            ];
                        } else {
                            $message = displayError('Failed to add staff member');
                        }
                        
                    } else if ($_POST['action'] === 'update') {
                        if (!empty($formData['password'])) {
                            // Update with new password
                            $hashedPassword = hash('sha256', $formData['password']);
                            $result = $dataAccess->UpdateStaffMemberWithPassword(
                                $formData['staffId'],
                                $formData['firstName'],
                                $formData['lastName'],
                                $formData['gender'],
                                $formData['email'],
                                $hashedPassword,
                                $formData['roleId'],
                                $formData['dateRegistered'] // Add date registered
                            );
                            $passwordMsg = ' Password has been updated.';
                        } else {
                            // Update without changing password
                            $result = $dataAccess->UpdateStaffMember(
                                $formData['staffId'],
                                $formData['firstName'],
                                $formData['lastName'],
                                $formData['gender'],
                                $formData['email'],
                                $formData['roleId'],
                                $formData['dateRegistered']
                            );
                            $passwordMsg = '';
                        }
                        
                        if ($result > 0) {
                            $message = displaySuccess('Staff member updated successfully.' . $passwordMsg);
                        } else {
                            $message = displayError('Failed to update staff member or no changes made');
                        }
                    }
                } catch (Exception $ex) {
                    $message = displayError('Error: ' . $ex->getMessage());
                }
            } else {
                $message = displayError('Please correct the following errors: ' . implode(', ', $errors));
            }
        }
    } else if (isset($_POST['delete'])) {
        // Handle staff deletion
        $staffId = sanitizeInput($_POST['staffId']);
        
        // Prevent deletion of staff with ID 1
        if ($staffId == 1) {
            $message = displayError('System administrator account cannot be deleted');
        } else {
            try {
                $result = $dataAccess->DeleteStaffMember($staffId);
                
                if ($result > 0) {
                    $message = displaySuccess('Staff member deleted successfully');
                } else {
                    $message = displayError('Failed to delete staff member');
                }
            } catch (Exception $ex) {
                $message = displayError('Error: ' . $ex->getMessage());
            }
        }
    } else if (isset($_POST['search'])) {
        // Handle search
        $searchTerm = sanitizeInput($_POST['searchTerm']);
        try {
            if (!empty($searchTerm)) {
                $sql = "SELECT s.STAFFID, s.FIRSTNAME, s.SURNAME, s.EMAILADDRESS, r.ROLE 
                       FROM tbl_staff s
                       JOIN tbl_roles r ON s.ROLEID = r.ROLEID
                       WHERE s.STAFFID = ? OR s.FIRSTNAME LIKE ? OR s.SURNAME LIKE ? OR s.EMAILADDRESS LIKE ?";
                $params = array($searchTerm, "%$searchTerm%", "%$searchTerm%", "%$searchTerm%");
            } else {
                $sql = "SELECT s.STAFFID, s.FIRSTNAME, s.SURNAME, s.EMAILADDRESS, r.ROLE 
                       FROM tbl_staff s
                       JOIN tbl_roles r ON s.ROLEID = r.ROLEID";
                $params = array();
            }
            $staffList = $dataAccess->GetData($sql, $params);
            
            if (count($staffList) == 0) {
                $message = displayInfo('No staff members found matching your search criteria.');
            }
        } catch (Exception $ex) {
            $message = displayError('Error searching staff: ' . $ex->getMessage());
            $staffList = [];
        }
    }
}

// Get all staff if not searching
if (!isset($staffList)) {
    $staffList = [];
}

// Start output buffer for master template
ob_start();
?>

<h2 class="page-title">MANAGE STAFF</h2>

<?php if (!empty($message)): ?>
<div class="message-container">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="manage-container">
    <!-- Staff List - Left side -->
    <div class="list-container">
        <h3>Staff List</h3>
        
        <!-- Search form -->
        <div class="search-container">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="search-form">
                <input type="text" id="searchTerm" name="searchTerm" placeholder="Search by ID, name or email">
                <div class="search-buttons">
                    <button type="submit" name="search" class="primary-button">Search</button>
                    <button type="submit" name="search" value="showall" class="secondary-button">Show All</button>
                </div>
            </form>
        </div>
        
        <!-- Staff table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="staffTableBody">
                    <?php if (count($staffList) > 0): ?>
                        <?php foreach ($staffList as $staff): ?>
                        <tr>
                            <td><?php echo $staff['STAFFID']; ?></td>
                            <td><?php echo htmlspecialchars($staff['FIRSTNAME'] . ' ' . $staff['SURNAME']); ?></td>
                            <td><?php echo htmlspecialchars($staff['EMAILADDRESS']); ?></td>
                            <td><?php echo htmlspecialchars($staff['ROLE']); ?></td>
                            <td class="actions-cell">
                                <?php if ($staff['STAFFID'] != 1): ?>
                                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $staff['STAFFID']); ?>" class="action-button edit-button">Edit</a>
                                
                                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this staff member?');">
                                    <input type="hidden" name="staffId" value="<?php echo $staff['STAFFID']; ?>">
                                    <button type="submit" name="delete" class="action-button delete-button">Delete</button>
                                </form>
                                <?php else: ?>
                                <span class="action-button disabled">System Account</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="no-records">Use the search above to find staff records</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Staff Form - Right side -->
    <div class="form-container">
        <h3><?php echo empty($formData['staffId']) ? 'Add New Staff' : 'Update Staff'; ?></h3>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" class="staff-form">
            <input type="hidden" name="action" value="<?php echo empty($formData['staffId']) ? 'add' : 'update'; ?>">
            <input type="hidden" name="staffId" value="<?php echo htmlspecialchars($formData['staffId']); ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="firstName">First Name <span class="required">*</span></label>
                    <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($formData['firstName']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="lastName">Last Name <span class="required">*</span></label>
                    <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($formData['lastName']); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="gender">Gender <span class="required">*</span></label>
                <select id="gender" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="M" <?php echo ($formData['gender'] === 'M') ? 'selected' : ''; ?>>Male</option>
                    <option value="F" <?php echo ($formData['gender'] === 'F') ? 'selected' : ''; ?>>Female</option>
                    <option value="O" <?php echo ($formData['gender'] === 'O') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password"><?php echo empty($formData['staffId']) ? 'Password' : 'New Password'; ?> <?php echo empty($formData['staffId']) ? '<span class="required">*</span>' : '<small>(leave blank to keep current)</small>'; ?></label>
                    <input type="password" id="password" name="password" value="" <?php echo empty($formData['staffId']) ? 'required' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label for="confirmPassword">Confirm <?php echo empty($formData['staffId']) ? 'Password' : 'New Password'; ?> <?php echo empty($formData['staffId']) ? '<span class="required">*</span>' : ''; ?></label>
                    <input type="password" id="confirmPassword" name="confirmPassword" value="" <?php echo empty($formData['staffId']) ? 'required' : ''; ?>>
                </div>
            </div>
            
            <div class="form-group">
                <label for="roleId">Role <span class="required">*</span></label>
                <select id="roleId" name="roleId" required>
                    <option value="">Select Role</option>
                    <?php foreach ($roles as $role): ?>
                    <option value="<?php echo $role['ROLEID']; ?>" <?php echo ($formData['roleId'] == $role['ROLEID']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($role['ROLE']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Hidden date registered field -->
            <input type="hidden" id="dateRegistered" name="dateRegistered" value="<?php echo htmlspecialchars($formData['dateRegistered']); ?>"
            
            <div class="form-buttons">
                <button type="submit"><?php echo empty($formData['staffId']) ? 'Add Staff' : 'Update Staff'; ?></button>
                
                <?php if (!empty($formData['staffId'])): ?>
                <button type="button" class="secondary-button" onclick="resetForm()">Cancel</button>
                <?php else: ?>
                <button type="button" class="secondary-button" onclick="resetForm()">Reset</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<style>
/* General Styles */
.page-title {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 20px;
    color: #333;
    text-align: center;
}

.message-container {
    margin-bottom: 20px;
}

.success-message {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
    padding: 10px 15px;
    border-radius: 4px;
}

.error-message {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    padding: 10px 15px;
    border-radius: 4px;
}

.info-message {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
    padding: 10px 15px;
    border-radius: 4px;
}

/* Layout Styles */
.manage-container {
    display: flex;
    flex-direction: row;
    gap: 20px;
    margin-top: 20px;
}

.list-container {
    flex: 3;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    padding: 20px;
}

.form-container {
    flex: 2;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    padding: 20px;
}

/* Mobile responsive */
@media (max-width: 800px) {
    .manage-container {
        flex-direction: column;
    }
}

/* Section Headings */
.list-container h3,
.form-container h3 {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eee;
    color: #444;
}

/* Search Form */
.search-container {
    margin-bottom: 20px;
}

.search-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.search-form input[type="text"] {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    width: 100%;
}

.search-buttons {
    display: flex;
    gap: 10px;
}

/* Staff Table */
.table-container {
    overflow-x: auto;
    margin-bottom: 15px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.data-table th {
    background-color: #f8f9fa;
    color: #495057;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 2px solid #dee2e6;
    font-weight: bold;
}

.data-table td {
    padding: 10px 15px;
    border-bottom: 1px solid #dee2e6;
}

.data-table tr:nth-child(even) {
    background-color: #f8f9fa;
}

.data-table tr:hover {
    background-color: #f1f1f1;
}

.actions-cell {
    white-space: nowrap;
    text-align: center;
}

.no-records {
    text-align: center;
    color: #6c757d;
    padding: 20px !important;
}

/* Form Styles */
.staff-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-group {
    flex: 1;
    display: flex;
    flex-direction: column;
}

label {
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 5px;
    color: #495057;
}

.required {
    color: #dc3545;
}

input[type="text"],
input[type="email"],
input[type="password"],
input[type="date"],
select {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    width: 100%;
}

.field-info {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
}

button, .action-button {
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    padding: 8px 15px;
    border-radius: 4px;
    border: none;
    transition: background-color 0.2s;
}

.primary-button, 
button[type="submit"]:not(.secondary-button):not(.delete-button) {
    background-color: #007bff;
    color: white;
}

.primary-button:hover, 
button[type="submit"]:not(.secondary-button):not(.delete-button):hover {
    background-color: #0069d9;
}

.secondary-button {
    background-color: #6c757d;
    color: white;
}

.secondary-button:hover {
    background-color: #5a6268;
}

.action-button {
    display: inline-block;
    text-decoration: none;
    text-align: center;
    margin: 2px;
}

.edit-button {
    background-color: #ffc107;
    color: #212529;
}

.edit-button:hover {
    background-color: #e0a800;
}

.delete-button {
    background-color: #dc3545;
    color: white;
}

.delete-button:hover {
    background-color: #c82333;
}

.disabled {
    background-color: #e9ecef;
    color: #6c757d;
    cursor: not-allowed;
}

.delete-form {
    display: inline;
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 10px;
}
</style>

<script>
    function resetForm() {
        // Redirect back to the main page without query parameters
        window.location.href = '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>';
    }
</script>

<?php
$content = ob_get_clean();

include 'master.php';
?>
