<?php
// Xendit Configuration
define('XENDIT_SECRET_KEY', 'xnd_development_Sc8hqfGmY7rq1LRKFUjZJO1EWFTIEkz1oFKNjAqHbP68Dj4LPVbtRLAf5aRqO4');
define('XENDIT_PUBLIC_KEY', 'xnd_public_development_dtn8FEyM7EBAuPN2xTJbF7FhOmZv6cF6xxLuJpelCF4dhyhXeFEsU0xmDS_BEI');
define('XENDIT_API_URL', 'https://api.xendit.co');

// Helper function to make Xendit API calls
function xendit_api_call($endpoint, $method = 'POST', $data = []) {
    $url = XENDIT_API_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, XENDIT_SECRET_KEY . ":");
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'status_code' => $httpCode,
        'data' => json_decode($response, true),
        'error' => $error
    ];
}

// Create Xendit Invoice
function create_xendit_invoice($order_id, $customer_name, $customer_email, $amount, $description) {
    $payload = [
        'external_id' => 'order_' . $order_id . '_' . time(),
        'amount' => intval($amount),
        'payer_email' => $customer_email,
        'description' => $description,
        'invoice_duration' => 86400, // 24 hours
        'currency' => 'PHP'
    ];
    
    return xendit_api_call('/v2/invoices', 'POST', $payload);
}

// Get Invoice Status
function get_xendit_invoice($invoice_id) {
    return xendit_api_call('/v2/invoices/' . $invoice_id, 'GET', []);
}

// Create Xendit Invoice by External ID
function get_xendit_invoice_by_external_id($external_id) {
    return xendit_api_call('/v2/invoices?external_id=' . urlencode($external_id), 'GET', []);
}

// Verify Xendit Webhook Signature
function verify_xendit_webhook($body, $signature) {
    $computed_signature = hash_hmac('sha256', $body, XENDIT_SECRET_KEY);
    return hash_equals($computed_signature, $signature);
}
?>