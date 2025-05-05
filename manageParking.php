<?php
require_once 'config.php'; // Ensures config runs first, handling session start

requireAdmin(); // Checks if user is admin (requires session)

$message = '';
$formData = [
    'parkingId' => '',
    'parkingName' => '',
    'description' => '',
    'siteId' => '',
    'filterId' => 4, // Default to Normal (FILTERID = 4)
    'parkingImages' => [],
    // Removed detailed feature arrays
];

$upload_dir = 'uploads/parking/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

try {
    // Use $dataAccess created in config.php
    $sites = $dataAccess->GetDataSQL("SELECT * FROM tbl_site ORDER BY SITENAME");
} catch (Exception $ex) {
    $message = displayError('Error loading sites: ' . $ex->getMessage());
    $sites = [];
}

// Handle Edit request from URL parameter
if (isset($_GET['id'])) {
    $parkingId = $_GET['id'];

    try {
        $siteId = null;
        $parkingDetails = null;

        // Use the existing method that checks across all tables
        $parkingDetails = $dataAccess->GetParkingSpaceByGlobalId($parkingId);

        if ($parkingDetails !== null) {
            $formData = [
                'parkingId' => $parkingDetails['PARKINGID'],
                'parkingName' => $parkingDetails['PARKINGNAME'],
                'description' => $parkingDetails['DESCRIPTION'],
                'siteId' => $parkingDetails['SITEID'],
                'filterId' => $parkingDetails['FILTERID'], // Load the existing filter ID
                'parkingImages' => [], // Image loading logic would go here if implemented
                // Removed detailed feature arrays
            ];
        } else {
             $message = displayError('Parking space with ID ' . $parkingId . ' not found.');
             $formData['parkingId'] = ''; // Reset ID if not found
        }
    } catch (Exception $ex) {
        $message = displayError('Error loading parking details: ' . $ex->getMessage());
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Add/Update parking space
    if (isset($_POST['action'])) {
        $is_add_action = ($_POST['action'] === 'add');
        $formParkingId = sanitizeInput($_POST['parkingId']);

        // Get form data, including the new filterId dropdown
        $formData = [
            'parkingId' => $formParkingId,
            'parkingName' => sanitizeInput($_POST['parkingName']),
            'description' => sanitizeInput($_POST['description']),
            'siteId' => sanitizeInput($_POST['siteId']),
            'filterId' => sanitizeInput($_POST['filterId']) // Get selected filter ID
            // Removed detailed feature arrays
        ];

        // Validate form data
        $errors = [];
        if (empty($formData['parkingId']) || !is_numeric($formData['parkingId']) || $formData['parkingId'] <= 0) $errors[] = 'Valid Parking ID is required';
        if (empty($formData['parkingName'])) $errors[] = 'Parking name is required';
        if (empty($formData['description'])) $errors[] = 'Description is required';
        if (empty($formData['siteId'])) $errors[] = 'Site selection is required';
        if (empty($formData['filterId']) || !in_array($formData['filterId'], [1, 2, 3, 4, 5])) $errors[] = 'Valid Filter Type is required'; // Added 5 for admin-disabled

        // --- TODO: Add Image Upload Handling Logic Here if needed ---
        // Example:
        // $uploadedImagePaths = [];
        // if (isset($_FILES['parkingImages']) && !empty($_FILES['parkingImages']['name'][0])) {
        //     // Loop through uploaded files, validate, move, and collect paths
        //     // Add paths to $uploadedImagePaths array
        //     // Handle potential upload errors and add to $errors array
        // }
        // $imageJson = json_encode($uploadedImagePaths); // Store as JSON

        if (empty($errors)) {
            try {
                $siteId = intval($formData['siteId']);
                $filterId = intval($formData['filterId']); // Use the filterId from the form
                $tableName = '';
                switch ($siteId) {
                    case 1: $tableName = 'tbl_parkingspaceA'; break;
                    case 2: $tableName = 'tbl_parkingspaceB'; break;
                    case 3: $tableName = 'tbl_parkingspaceC'; break;
                    default: throw new Exception("Invalid site ID");
                }

                // --- REMOVED calculation of FILTERID based on checkboxes ---

                // Check if parking ID exists in the target site table
                $existing = $dataAccess->GetData("SELECT PARKINGID FROM `$tableName` WHERE PARKINGID = ?", [$formData['parkingId']]);

                if (count($existing) > 0) {
                    // Update existing record
                    if ($is_add_action) {
                         // If it's an 'add' action but the ID already exists in the target table, it's an error.
                         // However, if the ID exists in *another* site's table, that's okay for a global ID system.
                         // We should only prevent adding if the ID *specifically* exists in the *target* table.
                         throw new Exception("Parking ID {$formData['parkingId']} already exists in site table '{$tableName}'. Cannot add duplicate to the same site.");
                    }
                    // Update the record in the specific site table
                    $sqlUpdate = "UPDATE `$tableName` SET PARKINGNAME = ?, DESCRIPTION = ?, SITEID = ?, FILTERID = ? WHERE PARKINGID = ?";
                    // Add PARKINGIMAGE = ? if handling uploads: $imageJson
                    $updateParams = [
                        $formData['parkingName'],
                        $formData['description'],
                        $siteId,
                        $filterId, // Use the direct value from dropdown
                        // $imageJson, // Add if handling uploads
                        $formData['parkingId']
                    ];
                    $result = $dataAccess->ExecuteCommand($sqlUpdate, $updateParams);
                    $actionMessage = "updated";

                } else {
                    // Insert new record
                    // Before inserting, double-check the ID doesn't exist in *any* site table if it's meant to be globally unique
                     $globalCheckSql = "SELECT PARKINGID FROM (
                            SELECT PARKINGID FROM tbl_parkingspaceA WHERE PARKINGID = ?
                            UNION ALL SELECT PARKINGID FROM tbl_parkingspaceB WHERE PARKINGID = ?
                            UNION ALL SELECT PARKINGID FROM tbl_parkingspaceC WHERE PARKINGID = ?
                        ) AS combined WHERE PARKINGID = ?";
                     $globalExisting = $dataAccess->GetData($globalCheckSql, [$formData['parkingId'], $formData['parkingId'], $formData['parkingId'], $formData['parkingId']]);

                     if (count($globalExisting) > 0) {
                         throw new Exception("Parking ID {$formData['parkingId']} already exists in another site's table. Please use a unique ID.");
                     }

                    // Insert into the specific site table
                    $sqlInsert = "INSERT INTO `$tableName` (PARKINGID, PARKINGNAME, DESCRIPTION, SITEID, FILTERID) VALUES (?, ?, ?, ?, ?)";
                    // Add PARKINGIMAGE if handling uploads
                    $insertParams = [
                        $formData['parkingId'],
                        $formData['parkingName'],
                        $formData['description'],
                        $siteId,
                        $filterId // Use the direct value from dropdown
                        // $imageJson // Add if handling uploads
                    ];
                    $result = $dataAccess->ExecuteCommand($sqlInsert, $insertParams);
                    $actionMessage = "added";
                }

                // --- REMOVED DELETE/INSERT for feature tables ---

                if ($result > 0) {
                    $message = displaySuccess("Parking space successfully {$actionMessage}.");
                    // Reset form after successful add
                    if ($is_add_action) {
                        $formData = [
                            'parkingId' => '', 'parkingName' => '', 'description' => '', 'siteId' => '',
                            'filterId' => 4, // Reset filter to Normal
                            'parkingImages' => [],
                        ];
                    }
                } else {
                     $message = displayError("Failed to {$actionMessage} parking space or no changes were made.");
                }

            } catch (Exception $ex) {
                $message = displayError("Error saving parking space: " . $ex->getMessage());
            }
        } else {
             $message = displayError('Please correct the following errors: ' . implode(', ', $errors));
        }
    }
    
    // Handle quick disable/enable of parking space
    elseif (isset($_POST['quick_disable']) || isset($_POST['quick_enable'])) {
        $parkingIdToToggle = sanitizeInput($_POST['parkingId']);
        $isDisable = isset($_POST['quick_disable']);
        
        try {
            // First, find which site table the parking space belongs to
            $parkingDetails = $dataAccess->GetParkingSpaceByGlobalId($parkingIdToToggle);
            
            if ($parkingDetails) {
                $siteId = $parkingDetails['SITEID'];
                $tableName = '';
                
                switch ($siteId) {
                    case 1: $tableName = 'tbl_parkingspaceA'; break;
                    case 2: $tableName = 'tbl_parkingspaceB'; break;
                    case 3: $tableName = 'tbl_parkingspaceC'; break;
                    default: throw new Exception("Invalid site ID");
                }
                
                // Update FILTERID to 5 (admin-disabled) or 4 (normal)
                $newFilterId = $isDisable ? 5 : 4;
                $sqlUpdate = "UPDATE `$tableName` SET FILTERID = ? WHERE PARKINGID = ?";
                $result = $dataAccess->ExecuteCommand($sqlUpdate, [$newFilterId, $parkingIdToToggle]);
                
                if ($result > 0) {
                    $message = displaySuccess("Parking space #" . $parkingIdToToggle . " " . 
                                             ($isDisable ? "disabled" : "enabled") . " successfully.");
                                             
                    // Refresh the data if we're currently editing this space
                    if ($formData['parkingId'] == $parkingIdToToggle) {
                        $formData['filterId'] = $newFilterId;
                    }
                } else {
                    $message = displayError("Failed to " . ($isDisable ? "disable" : "enable") . " parking space.");
                }
            } else {
                $message = displayError("Parking space not found.");
            }
        } catch (Exception $ex) {
            $message = displayError("Error toggling parking space: " . $ex->getMessage());
        }
    }
    
    // Handle parking space deletion
    elseif (isset($_POST['delete'])) {
        $parkingIdToDelete = sanitizeInput($_POST['parkingId']);
        try {
            // Use the method that handles deletion across tables
            $result = $dataAccess->DeleteParkingSpaceByGlobalId($parkingIdToDelete);
            if ($result > 0) {
                $message = displaySuccess('Parking space deleted successfully.');
                // Reset form if the deleted item was the one being edited
                if ($formData['parkingId'] == $parkingIdToDelete) {
                     $formData = [ // Reset form data
                        'parkingId' => '', 'parkingName' => '', 'description' => '', 'siteId' => '',
                        'filterId' => 4, // Reset filter to Normal
                        'parkingImages' => [],
                     ];
                }
            } else {
                $message = displayError('Failed to delete parking space (it might not exist).');
            }
        } catch (Exception $ex) {
            $message = displayError('Error deleting parking space: ' . $ex->getMessage());
        }
    }
}

// Initialize search variables and list
$filterId = null;
$siteId = null;
$searchTerm = '';
$parkingList = []; // Initialize parking list

// Handle search submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $searchTerm = trim($_POST['searchTerm'] ?? '');
    try {
        if ($searchTerm !== '') {
            // If search term is numeric, assume it's an ID
            if (is_numeric($searchTerm)) {
                $parkingDetails = $dataAccess->GetParkingSpaceByGlobalId($searchTerm);
                if ($parkingDetails) {
                    $parkingList[] = $parkingDetails; // Add the single result
                }
            }
            // Otherwise, search by name or site name
            else {
                $sql = "SELECT p.PARKINGID, p.PARKINGNAME, s.SITENAME, p.SITEID, p.FILTERID, p.DESCRIPTION, p.PARKINGIMAGE
                        FROM tbl_site s
                        JOIN (
                            SELECT PARKINGID, PARKINGNAME, SITEID, FILTERID, DESCRIPTION, PARKINGIMAGE FROM tbl_parkingspaceA
                            UNION ALL
                            SELECT PARKINGID, PARKINGNAME, SITEID, FILTERID, DESCRIPTION, PARKINGIMAGE FROM tbl_parkingspaceB
                            UNION ALL
                            SELECT PARKINGID, PARKINGNAME, SITEID, FILTERID, DESCRIPTION, PARKINGIMAGE FROM tbl_parkingspaceC
                        ) p ON s.SITEID = p.SITEID
                        WHERE p.PARKINGNAME LIKE ? OR s.SITENAME LIKE ?";
                $likeTerm = '%' . $searchTerm . '%';
                $parkingList = $dataAccess->GetData($sql, [$likeTerm, $likeTerm]);
            }
        }
        // Handle "Show All"
        elseif (isset($_POST['search']) && $_POST['search'] === 'showall') {
            $parkingList = $dataAccess->GetAllParkingSpacesCombined();
        }

        // Display message if search yielded no results
        if (empty($parkingList) && $searchTerm !== '' && (!isset($_POST['search']) || $_POST['search'] !== 'showall')) {
             $message = displayInfo('No parking spaces found matching your search criteria.');
        }
    } catch (Exception $ex) {
         $message = displayError('Error searching parking spaces: ' . $ex->getMessage());
    }
}

// Get the next GLOBAL parking ID for new records
try {
    $next_parking_id = $dataAccess->GetNextGlobalParkingId();
} catch (Exception $ex) {
     // If message is already set, append; otherwise, set it.
     $errorMsg = 'Error getting next parking ID: ' . $ex->getMessage();
     $message = !empty($message) ? $message . '<br>' . displayError($errorMsg) : displayError($errorMsg);
     $next_parking_id = 1; // Fallback
}


ob_start(); // Start output buffering
?>

<h2 class="page-title">MANAGE PARKING</h2>

<?php if (!empty($message)): ?>
<div class="message-container">
    <?php echo $message; // Message includes HTML from displayX functions ?>
</div>
<?php endif; ?>

<div class="manage-container">
    <!-- Left Column: Parking List -->
    <div class="list-container">
        <h3>Parking Spaces</h3>

        <!-- Search Form -->
        <div class="search-container">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="search-form">
                <input type="text" id="searchTerm" name="searchTerm" placeholder="Search by ID, name or site" value="<?php echo htmlspecialchars($searchTerm); ?>">
                <div class="search-buttons">
                    <button type="submit" name="search" value="search" class="primary-button">Search</button>
                    <button type="submit" name="search" value="showall" class="secondary-button">Show All</button>
                </div>
            </form>
        </div>

        <!-- Parking Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Site</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="parkingTableBody">
                    <?php if (count($parkingList) > 0): ?>
                        <?php foreach ($parkingList as $parking): ?>
                        <tr<?php echo ($parking['FILTERID'] == 5) ? ' class="admin-disabled-row"' : ''; ?>>
                            <td><?php echo $parking['PARKINGID']; ?></td>
                            <td><?php echo htmlspecialchars($parking['PARKINGNAME']); ?></td>
                            <td><?php echo htmlspecialchars($parking['SITENAME']); ?></td>
                            <td>
                                <?php 
                                    $statusClass = '';
                                    $statusText = 'Normal';
                                    switch($parking['FILTERID']) {
                                        case 1: 
                                            $statusClass = 'status-disability';
                                            $statusText = 'Disability';
                                            break;
                                        case 2: 
                                            $statusClass = 'status-ev';
                                            $statusText = 'EV Charging'; 
                                            break;
                                        case 3: 
                                            $statusClass = 'status-visitor';
                                            $statusText = 'Visitor'; 
                                            break;
                                        case 5: 
                                            $statusClass = 'status-disabled';
                                            $statusText = 'Disabled'; 
                                            break;
                                        default: 
                                            $statusClass = 'status-normal';
                                            $statusText = 'Normal';
                                    }
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            </td>
                            <td class="actions-cell">
                                <!-- Edit Link -->
                                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $parking['PARKINGID']); ?>" class="action-button edit-button">Edit</a>

                                <!-- Quick Enable/Disable Buttons -->
                                <?php if ($parking['FILTERID'] == 5): // If admin-disabled ?>
                                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline-form">
                                    <input type="hidden" name="parkingId" value="<?php echo $parking['PARKINGID']; ?>">
                                    <button type="submit" name="quick_enable" class="action-button enable-button">Enable</button>
                                </form>
                                <?php else: ?>
                                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline-form">
                                    <input type="hidden" name="parkingId" value="<?php echo $parking['PARKINGID']; ?>">
                                    <button type="submit" name="quick_disable" class="action-button disable-button">Disable</button>
                                </form>
                                <?php endif; ?>

                                <!-- Delete Form -->
                                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline-form" onsubmit="return confirmDelete(<?php echo $parking['PARKINGID']; ?>);">
                                    <input type="hidden" name="parkingId" value="<?php echo $parking['PARKINGID']; ?>">
                                    <button type="submit" name="delete" class="action-button delete-button">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="no-records">Use the search above to find parking spaces</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div> <!-- End List Container -->

    <!-- Right Column: Add/Edit Form -->
    <div class="form-container">
        <h3><?php echo empty($formData['parkingId']) || !isset($_GET['id']) ? 'Add New Parking Space' : 'Update Parking Space'; ?></h3>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data" class="parking-form">
            <!-- Hidden fields for action and ID -->
            <input type="hidden" name="action" value="<?php echo empty($formData['parkingId']) || !isset($_GET['id']) ? 'add' : 'update'; ?>">
            <!-- Use next ID for add, existing ID for update -->
            <input type="hidden" name="parkingId" value="<?php echo htmlspecialchars(empty($formData['parkingId']) || !isset($_GET['id']) ? $next_parking_id : $formData['parkingId']); ?>">

            <!-- Display Parking ID (read-only for user) -->
             <div class="form-group">
                <label for="displayParkingId">Parking ID</label>
                <input type="text" id="displayParkingId" value="<?php echo htmlspecialchars(empty($formData['parkingId']) || !isset($_GET['id']) ? $next_parking_id : $formData['parkingId']); ?>" readonly style="background-color: #e9ecef;">
                <?php if (empty($formData['parkingId']) || !isset($_GET['id'])): ?>
                    <span class="field-info">Next available ID. This will be used for the new space.</span>
                <?php endif; ?>
            </div>

            <!-- Parking Name -->
            <div class="form-group">
                <label for="parkingName">Parking Name <span class="required">*</span></label>
                <input type="text" id="parkingName" name="parkingName" value="<?php echo htmlspecialchars($formData['parkingName']); ?>" required>
            </div>

            <!-- Site Selection -->
            <div class="form-group">
                <label for="siteId">Site <span class="required">*</span></label>
                <select id="siteId" name="siteId" required>
                    <option value="">Select Site</option>
                    <?php foreach ($sites as $site): ?>
                    <option value="<?php echo $site['SITEID']; ?>" <?php echo ($formData['siteId'] == $site['SITEID']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($site['SITENAME']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Type Selection -->
            <div class="form-group">
                <label for="filterId">Filter Type <span class="required">*</span></label>
                <select id="filterId" name="filterId" required>
                    <option value="4" <?php echo ($formData['filterId'] == 4) ? 'selected' : ''; ?>>Normal</option>
                    <option value="1" <?php echo ($formData['filterId'] == 1) ? 'selected' : ''; ?>>Disabled</option>
                    <option value="2" <?php echo ($formData['filterId'] == 2) ? 'selected' : ''; ?>>EV Charging</option>
                    <option value="3" <?php echo ($formData['filterId'] == 3) ? 'selected' : ''; ?>>Visitor</option>
                    <option value="5" <?php echo ($formData['filterId'] == 5) ? 'selected' : ''; ?>>Admin Disabled</option>
                </select>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label for="description">Description <span class="required">*</span></label>
                <textarea id="description" name="description" required><?php echo htmlspecialchars($formData['description']); ?></textarea>
            </div>

            <!-- Image Upload (Basic - Needs PHP handling) -->
            <!-- <div class="form-group">
                <label for="parkingImages">Parking Images <small>(Optional)</small></label>
                <input type="file" id="parkingImages" name="parkingImages[]" multiple accept="image/*">
                <span class="field-info">Select one or more images.</span>
                 Display existing images here if needed
            </div> -->

            <!-- Form Buttons -->
            <div class="form-buttons">
                <button type="submit"><?php echo empty($formData['parkingId']) || !isset($_GET['id']) ? 'Add Parking Space' : 'Update Parking Space'; ?></button>

                <?php if (!empty($formData['parkingId']) && isset($_GET['id'])): ?>
                <!-- Show Cancel button when editing -->
                <button type="button" class="secondary-button" onclick="resetForm()">Cancel</button>
                <?php else: ?>
                <!-- Show Reset button when adding -->
                <button type="button" class="secondary-button" onclick="resetForm()">Reset</button>
                <?php endif; ?>
            </div>
        </form>
    </div> <!-- End Form Container -->
</div> <!-- End Manage Container -->

<!-- Image Modal (HTML Structure) -->
<div id="imageModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <div class="gallery-container">
            <img id="galleryImage" class="gallery-image">
            <button id="prevButton" class="gallery-nav prev-button">&#10094;</button>
            <button id="nextButton" class="gallery-nav next-button">&#10095;</button>
        </div>
        <div id="thumbnailContainer" class="thumbnails-container"></div>
    </div>
</div>

<!-- Inline CSS -->
<style>
.page-title { font-size: 24px; font-weight: bold; margin-bottom: 20px; color: #333; text-align: center; }
.message-container { margin-bottom: 20px; }
.success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px 15px; border-radius: 4px; }
.error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px 15px; border-radius: 4px; }
.info-message { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 10px 15px; border-radius: 4px; }
.manage-container { display: flex; flex-direction: row; gap: 20px; margin-top: 20px; align-items: flex-start; }
.list-container {
    flex: 3;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    padding: 20px;
    max-height: calc(100vh - 150px); /* Adjust based on header/footer height */
    overflow-y: auto;
}
.form-container {
    flex: 2;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    padding: 20px;
    position: sticky; /* Make form sticky */
    top: 20px; /* Adjust top position as needed */
    align-self: flex-start; /* Align to top */
    max-height: calc(100vh - 40px); /* Limit height to viewport minus padding */
    overflow-y: auto; /* Allow scrolling within the form container */
}
/* Responsive adjustments */
@media (max-width: 800px) {
    .manage-container { flex-direction: column; align-items: stretch; }
    .list-container {
        max-height: none; /* Remove height limit */
        overflow-y: visible; /* Allow natural flow */
    }
    .form-container {
        position: relative; /* Disable sticky */
        top: auto;
        max-height: none;
        overflow-y: visible;
    }
}
.list-container h3, .form-container h3 { font-size: 18px; font-weight: bold; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 1px solid #eee; color: #444; }
.search-container { margin-bottom: 20px; }
.search-form { display: flex; flex-direction: column; gap: 10px; }
.search-form input[type="text"] { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 100%; box-sizing: border-box; }
.search-buttons { display: flex; gap: 10px; }
.table-container { overflow-x: auto; margin-bottom: 15px; }
.data-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.data-table th { background-color: #f8f9fa; color: #495057; text-align: left; padding: 12px 15px; border-bottom: 2px solid #dee2e6; font-weight: bold; }
.data-table td { padding: 10px 15px; border-bottom: 1px solid #dee2e6; }
.data-table tr:nth-child(even) { background-color: #f8f9fa; }
.data-table tr:hover { background-color: #f1f1f1; }
.admin-disabled-row { background-color: #ffe6e6 !important; }
.admin-disabled-row:hover { background-color: #ffd9d9 !important; }
.actions-cell { white-space: nowrap; text-align: center; }
.no-records { text-align: center; color: #6c757d; padding: 20px !important; }
.parking-form { display: flex; flex-direction: column; gap: 15px; }
.form-group { display: flex; flex-direction: column; }
label { font-size: 14px; font-weight: 500; margin-bottom: 5px; color: #495057; }
.required { color: #dc3545; }
input[type="text"], input[type="file"], select, textarea { padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; width: 100%; box-sizing: border-box; }
textarea { min-height: 100px; resize: vertical; }
.field-info { font-size: 12px; color: #6c757d; margin-top: 4px; }
/* Removed filter-options-section styles */
button, .action-button { cursor: pointer; font-size: 14px; font-weight: 500; padding: 8px 15px; border-radius: 4px; border: none; transition: background-color 0.2s; }
.primary-button, button[type="submit"]:not(.secondary-button):not(.delete-button):not(.disable-button):not(.enable-button) { background-color: #007bff; color: white; }
.primary-button:hover, button[type="submit"]:not(.secondary-button):not(.delete-button):not(.disable-button):not(.enable-button):hover { background-color: #0069d9; }
.secondary-button { background-color: #6c757d; color: white; }
.secondary-button:hover { background-color: #5a6268; }
.action-button { display: inline-block; text-decoration: none; text-align: center; margin: 2px; }
.edit-button { background-color: #ffc107; color: #212529; }
.edit-button:hover { background-color: #e0a800; }
.view-button { background-color: #17a2b8; color: white; } /* Added for consistency if needed */
.view-button:hover { background-color: #138496; }
.delete-button { background-color: #dc3545; color: white; }
.delete-button:hover { background-color: #c82333; }
.disable-button { background-color: #ff3333; color: white; }
.disable-button:hover { background-color: #cc0000; }
.enable-button { background-color: #28a745; color: white; }
.enable-button:hover { background-color: #218838; }
.inline-form { display: inline; }
.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}
.status-normal { background-color: #E6E6FA; color: #333; }
.status-disability { background-color: #BA55D3; color: white; }
.status-ev { background-color: #9370DB; color: white; }
.status-visitor { background-color: #DA70D6; color: white; }
.status-disabled { background-color: #ff3333; color: white; }
.form-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; }
/* Modal Styles */
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.9); }
.modal-content { position: relative; margin: 5% auto; padding: 20px; width: 80%; max-width: 800px; animation: fadeIn 0.3s; }
.close { position: absolute; top: 10px; right: 25px; color: #f1f1f1; font-size: 35px; font-weight: bold; cursor: pointer; z-index: 1010; }
.gallery-container { position: relative; display: flex; justify-content: center; align-items: center; margin-bottom: 15px; }
.gallery-image { max-width: 100%; max-height: 70vh; object-fit: contain; border: 3px solid white; }
.gallery-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: white; border: none; padding: 15px; cursor: pointer; font-size: 18px; border-radius: 50%; transition: background-color 0.2s; }
.prev-button { left: 10px; }
.next-button { right: 10px; }
.gallery-nav:hover { background: rgba(0,0,0,0.8); }
.thumbnails-container { display: flex; justify-content: center; flex-wrap: wrap; gap: 8px; margin-top: 15px; }
.thumbnail { width: 60px; height: 40px; object-fit: cover; border: 2px solid transparent; cursor: pointer; transition: border-color 0.2s; }
.thumbnail.active { border-color: white; }
@keyframes fadeIn { from {opacity: 0} to {opacity: 1} }
</style>

<!-- JavaScript -->
<script>
    // Function to reset the form (clears fields and redirects to remove ID from URL)
    function resetForm() {
        window.location.href = '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>';
    }

    // Function to confirm deletion
    function confirmDelete(parkingId) {
        return confirm('Are you sure you want to delete parking space ID ' + parkingId + '? This action cannot be undone.');
    }

    // Function to fetch and display parking images (called by viewParkingImages button if added)
    function viewParkingImages(parkingId) {
        fetch('get_parking_images.php?id=' + parkingId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.images && data.images.length > 0) {
                    showGallery(data.images);
                } else {
                    alert(data.message || 'No images available for this parking space.');
                }
            })
            .catch(error => {
                console.error('Error loading images:', error);
                alert('Error loading images. Please try again.');
            });
    }

    // Function to display images in the modal gallery
    function showGallery(images) {
        if (!images || images.length === 0) return;

        const modal = document.getElementById('imageModal');
        const galleryImage = document.getElementById('galleryImage');
        const thumbnailContainer = document.getElementById('thumbnailContainer');
        const prevButton = document.getElementById('prevButton');
        const nextButton = document.getElementById('nextButton');

        thumbnailContainer.innerHTML = ''; // Clear previous thumbnails
        let currentIndex = 0;

        // Function to update the main image and active thumbnail
        function updateGallery() {
            galleryImage.src = images[currentIndex];
            const thumbnails = thumbnailContainer.querySelectorAll('.thumbnail');
            thumbnails.forEach((thumb, idx) => {
                thumb.classList.toggle('active', idx === currentIndex);
            });
        }

        // Create thumbnails
        images.forEach((src, idx) => {
            const thumb = document.createElement('img');
            thumb.src = src;
            thumb.className = 'thumbnail';
            if (idx === currentIndex) thumb.classList.add('active');
            thumb.addEventListener('click', () => {
                currentIndex = idx;
                updateGallery();
            });
            thumbnailContainer.appendChild(thumb);
        });

        // Set initial image
        galleryImage.src = images[currentIndex];

        // Navigation button handlers
        prevButton.onclick = () => {
            currentIndex = (currentIndex - 1 + images.length) % images.length;
            updateGallery();
        };

        nextButton.onclick = () => {
            currentIndex = (currentIndex + 1) % images.length;
            updateGallery();
        };

        // Show the modal
        modal.style.display = 'block';

        // Close button handler
        const closeBtn = document.querySelector('#imageModal .close');
        closeBtn.onclick = () => {
            modal.style.display = 'none';
            document.removeEventListener('keydown', handleKeydown); // Remove listener when closed
        };

        // Close modal if clicking outside the content
        window.onclick = (event) => {
            if (event.target === modal) {
                closeBtn.onclick();
            }
        };

        // Keyboard navigation handler
        function handleKeydown(event) {
             if (modal.style.display === 'block') { // Only act if modal is open
                if (event.key === 'ArrowLeft') prevButton.click();
                else if (event.key === 'ArrowRight') nextButton.click();
                else if (event.key === 'Escape') closeBtn.click();
            }
        }
        // Add keyboard listener when modal opens
        document.addEventListener('keydown', handleKeydown);
    }
</script>

<?php
$content = ob_get_clean(); // Get content from buffer

include 'master.php'; // Include the master template
?>
