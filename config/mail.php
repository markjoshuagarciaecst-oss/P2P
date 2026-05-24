<?php
// ─────────────────────────────────────────────────────────────────────────────
// Email configuration — using Brevo (free, 300 emails/day)
//
// Setup (takes 2 minutes):
//   1. Go to https://www.brevo.com and create a free account
//   2. Go to Settings → API Keys → Create a new API key
//   3. Paste the key below
//   4. Set MAIL_FROM to any email you verified in Brevo
//      (your own email works — just verify it in Brevo → Senders & Domains)
// ─────────────────────────────────────────────────────────────────────────────

define('BREVO_API_KEY', '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');   // ← paste your Brevo API key
define('MAIL_FROM',     'adminskillswap08@gmail.com');     // ← your verified sender email
define('MAIL_FROM_NAME', defined('APP_NAME') ? APP_NAME : 'Time for Skill');

/**
 * Send an HTML email via Brevo API.
 * Returns true on success, error string on failure.
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool|string {

    if (BREVO_API_KEY === 'your-brevo-api-key-here') {
        return 'not_configured';
    }

    $payload = json_encode([
        'sender'     => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM],
        'to'         => [['email' => $toEmail, 'name' => $toName]],
        'subject'    => $subject,
        'htmlContent'=> $htmlBody,
        'textContent'=> strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)),
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return "Network error: $curlErr";
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    }

    $body = json_decode($response, true);
    return 'Email error: ' . ($body['message'] ?? "HTTP $httpCode");
}
