<?php

// Include the data access layer
require_once 'PMSDataAccess.php';

// Create instance of DataAccess class
$da = new DataAccess();

try {
    // Initialize reports
    $result = $da->InitializeReports();
    
    echo "<html>
    <head>
        <title>Reports Initialization</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .container {
                max-width: 600px;
                margin: 40px auto;
                background-color: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            h1 {
                color: #2d3741;
            }
            .success-message {
                color: #155724;
                background-color: #d4edda;
                border: 1px solid #c3e6cb;
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            .back-link {
                display: inline-block;
                margin-top: 20px;
                background-color: #2d3741;
                color: white;
                padding: 10px 15px;
                text-decoration: none;
                border-radius: 4px;
            }
            .back-link:hover {
                background-color: #1e2631;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>Reports Initialization</h1>
            <div class='success-message'>
                Reports have been initialized successfully!
            </div>
            <a href='Reports.php' class='back-link'>Go to Reports</a>
        </div>
    </body>
</html>";
} catch (Exception $ex) {
    echo "<html>
    <head>
        <title>Reports Initialization Error</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .container {
                max-width: 600px;
                margin: 40px auto;
                background-color: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            h1 {
                color: #2d3741;
            }
            .error-message {
                color: #721c24;
                background-color: #f8d7da;
                border: 1px solid #f5c6cb;
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            .back-link {
                display: inline-block;
                margin-top: 20px;
                background-color: #2d3741;
                color: white;
                padding: 10px 15px;
                text-decoration: none;
                border-radius: 4px;
            }
            .back-link:hover {
                background-color: #1e2631;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>Reports Initialization Error</h1>
            <div class='error-message'>
                Error initializing reports: " . htmlspecialchars($ex->getMessage()) . "
            </div>
            <a href='Reports.php' class='back-link'>Go to Reports</a>
        </div>
    </body>
</html>";
}
