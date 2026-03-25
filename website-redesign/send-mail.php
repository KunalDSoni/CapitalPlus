<?php
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Honeypot spam check
if (!empty($_POST['_honey'])) {
    echo json_encode(['success' => true, 'message' => 'Message sent successfully!']);
    exit;
}

// Collect and sanitize form data
$name    = htmlspecialchars(trim($_POST['name'] ?? ''));
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone   = htmlspecialchars(trim($_POST['phone'] ?? ''));
$company = htmlspecialchars(trim($_POST['company'] ?? ''));
$service = htmlspecialchars(trim($_POST['service'] ?? ''));
$message = htmlspecialchars(trim($_POST['message'] ?? ''));

// Validate required fields
if (empty($name) || empty($email) || empty($phone) || empty($company) || empty($service) || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// ═══ SMTP CONFIGURATION ═══
$smtp_host = 'localhost';
$smtp_port = 25;
$smtp_user = 'noreply@capitalplusonline.in';
$smtp_pass = '6UPBZh%4qP!Au8#f';
$from_email = 'noreply@capitalplusonline.in';
$from_name = 'Capital Plus Website';
$to_email = 'writeonkd@gmail.com';

$subject = "New Contact Form Submission - Capital Plus | $service";

// Build HTML email body
$body = "<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; color: #333; }
    table { border-collapse: collapse; width: 100%; max-width: 600px; }
    th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #eee; }
    th { background: #7C3AED; color: #fff; width: 140px; }
    td { background: #f9f9f9; }
    .header { background: #7C3AED; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
    .header h2 { margin: 0; }
    .footer { padding: 16px; text-align: center; font-size: 12px; color: #999; }
  </style>
</head>
<body>
  <div class='header'>
    <h2>New Contact Form Submission</h2>
  </div>
  <table>
    <tr><th>Name</th><td>$name</td></tr>
    <tr><th>Email</th><td><a href='mailto:$email'>$email</a></td></tr>
    <tr><th>Phone</th><td>$phone</td></tr>
    <tr><th>Company</th><td>$company</td></tr>
    <tr><th>Service</th><td>$service</td></tr>
    <tr><th>Message</th><td>$message</td></tr>
  </table>
  <div class='footer'>
    <p>This email was sent from the Capital Plus website contact form.</p>
  </div>
</body>
</html>";

// ═══ SEND VIA SMTP ═══
function sendSmtpEmail($host, $port, $user, $pass, $from_email, $from_name, $to, $subject, $htmlBody, $replyTo, $replyToName) {

    // Connect to SMTP server on port 25
    $socket = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$socket) {
        // Fallback: Try SSL on port 465
        $socket = @fsockopen("ssl://$host", 465, $errno, $errstr, 10);
        if (!$socket) {
            return ['success' => false, 'message' => "Could not connect to mail server: $errstr ($errno)"];
        }
    }

    // Helper to read server response
    $getResponse = function() use ($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
    };

    // Helper to send command and get response
    $sendCmd = function($cmd) use ($socket, $getResponse) {
        fputs($socket, $cmd . "\r\n");
        return $getResponse();
    };

    // Read server greeting
    $greeting = $getResponse();
    if (substr($greeting, 0, 3) != '220') {
        fclose($socket);
        return ['success' => false, 'message' => 'Mail server not ready: ' . trim($greeting)];
    }

    // EHLO
    $response = $sendCmd("EHLO capitalplusonline.in");

    // Check if STARTTLS is available and upgrade connection
    if (strpos($response, 'STARTTLS') !== false) {
        $starttlsResp = $sendCmd("STARTTLS");
        if (substr($starttlsResp, 0, 3) == '220') {
            // Try TLS 1.2 first, fallback to generic TLS
            $crypto = defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')
                ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                : STREAM_CRYPTO_METHOD_TLS_CLIENT;
            stream_socket_enable_crypto($socket, true, $crypto);
            $sendCmd("EHLO capitalplusonline.in");
        }
    }

    // AUTH LOGIN
    $authResp = $sendCmd("AUTH LOGIN");
    if (substr($authResp, 0, 3) != '334') {
        fclose($socket);
        return ['success' => false, 'message' => 'Server does not support AUTH LOGIN.'];
    }

    $userResp = $sendCmd(base64_encode($user));
    if (substr($userResp, 0, 3) != '334') {
        fclose($socket);
        return ['success' => false, 'message' => 'SMTP authentication failed (username rejected).'];
    }

    $passResp = $sendCmd(base64_encode($pass));
    if (substr($passResp, 0, 3) != '235') {
        fclose($socket);
        return ['success' => false, 'message' => 'SMTP authentication failed (wrong password).'];
    }

    // MAIL FROM
    $response = $sendCmd("MAIL FROM:<$from_email>");
    if (substr($response, 0, 3) != '250') {
        fclose($socket);
        return ['success' => false, 'message' => 'Sender rejected: ' . trim($response)];
    }

    // RCPT TO
    $response = $sendCmd("RCPT TO:<$to>");
    if (substr($response, 0, 3) != '250') {
        fclose($socket);
        return ['success' => false, 'message' => 'Recipient rejected: ' . trim($response)];
    }

    // DATA - server should respond with 354
    $dataResp = $sendCmd("DATA");
    if (substr($dataResp, 0, 3) != '354') {
        fclose($socket);
        return ['success' => false, 'message' => 'Server rejected DATA command: ' . trim($dataResp)];
    }

    // Build full email message (headers + body)
    $emailMessage  = "From: $from_name <$from_email>\r\n";
    $emailMessage .= "To: $to\r\n";
    $emailMessage .= "Reply-To: $replyToName <$replyTo>\r\n";
    $emailMessage .= "Subject: $subject\r\n";
    $emailMessage .= "MIME-Version: 1.0\r\n";
    $emailMessage .= "Content-Type: text/html; charset=UTF-8\r\n";
    $emailMessage .= "Date: " . date('r') . "\r\n";
    $emailMessage .= "Message-ID: <" . md5(uniqid(time())) . "@capitalplusonline.in>\r\n";
    $emailMessage .= "\r\n";
    $emailMessage .= $htmlBody . "\r\n";

    // Send email body (write directly, don't use sendCmd for the body)
    fputs($socket, $emailMessage);

    // Send termination: a line with just a period
    $response = $sendCmd(".");

    // QUIT
    $sendCmd("QUIT");
    fclose($socket);

    if (substr($response, 0, 3) == '250') {
        return ['success' => true, 'message' => 'Message sent successfully! We will get back to you soon.'];
    } else {
        return ['success' => false, 'message' => 'Failed to send message. Server response: ' . trim($response)];
    }
}

$result = sendSmtpEmail($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $from_email, $from_name, $to_email, $subject, $body, $email, $name);

if ($result['success']) {
    echo json_encode($result);
} else {
    http_response_code(500);
    echo json_encode($result);
}
?>
