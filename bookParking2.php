<?php
// --- config.php MUST be included first ---
include_once 'config.php';

// --- Start session AFTER config.php ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'BOOK PARKING - PARKING MANAGEMENT SYSTEM';
$custom_layout = false;

// --- Site Configuration ---
$sitesConfig = [
    'A' => ['id' => 1, 'name' => 'Site A', 'color_prefix' => '#e9d8ff', 'ev_color' => '#9370DB', 'disabled_color' => '#BA55D3', 'visitor_color' => '#DA70D6', 'normal_color' => '#E6E6FA', 'admin_disabled_color' => '#ff3333'],
    'B' => ['id' => 2, 'name' => 'Site B', 'color_prefix' => '#ffd699', 'ev_color' => '#ff8c00', 'disabled_color' => '#ffa500', 'visitor_color' => '#ffbf00', 'normal_color' => '#ffdead', 'admin_disabled_color' => '#ff3333'],
    'C' => ['id' => 3, 'name' => 'Site C', 'color_prefix' => '#FAFAD2', 'ev_color' => '#FFD700', 'disabled_color' => '#FFBF00', 'visitor_color' => '#EEE8AA', 'normal_color' => '#FFFACD', 'admin_disabled_color' => '#ff3333'],
];
// --- End Site Configuration ---

// --- Determine Selected Site ---
$selectedSiteKey = null;
if (isset($_POST['selected_site'])) {
    $selectedSiteKey = strtoupper($_POST['selected_site']);
} elseif (isset($_GET['site'])) {
    $selectedSiteKey = strtoupper($_GET['site']);
}

if (empty($selectedSiteKey) || !isset($sitesConfig[$selectedSiteKey])) {
    $selectedSiteKey = 'A';
}

$selectedSiteConfig = $sitesConfig[$selectedSiteKey];
$selectedSiteId = $selectedSiteConfig['id'];
// --- End Determine Selected Site ---

// --- User Information ---
$isLoggedIn = isset($_SESSION['customer_id']) || isset($_SESSION['staff_id']);
$isStaff = isset($_SESSION['staff_id']);
$isCustomer = isset($_SESSION['customer_id']);
$loggedInCustomerId = $isCustomer ? $_SESSION['customer_id'] : null;
// --- End User Information ---

// --- Fetch Data for Selected Site ---
$parkingSpaces = [];
$allBookingsForDate = [];
$message = '';
$errors = [];
$categoryCounts = ['EV' => 0, 'DISABLED' => 0, 'VISITOR' => 0, 'NORMAL' => 0, 'ADMIN_DISABLED' => 0, 'TOTAL' => 0];
$displayTotalCount = 0;
$categorizedSpaces = [
    'DISABLED' => [],
    'EV' => [],
    'VISITOR' => [],
    'NORMAL' => [],
    'ADMIN_DISABLED' => []
];
$categoryOrder = ['DISABLED', 'EV', 'VISITOR', 'NORMAL', 'ADMIN_DISABLED'];
$date = $_POST['booking_date'] ?? ($_GET['booking_date'] ?? date('Y-m-d')); // Define $date earlier

try {
    $tableName = '';
    switch ($selectedSiteKey) {
        case 'A': $tableName = 'tbl_parkingspaceA'; break;
        case 'B': $tableName = 'tbl_parkingspaceB'; break;
        case 'C': $tableName = 'tbl_parkingspaceC'; break;
        default:  $tableName = 'tbl_parkingspaceA';
    }

    // --- MODIFICATION: Order by FILTERID then PARKINGID for consistent sequential numbering ---
    $sqlSpaces = "SELECT PARKINGID, PARKINGNAME, FILTERID FROM `$tableName` ORDER BY FILTERID, PARKINGID";
    // --- END MODIFICATION ---
    $parkingSpaces = $dataAccess->GetData($sqlSpaces);

    $displayTotalCount = count($parkingSpaces);
    foreach ($parkingSpaces as $space) {
        $category = 'NORMAL';
        if (isset($space['FILTERID'])) {
             switch ($space['FILTERID']) {
                 case 1: $category = 'DISABLED'; break;
                 case 2: $category = 'EV';       break;
                 case 3: $category = 'VISITOR';  break;
                 case 5: $category = 'ADMIN_DISABLED'; break; // New filter type for admin-disabled
                 default: $category = 'NORMAL'; break;
             }
        }
        if (isset($categoryCounts[$category])) {
            $categoryCounts[$category]++;
        } else {
            $categoryCounts['NORMAL']++;
        }
        $categoryCounts['TOTAL']++;

        if (isset($categorizedSpaces[$category])) {
            $categorizedSpaces[$category][] = $space;
        } else {
            $categorizedSpaces['NORMAL'][] = $space;
        }
    }

    // --- REMOVED usort - Sorting is now done in the SQL query ---

    // $date defined earlier
    $sqlBookings = "SELECT b.PARKINGID, b.CUSTOMERID, b.STARTTIME, b.ENDTIME
                    FROM tbl_booking b
                    JOIN `$tableName` p ON b.PARKINGID = p.PARKINGID
                    WHERE p.SITEID = ? AND b.DATE = ?";
$allBookingsForDate = $dataAccess->GetData($sqlBookings, [$selectedSiteId, $date]);

// Fetch user's active bookings for the selected site and date (not completed)
$userActiveBookings = [];
if ($isCustomer) {
    $sqlUserActiveBookings = "SELECT b.PARKINGID, b.STARTTIME, b.ENDTIME
                              FROM tbl_booking b
                              JOIN `$tableName` p ON b.PARKINGID = p.PARKINGID
                              WHERE b.CUSTOMERID = ? AND p.SITEID = ? AND b.DATE = ? AND b.ENDTIME > ?";
    $currentTime = date('H:i:s');
    $userActiveBookings = $dataAccess->GetData($sqlUserActiveBookings, [$loggedInCustomerId, $selectedSiteId, $date, $currentTime]);
}

} catch (Exception $ex) {
    $errors[] = "Error fetching parking data: " . $ex->getMessage();
    $displayTotalCount = 0;
}
// --- End Fetch Data ---

// --- Handle Selected Spaces ---
$selectedParkingData = isset($_POST['selected_spaces']) ? json_decode($_POST['selected_spaces'], true) : [];
if (!is_array($selectedParkingData)) $selectedParkingData = [];
$selectedParkingIds = array_map(function($item) { return $item['id'] ?? null; }, $selectedParkingData);
$selectedParkingIds = array_filter($selectedParkingIds);
// --- End Handle Selected Spaces ---

// --- Handle Booking Submission ---
if (isset($_POST['btnbook'])) {
    if (!$isLoggedIn) {
        $_SESSION['redirect_after_login'] = 'bookParking2.php?site=' . $selectedSiteKey . '&booking_date=' . urlencode($date);
        $_SESSION['login_message'] = "Please log in to complete your booking.";
        header("Location: login.php");
        exit;
    }

    $customerIdToBook = $isStaff ? (isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0) : $loggedInCustomerId;
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? '';

    if ($isStaff && $customerIdToBook <= 0) $errors[] = "Please select a customer.";
    if (empty($date)) $errors[] = "Date is required.";
    if (empty($startTime)) $errors[] = "Start time is required.";
    if (empty($endTime)) $errors[] = "End time is required.";
    if (strtotime($endTime) <= strtotime($startTime)) $errors[] = "End time must be after start time.";
    if (empty($selectedParkingData)) $errors[] = "Please select at least one parking space.";

    $requestedStartTimestamp = strtotime("$date $startTime");
    $currentTimestamp = time();
    if ($requestedStartTimestamp < $currentTimestamp) {
        $errors[] = "Cannot book a time slot that has already started or passed.";
    }

    if (empty($errors)) {
        $startDateTime = $date . ' ' . $startTime . ':00';
        $endDateTime = $date . ' ' . $endTime . ':00';
        $bookingSuccessCount = 0;
        $bookedDisplayLabels = [];

        foreach ($selectedParkingData as $spaceData) {
            $parkingId = intval($spaceData['id'] ?? 0);
            $visualLabel = htmlspecialchars($spaceData['label'] ?? "ID $parkingId");

            if ($parkingId <= 0) {
                $errors[] = "Invalid parking space data submitted.";
                continue;
            }

            // Check if space is admin-disabled
            $sqlCheckDisabled = "SELECT FILTERID FROM `$tableName` WHERE PARKINGID = ?";
            $spaceInfo = $dataAccess->GetData($sqlCheckDisabled, [$parkingId]);
            
            if (!empty($spaceInfo) && isset($spaceInfo[0]['FILTERID']) && $spaceInfo[0]['FILTERID'] == 5) {
                $errors[] = "Parking space '$visualLabel' is currently unavailable (administrator disabled).";
                continue;
            }

            $sqlCheck = "SELECT BOOKINGID FROM tbl_booking WHERE PARKINGID = ? AND DATE = ? AND NOT (ENDTIME <= ? OR STARTTIME >= ?)";
            $conflictParams = [$parkingId, $date, $startDateTime, $endDateTime];
            $existingBookings = $dataAccess->GetData($sqlCheck, $conflictParams);

            if (count($existingBookings) > 0) {
                $errors[] = "Parking space '$visualLabel' is already booked during the selected time.";
                continue;
            }

            try {
                $result = $dataAccess->AddBooking($customerIdToBook, $parkingId, $date, $startDateTime, $endDateTime);

                if ($result > 0) {
                    $bookingSuccessCount++;
                    $bookedDisplayLabels[] = $visualLabel;
                } else {
                    $errors[] = "Failed to book parking space '$visualLabel'. Database error occurred or time was invalid.";
                }
            } catch (Exception $ex) {
                $errors[] = "Error booking space '$visualLabel': " . $ex->getMessage();
            }
        }

        if ($bookingSuccessCount > 0 && empty($errors)) {
            $_SESSION['booking_message'] = "Booking confirmed for: " . implode(', ', $bookedDisplayLabels) . "!";
        } elseif ($bookingSuccessCount > 0) {
             $_SESSION['booking_message'] = "Partially booked: " . implode(', ', $bookedDisplayLabels) . ". Some spaces had issues.";
        }
        $_POST['selected_spaces'] = json_encode([]);
        header("Location: bookParking2.php?site=" . $selectedSiteKey . '&booking_date=' . urlencode($date));
        exit;
    }
}
// --- End Handle Booking Submission ---

// --- Get Customers for Staff Dropdown ---
$customers = [];
if ($isStaff) {
    try {
        $customers = $dataAccess->GetAllCustomers();
    } catch (Exception $ex) {
        $errors[] = "Error loading customers: " . $ex->getMessage();
    }
}
// --- End Get Customers ---

ob_start();
?>

<h1 style="text-align:center; margin-top: 20px;">Book Parking - <?php echo htmlspecialchars($selectedSiteConfig['name']); ?></h1>

<!-- Site Selection Links -->
<div style="text-align: center; margin-bottom: 20px;">
    Select Site:
    <?php foreach ($sitesConfig as $key => $site): ?>
        <a href="?site=<?php echo $key; ?>&booking_date=<?php echo urlencode($date); ?>"
           style="padding: 5px 10px; margin: 0 5px; text-decoration: none; border-radius: 4px;
                  background-color: <?php echo ($key == $selectedSiteKey) ? $site['color_prefix'] : '#2d3741'; ?>;
                  color: <?php echo ($key == $selectedSiteKey) ? 'black' : 'white'; ?>;
                  border: 1px solid #999;">
            <?php echo htmlspecialchars($site['name']); ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Main Layout Container (Flexbox) -->
<div style="display: flex; flex-direction: row; flex-wrap: wrap; align-items: flex-start; gap: 20px; max-width: 1200px; margin: 20px auto;">

    <!-- Left Column: Parking Grid -->
    <div class="grid-column" style="flex: 1; min-width: 450px; max-width: 100%; border: 1px solid #ccc; padding: 15px; background-color: #f9f9f9; border-radius: 8px; max-height: calc(100vh - 180px); overflow-y: auto;">
        <div style="font-size: 18px; font-weight: bold; margin-bottom: 15px; text-align: center; color: <?php echo $selectedSiteConfig['ev_color']; ?>;">
            Parking Spaces Overview - <?php echo htmlspecialchars($selectedSiteConfig['name']); ?>
        </div>
        <!-- CSS Styles for Parking Grid -->
        <style>
            .parking-lot { width: 35px; height: 35px; border-radius: 4px; cursor: pointer; font-size: 8px; color: black; display: flex; align-items: center; justify-content: center; user-select: none; border: 1px solid #555; box-sizing: border-box; margin: 2px; overflow: hidden; text-align: center; line-height: 1.1; font-weight: bold; word-wrap: break-word; flex-shrink: 0; }
            .booked { background-color: gray !important; cursor: not-allowed; border-color: #333; color: white; }
            .selected { background-color: #28a745 !important; border-color: #1c7430 !important; color: white; }
            .my-booking { background-color: #28a745 !important; border-color: #1c7430 !important; cursor: not-allowed; color: white; }
            .admin-disabled { background-color: #ff3333 !important; cursor: not-allowed; border-color: #cc0000 !important; color: white; }
            .parking-lot.available { cursor: pointer; }
            .parking-lot:not(.available) { cursor: not-allowed; }
            .category-group { width: 100%; margin-bottom: 20px; padding-top: 10px; border-top: 1px dashed #ccc; }
            .category-group:first-child { border-top: none; padding-top: 0; }
            .category-title { text-align: center; margin-bottom: 10px; font-weight: bold; color: #333; font-size: 16px; text-transform: capitalize; }
            .category-spaces-container {
                display: flex;
                flex-wrap: wrap;
                justify-content: flex-start; /* Changed from center */
                gap: 5px;
            }
            /* Responsive styles for sticky/scroll */
            @media (max-width: 800px) {
                .grid-column {
                    max-height: none; /* Remove height limit on small screens */
                    overflow-y: visible;
                }
                .form-column {
                    position: relative !important; /* Disable sticky positioning */
                    top: auto !important;
                    max-height: none !important;
                    overflow-y: visible !important;
                }
            }
            #time-error-message {
                color: red;
                font-size: 0.9em;
                margin-top: 5px;
                text-align: center;
                display: none; /* Hidden by default */
            }
        </style>
        <!-- Parking Grid Container -->
        <div id="parkingGridContainer">
            <?php
            if ($displayTotalCount > 0) {
                // --- MODIFICATION: Initialize a single counter for the site ---
                $siteVisualCounter = 0;
                // --- END MODIFICATION ---
                foreach ($categoryOrder as $categoryKey) {
                    if (!empty($categorizedSpaces[$categoryKey])) {
                        $groupColor = $selectedSiteConfig['normal_color'];
                        $groupLabel = 'Normal';
                        switch ($categoryKey) {
                            case 'EV':       $groupColor = $selectedSiteConfig['ev_color'];       $groupLabel = 'EV';       break;
                            case 'DISABLED': $groupColor = $selectedSiteConfig['disabled_color']; $groupLabel = 'Disabled'; break;
                            case 'VISITOR':  $groupColor = $selectedSiteConfig['visitor_color'];  $groupLabel = 'Visitor';  break;
                            case 'ADMIN_DISABLED': $groupColor = $selectedSiteConfig['admin_disabled_color']; $groupLabel = 'Unavailable'; break;
                        }

                        echo "<div class='category-group category-" . strtolower($categoryKey) . "'>";
                        // echo "<div class='category-title'>" . htmlspecialchars($groupLabel) . " (" . count($categorizedSpaces[$categoryKey]) . ")</div>";
                        echo "<div class='category-spaces-container'>";

                        // --- REMOVED: Resetting counter per category ---
                        // $visualCounters[$categoryKey] = 0;
                        // --- END REMOVED ---

                        foreach ($categorizedSpaces[$categoryKey] as $space) {
                            // --- MODIFICATION: Increment the single site counter ---
                            $siteVisualCounter++;
                            // --- MODIFICATION: Generate label using the single site counter ---
                            $visualLabel = $selectedSiteKey . $siteVisualCounter;
                            // --- END MODIFICATION ---

                            $parkingId = $space['PARKINGID'];
                            $dbParkingName = $space['PARKINGNAME'];
                            $color = $groupColor;
                            $typeLabel = $groupLabel;

                            $finalClass = 'parking-lot';
                            $finalStyle = 'background-color:' . $color . ';';
                            $titleText = htmlspecialchars($dbParkingName) . ' (' . $typeLabel . ')';
                            $isClickable = true;

                            // Add admin-disabled class for the new category
                            if ($categoryKey === 'ADMIN_DISABLED') {
                                $finalClass .= ' admin-disabled';
                                $finalStyle = 'background-color:' . $selectedSiteConfig['admin_disabled_color'] . '; color: white;';
                                $titleText .= ' - Unavailable (Administrator Disabled)';
                                $isClickable = false;
                            } else {
                                $finalClass .= ' available';
                            }

                            if (in_array($parkingId, $selectedParkingIds) && $categoryKey !== 'ADMIN_DISABLED') {
                                 $finalClass .= ' selected';
                                 $finalStyle = 'background-color: #28a745; color: white;';
                                 $titleText .= ' - Selected';
                            }

                            echo '<div class="' . $finalClass . '" ' .
                                 'data-parking-id="' . $parkingId . '" ' .
                                 'data-parking-name="' . htmlspecialchars($dbParkingName) . '" ' .
                                 'data-visual-label="' . htmlspecialchars($visualLabel) . '" ' . // Store the generated label
                                 'data-category-color="' . $color . '" ' .
                                 'data-type-label="' . $typeLabel . '" ' .
                                 'data-admin-disabled="' . ($categoryKey === 'ADMIN_DISABLED' ? 'true' : 'false') . '" ' . // Add flag for admin-disabled spaces
                                 'title="' . $titleText . '" ' .
                                 'style="' . $finalStyle . '"' .
                                 ($isClickable ? ' tabindex="0"' : '') . '>' .
                                 htmlspecialchars($visualLabel) . // Display the generated label
                                 '</div>';
                        }
                        echo "</div></div>";
                    }
                }
            } elseif (empty($errors)) {
                 echo "<p>No parking spaces found in the database for this site.</p>";
            }
            ?>
        </div> <!-- End parkingGridContainer -->
    </div> <!-- End Left Column -->

    <!-- Right Column: Form and Legend -->
    <div class="form-column" style="width: 100%; max-width: 450px; flex-shrink: 0; position: sticky; top: 20px; align-self: flex-start; max-height: calc(100vh - 40px); overflow-y: auto;">
        <!-- Booking Form -->
        <form method="POST" id="bookingForm" action="bookParking2.php" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <input type="hidden" name="selected_site" id="selected_site" value="<?php echo htmlspecialchars($selectedSiteKey); ?>">
            <input type="hidden" name="selected_spaces" id="selected_spaces" value='<?php echo htmlspecialchars(json_encode($selectedParkingData)); ?>'>

            <?php if ($isStaff): ?>
                <div style="margin-bottom: 10px;">
                    <label for="customer_id" style="display: block; margin-bottom: 5px; font-weight: bold;">Select Customer:</label>
                    <select name="customer_id" id="customer_id" required style="padding: 8px; width: 100%; box-sizing: border-box;">
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo htmlspecialchars($customer['CUSTOMERID']); ?>" <?php echo (isset($_POST['customer_id']) && $_POST['customer_id'] == $customer['CUSTOMERID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['FIRSTNAME'] . ' ' . $customer['SURNAME']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div style="margin-bottom: 10px;">
                <label for="booking_date" style="display: block; margin-bottom: 5px; font-weight: bold;">Date:</label>
                <input type="date" name="booking_date" id="booking_date" value="<?php echo htmlspecialchars($date); ?>" required style="padding: 8px; width: 100%; box-sizing: border-box;" min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                <div style="flex: 1;">
                    <label for="start_time" style="display: block; margin-bottom: 5px; font-weight: bold;">Start Time:</label>
                    <input type="time" name="start_time" id="start_time" value="<?php echo isset($_POST['start_time']) ? htmlspecialchars($_POST['start_time']) : '--:--'; ?>" required style="padding: 8px; width: 100%; box-sizing: border-box;">
                </div>
                <div style="flex: 1;">
                    <label for="end_time" style="display: block; margin-bottom: 5px; font-weight: bold;">End Time:</label>
                    <input type="time" name="end_time" id="end_time" value="<?php echo isset($_POST['end_time']) ? htmlspecialchars($_POST['end_time']) : '--:--'; ?>" required style="padding: 8px; width: 100%; box-sizing: border-box;">
                </div>
            </div>
            <div id="time-error-message"></div>

             <div style="margin-top: 15px; text-align: center;">
                 <button type="submit" name="btnbook" class="book-button" <?php echo empty($selectedParkingData) ? 'disabled' : ''; ?> style="background-color: #2d3741; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%;">
                     <?php echo $isLoggedIn ? 'Book Selected Spaces' : 'Login to Book'; ?>
                 </button>
             </div>
        </form>

        <!-- Error/Success Messages -->
        <?php if (!empty($errors)): ?>
            <div style="color: red; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin-top: 15px; border-radius: 4px; text-align: left;">
                <strong>Please fix the following errors:</strong><br>
                <?php foreach ($errors as $error): ?>
                    - <?php echo htmlspecialchars($error); ?><br>
                <?php endforeach; ?>
            </div>
        <?php elseif (isset($_SESSION['booking_message'])): ?>
            <div style="color: green; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin-top: 15px; border-radius: 4px; text-align: center;">
                <?php echo $_SESSION['booking_message']; unset($_SESSION['booking_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Legend -->
        <div style="margin-top: 20px; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h3 style="text-align: center; margin-top: 0; margin-bottom: 15px;">Legend</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                 <div style="display: flex; align-items: center; gap: 10px;"><div style="width: 20px; height: 20px; background-color: <?php echo $selectedSiteConfig['disabled_color']; ?>; border-radius: 4px; border: 1px solid #555;"></div> Disability Available</div>
                 <div style="display: flex; align-items: center; gap: 10px;"><div style="width: 20px; height: 20px; background-color: <?php echo $selectedSiteConfig['ev_color']; ?>; border-radius: 4px; border: 1px solid #555;"></div> EV Available</div>
                 <div style="display: flex; align-items: center; gap: 10px;"><div style="width: 20px; height: 20px; background-color: <?php echo $selectedSiteConfig['visitor_color']; ?>; border-radius: 4px; border: 1px solid #555;"></div> Visitor Available</div>
                 <div style="display: flex; align-items: center; gap: 10px;"><div style="width: 20px; height: 20px; background-color: <?php echo $selectedSiteConfig['normal_color']; ?>; border-radius: 4px; border: 1px solid #555;"></div> Regular Available</div>
                 <div style="display: flex; align-items: center; gap: 10px;"><div style="width: 20px; height: 20px; background-color: #28a745; border-radius: 4px; border: 1px solid #1c7430;"></div> My Booking / Selected</div>
                 <div style="display: flex; align-items: center; gap: 10px;"><div style="width: 20px; height: 20px; background-color: gray; border-radius: 4px; border: 1px solid #333;"></div> Booked/Unavailable</div>
                 <div style="display: flex; align-items: center; gap: 10px;"><div style="width: 20px; height: 20px; background-color: #ff3333; border-radius: 4px; border: 1px solid #cc0000;"></div> Admin Disabled</div>
            </div>
            <div style="margin-top: 15px; font-size: 12px; text-align: center; color: #555;">
                Counts:
                Normal: <?php echo $categoryCounts['NORMAL']; ?> |
                EV: <?php echo $categoryCounts['EV']; ?> |
                Disabled: <?php echo $categoryCounts['DISABLED']; ?> |
                Visitor: <?php echo $categoryCounts['VISITOR']; ?> |
                Admin Disabled: <?php echo $categoryCounts['ADMIN_DISABLED']; ?>
            </div>
        </div> <!-- End Legend -->
    </div> <!-- End Right Column -->
</div> <!-- End Main Flex Container -->

<!-- JavaScript for Grid Interaction -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const parkingGridContainer = document.getElementById('parkingGridContainer');
        const selectedSpacesInput = document.getElementById('selected_spaces');
        const bookingButton = document.querySelector('button[name="btnbook"]');
        const dateInput = document.getElementById('booking_date');
        const startTimeInput = document.getElementById('start_time');
        const endTimeInput = document.getElementById('end_time');
        const timeErrorMessageDiv = document.getElementById('time-error-message');
        const bookingForm = document.getElementById('bookingForm');
        const siteHiddenInput = document.getElementById('selected_site');

const allBookingsOnDate = <?php echo json_encode($allBookingsForDate); ?>;
const loggedInCustomerId = <?php echo json_encode($loggedInCustomerId); ?>;
const siteConfig = <?php echo json_encode($selectedSiteConfig); ?>;
const userActiveBookingParkingIds = <?php echo json_encode(array_column($userActiveBookings, 'PARKINGID')); ?>;

let selectedParkingData = [];
        try {
            selectedParkingData = JSON.parse(selectedSpacesInput.value || '[]');
            if (!Array.isArray(selectedParkingData)) selectedParkingData = [];
        } catch (e) { console.error("Error parsing selected spaces:", e); selectedParkingData = []; }

        function updateSelectedSpacesInput() {
            selectedSpacesInput.value = JSON.stringify(selectedParkingData);
            validateBookingTime();
        }

        function getOriginalColor(lotElement) {
            return lotElement.getAttribute('data-category-color') || siteConfig.normal_color;
        }

        function timeToMinutes(timeStr) {
            if (!timeStr || !timeStr.includes(':')) return 0;
            const [hours, minutes] = timeStr.split(':').map(Number);
            return hours * 60 + minutes;
        }

        function isSpaceBookedForTime(parkingId, selectedStartMinutes, selectedEndMinutes, selectedDate, todayDate, currentMinutes) {
            if (!parkingId || isNaN(parkingId)) return null;

            for (const booking of allBookingsOnDate) {
                if (booking.PARKINGID == parkingId) {
                    const bookingStartTimeStr = booking.STARTTIME.includes(' ') ? booking.STARTTIME.split(' ')[1] : booking.STARTTIME;
                    const bookingEndTimeStr = booking.ENDTIME.includes(' ') ? booking.ENDTIME.split(' ')[1] : booking.ENDTIME;
                    const bookingStartMinutes = timeToMinutes(bookingStartTimeStr);
                    const bookingEndMinutes = timeToMinutes(bookingEndTimeStr);

                    if (selectedDate === todayDate && bookingEndMinutes <= currentMinutes) {
                        continue;
                    }

                    if (selectedStartMinutes < bookingEndMinutes && selectedEndMinutes > bookingStartMinutes) {
                        return booking.CUSTOMERID;
                    }
                }
            }
            return null;
        }

        function validateBookingTime() {
            const selectedDate = dateInput.value;
            const selectedStartTime = startTimeInput.value;
            const selectedEndTime = endTimeInput.value;
            let isPast = false;
            let isEndTimeValid = true;

            if (selectedDate && selectedStartTime) {
                const now = new Date();
                const selectedStartDateTime = new Date(`${selectedDate}T${selectedStartTime}:00`);

                if (selectedStartDateTime < now) {
                    isPast = true;
                }
            }

            if (selectedStartTime && selectedEndTime && selectedEndTime <= selectedStartTime) {
                 isEndTimeValid = false;
            }

            if (isPast) {
                timeErrorMessageDiv.textContent = "Cannot select a start time in the past.";
                timeErrorMessageDiv.style.display = 'block';
                if (bookingButton) bookingButton.disabled = true;
                return false;
            } else if (!isEndTimeValid) {
                timeErrorMessageDiv.textContent = "End time must be after start time.";
                timeErrorMessageDiv.style.display = 'block';
                if (bookingButton) bookingButton.disabled = true;
                return false;
            }
            else {
                timeErrorMessageDiv.style.display = 'none';
                if (bookingButton) bookingButton.disabled = selectedParkingData.length === 0;
                return true;
            }
        }

        function updateParkingGridDisplay() {
            const isTimeValid = validateBookingTime();
            const now = new Date();
            const currentMinutes = now.getHours() * 60 + now.getMinutes();
            const todayDate = now.toISOString().split('T')[0];
            const selectedDate = dateInput.value;
            const selectedStartTime = startTimeInput.value;
            const selectedEndTime = endTimeInput.value;
            const selectedStartMinutes = timeToMinutes(selectedStartTime);
            const selectedEndMinutes = timeToMinutes(selectedEndTime);
            const parkingLots = parkingGridContainer.querySelectorAll('.parking-lot');

            parkingLots.forEach(lot => {
                const parkingId = parseInt(lot.getAttribute('data-parking-id'), 10);
                const parkingName = lot.getAttribute('data-parking-name');
                const visualLabel = lot.getAttribute('data-visual-label');
                const typeLabel = lot.getAttribute('data-type-label');
                const originalColor = getOriginalColor(lot);
                const isAdminDisabled = lot.getAttribute('data-admin-disabled') === 'true';

                // Skip processing for admin-disabled spaces
                if (isAdminDisabled) {
                    return; // Maintain admin-disabled styling
                }

                const bookingCustomerId = isTimeValid ? isSpaceBookedForTime(parkingId, selectedStartMinutes, selectedEndMinutes, selectedDate, todayDate, currentMinutes) : null;

                const isBooked = bookingCustomerId !== null;
                // Determine if this parking is in user's active bookings (persistent green)
                const isPersistentlyMyBooking = userActiveBookingParkingIds.includes(parkingId);
                const isMyBooking = (isBooked && loggedInCustomerId !== null && bookingCustomerId == loggedInCustomerId) || isPersistentlyMyBooking;
                const isSelected = selectedParkingData.some(item => item.id === parkingId);

                lot.classList.remove('booked', 'my-booking', 'selected', 'available');
                lot.style.cursor = '';
                lot.removeAttribute('tabindex');
                lot.style.backgroundColor = originalColor;
                lot.style.color = 'black';
                let titleText = `${parkingName} (${typeLabel})`;

                if (isBooked || isPersistentlyMyBooking) {
                    lot.classList.add('booked');
                    lot.style.cursor = 'not-allowed';
                    lot.style.color = 'white';
                    if (isMyBooking) {
                        lot.classList.add('my-booking');
                        lot.style.backgroundColor = '#28a745';
                        titleText += ' - Your Booking';
                    } else {
                        lot.style.backgroundColor = 'gray';
                        titleText += ' - Booked';
                    }
                    if (isSelected && !isMyBooking) {
                        selectedParkingData = selectedParkingData.filter(item => item.id !== parkingId);
                    }
                } else if (!isTimeValid) {
                    lot.style.cursor = 'not-allowed';
                    titleText += ' (Select valid time)';
                     if (isSelected) {
                        lot.classList.add('selected');
                        lot.style.backgroundColor = '#28a745';
                        lot.style.color = 'white';
                        titleText += ' - Selected';
                    }
                } else {
                    lot.classList.add('available');
                    lot.style.cursor = 'pointer';
                    lot.setAttribute('tabindex', '0');
                    if (isSelected) {
                        lot.classList.add('selected');
                        lot.style.backgroundColor = '#28a745';
                        lot.style.color = 'white';
                        titleText += ' - Selected';
                    } else {
                        lot.style.backgroundColor = originalColor;
                        if (siteConfig.id === 3) {
                           lot.style.color = 'black';
                        }
                    }
                }
                lot.title = titleText;
            });
        }

        if (parkingGridContainer) {
            parkingGridContainer.addEventListener('click', function(event) {
                const lot = event.target.closest('.parking-lot');
                if (lot && lot.classList.contains('available') && validateBookingTime()) {
                    const parkingId = parseInt(lot.getAttribute('data-parking-id'), 10);
                    const visualLabel = lot.getAttribute('data-visual-label');

                    const existingIndex = selectedParkingData.findIndex(item => item.id === parkingId);

                    if (existingIndex > -1) {
                        lot.classList.remove('selected');
                        lot.style.backgroundColor = getOriginalColor(lot);
                        lot.style.color = 'black';
                        if (siteConfig.id === 3) lot.style.color = 'black';
                        selectedParkingData.splice(existingIndex, 1);
                    } else {
                        lot.classList.add('selected');
                        lot.style.backgroundColor = '#28a745';
                        lot.style.color = 'white';
                        selectedParkingData.push({ id: parkingId, label: visualLabel });
                    }
                    updateSelectedSpacesInput();
                }
            });

             parkingGridContainer.addEventListener('keydown', function(event) {
                const lot = event.target.closest('.parking-lot');
                if (lot && (event.key === 'Enter' || event.key === ' ')) {
                    event.preventDefault();
                    if (lot.classList.contains('available') && validateBookingTime()) {
                         lot.click();
                    }
                }
            });
        }

        if (dateInput && bookingForm) {
            dateInput.addEventListener('change', function() {
                selectedParkingData = [];
                updateSelectedSpacesInput();
                window.location.href = `?site=${siteHiddenInput.value}&booking_date=${this.value}`;
            });
        }

        if (startTimeInput) startTimeInput.addEventListener('change', () => { validateBookingTime(); updateParkingGridDisplay(); });
        if (endTimeInput) endTimeInput.addEventListener('change', () => { validateBookingTime(); updateParkingGridDisplay(); });

        // Initial setup
        updateParkingGridDisplay();
        updateSelectedSpacesInput();
    });
</script>

<?php
$content = ob_get_clean();
include_once './master.php';
?>
