<?php
/**
 * Utility Functions
 * Shared utility functions for the Parking Management System
 */

/**
 * Sanitize user input
 * @param string $data User input to sanitize
 * @return string Sanitized input
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 
 * @param string $dbName 
 * @param string $siteKey T * @return string
 */
function getDisplayLabel($dbName, $siteKey) {
    return htmlspecialchars($dbName);
}


/**
 * Display a formatted message to the user
 * @param string $message Message content
 * @param string $type Type of message (success, error, info)
 * @return string HTML formatted message
 */
function displayMessage($message, $type = 'error') {
    $class = ($type === 'success') ? 'success-message' : 'error-message';
    return "<div class='message-container {$class}'>{$message}</div>";
}

/**
 * Display a success message
 * @param string $message Success message content
 * @return string HTML formatted success message
 */
function displaySuccess($message) {
    return displayMessage($message, 'success');
}

/**
 * Display an error message
 * @param string $message Error message content
 * @return string HTML formatted error message
 */
function displayError($message) {
    return displayMessage($message, 'error');
}

/**
 * Log error to a file
 * @param string $message Error message to log
 * @param string $level Error level
 * @return boolean Success or failure
 */
function logError($message, $level = 'ERROR') {
    $logDir = dirname(__FILE__) . '/logs';
    $logFile = $logDir . '/app_' . date('Y-m-d') . '.log';
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        if (!mkdir($logDir, 0755, true)) {
            return false;
        }
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    
    return file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Validate date format
 * @param string $date Date string to validate
 * @param string $format Expected date format
 * @return boolean True if valid, false otherwise
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Paginate an array of results
 * @param array $items Array of items to paginate
 * @param integer $page Current page number
 * @param integer $perPage Items per page
 * @return array Paginated results and metadata
 */
function paginateResults($items, $page = 1, $perPage = 10) {
    $page = max(1, intval($page));
    $perPage = max(1, intval($perPage));
    $totalItems = count($items);
    $totalPages = ceil($totalItems / $perPage);
    
    // Ensure page is within valid range
    $page = min($page, max(1, $totalPages));
    
    // Get the slice of items for the current page
    $offset = ($page - 1) * $perPage;
    $currentPageItems = array_slice($items, $offset, $perPage);
    
    return [
        'items' => $currentPageItems,
        'current_page' => $page,
        'per_page' => $perPage,
        'total_items' => $totalItems,
        'total_pages' => $totalPages
    ];
}

/**
 * Generate pagination links HTML
 * @param array $pagination Pagination metadata from paginateResults()
 * @param string $baseUrl Base URL for pagination links
 * @return string HTML pagination links
 */
function generatePaginationLinks($pagination, $baseUrl) {
    $links = '<div class="pagination">';
    
    // Previous page link
    if ($pagination['current_page'] > 1) {
        $prevPage = $pagination['current_page'] - 1;
        $links .= "<a href=\"{$baseUrl}?page={$prevPage}\" class=\"pagination-link\">&laquo; Previous</a>";
    } else {
        $links .= "<span class=\"pagination-link disabled\">&laquo; Previous</span>";
    }
    
    // Page numbers
    $startPage = max(1, $pagination['current_page'] - 2);
    $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $pagination['current_page']) {
            $links .= "<span class=\"pagination-link active\">{$i}</span>";
        } else {
            $links .= "<a href=\"{$baseUrl}?page={$i}\" class=\"pagination-link\">{$i}</a>";
        }
    }
    
    // Next page link
    if ($pagination['current_page'] < $pagination['total_pages']) {
        $nextPage = $pagination['current_page'] + 1;
        $links .= "<a href=\"{$baseUrl}?page={$nextPage}\" class=\"pagination-link\">Next &raquo;</a>";
    } else {
        $links .= "<span class=\"pagination-link disabled\">Next &raquo;</span>";
    }
    
    $links .= '</div>';
    
    return $links;
}

/**
 * Format a datetime for display
 * @param string $datetime Date/time string to format
 * @param string $format Output format
 * @return string Formatted date/time
 */
function formatDateTime($datetime, $format = 'M j, Y g:i A') {
    $dt = new DateTime($datetime);
    return $dt->format($format);
}

/**
 * Check if a string contains HTML
 * @param string $string String to check
 * @return boolean True if contains HTML, false otherwise
 */
function containsHTML($string) {
    return $string != strip_tags($string);
}

/**
 * Get client IP address
 * @return string Client IP address
 */
function getClientIP() {
    $ipAddress = '';
    
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
    } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } else if (isset($_SERVER['HTTP_FORWARDED'])) {
        $ipAddress = $_SERVER['HTTP_FORWARDED'];
    } else if (isset($_SERVER['REMOTE_ADDR'])) {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipAddress = 'UNKNOWN';
    }
    
    return $ipAddress;
}
