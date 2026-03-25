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

// Email configuration
$to      = 'writeonkd@gmail.com';
$subject = "New Contact Form Submission — Capital Plus | $service";

// Build HTML email body
$body = "
<html>
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
</html>
";

// Email headers
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: Capital Plus Website <noreply@capitalplusonline.in>\r\n";
$headers .= "Reply-To: $name <$email>\r\n";

// Send email
$sent = mail($to, $subject, $body, $headers);

if ($sent) {
    echo json_encode(['success' => true, 'message' => 'Message sent successfully! We will get back to you soon.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send message. Please try again later.']);
}
?>
