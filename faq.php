<?php

// Start the session
if (!isset($_SESSION)) {
    session_start();
}

// Include the data access layer
require_once 'PMSDataAccess.php';

// Create instance of DataAccess class
$da = new DataAccess();

// Get all FAQs
$faqs = $da->GetAllFAQs();

// Start output buffering to capture content
ob_start();
?>

<div class="faq-container">
    <h1 class="faq-title">Frequently Asked Questions</h1>
    
    <div class="faq-list">
        <?php if (count($faqs) > 0): ?>
            <?php foreach ($faqs as $faq): ?>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleAnswer(this)">
                        <span><?php echo htmlspecialchars($faq['FAQUESTION']); ?></span>
                        <span class="toggle-icon">+</span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-content">
                            <?php echo nl2br(htmlspecialchars($faq['FAQANSWER'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-faqs">
                <p>No FAQs available at the moment. Please check back later.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="contact-section">
        <h2>Still have questions?</h2>
        <p>Our team is here to help. Contact us to learn more about our Carpark Management System.</p>
        <div class="contact-links">
            <a href="feedback.php" class="contact-btn">Contact Us</a>
            <?php if (!isset($_SESSION['customer_id']) && !isset($_SESSION['staff_id'])): ?>
                <a href="login.php" class="login-btn">Log In</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .faq-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .faq-title {
        font-size: 24px;
        font-weight: bold;
        text-align: center;
        margin-bottom: 30px;
    }
    
    .faq-list {
        margin-bottom: 30px;
    }
    
    .faq-item {
        border: 1px solid #ddd;
        border-radius: 5px;
        margin-bottom: 10px;
        overflow: hidden;
    }
    
    .faq-question {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        background-color: #2d3741;
        cursor: pointer;
        text-align: left;
        border: none;
    }
    
    .faq-question:hover {
        background-color: #3d4751;
    }
    
    .toggle-icon {
        font-weight: bold;
        font-size: 18px;
    }
    
    .faq-answer {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-in-out;
    }
    
    .faq-answer-content {
        padding: 15px;
    }
    
    .no-faqs {
        text-align: center;
        padding: 30px;
        background-color: #f5f5f5;
        border-radius: 5px;
    }
    
    .contact-section {
        background-color: #f0f0f0;
        border-radius: 5px;
        padding: 30px;
        text-align: center;
    }
    
    .contact-section h2 {
        font-size: 20px;
        font-weight: bold;
        margin-bottom: 15px;
    }
    
    .contact-section p {
        margin-bottom: 20px;
    }
    
    .contact-links {
        display: flex;
        justify-content: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .contact-btn, .login-btn {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 500;
    }
    
    .contact-btn {
        background-color: #e0e0e0;
        color: #333;
    }
    
    .contact-btn:hover {
        background-color: #d0d0d0;
    }
    
    .login-btn {
        background-color: #2d3741;
        color: white;
    }
    
    .login-btn:hover {
        background-color: #3d4751;
    }
</style>

<script>
    function toggleAnswer(button) {
        // Toggle the active class on the button
        button.classList.toggle('active');
        
        // Find the answer container
        const answer = button.nextElementSibling;
        
        // Toggle the icon
        const icon = button.querySelector('.toggle-icon');
        if (button.classList.contains('active')) {
            icon.textContent = '-';
            // Expand the answer
            answer.style.maxHeight = answer.scrollHeight + 'px';
        } else {
            icon.textContent = '+';
            // Collapse the answer
            answer.style.maxHeight = '0';
        }
    }
</script>

<?php
$content = ob_get_clean();

include 'master.php';
?>
