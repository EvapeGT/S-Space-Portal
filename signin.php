<?php
session_start();

$host = "localhost";
$username = "root";
$password = "root";
$database = "s_space_tenant_portal";
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $loginID = $_POST["LoginID"];
    $password = $_POST["passwordID"];
    $stmt = $conn->prepare("SELECT `PasswordHash`, `UserType` FROM `Login` WHERE `LoginID` = ?");
    $stmt->bind_param("i", $loginID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $userType = $row['UserType'];
        $storedPassword = $row['PasswordHash'];

        if ($password == $storedPassword) {
            if ($userType == "admin") {
                $_SESSION['isAdminLoggedIn'] = true; // Set a session variable for admin login
                $tenantsStmt = $conn->prepare("SELECT 
                Tenants.TenantID, 
                Tenants.FirstName,
                Tenants.LastName,
                Tenants.Email,
                Tenants.ContactNumber,
                Contracts.ContractID,
                Contracts.StartDate,
                Contracts.EndDate,
                Bills.BillID,
                Bills.BillDate,
                Bills.WaterBill,
                ServiceRequests.RequestID,
                ServiceRequests.RequestDate,
                ServiceRequests.IssueDescription,
                ServiceRequests.Status,
                Payments.PaymentID,
                DATE_FORMAT(Payments.PaymentDate, '%Y-%m-%d') AS FormattedPaymentDate,
                Payments.PaymentAmount,
                Payments.PaymentMethod,
                Payments.PaymentBill,
                Payments.ReferenceNumber,
                Payments.ReceiptImageURL,
                Payments.PaymentStatus,
                Rooms.room_id,
                Rooms.price
            FROM 
                Tenants
            LEFT JOIN 
                Contracts ON Tenants.TenantID = Contracts.TenantID
            LEFT JOIN 
                Bills ON Tenants.TenantID = Bills.TenantID
            LEFT JOIN 
                ServiceRequests ON Tenants.TenantID = ServiceRequests.TenantID
            LEFT JOIN 
                Payments ON Tenants.TenantID = Payments.TenantID
            LEFT JOIN 
                Rooms ON Tenants.room_id = Rooms.room_id
            GROUP BY 
                Tenants.TenantID;");
                $tenantsStmt->execute();
                $tenantsResult = $tenantsStmt->get_result();
                $revenueStmt = $conn->prepare("SELECT SUM(PaymentAmount) AS TotalRevenueThisMonth
                               FROM Payments
                               WHERE MONTH(PaymentDate) = MONTH(CURRENT_DATE)
                               AND YEAR(PaymentDate) = YEAR(CURRENT_DATE);");
                $revenueStmt->execute();
                $revenueResult = $revenueStmt->get_result();
                $revenueData = $revenueResult->fetch_assoc();
                // Store the total revenue in a session variable
                $_SESSION['totalRevenueThisMonth'] = $revenueData['TotalRevenueThisMonth'];

                while ($tenantRow = $tenantsResult->fetch_assoc()) {
                    $_SESSION['tenantsData'][] = [
                        'TenantID' => $tenantRow['TenantID'],
                        'FirstName' => $tenantRow['FirstName'],
                        'LastName' => $tenantRow['LastName'],
                        'Email' => $tenantRow['Email'],
                        'ContactNumber' => $tenantRow['ContactNumber'],
                    ];
                    $_SESSION['contractsData'][] = [
                        'TenantID' => $tenantRow['TenantID'],
                        'ContractID' => $tenantRow['ContractID'],
                        'StartDate' => $tenantRow['StartDate'],
                        'EndDate' => $tenantRow['EndDate'],
                    ];
                    $_SESSION['billsData'][] = [
                        'TenantID' => $tenantRow['TenantID'],
                        'BillID' => $tenantRow['BillID'],
                        'BillDate' => $tenantRow['BillDate'],
                        'WaterBill' => $tenantRow['WaterBill'],
                    ];
                    $_SESSION['serviceRequestsData'][] = [
                    'TenantID' => $tenantRow['TenantID'],
                    'RequestID' => $tenantRow['RequestID'],
                    'RequestDate' => $tenantRow['RequestDate'],
                    'IssueDescription' => $tenantRow['IssueDescription'],
                    'Status' => $tenantRow['Status'],
                    'room_id' => $tenantRow['room_id'],
                    ];
                    $_SESSION['paymentsData'][] = [
                        'TenantID' => $tenantRow['TenantID'],
                        'PaymentID' => $tenantRow['PaymentID'],
                        'PaymentDate' => $tenantRow['FormattedPaymentDate'], // Use the alias from the SQL query
                        'PaymentAmount' => $tenantRow['PaymentAmount'],
                        'PaymentMethod' => $tenantRow['PaymentMethod'],
                        'PaymentStatus' =>  $tenantRow['PaymentStatus'],
                        // Add the new fields
                        'PaymentBill' => $tenantRow['PaymentBill'],
                        'ReferenceNumber' => $tenantRow['ReferenceNumber'],
                        'ReceiptImageURL' => $tenantRow['ReceiptImageURL'],
                    ];
                    $_SESSION['roomsData'][] = [
                        'room_id' => $tenantRow['room_id'],
                        'price' => $tenantRow['price'],
                    ];
                }
                $tenantsStmt->close();
                header('Location: NiceAdmin/index.php');
                exit();

            } else {
                $_SESSION['isUserLoggedIn'] = true; 
                $tenantStmt = $conn->prepare("SELECT Tenants.*, Contracts.*, Bills.*, ServiceRequests.*
                                        FROM Tenants
                                        LEFT JOIN Contracts ON Tenants.TenantID = Contracts.TenantID
                                        LEFT JOIN Bills ON Tenants.TenantID = Bills.TenantID
                                        LEFT JOIN ServiceRequests ON Tenants.TenantID = ServiceRequests.TenantID
                                        WHERE Tenants.LoginID = ?");
        $tenantStmt->bind_param("i", $loginID);
        $tenantStmt->execute();
        $tenantResult = $tenantStmt->get_result();
        $tenantRow = $tenantResult->fetch_assoc();
        $_SESSION['tenantData'] = $tenantRow;
        $tenantStmt->close();
                header('Location: successLogIn/success.php') ;
                exit();
            }
        } else {
            // Incorrect password logic
            header('Location: NiceAdmin/pages-error-404.html');
            exit();
        }
    } else {
        // No user found logic
        header('Location: NiceAdmin/pages-error-404.html');
        exit();
    }
    $stmt->close();
}

$conn->close();
?>
