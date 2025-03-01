<?php
// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load Composer's autoloader for PHPMailer
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    echo '<p style="color: red;">PHPMailer autoload file not found. Please install PHPMailer using Composer.</p>';
    echo '<p>Run this in your project root: <code>composer require phpmailer/phpmailer</code></p>';
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Create an instance of PHPMailer
$mail = new PHPMailer(true); // true enables exceptions

echo '<h1>PHP Mail Test with Brevo (Sendinblue)</h1>';

try {
    //Server settings
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;           // Enable verbose debug output
    $mail->isSMTP();                                 // Send using SMTP
    $mail->Host       = 'smtp-relay.brevo.com';      // Brevo SMTP server
    $mail->SMTPAuth   = true;                        // Enable SMTP authentication

    // YOUR BREVO CREDENTIALS
    $mail->Username   = '86f358001@smtp-brevo.com';  // Your Brevo login
    $mail->Password   = 'xsmtpsib-dbf2505c4dd0615807c129ba7c4f525714b7d758232dc0bb1557b1be6fc67567-1ESGmt9XUIa8McnP'; // Your Brevo SMTP key

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
    $mail->Port       = 587;                         // TCP port for TLS

    //Recipients - UPDATE WITH YOUR ACTUAL EMAIL FOR TESTING
    $mail->setFrom('86f358001@smtp-brevo.com', 'QA Reporter System');
    $mail->addReplyTo('qa@webmaster.ekwa.com', 'QA Reporter System');
    $mail->addAddress('agskanchana@gmail.com');  // Add your email address to receive the test

    // Content
    $mail->isHTML(true);                             // Set email format to HTML
    $mail->Subject = 'Test Email from QA Reporter via Brevo';
    $mail->Body    = '
        <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 600px;">
            <h2 style="color: #4a5568;">QA Reporter Test Email</h2>
            <p>This is a test email sent using <b>Brevo</b> from the QA Reporter system.</p>
            <p>If you received this email, the email configuration is working correctly!</p>
            <hr style="border: 1px solid #edf2f7; margin: 20px 0;">
            <p style="color: #718096; font-size: 14px;">This is an automated message, please do not reply.</p>
        </div>
    ';
    $mail->AltBody = 'This is a test email sent using Brevo from the QA Reporter system.';

    // Send the email
    $mail->send();
    echo '<div style="color: green; padding: 10px; background-color: #e7f7e7; border: 1px solid green; margin: 20px 0;">
            Message has been sent successfully!
          </div>';

    // Display configuration details
    echo '<h2>Configuration Used:</h2>';
    echo '<ul>';
    echo '<li>SMTP Server: ' . htmlspecialchars($mail->Host) . '</li>';
    echo '<li>Port: ' . $mail->Port . '</li>';
    echo '<li>Security: ' . ($mail->SMTPSecure ?: 'None') . '</li>';
    echo '<li>From: ' . htmlspecialchars($mail->From) . '</li>';
    echo '<li>To: ' . htmlspecialchars($mail->getToAddresses()[0][0]) . '</li>';
    echo '</ul>';

} catch (Exception $e) {
    echo '<div style="color: red; padding: 10px; background-color: #f7e7e7; border: 1px solid red; margin: 20px 0;">
            Message could not be sent. Mailer Error: ' . $mail->ErrorInfo . '
          </div>';

    // Troubleshooting tips for Brevo
    echo '<h2>Brevo SMTP Troubleshooting Tips:</h2>';
    echo '<ol>';
    echo '<li><strong>Check your SMTP Key:</strong> Make sure it\'s correctly copied with no extra spaces.</li>';
    echo '<li><strong>Check sending domain:</strong> The "from" email domain might need to be verified in Brevo.</li>';
    echo '<li><strong>Try SSL instead of TLS:</strong> Some servers work better with SSL:
        <pre>
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use SSL encryption instead
$mail->Port = 465; // Use SSL port
        </pre>
    </li>';
    echo '<li><strong>Check your daily limit:</strong> Free plan has a limit of 300 emails per day.</li>';
    echo '</ol>';
}

echo '<h2>Next Steps After Testing</h2>';
echo '<ol>';
echo '<li>Create an email_functions.php file with a reusable sendEmail() function</li>';
echo '<li>Store your SMTP credentials in a secure config file</li>';
echo '<li>Implement email functionality in your application</li>';
echo '</ol>';

// Show Brevo info
echo '<h2>About Brevo (formerly Sendinblue)</h2>';
echo '<ul>';
echo '<li>Free tier: 300 emails per day</li>';
echo '<li>Good deliverability rates</li>';
echo '<li>Provides email tracking and analytics</li>';
echo '<li><a href="https://app.brevo.com/settings/keys/smtp" target="_blank">Get your SMTP credentials here</a> (requires account)</li>';
echo '</ul>';

// Show PHPMailer version
echo '<h2>PHPMailer Version</h2>';
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo 'PHPMailer version: ' . PHPMailer::VERSION;
} else {
    echo 'PHPMailer class not found.';
}
?>