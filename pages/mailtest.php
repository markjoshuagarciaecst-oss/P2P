<?php
require_once '../config/database.php';
require_once '../config/mail.php';

// Change this to your own email to receive the test
$testTo   = 'adminskillswap08@gmail.com';
$testName = 'Test User';

echo '<h2>Mail Test</h2><pre>';
echo 'BREVO_API_KEY: ' . substr(BREVO_API_KEY, 0, 20) . "...\n";
echo 'MAIL_FROM: ' . MAIL_FROM . "\n";
echo 'Sending to: ' . $testTo . "\n\n";

$result = sendMail(
    $testTo,
    $testName,
    'Test Email from Time for Skill',
    '<h2>Test</h2><p>If you see this, email is working!</p>'
);

if ($result === true) {
    echo "✅ SUCCESS — email sent!\n";
} else {
    echo "❌ FAILED: " . $result . "\n";
}
echo '</pre>';
echo '<p style="color:red">Delete this file after testing.</p>';
