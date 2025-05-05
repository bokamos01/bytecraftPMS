<?php
session_start();

$pageTitle = 'MANAGE BOOKINGS - CARPARK MANAGEMENT SYSTEM';
$custom_layout = true;

include_once './PMSDataAccess.php';
$da = new DataAccess();

$message = '';
$allBookings = [];
$selectedBooking = null;
$customers = [];

$isStaffMember = isset($_SESSION['staff_id']);
$isAdmin = $isStaffMember && isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;

if (!$isAdmin) {
    $_SESSION['login_error'] = "You need administrator privileges to access this page.";
    header("Location: login.php");
    exit;
}

$searchName = isset($_GET['searchName']) ? trim($_GET['searchName']) : '';
$filterDate = isset($_GET['filterDate']) ? trim($_GET['filterDate']) : '';

try {
    $customers = $da->GetAllCustomers();
} catch (Exception $ex) {
    $message = displayError('Error loading customers: ' . $ex->getMessage());
}

try {
    $sql = "SELECT b.BOOKINGID, b.DATE, b.STARTTIME, b.ENDTIME,
                   b.CUSTOMERID, CONCAT(c.FIRSTNAME, ' ', c.SURNAME) AS CUSTOMER_NAME,
                   b.PARKINGID, p.PARKINGNAME, s.SITENAME
            FROM tbl_booking b
            JOIN tbl_customers c ON b.CUSTOMERID = c.CUSTOMERID
            JOIN (
                SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceA
                UNION ALL
                SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceB
                UNION ALL
                SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceC
            ) p ON b.PARKINGID = p.PARKINGID
            JOIN tbl_site s ON p.SITEID = s.SITEID
            WHERE 1=1";

    $params = [];

    if (!empty($searchName)) {
        $sql .= " AND (c.FIRSTNAME LIKE ? OR c.SURNAME LIKE ? OR CONCAT(c.FIRSTNAME, ' ', c.SURNAME) LIKE ?)";
        $searchTerm = "%$searchName%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if (!empty($filterDate)) {
        $sql .= " AND b.DATE = ?";
        $params[] = $filterDate;
    }

    $sql .= " ORDER BY b.DATE DESC, b.STARTTIME";

    $allBookings = empty($params) ? $da->GetDataSQL($sql) : $da->GetData($sql, $params);

} catch (Exception $ex) {
    $message = displayError('Error loading bookings: ' . $ex->getMessage());
}


if (isset($_POST['btnReassign']) && $isAdmin) {
    $bookingId = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $newCustomerId = isset($_POST['new_customer_id']) ? intval($_POST['new_customer_id']) : 0;
    $startTime = isset($_POST['start_time']) ? $_POST['start_time'] : '';
    $endTime = isset($_POST['end_time']) ? $_POST['end_time'] : '';

    $errors = [];

    if (empty($bookingId) || $bookingId <= 0) {
        $errors[] = "Invalid booking ID";
    }

    if (empty($startTime)) {
        $errors[] = "Start time is required";
    }

    if (empty($endTime)) {
        $errors[] = "End time is required";
    }

    if (!empty($startTime) && !empty($endTime)) {
        $startDateTime = strtotime($startTime);
        $endDateTime = strtotime($endTime);

        if ($endDateTime <= $startDateTime) {
            $errors[] = "End time must be after start time";
        }
    }

    if (empty($errors)) {
        try {
            $bookingBefore = $da->GetBookingById($bookingId);

            if (!$bookingBefore) {
                 throw new Exception("Booking #{$bookingId} not found.");
            }

            $customerChanged = false;
            $timeChanged = false;
            $updateCount = 0;

            if (!empty($newCustomerId) && $newCustomerId != $bookingBefore['CUSTOMERID']) {
                $customerResult = $da->RevokeAndReassignBooking($bookingId, $newCustomerId);
                $customerChanged = ($customerResult > 0);
                $updateCount += $customerResult;

                // Add notification for old customer about revocation
                if ($customerChanged) {
                    $oldCustomerId = $bookingBefore['CUSTOMERID'];
                    $message = "Your booking #{$bookingId} has been revoked. Kindly make another booking";
                    $da->AddNotification($oldCustomerId, $message, 'revoked', $bookingId);

                    // Add notification for new customer about granted booking
                    $newCustomerDetails = $da->GetCustomerById($newCustomerId);
                    $newCustomerName = $newCustomerDetails ? $newCustomerDetails['FIRSTNAME'] . ' ' . $newCustomerDetails['SURNAME'] : 'Unknown';
                    $parkingName = $bookingBefore['PARKINGNAME'] ?? 'Unknown Parking Space';
                    $messageNew = "You have been granted booking #{$bookingId} for parking space {$parkingName}.";
                    $da->AddNotification($newCustomerId, $messageNew, 'granted', $bookingId);
                }
            }

            $timeChanged = ($startTime != date('H:i', strtotime($bookingBefore['STARTTIME'])) ||
                            $endTime != date('H:i', strtotime($bookingBefore['ENDTIME'])));

            if ($timeChanged) {
                $bookingDate = $bookingBefore['DATE'];
                $startTimeFormatted = date('Y-m-d H:i:s', strtotime("$bookingDate $startTime"));
                $endTimeFormatted = date('Y-m-d H:i:s', strtotime("$bookingDate $endTime"));

                $sqlCheck = "SELECT BOOKINGID FROM tbl_booking
                             WHERE PARKINGID = ? AND DATE = ? AND BOOKINGID != ?
                             AND NOT (ENDTIME <= ? OR STARTTIME >= ?)";
                $conflictParams = [$bookingBefore['PARKINGID'], $bookingDate, $bookingId, $startTimeFormatted, $endTimeFormatted];
                $existingBookings = $da->GetData($sqlCheck, $conflictParams);

                if (count($existingBookings) > 0) {
                     throw new Exception("The new time slot conflicts with another booking for this space.");
                }

                $sql = "UPDATE tbl_booking SET STARTTIME = ?, ENDTIME = ? WHERE BOOKINGID = ?";
                $timeResult = $da->ExecuteNonQuery($sql, [$startTimeFormatted, $endTimeFormatted, $bookingId]);
                $updateCount += $timeResult;

                // Add notification for customer about time change
                if ($timeResult > 0) {
                    $message = "Your booking #{$bookingId} time has been updated to " . date('g:i A', strtotime($startTime)) . " - " . date('g:i A', strtotime($endTime)) . ".";
                    $da->AddNotification($bookingBefore['CUSTOMERID'], $message, 'time_changed', $bookingId);
                }
            }

            if ($updateCount > 0) {
                $successMsg = "Booking #{$bookingId} successfully updated.";

                if ($customerChanged) {
                    $newCustomerDetails = $da->GetCustomerById($newCustomerId);
                    $newCustomerName = $newCustomerDetails ? $newCustomerDetails['FIRSTNAME'] . ' ' . $newCustomerDetails['SURNAME'] : 'Unknown';
                    $successMsg = "Booking #{$bookingId} successfully revoked from {$bookingBefore['CUSTOMER_NAME']} and reassigned to {$newCustomerName}.";
                }

                if ($timeChanged) {
                    $successMsg .= ($customerChanged ? " Additionally, the" : " The") . " booking time was updated to " . date('g:i A', strtotime($startTime)) . " - " . date('g:i A', strtotime($endTime)) . ".";
                }

                $_SESSION['booking_message'] = $successMsg;
                header("Location: manageBooking.php");
                exit;
            } else {
                $_SESSION['booking_error'] = "No changes were detected or the update failed.";
            }
        } catch (Exception $ex) {
            $_SESSION['booking_error'] = "Error updating booking: " . $ex->getMessage();
        }
    } else {
        $_SESSION['booking_error'] = implode("<br>", $errors);
    }

    header("Location: manageBooking.php" . ($bookingId ? "?id=".$bookingId : ""));
    exit;
}


if (isset($_GET['delete']) && $isAdmin) {
    $bookingId = intval($_GET['delete']);

    try {
        $bookingBefore = $da->GetBookingById($bookingId);

        if ($bookingBefore) {
            $result = $da->DeleteBooking($bookingId);

            if ($result > 0) {
                $_SESSION['booking_message'] = "Booking #{$bookingId} for {$bookingBefore['CUSTOMER_NAME']} has been deleted.";

                // Add notification for customer about deletion
                $message = "Your booking #{$bookingId} has been deleted by the administrator.";
                $da->AddNotification($bookingBefore['CUSTOMERID'], $message, 'deleted', $bookingId);
            } else {
                $_SESSION['booking_error'] = "Deletion failed. Booking might have already been deleted.";
            }
        } else {
            $_SESSION['booking_error'] = "Booking #{$bookingId} not found.";
        }
    } catch (Exception $ex) {
        $_SESSION['booking_error'] = "Error deleting booking: " . $ex->getMessage();
    }

    header("Location: manageBooking.php");
    exit;
}

if (isset($_GET['id'])) {
    $bookingId = intval($_GET['id']);

    try {
        $selectedBooking = $da->GetBookingById($bookingId);
        if (!$selectedBooking) {
             $message = displayError("Booking #{$bookingId} not found.");
        }
    } catch (Exception $ex) {
        $message = displayError('Error loading booking details: ' . $ex->getMessage());
    }
}

ob_start();
?>

<h2 class="page-title">MANAGE BOOKINGS</h2>

<?php if (isset($_SESSION['booking_message'])): ?>
    <div class="message-container success-message">
        <?php echo $_SESSION['booking_message']; unset($_SESSION['booking_message']); ?>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['booking_error'])): ?>
    <div class="message-container error-message">
        <?php echo $_SESSION['booking_error']; unset($_SESSION['booking_error']); ?>
    </div>
<?php endif; ?>
<?php if (!empty($message)): ?>
    <?php echo $message; ?>
<?php endif; ?>


<?php if ($selectedBooking): ?>
    <div style="max-width: 800px; margin: 0 auto 30px auto; background-color: #e9e9e9; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0; color: #2d3741;">MANAGE BOOKING #<?php echo $selectedBooking['BOOKINGID']; ?></h3>

        <div style="display: grid; grid-template-columns: 150px 1fr; gap: 10px; margin-bottom: 20px; font-size: 14px;">
            <div style="font-weight: bold;">CURRENT CUSTOMER:</div>
            <div><?php echo htmlspecialchars($selectedBooking['CUSTOMER_NAME']); ?></div>

            <div style="font-weight: bold;">PARKING SPACE:</div>
            <div><?php echo htmlspecialchars($selectedBooking['PARKINGNAME']); ?> (<?php echo htmlspecialchars($selectedBooking['SITENAME']); ?>)</div>

            <div style="font-weight: bold;">DATE:</div>
            <div><?php echo date('F j, Y', strtotime($selectedBooking['DATE'])); ?></div>

            <div style="font-weight: bold;">CURRENT TIME:</div>
            <div><?php echo date('g:i A', strtotime($selectedBooking['STARTTIME'])); ?> - <?php echo date('g:i A', strtotime($selectedBooking['ENDTIME'])); ?></div>
        </div>

        <form method="POST" action="manageBooking.php">
            <input type="hidden" name="booking_id" value="<?php echo $selectedBooking['BOOKINGID']; ?>">

            <div style="margin-bottom: 20px;">
                <label for="new_customer_id" style="display: block; margin-bottom: 5px; font-weight: bold;">REASSIGN TO:</label>
                <select id="new_customer_id" name="new_customer_id" style="width: 100%; padding: 8px; background-color: #fff; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">-- KEEP CURRENT CUSTOMER --</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo $customer['CUSTOMERID']; ?>" <?php echo ($customer['CUSTOMERID'] == $selectedBooking['CUSTOMERID']) ? 'disabled' : ''; ?>>
                            <?php echo htmlspecialchars($customer['FIRSTNAME'] . ' ' . $customer['SURNAME']); ?>
                            <?php echo ($customer['CUSTOMERID'] == $selectedBooking['CUSTOMERID']) ? ' (Current)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">UPDATE BOOKING TIME:</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label for="start_time" style="display: block; margin-bottom: 5px; font-size: 13px;">Start Time:</label>
                        <input type="time" id="start_time" name="start_time" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" value="<?php echo date('H:i', strtotime($selectedBooking['STARTTIME'])); ?>" required>
                    </div>
                    <div>
                        <label for="end_time" style="display: block; margin-bottom: 5px; font-size: 13px;">End Time:</label>
                        <input type="time" id="end_time" name="end_time" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" value="<?php echo date('H:i', strtotime($selectedBooking['ENDTIME'])); ?>" required>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="submit" name="btnReassign" style="background-color: #007bff; color: white; border: none; padding: 10px 15px; cursor: pointer; border-radius: 4px; font-weight: bold;">UPDATE BOOKING</button>
                <a href="manageBooking.php" style="background-color: #6c757d; color: white; border: none; padding: 10px 15px; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; border-radius: 4px;">CANCEL</a>
            </div>
        </form>
    </div>
<?php endif; ?>


<div style="margin: 20px 0; background-color: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px;">
    <div style="font-weight: bold; margin-bottom: 10px; font-size: 16px;">SEARCH BOOKINGS</div>
    <form method="GET" action="manageBooking.php" id="searchForm">
        <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 1; min-width: 200px;">
                <label for="searchName" style="display: block; margin-bottom: 5px; font-weight: 500;">Customer Name:</label>
                <input type="text" id="searchName" name="searchName" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;" placeholder="Enter name..." value="<?php echo htmlspecialchars($searchName); ?>">
            </div>

            <div style="flex: 1; min-width: 150px;">
                <label for="filterDate" style="display: block; margin-bottom: 5px; font-weight: 500;">Date:</label>
                <input type="date" id="filterDate" name="filterDate" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;" value="<?php echo htmlspecialchars($filterDate); ?>">
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" style="padding: 8px 15px; background-color: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px;">SEARCH</button>
                <a href="manageBooking.php" style="padding: 8px 15px; background-color: #6c757d; color: white; border: none; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; border-radius: 4px;">RESET</a>
            </div>
        </div>
    </form>
</div>

<h3 style="color: #343a40; margin-top: 30px; margin-bottom: 15px;">
    BOOKINGS LIST
    <?php if (!empty($searchName) || !empty($filterDate)): ?>
        <span style="font-size: 0.8em; color: #6c757d;">
            (Filtered:
            <?php
                $filters = [];
                if (!empty($searchName)) $filters[] = "Name contains \"" . htmlspecialchars($searchName) . "\"";
                if (!empty($filterDate)) $filters[] = "Date = " . date('M j, Y', strtotime($filterDate));
                echo implode(', ', $filters);
            ?>)
        </span>
    <?php endif; ?>
</h3>

<?php if (empty($allBookings)): ?>
    <p style="text-align: center; color: #6c757d; margin-top: 20px;">No bookings found matching your criteria.</p>
<?php else: ?>
    <div style="overflow-x: auto; background-color: #fff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;" id="bookings-table">
            <thead>
                <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                    <th style="padding: 12px 15px; text-align: left; cursor: pointer;">ID</th>
                    <th style="padding: 12px 15px; text-align: left; cursor: pointer;">CUSTOMER</th>
                    <th style="padding: 12px 15px; text-align: left; cursor: pointer;">PARKING SPACE</th>
                    <th style="padding: 12px 15px; text-align: left; cursor: pointer;">DATE</th>
                    <th style="padding: 12px 15px; text-align: left; cursor: pointer;">TIME</th>
                    <th style="padding: 12px 15px; text-align: center;">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allBookings as $booking): ?>
                    <tr class="booking-row" style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 10px 15px;"><?php echo $booking['BOOKINGID']; ?></td>
                        <td style="padding: 10px 15px;"><?php echo htmlspecialchars($booking['CUSTOMER_NAME']); ?></td>
                        <td style="padding: 10px 15px;"><?php echo htmlspecialchars($booking['PARKINGNAME'] . ' (' . $booking['SITENAME'] . ')'); ?></td>
                        <td style="padding: 10px 15px;"><?php echo date('M j, Y', strtotime($booking['DATE'])); ?></td>
                        <td style="padding: 10px 15px;"><?php echo date('g:i A', strtotime($booking['STARTTIME'])) . ' - ' . date('g:i A', strtotime($booking['ENDTIME'])); ?></td>
                        <td style="padding: 10px 15px; text-align: center;">
                            <div style="display: flex; gap: 5px; justify-content: center;">
                                <a href="manageBooking.php?id=<?php echo $booking['BOOKINGID']; ?><?php echo (!empty($searchName) ? '&searchName='.urlencode($searchName) : ''); ?><?php echo (!empty($filterDate) ? '&filterDate='.urlencode($filterDate) : ''); ?>"
                                   style="background-color: #17a2b8; color: white; border: none; padding: 5px 10px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 12px; border-radius: 4px;">
                                   MANAGE
                                </a>
                                <a href="manageBooking.php?delete=<?php echo $booking['BOOKINGID']; ?>"
                                   style="background-color: #dc3545; color: white; border: none; padding: 5px 10px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 12px; border-radius: 4px;"
                                   onclick="return confirm('Are you sure you want to delete booking #<?php echo $booking['BOOKINGID']; ?>?')">
                                   DELETE
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('bookings-table');
        if (!table) return;

        const headers = table.querySelectorAll('th[style*="cursor: pointer"]');
        let sortDirection = {};

        headers.forEach((header, index) => {
            header.addEventListener('click', () => {
                const currentDirection = sortDirection[index] === 'asc' ? 'desc' : 'asc';
                sortDirection = { [index]: currentDirection };
                sortTable(index, currentDirection);
                updateSortIndicators(headers, index, currentDirection);
            });
        });
    });

    function sortTable(columnIndex, direction) {
        const table = document.getElementById('bookings-table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const isAsc = direction === 'asc';

        rows.sort((a, b) => {
            const cellA = a.querySelectorAll('td')[columnIndex].textContent.trim();
            const cellB = b.querySelectorAll('td')[columnIndex].textContent.trim();

            let comparison = 0;
            if (columnIndex === 0) {
                comparison = parseInt(cellA) - parseInt(cellB);
            } else if (columnIndex === 3) {
                const dateA = new Date(cellA).toISOString().split('T')[0];
                const dateB = new Date(cellB).toISOString().split('T')[0];
                comparison = dateA.localeCompare(dateB);
            } else {
                comparison = cellA.localeCompare(cellB, undefined, {numeric: true, sensitivity: 'base'});
            }

            return isAsc ? comparison : -comparison;
        });

        rows.forEach(row => tbody.appendChild(row));
    }

    function updateSortIndicators(headers, activeIndex, direction) {
         headers.forEach((header, index) => {
             let indicator = header.querySelector('.sort-indicator');
             if (indicator) {
                 header.removeChild(indicator);
             }
             if (index === activeIndex) {
                 indicator = document.createElement('span');
                 indicator.className = 'sort-indicator';
                 indicator.innerHTML = direction === 'asc' ? ' &uarr;' : ' &darr;';
                 header.appendChild(indicator);
             }
         });
    }
</script>

<?php
$content = ob_get_clean();

require_once 'master.php';
?>
