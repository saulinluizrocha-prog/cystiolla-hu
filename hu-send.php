<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Get and normalize phone and name
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';

    // Smart phone validation on server side (Hungarian rules)
    // Remove all whitespace, dashes, parentheses
    $clean_phone = preg_replace('/[\s\-\(\)]/', '', $phone);
    
    // Normalize prefix
    $rest = '';
    if (strpos($clean_phone, '+36') === 0) {
        $rest = substr($clean_phone, 3);
    } elseif (strpos($clean_phone, '0036') === 0) {
        $rest = substr($clean_phone, 4);
    } elseif (strpos($clean_phone, '36') === 0) {
        $rest = substr($clean_phone, 2);
    } elseif (strpos($clean_phone, '06') === 0) {
        $rest = substr($clean_phone, 2);
    } else {
        $rest = $clean_phone;
    }

    // Must consist only of digits
    $is_digits = preg_match('/^\d+$/', $rest);
    $len = strlen($rest);

    // Hungarian numbers without country code must be exactly 8 or 9 digits
    if (!$is_digits || ($len !== 8 && $len !== 9)) {
        // Redirect back with validation error to reduce friction
        header("Location: /?error=invalid_phone");
        exit();
    }

    // Phone is valid, format it as 36 + rest
    $normalized_phone = '36' . $rest;

    // 2. Capture parameters
    $token = 'YZA0ZJDLZWYTZDK4ZC00YMJJLWJJNJATODZKNGJJMTE2MZQ4';
    $stream_code = '5omgl';

    // Capture gclid and sub1-sub5 from POST (fallback to GET)
    $gclid = !empty($_POST['gclid']) ? $_POST['gclid'] : (isset($_GET['gclid']) ? $_GET['gclid'] : '');
    $sub1 = !empty($_POST['sub1']) ? $_POST['sub1'] : (isset($_GET['sub1']) ? $_GET['sub1'] : '');
    $sub2 = !empty($_POST['sub2']) ? $_POST['sub2'] : (isset($_GET['sub2']) ? $_GET['sub2'] : '');
    $sub3 = !empty($_POST['sub3']) ? $_POST['sub3'] : (isset($_GET['sub3']) ? $_GET['sub3'] : '');
    $sub4 = !empty($_POST['sub4']) ? $_POST['sub4'] : (isset($_GET['sub4']) ? $_GET['sub4'] : '');
    $sub5 = !empty($_POST['sub5']) ? $_POST['sub5'] : (isset($_GET['sub5']) ? $_GET['sub5'] : '');

    // Get client real IP address
    $ip = null;
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // Handle comma-separated proxy IPs
    if ($ip && strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    // 3. Prepare POST fields for dr.cash CPA API
    $post_fields = [
        'stream_code' => $stream_code,
        'client' => [
            'phone' => $normalized_phone,
            'name'  => $name,
            'ip'    => $ip,
            'country' => 'HU'
        ],
        'sub1' => $sub1,
        'sub2' => $sub2,
        'sub3' => $sub3,
        'sub4' => $sub4,
        'sub5' => $sub5
    ];

    // Include gclid in the API payload if supported
    if (!empty($gclid)) {
        $post_fields['gclid'] = $gclid;
        // Fallback: put gclid in sub5 if it was empty, for safety
        if (empty($sub5)) {
            $post_fields['sub5'] = $gclid;
        }
    }

    // 4. Send API request
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://order.drcash.sh/v1/order");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 5. Generate random order ID for the thank you page
    $order_id = rand(1000000, 9999999) . '-ID';

    // 6. Redirect to success page
    // Even if API returns error, we redirect to success to avoid loss of user conversion feeling, 
    // but in a production setup you might want to log failures.
    header("Location: /hu-success.html?order_id=" . urlencode($order_id));
    exit();
} else {
    // If accessed directly, redirect to homepage
    header("Location: /");
    exit();
}
?>
