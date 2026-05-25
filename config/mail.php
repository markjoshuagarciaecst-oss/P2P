<?php
// Guard against re-definition when file is included multiple times
if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', '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');  // ← replace with new key
}
if (!defined('MAIL_FROM')) {
    define('MAIL_FROM', 'adminskillswap08@gmail.com');
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', defined('APP_NAME') ? APP_NAME : 'Time for Skill');
}

if (!function_exists('sendMail')) {
    function sendMail($toEmail, $toName, $subject, $htmlBody) {

        if (BREVO_API_KEY === 'your-brevo-api-key-here') {
            return 'not_configured';
        }

        $payload = json_encode(array(
            'sender'      => array('name' => MAIL_FROM_NAME, 'email' => MAIL_FROM),
            'to'          => array(array('email' => $toEmail, 'name' => $toName)),
            'subject'     => $subject,
            'htmlContent' => $htmlBody,
            'textContent' => strip_tags(str_replace(array('<br>', '<br/>', '<br />'), "\n", $htmlBody)),
        ));

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => array(
                'accept: application/json',
                'api-key: ' . BREVO_API_KEY,
                'content-type: application/json',
            ),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return 'Network error: ' . $curlErr;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        $body = json_decode($response, true);
        return 'Email error: ' . (isset($body['message']) ? $body['message'] : 'HTTP ' . $httpCode);
    }
}
