<?php
// config/mail.php — High-Performance Native Gmail SMTP Mailer (Zero Composer Dependencies)

define('SMTP_HOST', 'ssl://smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'firegamingv8@gmail.com');
define('SMTP_PASS', 'qiaggblehhhnnefg');
define('SMTP_FROM_EMAIL', 'firegamingv8@gmail.com');
define('SMTP_FROM_NAME', 'SafeExam.AI Security');

/**
 * Sends an email directly via Google Gmail SMTP over secure SSL socket
 */
function sendSmtpEmail($toEmail, $toName, $subject, $htmlBody) {
    $timeout = 15;
    $socket = @stream_socket_client(SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return ['success' => false, 'error' => "SMTP Connection failed: $errstr ($errno)"];
    }

    $read = function() use ($socket) {
        $response = '';
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) === ' ') break;
        }
        return $response;
    };

    $send = function($cmd) use ($socket, $read) {
        fputs($socket, $cmd . "\r\n");
        return $read();
    };

    // 1. Initial Greeting
    $greet = $read();
    if (substr($greet, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'error' => "Server error: $greet"];
    }

    // 2. Handshake
    $ehlo = $send("EHLO safeexam.local");
    if (substr($ehlo, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => "EHLO rejected: $ehlo"];
    }

    // 3. Authenticate
    $auth = $send("AUTH LOGIN");
    if (substr($auth, 0, 3) !== '334') {
        fclose($socket);
        return ['success' => false, 'error' => "AUTH LOGIN rejected: $auth"];
    }

    $usr = $send(base64_encode(SMTP_USER));
    $pwd = $send(base64_encode(SMTP_PASS));
    if (substr($pwd, 0, 3) !== '235') {
        fclose($socket);
        return ['success' => false, 'error' => "Authentication failed: $pwd"];
    }

    // 4. Envelope
    $mailFrom = $send("MAIL FROM:<" . SMTP_FROM_EMAIL . ">");
    $rcptTo = $send("RCPT TO:<" . trim($toEmail) . ">");
    if (substr($rcptTo, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => "Recipient rejected: $rcptTo"];
    }

    // 5. Data Transmission
    $dataCmd = $send("DATA");
    if (substr($dataCmd, 0, 3) !== '354') {
        fclose($socket);
        return ['success' => false, 'error' => "DATA command rejected: $dataCmd"];
    }

    // Build MIME Headers & Body
    $boundary = "safeexam_bnd_" . md5(time());
    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $encodedFromName = "=?UTF-8?B?" . base64_encode(SMTP_FROM_NAME) . "?=";

    $headers = [
        "From: $encodedFromName <" . SMTP_FROM_EMAIL . ">",
        "To: <$toEmail>",
        "Subject: $encodedSubject",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"$boundary\"",
        "Date: " . date('r'),
        "Message-ID: <" . time() . "." . uniqid() . "@gmail.com>",
        "X-Mailer: SafeExam.AI Engine"
    ];

    $plainText = strip_tags(str_replace(['<br>', '<br/>', '<p>'], "\n", $htmlBody));

    $messageContent = implode("\r\n", $headers) . "\r\n\r\n";
    $messageContent .= "--$boundary\r\n";
    $messageContent .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $messageContent .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $messageContent .= chunk_split(base64_encode($plainText)) . "\r\n";
    $messageContent .= "--$boundary\r\n";
    $messageContent .= "Content-Type: text/html; charset=UTF-8\r\n";
    $messageContent .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $messageContent .= chunk_split(base64_encode($htmlBody)) . "\r\n";
    $messageContent .= "--$boundary--\r\n";
    $messageContent .= ".";

    $sendResult = $send($messageContent);
    $send("QUIT");
    fclose($socket);

    if (substr($sendResult, 0, 3) === '250') {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => "Data transmission failed: $sendResult"];
    }
}

/**
 * Send stylized verification OTP email
 */
function sendOtpEmail($toEmail, $toName, $otp, $purpose = 'Registration') {
    $subject = "[$otp] Your SafeExam.AI $purpose Verification Code";
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="utf-8">
      <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b; }
        .card { max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 20px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .header { background: #0f172a; padding: 28px 24px; text-align: center; }
        .logo { font-size: 22px; font-weight: 900; color: #ffffff; letter-spacing: -0.5px; }
        .logo-accent { color: #f43f5e; }
        .body { padding: 32px 28px; }
        .greeting { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 12px; }
        .text { font-size: 14px; line-height: 1.6; color: #64748b; margin-bottom: 24px; }
        .otp-container { text-align: center; background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 16px; padding: 20px; margin: 24px 0; }
        .otp-label { font-size: 11px; text-transform: uppercase; font-weight: 800; letter-spacing: 1.5px; color: #94a3b8; margin-bottom: 8px; }
        .otp-code { font-size: 34px; font-weight: 900; color: #0f172a; letter-spacing: 8px; font-family: monospace; }
        .notice { font-size: 12px; color: #94a3b8; text-align: center; line-height: 1.5; margin-top: 24px; }
        .footer { background: #f8fafc; padding: 18px 24px; text-align: center; border-top: 1px solid #f1f5f9; font-size: 11px; color: #94a3b8; }
      </style>
    </head>
    <body>
      <div class="card">
        <div class="header">
          <div class="logo">SExam<span class="logo-accent">.AI</span></div>
          <div style="font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; font-weight: 700; margin-top: 4px;">Secure AI Assessment Platform</div>
        </div>
        <div class="body">
          <div class="greeting">Hello, ' . htmlspecialchars($toName ?: 'Candidate') . '!</div>
          <p class="text">
            Use the verification code below to authorize your <strong>' . htmlspecialchars($purpose) . '</strong> on the SafeExam.AI platform.
          </p>
          <div class="otp-container">
            <div class="otp-label">One-Time Verification Code</div>
            <div class="otp-code">' . htmlspecialchars($otp) . '</div>
          </div>
          <p class="notice">
            ⏰ This code will expire in <strong>10 minutes</strong>.<br>
            If you did not request this verification, you can safely ignore this email.
          </p>
        </div>
        <div class="footer">
          SafeExam.AI &bull; Automated Identity Verification System &bull; All Rights Reserved.
        </div>
      </div>
    </body>
    </html>
    ';

    return sendSmtpEmail($toEmail, $toName, $subject, $html);
}

