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

// Keep subject ASCII-safe (no special dashes or unicode)
$subject = "New Contact Form Submission - Capital Plus | " . str_replace(array("\r", "\n"), '', $service);

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

// ═══ SMTP DOT-STUFFING ═══
// In SMTP, any line starting with "." must be escaped as ".." to prevent premature termination
function dotStuff($text) {
    $lines = explode("\n", str_replace("\r\n", "\n", $text));
    foreach ($lines as &$line) {
        if (isset($line[0]) && $line[0] === '.') {
            $line = '.' . $line;
        }
    }
    return implode("\r\n", $lines);
}

// ═══ SEND VIA SMTP ═══
function sendSmtpEmail($host, $port, $user, $pass, $from_email, $from_name, $to, $subject, $htmlBody, $replyTo, $replyToName) {

    // Check if fsockopen is available
    if (!function_exists('fsockopen')) {
        return ['success' => false, 'message' => 'Socket functions are disabled on this server.'];
    }

    // Connect to SMTP server on port 25
    $socket = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$socket) {
        // Fallback: Try SSL on port 465
        $socket = @fsockopen("ssl://$host", 465, $errno, $errstr, 10);
        if (!$socket) {
            // Last fallback: Try port 587
            $socket = @fsockopen($host, 587, $errno, $errstr, 10);
            if (!$socket) {
                return ['success' => false, 'message' => "Could not connect to mail server on ports 25, 465, or 587: $errstr ($errno)"];
            }
        }
    }

    // Set stream timeout to prevent hanging (15 seconds)
    stream_set_timeout($socket, 15);

    // Helper to read server response
    $getResponse = function() use ($socket) {
        $response = '';
        $maxLoops = 50; // Safety limit
        $i = 0;
        while ($i < $maxLoops) {
            $line = fgets($socket, 515);
            if ($line === false) break;
            $response .= $line;
            // Last line of multi-line response has space at position 3
            if (isset($line[3]) && $line[3] === ' ') break;
            // Single line response (less than 4 chars)
            if (strlen($line) < 4) break;
            $i++;
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
    $ehloResp = $sendCmd("EHLO capitalplusonline.in");
    if (substr($ehloResp, 0, 3) != '250') {
        // Fallback to HELO if EHLO not supported
        $ehloResp = $sendCmd("HELO capitalplusonline.in");
        if (substr($ehloResp, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'message' => 'EHLO/HELO failed: ' . trim($ehloResp)];
        }
    }

    // Check if STARTTLS is available and upgrade connection
    if (strpos($ehloResp, 'STARTTLS') !== false) {
        $starttlsResp = $sendCmd("STARTTLS");
        if (substr($starttlsResp, 0, 3) == '220') {
            $crypto = defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')
                ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                : STREAM_CRYPTO_METHOD_TLS_CLIENT;
            $tlsResult = @stream_socket_enable_crypto($socket, true, $crypto);
            if ($tlsResult === true) {
                // Re-issue EHLO after TLS upgrade
                $sendCmd("EHLO capitalplusonline.in");
            }
            // If TLS fails, continue without encryption on localhost (acceptable)
        }
    }

    // Try AUTH LOGIN (skip if server doesn't support it)
    $authResp = $sendCmd("AUTH LOGIN");
    if (substr($authResp, 0, 3) == '334') {
        // Server supports auth — proceed with credentials
        $userResp = $sendCmd(base64_encode($user));
        if (substr($userResp, 0, 3) == '334') {
            $passResp = $sendCmd(base64_encode($pass));
            // If auth succeeds (235) great; if not, we'll try sending anyway
        }
    } else {
        // AUTH LOGIN not supported — send RSET to clean up server state
        $sendCmd("RSET");
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

    // MIME-encode subject if it contains non-ASCII characters
    $encodedSubject = $subject;
    if (preg_match('/[^\x20-\x7E]/', $subject)) {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    // Sanitize reply-to name (remove any newlines to prevent header injection)
    $safeReplyToName = str_replace(array("\r", "\n"), '', $replyToName);
    $safeReplyTo = str_replace(array("\r", "\n"), '', $replyTo);

    // Build full email message (headers + body) with proper \r\n line endings
    $emailMessage  = "From: $from_name <$from_email>\r\n";
    $emailMessage .= "To: $to\r\n";
    $emailMessage .= "Reply-To: $safeReplyToName <$safeReplyTo>\r\n";
    $emailMessage .= "Subject: $encodedSubject\r\n";
    $emailMessage .= "MIME-Version: 1.0\r\n";
    $emailMessage .= "Content-Type: text/html; charset=UTF-8\r\n";
    $emailMessage .= "Content-Transfer-Encoding: 7bit\r\n";
    $emailMessage .= "Date: " . date('r') . "\r\n";
    $emailMessage .= "Message-ID: <" . md5(uniqid(time())) . "@capitalplusonline.in>\r\n";
    $emailMessage .= "X-Mailer: CapitalPlus-WebForm/1.0\r\n";
    $emailMessage .= "\r\n";

    // Apply dot-stuffing to body and normalize line endings to \r\n
    $emailMessage .= dotStuff($htmlBody) . "\r\n";

    // Send email body
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
