<?php
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

$pageTitle = 'Reports | Carpark Management System';

ob_start();
?>

<style>
    .reports-container {
        margin: 0 auto;
        max-width: 1200px;
        padding: 20px;
    }
    
    .reports-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .reports-title {
        font-size: 24px;
        font-weight: bold;
        color: #333;
    }
    
    .reports-card {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 30px;
    }
    
    .reports-card-body {
        padding: 24px;
    }
    
    .reports-alert {
        background-color: #fff8e1;
        border-left: 4px solid #ffc107;
        color: #856404;
        padding: 16px;
        margin-bottom: 16px;
    }
    
    .reports-grid {
        display: grid;
        grid-template-columns: repeat(1, 1fr);
        gap: 24px;
    }
    
    @media (min-width: 768px) {
        .reports-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (min-width: 1024px) {
        .reports-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    
    .report-item {
        background-color: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
        transition: box-shadow 0.3s;
    }
    
    .report-item:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    
    .report-content {
        display: flex;
        align-items: flex-start;
    }
    
    .report-icon {
        flex-shrink: 0;
    }
    
    .report-icon svg {
        height: 40px;
        width: 40px;
        color: #6366f1;
    }
    
    .report-details {
        margin-left: 16px;
    }
    
    .report-title {
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }
    
    .report-category {
        color: #6b7280;
        margin-top: 4px;
    }
    
    .report-preview {
        font-size: 14px;
        color: #4b5563;
        margin-top: 8px;
    }
    
    a {
        text-decoration: none;
    }
</style>

<div class="reports-container">
    <div class="reports-header">
        <h1 class="reports-title">Reports</h1>
    </div>

    <div class="reports-card">
        <div class="reports-card-body">
            <?php
            // Get all reports from database
            $reports = $da->GetAllReports();
            
            if (empty($reports)): 
            ?>
                <div class="reports-alert">
                    <p>No reports are available. Please run the initialization script.</p>
                </div>
            <?php else: ?>
                <div class="reports-grid">
                    <?php
                    // report icons based on category
                    $categoryIcons = [
                        'Staff' => '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>',
                        'Events' => '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>',
                        'Feedback' => '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" /></svg>',
                        'Demonstrations' => '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>'
                    ];
                    
                    $defaultIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>';
                    
                    foreach ($reports as $report): 
                        $icon = $categoryIcons[$report['CATEGORY']] ?? $defaultIcon;
                    ?>
                        <a href="reportViewer.php?reportId=<?php echo $report['REPORTID']; ?>" class="report-item">
                            <div class="report-content">
                                <div class="report-icon"><?php echo $icon; ?></div>
                                <div class="report-details">
                                    <h3 class="report-title"><?php echo htmlspecialchars($report['REPORTNAME']); ?></h3>
                                    <p class="report-category"><?php echo htmlspecialchars($report['CATEGORY']); ?></p>
                                    <?php if (!empty($report['PREVIEW'])): ?>
                                        <p class="report-preview"><?php echo htmlspecialchars($report['PREVIEW']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

include 'master.php';
?>
