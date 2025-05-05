<?php
session_start();

$pageTitle = 'MY BOOKINGS - CARPARK MANAGEMENT SYSTEM';
$custom_layout = true; // Uses master.php

// --- Ensure config.php is included if it sets up $dataAccess ---
include_once 'config.php'; // Includes DataAccess via $dataAccess
// --- OR ensure PMSDataAccess is included directly if config doesn't handle it ---
// include_once './PMSDataAccess.php'; // Make sure path is correct
// if (!isset($dataAccess)) { $dataAccess = new DataAccess(); }
// --- End DataAccess setup ---

$da = $dataAccess; // Use $da alias for consistency

// Initialize variables
$message = '';
$userBookings = [];
$selectedBooking = null;

// Check if user is logged in as customer
$isCustomerLoggedIn = isset($_SESSION['customer_id']);

// Redirect if not logged in
if (!$isCustomerLoggedIn) {
    $_SESSION['login_message'] = "Please log in to view your bookings.";
    $_SESSION['redirect_after_login'] = 'customerManageBooking.php';
    header("Location: login.php");
    exit;
}

$customerId = $_SESSION['customer_id'];
// Use customer name from session if available, otherwise default
$customerName = $_SESSION['customer_name'] ?? 'Customer';

// Handle cancellation (GET request)
if (isset($_GET['cancel']) && $isCustomerLoggedIn) {
    $bookingId = intval($_GET['cancel']);

    try {
        // Get booking details before cancelling (uses fixed GetBookingById)
        $bookingBefore = $da->GetBookingById($bookingId);

        // Verify it's the customer's booking
        if ($bookingBefore && $bookingBefore['CUSTOMERID'] == $customerId) {
            // Check if the booking is in the future
            $bookingDate = $bookingBefore['DATE'];
            $bookingStartTime = $bookingBefore['STARTTIME'];
            // Ensure time comparison works correctly even if only H:i is stored
            $bookingDateTime = strtotime("$bookingDate " . date('H:i:s', strtotime($bookingStartTime)));
            $currentDateTime = time(); // Get current server time

            if ($bookingDateTime > $currentDateTime) {
                $result = $da->DeleteBooking($bookingId);

                if ($result > 0) {
                    $_SESSION['booking_message'] = "Your booking for {$bookingBefore['PARKINGNAME']} on " . date('F j, Y', strtotime($bookingDate)) . " at " . date('g:i A', strtotime($bookingStartTime)) . " has been cancelled.";
                } else {
                    $_SESSION['booking_error'] = "Cancellation failed. Please try again.";
                }
            } else {
                $_SESSION['booking_error'] = "You cannot cancel a booking that has already started or passed.";
            }
        } else {
            // If booking not found or doesn't belong to user
            $_SESSION['booking_error'] = "Booking not found or you do not have permission to cancel it.";
        }
    } catch (Exception $ex) {
        $_SESSION['booking_error'] = "Error cancelling booking: " . $ex->getMessage();
    }

    // Redirect to avoid repeat cancellation on refresh
    header("Location: customerManageBooking.php");
    exit;
}

// Get customer's bookings (uses fixed GetBookingsByCustomer)
try {
    $userBookings = $da->GetBookingsByCustomer($customerId);
} catch (Exception $ex) {
    // Use the utility function for consistent error display
    $message = displayError('Error loading your bookings: ' . $ex->getMessage());
}

// Load booking details if ID is provided in GET for viewing
if (isset($_GET['id'])) {
    $bookingId = intval($_GET['id']);

    try {
        $selectedBooking = $da->GetBookingById($bookingId); // Uses fixed method

        // Verify it's the customer's booking
        if ($selectedBooking && $selectedBooking['CUSTOMERID'] != $customerId) {
            $selectedBooking = null; // Don't show if not theirs
            $message = displayError("You can only view details for your own bookings.");
        } elseif (!$selectedBooking) {
             $message = displayError("Booking #{$bookingId} not found.");
        }
    } catch (Exception $ex) {
        $message = displayError('Error loading booking details: ' . $ex->getMessage());
    }
}

ob_start();
?>

<h2 class="page-title">MY BOOKINGS</h2>

<!-- Display Messages -->
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
    <!-- Message already includes message-container div from displayError -->
    <?php echo $message; ?>
<?php endif; ?>


<!-- Selected Booking Details (if viewing one) -->
<?php if ($selectedBooking): ?>
    <div style="max-width: 800px; margin: 0 auto 30px auto; background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0; color: #343a40; border-bottom: 1px solid #dee2e6; padding-bottom: 10px;">BOOKING DETAILS</h3>

        <div style="display: grid; grid-template-columns: 150px 1fr; gap: 10px 20px; margin-bottom: 20px; font-size: 14px;">
            <div style="font-weight: bold; color: #495057;">PARKING SPACE:</div>
            <div><?php echo htmlspecialchars($selectedBooking['PARKINGNAME']); ?> (<?php echo htmlspecialchars($selectedBooking['SITENAME']); ?>)</div>

            <div style="font-weight: bold; color: #495057;">DATE:</div>
            <div><?php echo date('F j, Y', strtotime($selectedBooking['DATE'])); ?></div>

            <div style="font-weight: bold; color: #495057;">TIME:</div>
            <div><?php echo date('g:i A', strtotime($selectedBooking['STARTTIME'])); ?> - <?php echo date('g:i A', strtotime($selectedBooking['ENDTIME'])); ?></div>

            <div style="font-weight: bold; color: #495057;">BOOKING ID:</div>
            <div>#<?php echo $selectedBooking['BOOKINGID']; ?></div>
        </div>

        <?php
        // Check if booking is in the future for cancellation
        $bookingDate = $selectedBooking['DATE'];
        $bookingStartTime = $selectedBooking['STARTTIME'];
        $bookingDateTime = strtotime("$bookingDate " . date('H:i:s', strtotime($bookingStartTime)));
        $currentDateTime = time(); // Get current server time
        $canCancel = ($bookingDateTime > $currentDateTime);
        ?>

        <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #dee2e6; padding-top: 15px;">
            <?php if ($canCancel): ?>
                <a href="customerManageBooking.php?cancel=<?php echo $selectedBooking['BOOKINGID']; ?>"
                   style="background-color: #dc3545; color: white; border: none; padding: 10px 15px; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; border-radius: 4px;"
                   onclick="return confirm('Are you sure you want to cancel this booking?')">
                    CANCEL BOOKING
                </a>
            <?php else: ?>
                <span style="background-color: #6c757d; color: white; border: none; padding: 10px 15px; display: inline-block; text-align: center; border-radius: 4px; font-size: 13px;">
                    CANNOT CANCEL
                </span>
            <?php endif; ?>
            <a href="customerManageBooking.php" style="background-color: #6c757d; color: white; border: none; padding: 10px 15px; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; border-radius: 4px;">BACK TO LIST</a>
        </div>
    </div>
<?php endif; ?>


<!-- Filter options -->
<div style="margin: 20px 0; background-color: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div style="font-weight: bold; font-size: 16px;">YOUR BOOKINGS</div>
        <div>
            <label for="booking-filter" style="margin-right: 5px; font-weight: 500;">Show:</label>
            <select id="booking-filter" style="padding: 5px; border-radius: 4px; border: 1px solid #ced4da;" onchange="filterBookings(this.value)">
                <option value="all">All bookings</option>
                <option value="upcoming">Upcoming bookings</option>
                <option value="past">Past bookings</option> <!-- Changed label slightly -->
            </select>
        </div>
    </div>
</div>

<!-- Bookings table or message -->
<?php if (empty($userBookings)): ?>
    <div style="text-align: center; padding: 30px; background-color: #fff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <p style="font-size: 16px; margin-bottom: 20px; color: #495057;">You don't have any bookings yet.</p>
        <a href="bookParking2.php" style="background-color: #007bff; color: white; border: none; padding: 10px 20px; cursor: pointer; text-decoration: none; display: inline-block; border-radius: 4px; font-weight: bold;">BOOK A PARKING SPACE</a>
    </div>
<?php else: ?>
    <div style="overflow-x: auto; background-color: #fff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;" id="bookings-table">
            <thead>
                <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                    <th style="padding: 12px 15px; text-align: left;">DATE</th>
                    <th style="padding: 12px 15px; text-align: left;">TIME</th>
                    <th style="padding: 12px 15px; text-align: left;">PARKING SPACE</th>
                    <th style="padding: 12px 15px; text-align: left;">LOCATION</th>
                    <th style="padding: 12px 15px; text-align: center;">STATUS</th>
                    <th style="padding: 12px 15px; text-align: center;">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $currentDateTime = time(); // Get current server timestamp ONCE before the loop
                foreach ($userBookings as $booking):
                    $bookingDate = $booking['DATE'];
                    $bookingStartTime = $booking['STARTTIME'];
                    $bookingEndTime = $booking['ENDTIME'];

                    // Calculate timestamps for start and end, including the date
                    $bookingStartDateTime = strtotime("$bookingDate " . date('H:i:s', strtotime($bookingStartTime)));
                    $bookingEndDateTime = strtotime("$bookingDate " . date('H:i:s', strtotime($bookingEndTime)));

                    // --- DEBUGGING (Uncomment to see timestamps in your environment) ---
                    /*
                    echo "<!-- ";
                    echo "Booking ID: " . $booking['BOOKINGID'] . "\n";
                    echo "Current Time (Server): " . date('Y-m-d H:i:s', $currentDateTime) . " (Timestamp: " . $currentDateTime . ")\n";
                    echo "Booking Start: " . date('Y-m-d H:i:s', $bookingStartDateTime) . " (Timestamp: " . $bookingStartDateTime . ")\n";
                    echo "Booking End: " . date('Y-m-d H:i:s', $bookingEndDateTime) . " (Timestamp: " . $bookingEndDateTime . ")\n";
                    echo "Comparison (Current > End): " . ($currentDateTime > $bookingEndDateTime ? 'TRUE' : 'FALSE') . "\n";
                    echo " -->\n";
                    */
                    // --- END DEBUGGING ---

                    $status = "";
                    $statusColor = "";
                    $bookingClass = "";
                    $canCancel = false; // Flag to check if cancellation is possible

                    // Determine Status based on comparison with current server time
                    if ($currentDateTime < $bookingStartDateTime) {
                        $status = "Upcoming";
                        $statusColor = "#28a745"; // Green
                        $bookingClass = "upcoming-booking";
                        $canCancel = true; // Can cancel upcoming bookings
                    } elseif ($currentDateTime >= $bookingStartDateTime && $currentDateTime <= $bookingEndDateTime) {
                        $status = "In Progress";
                        $statusColor = "#007bff"; // Blue
                        $bookingClass = "current-booking"; // Added class for filtering
                    } else { // This condition means: $currentDateTime > $bookingEndDateTime
                        $status = "Completed";
                        $statusColor = "#6c757d"; // Gray
                        $bookingClass = "past-booking";
                    }
                ?>
                    <tr class="booking-row <?php echo $bookingClass; ?>" style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 10px 15px;"><?php echo date('D, M j, Y', strtotime($booking['DATE'])); ?></td>
                        <td style="padding: 10px 15px;"><?php echo date('g:i A', strtotime($booking['STARTTIME'])) . ' - ' . date('g:i A', strtotime($booking['ENDTIME'])); ?></td>
                        <td style="padding: 10px 15px;"><?php echo htmlspecialchars($booking['PARKINGNAME']); ?></td>
                        <td style="padding: 10px 15px;"><?php echo htmlspecialchars($booking['SITENAME']); ?></td>
                        <td style="padding: 10px 15px; text-align: center;">
                            <span style="display: inline-block; padding: 3px 8px; border-radius: 12px; background-color: <?php echo $statusColor; ?>; color: white; font-size: 12px; font-weight: 500;">
                                <?php echo $status; ?>
                            </span>
                        </td>
                        <td style="padding: 10px 15px; text-align: center;">
                            <div style="display: flex; gap: 5px; justify-content: center;">
                                <!-- View Button -->
                                <a href="customerManageBooking.php?id=<?php echo $booking['BOOKINGID']; ?>" style="background-color: #2d3741; color: white; border: none; padding: 5px 10px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 12px; border-radius: 4px;">VIEW</a>

                                <!-- Cancel Button (only show if $canCancel is true) -->
                                <?php if ($canCancel): ?>
                                    <a href="customerManageBooking.php?cancel=<?php echo $booking['BOOKINGID']; ?>"
                                       style="background-color: #dc3545; color: white; border: none; padding: 5px 10px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 12px; border-radius: 4px;"
                                       onclick="return confirm('Are you sure you want to cancel this booking?')">
                                       CANCEL
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="text-align: center; margin-top: 30px;">
        <a href="bookParking2.php" style="background-color: #007bff; color: white; border: none; padding: 10px 20px; cursor: pointer; text-decoration: none; display: inline-block; border-radius: 4px; font-weight: bold;">BOOK ANOTHER PARKING SPACE</a>
    </div>
<?php endif; ?>

<script>
    function filterBookings(filter) {
        const rows = document.querySelectorAll('#bookings-table tbody tr.booking-row');
        let visibleCount = 0;

        rows.forEach(row => {
            let show = false;
            if (filter === 'all') {
                show = true;
            } else if (filter === 'upcoming' && row.classList.contains('upcoming-booking')) {
                show = true;
            // --- MODIFIED: 'past' filter now includes 'past-booking' AND 'current-booking' ---
            } else if (filter === 'past' && (row.classList.contains('past-booking') || row.classList.contains('current-booking'))) {
                show = true;
            // --- END MODIFICATION ---
            }

            row.style.display = show ? '' : 'none';
            if (show) {
                visibleCount++;
            }
        });

        // Add or remove the 'No results' row
        const table = document.getElementById('bookings-table');
        let noResultsRow = table.querySelector('.no-results-row');
        if (visibleCount === 0) {
            if (!noResultsRow) {
                noResultsRow = table.querySelector('tbody').insertRow(-1);
                noResultsRow.className = 'no-results-row';
                const cell = noResultsRow.insertCell(0);
                const colCount = table.querySelector('thead th') ? table.querySelectorAll('thead th').length : 6;
                cell.colSpan = colCount;
                cell.textContent = 'No bookings match the selected filter.';
                cell.style.textAlign = 'center';
                cell.style.padding = '20px';
                cell.style.color = '#6c757d';
            }
        } else {
            if (noResultsRow) {
                noResultsRow.remove();
            }
        }
    }

    // Optional: Apply a default filter on load, e.g., 'upcoming'
    // document.addEventListener('DOMContentLoaded', () => {
    //     const filterDropdown = document.getElementById('booking-filter');
    //     if (filterDropdown) {
    //         filterDropdown.value = 'upcoming'; // Set default selection
    //         filterBookings('upcoming'); // Apply the filter
    //     }
    // });
</script>

<?php
$content = ob_get_clean();

require_once 'master.php'; 
?>
