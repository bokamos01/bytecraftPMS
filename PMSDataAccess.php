<?php

class DataAccess
{
    // Database configuration
    private $server = "localhost";
    private $database = "pmsdb1";
    private $username = "darth_vader";
    private $password = "verysecurepassword";
    private $port = "3306";

    // Global date format
    public $mariadb_dateformat = "Y-m-d";

    /**
     * CORE DATABASE METHODS
     */

    /**
     * Creates and returns a database connection
     * @return PDO Database connection
     * @throws Exception on connection error
     */
    public function GetConnection()
    {
        try {
            // Connection string
            $conn = new PDO("mysql:host={$this->server}:{$this->port};dbname={$this->database}",
                           $this->username,
                           $this->password);

            // Set PDO attributes
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);

            return $conn;
        } catch (PDOException $ex) {
            throw new Exception("Database connection failed: " . $ex->getMessage());
        }
    }

    /**
     * Executes a SELECT query and returns data as an associative array
     * @param string $sql SQL SELECT query
     * @return array Results as associative array
     * @throws Exception on query error
     */
    public function GetDataSQL($sql)
    {
        try {
            $conn = $this->GetConnection();
            $result = $conn->query($sql);
            $arrdata = $result->fetchAll(PDO::FETCH_ASSOC);

            $result->closeCursor();
            $conn = null;

            return $arrdata;
        } catch (PDOException $ex) {
            throw new Exception("Query execution failed: " . $ex->getMessage());
        }
    }

    /**
     * Executes SQL for INSERT, UPDATE, DELETE using exec method
     * @param string $sql SQL query to execute
     * @return int Number of affected rows
     * @throws Exception on query error
     */
    public function ExecuteSQL($sql)
    {
        try {
            $conn = $this->GetConnection();
            $count = $conn->exec($sql);
            $conn = null;

            return $count;
        } catch (PDOException $ex) {
            throw new Exception("SQL execution failed: " . $ex->getMessage());
        }
    }

    /**
     * Executes a SELECT query with parameters and returns data as an array
     * @param string $sql SQL SELECT query with placeholders
     * @param mixed $params Parameters for query (array or single value)
     * @return array Results as associative array
     * @throws Exception on query error
     */
    public function GetData($sql, $params = null)
    {
        try {
            $conn = $this->GetConnection();
            $values = is_array($params) ? $params : ((is_null($params)) ? array() : array($params));

            $stmt = $conn->prepare($sql);
            $stmt->execute($values);
            $arr_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt->closeCursor();
            $conn = null;

            return $arr_data;
        } catch (PDOException $ex) {
            throw new Exception("Parameterized query failed: " . $ex->getMessage());
        }
    }

    /**
     * Executes SQL for INSERT, UPDATE, DELETE with parameters
     * @param string $sql SQL query with placeholders
     * @param mixed $params Parameters for query (array or single value)
     * @return int Number of affected rows
     * @throws Exception on query error
     */
    public function ExecuteCommand($sql, $params = null)
    {
        try {
            $conn = $this->GetConnection();
            $values = is_array($params) ? $params : ((is_null($params)) ? array() : array($params));

            $stmt = $conn->prepare($sql);
            $stmt->execute($values);
            $count = $stmt->rowCount();

            $stmt->closeCursor();
            $conn = null;

            return $count;
        } catch (PDOException $ex) {
            throw new Exception("Command execution failed: " . $ex->getMessage());
        }
    }

    /**
     * Alias for ExecuteCommand to maintain compatibility with other code
     * @param string $sql SQL query with placeholders
     * @param mixed $params Parameters for query (array or single value)
     * @return int Number of affected rows
     * @throws Exception on query error
     */
    public function ExecuteNonQuery($sql, $params = null)
    {
        return $this->ExecuteCommand($sql, $params);
    }

    /**
     * Executes multiple queries as a transaction
     * @param array $arrsqls Array of SQL queries with placeholders
     * @param array $params Array of parameter arrays for each query
     * @return int Number of affected rows
     * @throws Exception on transaction failure
     */
    public function ExecuteTransaction($arrsqls, $params = null)
    {
        try {
            $conn = $this->GetConnection();
            $conn->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
            $conn->beginTransaction();

            $arrparams = is_array($params) ? $params : ((is_null($params)) ? array() : array($params));
            $count = 0;

            for ($k = 0; $k < count($arrsqls); $k++) {
                $values = $arrparams[$k];
                $sql = $arrsqls[$k];

                $stmt = $conn->prepare($sql);
                $stmt->execute($values);

                $rowcount = $stmt->rowCount();
                if ($rowcount > 0) {
                    $count += $rowcount;
                } else {
                    // Allow transactions where some steps might not affect rows (e.g., DELETE if not exists)
                }
            }

            $conn->commit();
            $conn = null;

            return $count;
        } catch (PDOException $ex) {
            if (isset($conn)) {
                $conn->rollback();
            }
            throw new Exception("Transaction failed: " . $ex->getMessage());
        }
    }

    /**
     * AUTHENTICATION METHODS
     */

    /**
     * Authenticates a user by email and password
     * @param string $emailAddress User's email
     * @param string $password User's password (hashed)
     * @return array User details if authenticated, empty array otherwise
     * @throws Exception on query error
     */
    public function GetUserDetails($emailAddress, $password)
    {
        try {
            // Check staff first
            $sql_staff = "SELECT a.STAFFID, a.FIRSTNAME, a.SURNAME, a.GENDER,
                           a.EMAILADDRESS, a.PASSWORD, a.ROLEID, b.ROLE,
                           a.DATEREGISTERED
                    FROM tbl_staff a
                    JOIN tbl_roles b ON a.ROLEID = b.ROLEID
                    WHERE a.EMAILADDRESS = ?";
            $arr_staff = $this->GetData($sql_staff, [$emailAddress]);

            if (count($arr_staff) > 0) {
                $stored_password = $arr_staff[0]["PASSWORD"];
                if ($password == $stored_password) {
                    return $arr_staff;
                }
            }

            // Check customers if not found in staff
            $sql_customer = "SELECT CUSTOMERID, FIRSTNAME, SURNAME, GENDER, EMAILADDRESS, PASSWORD, COMPANY, DATEREGISTERED
                             FROM tbl_customers
                             WHERE EMAILADDRESS = ?";
            $arr_customer = $this->GetData($sql_customer, [$emailAddress]);

            if (count($arr_customer) > 0) {
                 $stored_password = $arr_customer[0]["PASSWORD"];
                 if ($password == $stored_password) {
                     $arr_customer[0]['ROLEID'] = 3;
                     $arr_customer[0]['ROLE'] = 'Customer';
                     return $arr_customer;
                 }
            }

            return [];
        } catch (Exception $ex) {
            throw new Exception("Authentication failed: " . $ex->getMessage());
        }
    }


    /**
     * STAFF MANAGEMENT METHODS
     */
    // ... (GetAllStaff, GetStaffById, AddStaffMember, UpdateStaffMember, UpdateStaffMemberWithPassword, DeleteStaffMember remain unchanged) ...
    public function GetAllStaff()
    {
        try {
            $sql = "SELECT a.STAFFID, a.FIRSTNAME, a.SURNAME, a.GENDER, a.EMAILADDRESS,
                           b.ROLE, b.ROLEID, a.DATEREGISTERED
                    FROM tbl_staff a
                    JOIN tbl_roles b ON a.ROLEID = b.ROLEID
                    ORDER BY a.STAFFID";
            return $this->GetDataSQL($sql);
        } catch (Exception $ex) {
            throw new Exception("Failed to get staff: " . $ex->getMessage());
        }
    }
    public function GetStaffById($staffId)
    {
        try {
            $sql = "SELECT a.STAFFID, a.FIRSTNAME, a.SURNAME, a.GENDER, a.EMAILADDRESS,
                           a.ROLEID, b.ROLE, a.DATEREGISTERED
                    FROM tbl_staff a
                    JOIN tbl_roles b ON a.ROLEID = b.ROLEID
                    WHERE a.STAFFID = ?";

            $result = $this->GetData($sql, [$staffId]);
            return count($result) > 0 ? $result[0] : null;
        } catch (Exception $ex) {
            throw new Exception("Failed to get staff member: " . $ex->getMessage());
        }
    }
    public function AddStaffMember($firstName, $lastName, $gender, $email, $password, $roleId, $dateRegistered)
    {
        try {
            $sql = "INSERT INTO tbl_staff (FIRSTNAME, SURNAME, GENDER, EMAILADDRESS, PASSWORD, ROLEID, DATEREGISTERED)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $params = [$firstName, $lastName, $gender, $email, $password, $roleId, $dateRegistered];

            return $this->ExecuteCommand($sql, $params);
        } catch (Exception $ex) {
            if ($ex->getCode() == '23000') {
                 throw new Exception("Failed to add staff member: Email address already exists.");
            }
            throw new Exception("Failed to add staff member: " . $ex->getMessage());
        }
    }
    public function UpdateStaffMember($staffId, $firstName, $lastName, $gender, $email, $roleId, $dateRegistered)
    {
        try {
            $sql = "UPDATE tbl_staff
                    SET FIRSTNAME = ?, SURNAME = ?, GENDER = ?,
                        EMAILADDRESS = ?, ROLEID = ?, DATEREGISTERED = ?
                    WHERE STAFFID = ?";

            $params = [$firstName, $lastName, $gender, $email, $roleId, $dateRegistered, $staffId];

            return $this->ExecuteCommand($sql, $params);
        } catch (Exception $ex) {
             if ($ex->getCode() == '23000') {
                 throw new Exception("Failed to update staff member: Email address already exists for another user.");
            }
            throw new Exception("Failed to update staff member: " . $ex->getMessage());
        }
    }
    public function UpdateStaffMemberWithPassword($staffId, $firstName, $lastName, $gender, $email, $password, $roleId, $dateRegistered)
    {
        try {
            $sql = "UPDATE tbl_staff
                    SET FIRSTNAME = ?, SURNAME = ?, GENDER = ?,
                        EMAILADDRESS = ?, PASSWORD = ?, ROLEID = ?, DATEREGISTERED = ?
                    WHERE STAFFID = ?";

            $params = [$firstName, $lastName, $gender, $email, $password, $roleId, $dateRegistered, $staffId];

            return $this->ExecuteCommand($sql, $params);
        } catch (Exception $ex) {
             if ($ex->getCode() == '23000') {
                 throw new Exception("Failed to update staff member: Email address already exists for another user.");
            }
            throw new Exception("Failed to update staff member: " . $ex->getMessage());
        }
    }
    public function DeleteStaffMember($staffId)
    {
        try {
            if ($staffId == 1) {
                throw new Exception("Cannot delete the primary administrator account.");
            }
            $sql = "DELETE FROM tbl_staff WHERE STAFFID = ?";
            return $this->ExecuteCommand($sql, [$staffId]);
        } catch (Exception $ex) {
            throw new Exception("Failed to delete staff member: " . $ex->getMessage());
        }
    }

    /**
     * CUSTOMER MANAGEMENT METHODS
     */
    // ... (GetAllCustomers, GetCustomerById, AddCustomer, UpdateCustomer, UpdateCustomerPassword, DeleteCustomer remain unchanged) ...
    public function GetAllCustomers()
    {
        try {
            $sql = "SELECT CUSTOMERID, FIRSTNAME, SURNAME, GENDER, EMAILADDRESS,
                           COMPANY, DATEREGISTERED
                    FROM tbl_customers
                    ORDER BY CUSTOMERID";
            return $this->GetDataSQL($sql);
        } catch (Exception $ex) {
            throw new Exception("Failed to get customers: " . $ex->getMessage());
        }
    }
    public function GetCustomerById($customerId)
    {
        try {
            $sql = "SELECT CUSTOMERID, FIRSTNAME, SURNAME, GENDER, EMAILADDRESS,
                           COMPANY, DATEREGISTERED
                    FROM tbl_customers
                    WHERE CUSTOMERID = ?";

            $result = $this->GetData($sql, [$customerId]);
            return count($result) > 0 ? $result[0] : null;
        } catch (Exception $ex) {
            throw new Exception("Failed to get customer: " . $ex->getMessage());
        }
    }
    public function AddCustomer($firstName, $lastName, $gender, $email, $password, $company)
    {
        try {
            $sql = "INSERT INTO tbl_customers (FIRSTNAME, SURNAME, GENDER, EMAILADDRESS, PASSWORD, COMPANY, DATEREGISTERED)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $currentDate = date($this->mariadb_dateformat);
            $params = [$firstName, $lastName, $gender, $email, $password, $company, $currentDate];

            return $this->ExecuteCommand($sql, $params);
        } catch (Exception $ex) {
             if ($ex->getCode() == '23000') {
                 throw new Exception("Failed to add customer: Email address already exists.");
            }
            throw new Exception("Failed to add customer: " . $ex->getMessage());
        }
    }
    public function UpdateCustomer($customerId, $firstName, $lastName, $gender, $email, $company)
    {
        try {
            $sql = "UPDATE tbl_customers
                    SET FIRSTNAME = ?, SURNAME = ?, GENDER = ?,
                        EMAILADDRESS = ?, COMPANY = ?
                    WHERE CUSTOMERID = ?";

            $params = [$firstName, $lastName, $gender, $email, $company, $customerId];

            return $this->ExecuteCommand($sql, $params);
        } catch (Exception $ex) {
             if ($ex->getCode() == '23000') {
                 throw new Exception("Failed to update customer: Email address already exists for another user.");
            }
            throw new Exception("Failed to update customer: " . $ex->getMessage());
        }
    }
    public function UpdateCustomerPassword($customerId, $newPassword)
    {
        try {
            $sql = "UPDATE tbl_customers SET PASSWORD = ? WHERE CUSTOMERID = ?";
            return $this->ExecuteCommand($sql, [$newPassword, $customerId]);
        } catch (Exception $ex) {
            throw new Exception("Failed to update customer password: " . $ex->getMessage());
        }
    }
    public function DeleteCustomer($customerId)
    {
        try {
            $sql = "DELETE FROM tbl_customers WHERE CUSTOMERID = ?";
            return $this->ExecuteCommand($sql, [$customerId]);
        } catch (Exception $ex) {
            throw new Exception("Failed to delete customer: " . $ex->getMessage());
        }
    }

    /**
     * ROLE MANAGEMENT METHODS
     */
    // ... (GetAllRoles remains unchanged) ...
    public function GetAllRoles()
    {
        try {
            $sql = "SELECT ROLEID, ROLE, DESCRIPTION FROM tbl_roles ORDER BY ROLEID";
            return $this->GetDataSQL($sql);
        } catch (Exception $ex) {
            throw new Exception("Failed to get roles: " . $ex->getMessage());
        }
    }

    /**
     * PARKING MANAGEMENT METHODS
     */
    // ... (GetAllSites, GetParkingSpacesBySite, GetParkingSpaceByGlobalId, GetNextGlobalParkingId, DeleteParkingSpaceByGlobalId remain unchanged as they already handle A/B/C or use global IDs correctly) ...
    public function GetAllSites()
    {
        try {
            $sql = "SELECT SITEID, SITENAME, DESCRIPTION FROM tbl_site ORDER BY SITEID";
            return $this->GetDataSQL($sql);
        } catch (Exception $ex) {
            throw new Exception("Failed to get sites: " . $ex->getMessage());
        }
    }
    public function GetAllParkingSpacesCombined()
    {
        try {
            $sql = "
                SELECT p.PARKINGID, p.PARKINGNAME, p.PARKINGIMAGE, p.DESCRIPTION, p.SITEID, s.SITENAME, p.FILTERID
                FROM tbl_parkingspaceA p JOIN tbl_site s ON p.SITEID = s.SITEID
                UNION ALL
                SELECT p.PARKINGID, p.PARKINGNAME, p.PARKINGIMAGE, p.DESCRIPTION, p.SITEID, s.SITENAME, p.FILTERID
                FROM tbl_parkingspaceB p JOIN tbl_site s ON p.SITEID = s.SITEID
                UNION ALL
                SELECT p.PARKINGID, p.PARKINGNAME, p.PARKINGIMAGE, p.DESCRIPTION, p.SITEID, s.SITENAME, p.FILTERID
                FROM tbl_parkingspaceC p JOIN tbl_site s ON p.SITEID = s.SITEID
                ORDER BY SITEID, PARKINGID
            ";
            return $this->GetDataSQL($sql);
        } catch (Exception $ex) {
            throw new Exception("Failed to get combined parking spaces: " . $ex->getMessage());
        }
    }
    public function GetParkingSpacesBySite($siteId, $filterId = null)
    {
        try {
            $table = '';
            switch ($siteId) {
                case 1: $table = 'tbl_parkingspaceA'; break;
                case 2: $table = 'tbl_parkingspaceB'; break;
                case 3: $table = 'tbl_parkingspaceC'; break;
                default: throw new Exception("Invalid site ID");
            }

            $sql = "SELECT p.PARKINGID, p.PARKINGNAME, p.PARKINGIMAGE, p.DESCRIPTION,
                           p.SITEID, s.SITENAME, p.FILTERID
                    FROM $table p
                    JOIN tbl_site s ON p.SITEID = s.SITEID
                    WHERE p.SITEID = ?";

            $params = [$siteId];

            if (!is_null($filterId)) {
                $sql .= " AND p.FILTERID = ?";
                $params[] = $filterId;
            }

            $sql .= " ORDER BY p.PARKINGNAME";

            return $this->GetData($sql, $params);
        } catch (Exception $ex) {
            throw new Exception("Failed to get parking spaces for site: " . $ex->getMessage());
        }
    }
    public function GetParkingSpaceByGlobalId($parkingId)
    {
        try {
            $sql = "
                SELECT p.PARKINGID, p.PARKINGNAME, p.PARKINGIMAGE, p.DESCRIPTION, p.SITEID, s.SITENAME, p.FILTERID
                FROM tbl_parkingspaceA p JOIN tbl_site s ON p.SITEID = s.SITEID WHERE p.PARKINGID = ?
                UNION ALL
                SELECT p.PARKINGID, p.PARKINGNAME, p.PARKINGIMAGE, p.DESCRIPTION, p.SITEID, s.SITENAME, p.FILTERID
                FROM tbl_parkingspaceB p JOIN tbl_site s ON p.SITEID = s.SITEID WHERE p.PARKINGID = ?
                UNION ALL
                SELECT p.PARKINGID, p.PARKINGNAME, p.PARKINGIMAGE, p.DESCRIPTION, p.SITEID, s.SITENAME, p.FILTERID
                FROM tbl_parkingspaceC p JOIN tbl_site s ON p.SITEID = s.SITEID WHERE p.PARKINGID = ?
            ";
            $result = $this->GetData($sql, [$parkingId, $parkingId, $parkingId]);
            return count($result) > 0 ? $result[0] : null;
        } catch (Exception $ex) {
            throw new Exception("Failed to get parking space by global ID: " . $ex->getMessage());
        }
    }
    public function GetNextGlobalParkingId()
    {
        try {
            $sql = "SELECT MAX(PARKINGID) AS max_id FROM (
                        SELECT PARKINGID FROM tbl_parkingspaceA
                        UNION ALL
                        SELECT PARKINGID FROM tbl_parkingspaceB
                        UNION ALL
                        SELECT PARKINGID FROM tbl_parkingspaceC
                    ) AS all_ids";
            $result = $this->GetDataSQL($sql);
            $maxId = isset($result[0]['max_id']) ? intval($result[0]['max_id']) : 0;
            return $maxId + 1;
        } catch (Exception $ex) {
            throw new Exception("Failed to get next global parking ID: " . $ex->getMessage());
        }
    }
    public function DeleteParkingSpaceByGlobalId($parkingId)
    {
        try {
            $tableName = null;
            
            $siteA_min = 1;
            $siteA_max = 400; 
            
            // Example: Check your actual min/max IDs for Site B
            $siteB_min = 128; 
            $siteB_max = 400; 
            
            // Example: Check your actual min/max IDs for Site C
            $siteC_min = 231; 
            $siteC_max = 400; 

            if ($parkingId >= $siteA_min && $parkingId <= $siteA_max) {
                $tableName = 'tbl_parkingspaceA';
            } elseif ($parkingId >= $siteB_min && $parkingId <= $siteB_max) {
                $tableName = 'tbl_parkingspaceB';
            } elseif ($parkingId >= $siteC_min && $parkingId <= $siteC_max) { 
                $tableName = 'tbl_parkingspaceC';
            }
           
            
            // --- END Range Update ---
            
            if ($tableName === null) {
                throw new Exception("Parking space ID $parkingId does not fall into a known site range.");
            }

            //$this->ExecuteCommand("DELETE FROM tbl_parking_disability WHERE PARKINGID = ?", [$parkingId]);
           // $this->ExecuteCommand("DELETE FROM tbl_parking_ev WHERE PARKINGID = ?", [$parkingId]);
          //  $this->ExecuteCommand("DELETE FROM tbl_parking_visitor WHERE PARKINGID = ?", [$parkingId]);
            // $this->ExecuteCommand("DELETE FROM tbl_booking WHERE PARKINGID = ?", [$parkingId]); // Decide if bookings should be deleted

            $sql = "DELETE FROM $tableName WHERE PARKINGID = ?";
            return $this->ExecuteCommand($sql, [$parkingId]);

        } catch (Exception $ex) {
            throw new Exception("Failed to delete parking space: " . $ex->getMessage());
        }
    }

        /**
     * Adds a notification for a specific customer.
     *
     * @param int $customerId The ID of the customer to notify.
     * @param string $message The notification message content.
     * @param string $notificationType A string identifying the type of notification (e.g., 'booking_revoked').
     * @param int|null $bookingId The ID of the related booking, if applicable.
     * @return int Number of affected rows (should be 1 on success).
     * @throws Exception on query error or invalid input.
     */
    public function AddNotification($customerId, $message, $notificationType, $bookingId = null)
    {
        // Basic validation
        if (empty($customerId) || !is_numeric($customerId) || $customerId <= 0) {
             throw new Exception("Invalid Customer ID provided for notification.");
        }
        if (empty($message)) {
             throw new Exception("Message is required to add a notification.");
        }
        if (empty($notificationType) || strlen($notificationType) > 50) { // Check length against VARCHAR(50)
             throw new Exception("Valid Notification Type is required (max 50 chars).");
        }
        if ($bookingId !== null && (!is_numeric($bookingId) || $bookingId <= 0)) {
             throw new Exception("Invalid Booking ID provided for notification.");
        }

        try {
            // SQL matches the table schema: notification_id is auto-increment,
            // is_read and created_at have defaults.
            $sql = "INSERT INTO tbl_notifications (customer_id, booking_id, notification_type, message)
                    VALUES (?, ?, ?, ?)";

            // Parameters match the SQL statement
            $params = [$customerId, $bookingId, $notificationType, $message];

            return $this->ExecuteCommand($sql, $params);

        } catch (Exception $ex) {
            // Log the error for debugging
            error_log("Failed to add notification for Customer ID $customerId (Type: $notificationType, Booking ID: " . ($bookingId ?? 'N/A') . "): " . $ex->getMessage());
            // Re-throw the exception to make the calling code aware of the failure.
            throw new Exception("Failed to add notification: " . $ex->getMessage());
        }
    }

    /**
     * Marks specific notifications as read for a customer.
     *
     * @param int $customerId The ID of the customer.
     * @param array $notificationIds An array of notification IDs to mark as read.
     * @return int Number of rows updated.
     * @throws Exception on query error or invalid input.
     */
    public function MarkNotificationsAsRead($customerId, array $notificationIds)
    {
        if (empty($customerId) || !is_numeric($customerId) || $customerId <= 0) {
            throw new Exception("Invalid Customer ID provided.");
        }
        if (empty($notificationIds)) {
            return 0; // Nothing to mark as read
        }

        // Ensure all IDs are integers
        $sanitizedIds = array_map('intval', $notificationIds);
        $sanitizedIds = array_filter($sanitizedIds, function($id) { return $id > 0; }); // Remove non-positive IDs

        if (empty($sanitizedIds)) {
             return 0; // No valid IDs provided
        }

        try {
            // Create placeholders for the IN clause
            $placeholders = implode(',', array_fill(0, count($sanitizedIds), '?'));

            $sql = "UPDATE tbl_notifications
                    SET is_read = TRUE
                    WHERE customer_id = ? AND notification_id IN ($placeholders) AND is_read = FALSE";

            // Combine customer ID and notification IDs into the parameters array
            $params = array_merge([$customerId], $sanitizedIds);

            return $this->ExecuteCommand($sql, $params);

        } catch (Exception $ex) {
            error_log("Failed to mark notifications as read for Customer ID $customerId: " . $ex->getMessage());
            throw new Exception("Failed to update notification status: " . $ex->getMessage());
        }
    }

    /**
     * Gets unread notifications for a specific customer.
     *
     * @param int $customerId The ID of the customer.
     * @return array List of unread notifications.
     * @throws Exception on query error.
     */
    public function GetUnreadNotifications($customerId)
    {
        if (empty($customerId) || !is_numeric($customerId) || $customerId <= 0) {
            throw new Exception("Invalid Customer ID provided.");
        }

        try {
            $sql = "SELECT notification_id, booking_id, notification_type, message, created_at
                    FROM tbl_notifications
                    WHERE customer_id = ? AND is_read = FALSE
                    ORDER BY created_at DESC"; // Show newest first

            return $this->GetData($sql, [$customerId]);
        } catch (Exception $ex) {
            error_log("Failed to get unread notifications for Customer ID $customerId: " . $ex->getMessage());
            throw new Exception("Failed to retrieve notifications: " . $ex->getMessage());
        }
    }


    /**
     * BOOKING MANAGEMENT METHODS
     */

    /**
     * Get all bookings with correct parking space details
     * @return array All bookings
     * @throws Exception on query error
     */
    public function GetAllBookings()
    {
        try {
            $sql = "SELECT b.BOOKINGID, b.DATE, b.STARTTIME, b.ENDTIME,
                           b.CUSTOMERID, CONCAT(c.FIRSTNAME, ' ', c.SURNAME) AS CUSTOMER_NAME,
                           b.PARKINGID, p.PARKINGNAME, s.SITENAME
                    FROM tbl_booking b
                    JOIN tbl_customers c ON b.CUSTOMERID = c.CUSTOMERID
                    -- *** MODIFIED JOIN using UNION ALL *** --
                    JOIN (
                        SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceA
                        UNION ALL
                        SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceB
                        UNION ALL
                        SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceC
                    ) p ON b.PARKINGID = p.PARKINGID
                    JOIN tbl_site s ON p.SITEID = s.SITEID
                    -- *** END MODIFIED JOIN *** --
                    ORDER BY b.DATE DESC, b.STARTTIME";
            return $this->GetDataSQL($sql);
        } catch (Exception $ex) {
            throw new Exception("Failed to get bookings: " . $ex->getMessage());
        }
    }

    /**
     * Get bookings for a customer with correct parking space details
     * @param int $customerId Customer ID
     * @return array Customer's bookings
     * @throws Exception on query error
     */
    public function GetBookingsByCustomer($customerId)
    {
        try {
            $sql = "SELECT b.BOOKINGID, b.DATE, b.STARTTIME, b.ENDTIME,
                           b.CUSTOMERID, CONCAT(c.FIRSTNAME, ' ', c.SURNAME) AS CUSTOMER_NAME,
                           b.PARKINGID, p.PARKINGNAME, s.SITENAME
                    FROM tbl_booking b
                    JOIN tbl_customers c ON b.CUSTOMERID = c.CUSTOMERID
                     -- *** MODIFIED JOIN using UNION ALL *** --
                    JOIN (
                        SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceA
                        UNION ALL
                        SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceB
                        UNION ALL
                        SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceC
                    ) p ON b.PARKINGID = p.PARKINGID
                    JOIN tbl_site s ON p.SITEID = s.SITEID
                    -- *** END MODIFIED JOIN *** --
                    WHERE b.CUSTOMERID = ?
                    ORDER BY b.DATE DESC, b.STARTTIME";
            return $this->GetData($sql, [$customerId]);
        } catch (Exception $ex) {
            throw new Exception("Failed to get customer bookings: " . $ex->getMessage());
        }
    }

    /**
     * Check if a parking space is available for booking (uses unique PARKINGID)
     * @param int $parkingId Parking ID
     * @param string $date Booking date
     * @param string $startTime Start time (H:i or H:i:s)
     * @param string $endTime End time (H:i or H:i:s)
     * @return bool True if available, false if already booked
     * @throws Exception on query error
     */
    public function IsParkingSpaceAvailable($parkingId, $date, $startTime, $endTime)
    {
        try {
            // Combine date and time for proper comparison
            $startDateTime = date('Y-m-d H:i:s', strtotime("$date $startTime"));
            $endDateTime = date('Y-m-d H:i:s', strtotime("$date $endTime"));

            $sql = "SELECT COUNT(*) AS booking_count
                    FROM tbl_booking
                    WHERE PARKINGID = ?
                    AND DATE = ?
                    AND NOT (ENDTIME <= ? OR STARTTIME >= ?)"; // Overlap check

            $params = [$parkingId, $date, $startDateTime, $endDateTime];

            $result = $this->GetData($sql, $params);
            return ($result[0]['booking_count'] == 0);
        } catch (Exception $ex) {
            throw new Exception("Failed to check parking availability: " . $ex->getMessage());
        }
    }


    /**
     * Add a new booking
     * @param int $customerId Customer ID
     * @param int $parkingId Parking ID (now globally unique)
     * @param string $date Booking date (Y-m-d)
     * @param string $startTime Start time (Y-m-d H:i:s)
     * @param string $endTime End time (Y-m-d H:i:s)
     * @return int Number of affected rows
     * @throws Exception on query error or if space unavailable
     */
    public function AddBooking($customerId, $parkingId, $date, $startTime, $endTime)
    {
        try {
            // Check availability using the correct time format expected by IsParkingSpaceAvailable
            if (!$this->IsParkingSpaceAvailable($parkingId, $date, date('H:i:s', strtotime($startTime)), date('H:i:s', strtotime($endTime)))) {
                throw new Exception("Parking space is not available for the requested time");
            }

            $sql_next_id = "SELECT MAX(CAST(BOOKINGID AS UNSIGNED)) + 1 AS NEXTID FROM tbl_booking";
            $result_next_id = $this->GetDataSQL($sql_next_id);
            $bookingId = isset($result_next_id[0]['NEXTID']) ? $result_next_id[0]['NEXTID'] : 1;

            $sql_insert = "INSERT INTO tbl_booking (BOOKINGID, CUSTOMERID, PARKINGID, DATE, STARTTIME, ENDTIME)
                           VALUES (?, ?, ?, ?, ?, ?)";

            $params = [$bookingId, $customerId, $parkingId, $date, $startTime, $endTime];

            return $this->ExecuteCommand($sql_insert, $params);
        } catch (Exception $ex) {
            throw new Exception("Failed to add booking: " . $ex->getMessage());
        }
    }


    /**
     * Get booking details by ID with correct parking space details
     * @param int $bookingId Booking ID
     * @return array|null Booking details or null if not found
     * @throws Exception on query error
     */
    public function GetBookingById($bookingId)
    {
        try {
            $sql = "SELECT b.BOOKINGID, b.DATE, b.STARTTIME, b.ENDTIME,
                           b.CUSTOMERID, CONCAT(c.FIRSTNAME, ' ', c.SURNAME) AS CUSTOMER_NAME,
                           b.PARKINGID, p.PARKINGNAME, s.SITENAME
                    FROM tbl_booking b
                    JOIN tbl_customers c ON b.CUSTOMERID = c.CUSTOMERID
                     -- *** MODIFIED JOIN using UNION ALL *** --
                    JOIN (
                        SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceA
                        UNION ALL
                        SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceB
                        UNION ALL
                        SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceC
                    ) p ON b.PARKINGID = p.PARKINGID
                    JOIN tbl_site s ON p.SITEID = s.SITEID
                    -- *** END MODIFIED JOIN *** --
                    WHERE b.BOOKINGID = ?";

            $result = $this->GetData($sql, [$bookingId]);
            return count($result) > 0 ? $result[0] : null;
        } catch (Exception $ex) {
            throw new Exception("Failed to get booking details: " . $ex->getMessage());
        }
    }

    /**
     * Revoke and reassign a booking to a different customer
     * @param int $bookingId Booking ID
     * @param int $newCustomerId New customer ID
     * @return int Number of affected rows
     * @throws Exception on query error
     */
    public function RevokeAndReassignBooking($bookingId, $newCustomerId)
    {
        try {
            $sql = "UPDATE tbl_booking SET CUSTOMERID = ? WHERE BOOKINGID = ?";
            return $this->ExecuteCommand($sql, [$newCustomerId, $bookingId]);
        } catch (Exception $ex) {
            throw new Exception("Failed to reassign booking: " . $ex->getMessage());
        }
    }

    /**
     * Delete a booking
     * @param int $bookingId Booking ID
     * @return int Number of affected rows
     * @throws Exception on query error
     */
    public function DeleteBooking($bookingId)
    {
        try {
            $sql = "DELETE FROM tbl_booking WHERE BOOKINGID = ?";
            return $this->ExecuteCommand($sql, [$bookingId]);
        } catch (Exception $ex) {
            throw new Exception("Failed to delete booking: " . $ex->getMessage());
        }
    }

    /**
     * Get bookings for a specific parking space on a specific date
     * @param int $parkingId Parking ID (globally unique)
     * @param string $date Date in Y-m-d format (optional, defaults to today)
     * @return array Bookings for the parking space
     * @throws Exception on query error
     */
    public function GetBookingsForParkingSpace($parkingId, $date = null)
    {
        try {
            $date = $date ?: date('Y-m-d');

            $sql = "SELECT BOOKINGID, DATE, STARTTIME, ENDTIME, CUSTOMERID, PARKINGID
                    FROM tbl_booking
                    WHERE PARKINGID = ? AND DATE = ?
                    ORDER BY STARTTIME";

            return $this->GetData($sql, [$parkingId, $date]);
        } catch (Exception $ex) {
            throw new Exception("Failed to get parking space bookings: " . $ex->getMessage());
        }
    }

    /**
     * FAQ MANAGEMENT METHODS
     */
    // ... (GetAllFAQs, AddFAQ, UpdateFAQ, DeleteFAQ remain unchanged) ...
    public function GetAllFAQs()
    {
        try {
            $sql = "SELECT FAQID, FAQUESTION, FAQANSWER FROM tbl_faq ORDER BY FAQID";
            return $this->GetDataSQL($sql);
        } catch (Exception $ex) {
            throw new Exception("Failed to get FAQs: " . $ex->getMessage());
        }
    }
    public function AddFAQ($question, $answer)
    {
        try {
            $sql_next_id = "SELECT MAX(FAQID) + 1 AS NEXTID FROM tbl_faq";
            $result_next_id = $this->GetDataSQL($sql_next_id);
            $faqId = isset($result_next_id[0]['NEXTID']) ? $result_next_id[0]['NEXTID'] : 1;

            $sql_insert = "INSERT INTO tbl_faq (FAQID, FAQUESTION, FAQANSWER) VALUES (?, ?, ?)";
            $params = [$faqId, $question, $answer];

            return $this->ExecuteCommand($sql_insert, $params);
        } catch (Exception $ex) {
            throw new Exception("Failed to add FAQ: " . $ex->getMessage());
        }
    }
    public function UpdateFAQ($faqId, $question, $answer)
    {
        try {
            $sql = "UPDATE tbl_faq SET FAQUESTION = ?, FAQANSWER = ? WHERE FAQID = ?";
            return $this->ExecuteCommand($sql, [$question, $answer, $faqId]);
        } catch (Exception $ex) {
            throw new Exception("Failed to update FAQ: " . $ex->getMessage());
        }
    }
    public function DeleteFAQ($faqId)
    {
        try {
            $sql = "DELETE FROM tbl_faq WHERE FAQID = ?";
            return $this->ExecuteCommand($sql, [$faqId]);
        } catch (Exception $ex) {
            throw new Exception("Failed to delete FAQ: " . $ex->getMessage());
        }
    }

    /**
     * FEEDBACK MANAGEMENT METHODS
     */
    // ... (GetAllFeedback, SubmitFeedback, DeleteFeedback remain unchanged) ...
    public function GetAllFeedback()
    {
        try {
            $sql = "SELECT f.FEEDBACKID, f.EMAILADDRESS, f.FEEDBACK, f.CUSTOMERID,
                           CONCAT(c.FIRSTNAME, ' ', c.SURNAME) AS CUSTOMER_NAME
                    FROM tbl_feedback f
                    LEFT JOIN tbl_customers c ON f.CUSTOMERID = c.CUSTOMERID
                    ORDER BY f.FEEDBACKID DESC";
            return $this->GetDataSQL($sql);
        } catch (Exception $ex) {
            throw new Exception("Failed to get feedback: " . $ex->getMessage());
        }
    }
    public function SubmitFeedback($feedbackText, $firstName = '', $lastName = '', $email = '', $phone = '')
    {
        try {
            $sql_next_id = "SELECT MAX(FEEDBACKID) + 1 AS NEXTID FROM tbl_feedback";
            $result_next_id = $this->GetDataSQL($sql_next_id);
            $feedbackId = isset($result_next_id[0]['NEXTID']) ? $result_next_id[0]['NEXTID'] : 1;

            $customerId = null;
            if (!empty($email)) {
                $customerQuery = "SELECT CUSTOMERID FROM tbl_customers WHERE EMAILADDRESS = ?";
                $customerResult = $this->GetData($customerQuery, [$email]);
                if (count($customerResult) > 0) {
                    $customerId = $customerResult[0]['CUSTOMERID'];
                }
            }

            $sql_insert = "INSERT INTO tbl_feedback (FEEDBACKID, EMAILADDRESS, FEEDBACK, CUSTOMERID)
                           VALUES (?, ?, ?, ?)";

            $params = [$feedbackId, $email, $feedbackText, $customerId];

            return $this->ExecuteCommand($sql_insert, $params);
        } catch (Exception $ex) {
            throw new Exception("Failed to submit feedback: " . $ex->getMessage());
        }
    }
    public function DeleteFeedback($feedbackId)
    {
        try {
            $sql = "DELETE FROM tbl_feedback WHERE FEEDBACKID = ?";
            return $this->ExecuteCommand($sql, [$feedbackId]);
        } catch (Exception $ex) {
            throw new Exception("Failed to delete feedback: " . $ex->getMessage());
        }
    }

    /**
     * REPORT MANAGEMENT METHODS
     */

    /**
     * Gets all reports
     * @return array All reports
     * @throws Exception on query error
     */
    public function GetAllReports()
    {
        try {
            $this->InitializeReports();
            $sql = "SELECT REPORTID, REPORTNAME, CATEGORY, PREVIEW FROM tbl_reports ORDER BY CATEGORY, REPORTID";
            return $this->GetDataSQL($sql);
        } catch (Exception $ex) {
            throw new Exception("Failed to get reports: " . $ex->getMessage());
        }
    }

    /**
     * Initialize reports in the database
     * @return bool True if successful
     * @throws Exception on database error
     */
    public function InitializeReports()
    {
        try {
            $conn = $this->GetConnection();

            $tableCheckSql = "SHOW TABLES LIKE 'tbl_reports'";
            $result = $conn->query($tableCheckSql);

            if ($result->rowCount() == 0) {
                $createTableSql = "CREATE TABLE tbl_reports (
                    REPORTID INT PRIMARY KEY,
                    REPORTNAME VARCHAR(100) NOT NULL,
                    CATEGORY VARCHAR(50) NOT NULL,
                    PREVIEW TEXT,
                    REPORTQUERY TEXT
                )";
                $conn->exec($createTableSql);
            }

            $dataCheckSql = "SELECT COUNT(*) AS count FROM tbl_reports";
            $result = $conn->query($dataCheckSql);
            $row = $result->fetch(PDO::FETCH_ASSOC);

            if ($row['count'] == 0) {
                // *** MODIFIED REPORT QUERIES to use UNION ALL ***
                $reports = [
                    [
                        1, 'Staff Activity Summary', 'Staff', 'Overview of staff activities and performance metrics.',
                        'SELECT s.STAFFID, CONCAT(s.FIRSTNAME, " ", s.SURNAME) AS STAFFNAME, r.ROLE, COUNT(b.BOOKINGID) AS BOOKING_COUNT
                         FROM tbl_staff s
                         JOIN tbl_roles r ON s.ROLEID = r.ROLEID
                         LEFT JOIN tbl_booking b ON s.STAFFID = b.STAFFID -- Assuming staff can make bookings? Check schema
                         GROUP BY s.STAFFID, STAFFNAME, r.ROLE ORDER BY BOOKING_COUNT DESC'
                    ],
                    [
                        2, 'Parking Space Utilization', 'Events', 'Monthly report on parking space usage and availability.',
                        'SELECT p.PARKINGID, p.PARKINGNAME, s.SITENAME, COUNT(b.BOOKINGID) AS BOOKING_COUNT
                         FROM (
                             SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceA
                             UNION ALL SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceB
                             UNION ALL SELECT PARKINGID, PARKINGNAME, SITEID FROM tbl_parkingspaceC
                         ) p
                         JOIN tbl_site s ON p.SITEID = s.SITEID
                         LEFT JOIN tbl_booking b ON p.PARKINGID = b.PARKINGID
                         GROUP BY p.PARKINGID, p.PARKINGNAME, s.SITENAME ORDER BY BOOKING_COUNT DESC'
                    ],
                    [
                        3, 'Customer Feedback Analysis', 'Feedback', 'Summary of customer feedback and satisfaction ratings.',
                        'SELECT DATE_FORMAT(f.DATEREGISTERED, "%Y-%m") AS MONTH, COUNT(*) AS FEEDBACK_COUNT
                         FROM tbl_feedback f GROUP BY MONTH ORDER BY MONTH DESC' // Assuming feedback has a date
                    ],
                    [
                        4, 'Bookings by Site', 'Demonstrations', 'Total bookings per site.',
                        'SELECT s.SITEID, s.SITENAME, COUNT(b.BOOKINGID) AS BOOKING_COUNT, COUNT(DISTINCT b.CUSTOMERID) AS UNIQUE_CUSTOMERS
                         FROM tbl_site s
                         JOIN (
                             SELECT PARKINGID, SITEID FROM tbl_parkingspaceA
                             UNION ALL SELECT PARKINGID, SITEID FROM tbl_parkingspaceB
                             UNION ALL SELECT PARKINGID, SITEID FROM tbl_parkingspaceC
                         ) p ON s.SITEID = p.SITEID
                         LEFT JOIN tbl_booking b ON p.PARKINGID = b.PARKINGID
                         GROUP BY s.SITEID, s.SITENAME ORDER BY BOOKING_COUNT DESC'
                    ],
                    [
                        5, 'Bookings Per Month', 'Events', 'Analysis of booking trends over time.',
                        'SELECT DATE_FORMAT(DATE, "%Y-%m") AS MONTH, COUNT(*) AS BOOKING_COUNT
                         FROM tbl_booking GROUP BY MONTH ORDER BY MONTH DESC'
                    ]
                ];
                // *** END MODIFIED REPORT QUERIES ***

                $insertSql = "INSERT INTO tbl_reports (REPORTID, REPORTNAME, CATEGORY, PREVIEW, REPORTQUERY) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insertSql);
                foreach ($reports as $report) {
                    $stmt->execute($report);
                }
            }

            $conn = null;
            return true;
        } catch (PDOException $ex) {
            throw new Exception("Failed to initialize reports: " . $ex->getMessage());
        }
    }


    /**
     * Execute a report query
     * @param int $reportId Report ID to execute
     * @return array Report results
     * @throws Exception on query error
     */
    public function ExecuteReport($reportId)
    {
        try {
            $sql = "SELECT REPORTNAME, REPORTQUERY FROM tbl_reports WHERE REPORTID = ?";
            $reportInfo = $this->GetData($sql, [$reportId]);

            if (count($reportInfo) == 0) {
                throw new Exception("Report not found");
            }

            $reportQuery = $reportInfo[0]['REPORTQUERY'];
            return $this->GetDataSQL($reportQuery);
        } catch (Exception $ex) {
            throw new Exception("Failed to execute report: " . $ex->getMessage());
        }
    }

    /**
     * Get report information
     * @param int $reportId Report ID
     * @return array Report information
     * @throws Exception on query error
     */
    public function GetReportInfo($reportId)
    {
        try {
            $sql = "SELECT REPORTID, REPORTNAME, CATEGORY, PREVIEW FROM tbl_reports WHERE REPORTID = ?";
            $result = $this->GetData($sql, [$reportId]);

            if (count($result) == 0) {
                throw new Exception("Report not found");
            }

            return $result[0];
        } catch (Exception $ex) {
            throw new Exception("Failed to get report information: " . $ex->getMessage());
        }
    }

    /**
     * SESSION AND SECURITY METHODS
     */
    // ... (isLoggedIn, hasRole, isAdmin remain unchanged) ...
    public function isLoggedIn()
    {
        return isset($_SESSION['staff_id']) || isset($_SESSION['customer_id']);
    }
    public function hasRole($roleId)
    {
        return isset($_SESSION['role_id']) && $_SESSION['role_id'] == $roleId;
    }
    public function isAdmin()
    {
        return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
    }

    /**
     * UTILITY METHODS
     */
    // ... (GetCurrentDate, GetCurrentTime, FormatDate, FormatTime, ShowMessage, GenerateUniqueId remain unchanged) ...
    public function GetCurrentDate()
    {
        return date($this->mariadb_dateformat);
    }
    public function GetCurrentTime()
    {
        return date("H:i:s");
    }
    public function FormatDate($date)
    {
        return date("F j, Y", strtotime($date));
    }
    public function FormatTime($time)
    {
        return date("g:i A", strtotime($time));
    }
    public function ShowMessage($msg, $type = "Error", $terminate = true)
    {
        $type = strtolower($type[0]);
        $back_color = ($type == "i") ? "#d9ff66" : "yellow";
        $msg_html = "<fieldset><legend><font color=blue>USER MESSAGE</font></legend><marquee scrollamount=0 bgcolor='$back_color' height=''><center><font size='4' face='Arial' color='red'>$msg</font></center></marquee></fieldset>";
        if ($terminate) {
            die($msg_html);
        } else {
            echo $msg_html;
        }
    }
    public function GenerateUniqueId($prefix = "")
    {
        return $prefix . uniqid();
    }
}
?>
