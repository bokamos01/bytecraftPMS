<?php
require_once 'config.php';

$content = '
<div class="unauthorized-container">
    <div class="unauthorized-icon">⚠️</div>
    <h2 class="unauthorized-title">Unauthorized Access</h2>
    <p class="unauthorized-message">
        You do not have permission to access this page. 
        Please contact your administrator if you believe this is a mistake.
    </p>
    <a href="index.php" class="go-back-button">Return to Home</a>
</div>
';

include 'master.php';
?>
