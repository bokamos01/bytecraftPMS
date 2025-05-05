<?php
// Start the session
session_start();

// Include the data access layer
require_once 'PMSDataAccess.php';

// Create instance of DataAccess class
$da = new DataAccess();

// Check if user is logged in and is an admin
if (!$da->isLoggedIn() || !$da->isAdmin()) {
    // Not logged in or not an admin, redirect to login
    header("Location: login.php");
    exit;
}

// Check if report ID is provided
if (!isset($_GET['reportId']) || !is_numeric($_GET['reportId'])) {
    header("Location: Reports.php");
    exit;
}

$reportId = (int)$_GET['reportId'];

try {
    // Get report information
    $reportInfo = $da->GetReportInfo($reportId);
    
    // Execute the report
    $reportData = $da->ExecuteReport($reportId);
    
    // Set page title
    $pageTitle = $reportInfo['REPORTNAME'] . ' | Carpark Management System';
    
    // Start output buffering to capture content
    ob_start();
?>

<style>
    .report-container {
        margin: 0 auto;
        max-width: 1200px;
        padding: 20px;
    }
    
    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .report-title {
        font-size: 24px;
        font-weight: bold;
        color: #333;
    }
    
    .report-category {
        background-color: #e5e7eb;
        color: #374151;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .report-card {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 30px;
    }
    
    .report-card-header {
        background-color: #f9fafb;
        padding: 16px 24px;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .report-card-body {
        padding: 24px;
    }
    
    .report-preview {
        color: #6b7280;
        margin-top: 8px;
    }
    
    .report-alert {
        background-color: #fff8e1;
        border-left: 4px solid #ffc107;
        color: #856404;
        padding: 16px;
        margin-bottom: 16px;
    }
    
    .report-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .report-table th {
        background-color: #f9fafb;
        border-bottom: 2px solid #e5e7eb;
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
    }
    
    .report-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .report-table tr:nth-child(even) {
        background-color: #f9fafb;
    }
    
    .report-table tr:hover {
        background-color: #f3f4f6;
    }
    
    .report-back-link {
        display: inline-block;
        background-color: #2d3741;
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        text-decoration: none;
        font-size: 14px;
        margin-top: 20px;
    }
    
    .report-back-link:hover {
        background-color: #1e2631;
    }
</style>

<div class="report-container">
    <div class="report-header">
        <h1 class="report-title"><?php echo htmlspecialchars($reportInfo['REPORTNAME']); ?></h1>
        <span class="report-category"><?php echo htmlspecialchars($reportInfo['CATEGORY']); ?></span>
    </div>

    <div class="report-card">
        <div class="report-card-header">
            <p class="report-preview"><?php echo htmlspecialchars($reportInfo['PREVIEW']); ?></p>
        </div>
        
        <div class="report-card-body">
            <?php if (empty($reportData)): ?>
                <div class="report-alert">
                    <p>No data available for this report.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <?php foreach (array_keys($reportData[0]) as $column): ?>
                                    <th><?php echo htmlspecialchars($column); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): ?>
                                        <td><?php echo htmlspecialchars($cell ?? ''); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <a href="Reports.php" class="report-back-link">Back to Reports</a>
        </div>
    </div>
</div>

<?php
    $content = ob_get_clean();
    
    include 'master.php';
    
} catch (Exception $ex) {
    header("Location: Reports.php?error=" . urlencode($ex->getMessage()));
    exit;
}
?>
