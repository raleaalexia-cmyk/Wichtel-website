<?php
// Import PHPMailer.
use PHPMailer\PHPMailer\PHPMailer;

// Connect to the database.
function f_connect($debugging)
{
    // Load database credentials.
    require_once __DIR__ . "/../credentials/credentials.php";
    // Create the database connection.
    $dbc = mysqli_connect(DB_HOST, DB_USER, DB_PW, DB_NAME);
    // Return a safe error if the configured connection fails.
    if (!$dbc) {
        error_log('Wichtel database connection failed: ' . mysqli_connect_error());
        return false;
    }
    // Set UTF-8 for data exchanged with MySQL.
    mysqli_set_charset($dbc, 'utf8mb4');
    // Show a message when debugging is enabled.
    if ($debugging) {
        echo "Connected to db : " . DB_NAME . "<br>";
    }
    // Return the connection.
    return $dbc;
}

// Check if both emails match.
function testCorrectEmail($pEmail, $pConfirmEmail): bool
{
    return $pEmail === $pConfirmEmail;
}

// Send an email with PHPMailer.
function emailSent($subject, $body, $email)
{
    // Create the mail object.
    $mail = new PHPMailer(true);
    // Use SMTP.
    $mail->isSMTP();
    // SMTP server and credentials come from the PHP host's environment settings.
    $mail->Host = getenv('WICHTEL_SMTP_HOST') ?: 'smtp.gmail.com';
    // Enable SMTP login.
    $mail->SMTPAuth = true;
    // Keep credentials out of the source repository.
    $mail->Username = getenv('WICHTEL_SMTP_USERNAME') ?: '';
    $mail->Password = getenv('WICHTEL_SMTP_PASSWORD') ?: '';
    if ($mail->Username === '' || $mail->Password === '') {
        error_log('Wichtel SMTP is not configured.');
        return 'Email delivery is not configured on this server.';
    }
    // Use secure encryption.
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    // Use SMTP port 465.
    $mail->Port = 465;
    // Support special characters.
    $mail->CharSet = 'UTF-8';
    // Set the sender.
    $mail->setFrom(getenv('WICHTEL_SMTP_FROM') ?: $mail->Username, 'Wichtel');
    // Add the receiver.
    $mail->addAddress($email);
    // Allow HTML content.
    $mail->isHTML(true);
    // Set the subject.
    $mail->Subject = $subject;
    // Set the email body.
    $mail->Body = $body;
    // Send the email.
    try {
        if ($mail->send()) {
            return true;
        }
    } catch (\Throwable $exception) {
        error_log('Wichtel email sending failed: ' . $exception->getMessage());
        return "Email could not be sent.";
    }
    // Return a generic sending error.
    error_log("Wichtel email sending failed: " . $mail->ErrorInfo);
    return "Email could not be sent.";
}

// Run an SQL query.
function InsertToDB($dbc, $query)
{
    // Execute the query.
    $result = mysqli_query($dbc, $query);
    // Stop if the query fails.
    if (!$result) {
        error_log("MySQL Error: " . mysqli_error($dbc));
        die("A database query failed. Please check the server logs.");
    }
    // Return the query result.
    return $result;
}

// Delete the login session and cookie.
function destroyLogin()
{
    // Remove session values.
    session_unset();
    // End the session.
    session_destroy();
    // Delete the email cookie.
    setcookie("login_email", "", time() - 3600, "/");
}

// Get a cookie value safely.
function cookieVal($cookieName)
{
    // Check if the cookie exists.
    if (isset($_COOKIE[$cookieName])) {
        // Return the protected value.
        return htmlspecialchars($_COOKIE[$cookieName], ENT_QUOTES, 'UTF-8');
    }
    // Return empty text if missing.
    return "";
}

// Save a cookie for seven days.
function saveCookie($cookieName, $cookieValue)
{
    setcookie(
        $cookieName, // Cookie name.
        $cookieValue, // Cookie value.
        time() + (7 * 24 * 60 * 60), // Seven-day lifetime.
        "/" // Available everywhere.
    );
}

// Delete a cookie.
function deleteCookie($cookieName)
{
    setcookie(
        $cookieName, // Cookie name.
        "", // Empty value.
        time() - 3600, // Expired time.
        "/" // Entire website.
    );
    // Remove it from the current request.
    unset($_COOKIE[$cookieName]);
}
?>