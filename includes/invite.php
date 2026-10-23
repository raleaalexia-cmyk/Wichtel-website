<?php
// Enable PHP error messages when needed.
/*
error_reporting(E_ALL);
ini_set('display_errors', 1);
*/

// Start the session.
session_start();

// Store the form cookie names.
$cookieFields = [
        "DATA_firstname",
        "DATA_lastname",
        "DATA_email"
];

// Check if logout was clicked.
if (isset($_POST["BUTTON_logout"])) {
    // Delete every form cookie.
    foreach ($cookieFields as $field) {
        // Expire the cookie by setting a time in the past.
        setcookie($field, "", time() - 3600, "/");
        // Remove it from this request too, so it's gone right away.
        unset($_COOKIE[$field]);
    }

    // Delete the session.
    session_destroy();

    // Return to login.
    header("Location: index.php?page=login");
    // Stop the script.
    exit();
}

// Check login and submitted fields.
// isset() with several arguments only returns true if ALL of them are set.
if (isset(
        $_SESSION["HeadElf"],
        $_POST["DATA_firstname"],
        $_POST["DATA_lastname"],
        $_POST["DATA_email"],
        $_POST["BUTTON_invite"]
)) {
    // Save each input for one day.
    foreach ($cookieFields as $field) {
        // Create the cookie.
        setcookie(
                $field,
                $_POST[$field],
                time() + 86400,
                "/"
        );

        // Make it available immediately.
        // (setcookie() only takes effect on the next request, so we set it manually too.)
        $_COOKIE[$field] = $_POST[$field];
    }

    // Store the submitted values.
    $firstname = trim((string)$_POST["DATA_firstname"]);
    $lastname = trim((string)$_POST["DATA_lastname"]);
    $email = trim((string)$_POST["DATA_email"]);

    // Check required fields before creating the invitation.
    if ($firstname === "" || $lastname === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a first name, last name and a valid email address.";
    } else {
        // Connect to the database.
        $dbc = f_connect(false);

        if (!$dbc) {
            $error = "Database connection failed. Please check the server configuration.";
        } else {
            // Escape values for SQL.
            $firstnameDB = mysqli_real_escape_string($dbc, $firstname);
            $lastnameDB = mysqli_real_escape_string($dbc, $lastname);
            $emailDB = mysqli_real_escape_string($dbc, $email);

            // Search for an existing Elf.
            $query = "SELECT pk_elf
                      FROM Elf
                      WHERE email = '$emailDB'";

            // Prepare the query.
            $statement = mysqli_prepare($dbc, $query);

            // Run the query.
            mysqli_stmt_execute($statement);

            // Get the result.
            $result = mysqli_stmt_get_result($statement);

            // Check if the Elf exists.
            if (mysqli_num_rows($result) > 0) {
                // Read the existing Elf.
                $row = mysqli_fetch_assoc($result);
                // Store the Elf ID.
                $elfId = (int)$row["pk_elf"];
            } else {
                // Insert a new Elf.
                $query = "INSERT INTO Elf
                          (firstName, lastName, email)
                          VALUES
                          ('$firstnameDB', '$lastnameDB', '$emailDB')";

                // Prepare the insert.
                $statement = mysqli_prepare($dbc, $query);

                // Run the insert.
                mysqli_stmt_execute($statement);

                // Get the new Elf ID.
                $elfId = (int)mysqli_insert_id($dbc);
            }

            // Free the result.
            mysqli_free_result($result);
            // Close the statement.
            mysqli_stmt_close($statement);

            // Get the Head Elf password.
            $actionPassword = $_SESSION["HeadElf"];

            // Escape the password for SQL.
            $actionPasswordDB = mysqli_real_escape_string($dbc, (string)$actionPassword);

            // Get the action details.
            // The action is found using the Head Elf's password, which is stored in the session.
            $query = "SELECT pk_action, targetValue, firstName, lastName, startDate, endDate
                      FROM Action
                      WHERE actionPassword = '$actionPasswordDB'";

            // Prepare the query.
            $statement = mysqli_prepare($dbc, $query);

            // Run the query.
            mysqli_stmt_execute($statement);

            // Get the result.
            $result = mysqli_stmt_get_result($statement);

            // Read the action details.
            $row = mysqli_fetch_assoc($result);

            if (!$row) {
                $error = "Action not found. Please log in again.";
                mysqli_free_result($result);
                mysqli_stmt_close($statement);
                mysqli_close($dbc);
            } else {
                // Store the action information.
                $actionId = (int)$row["pk_action"];
                $targetValue = $row["targetValue"];
                $hostFirstname = $row["firstName"];
                $hostLastname = $row["lastName"];
                $startDate = $row["startDate"];
                $endDate = $row["endDate"];

                // Free the result and close the action query.
                mysqli_free_result($result);
                mysqli_stmt_close($statement);

                // Check if the Elf was invited before.
                $query = "SELECT *
                          FROM Invite
                          WHERE pkfk_action = $actionId
                          AND pkfk_elf = $elfId";

                // Prepare the query.
                $statement = mysqli_prepare($dbc, $query);

                // Run the query.
                mysqli_stmt_execute($statement);

                // Get the result.
                $result = mysqli_stmt_get_result($statement);

                // Check if the invitation exists.
                if (mysqli_num_rows($result) > 0) {
                    // Reset the invitation to Invited.
                    // This happens if the same Elf gets invited again to the same action.
                    $query = "UPDATE Invite
                              SET status = 'Invited'
                              WHERE pkfk_action = $actionId
                              AND pkfk_elf = $elfId";
                } else {
                    // Create a new invitation.
                    $query = "INSERT INTO Invite
                              (pkfk_action, pkfk_elf, status)
                              VALUES
                              ($actionId, $elfId, 'Invited')";
                }

                // Free the result and close the lookup statement.
                mysqli_free_result($result);
                mysqli_stmt_close($statement);

                // Prepare the invitation query.
                $statement = mysqli_prepare($dbc, $query);

                // Run the invitation query.
                mysqli_stmt_execute($statement);

                // Close the statement.
                mysqli_stmt_close($statement);

                // Close the database.
                mysqli_close($dbc);

                // Build links that point to the current installation of the website.
                $basePath = rtrim(dirname($_SERVER["SCRIPT_NAME"] ?? "/index.php"), "/\\");
                $host = $_SERVER["HTTP_HOST"] ?? "";
                $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https://" : "http://";
                $baseUrl = $host !== "" ? $scheme . $host . $basePath : "";
                $agreeLink = $baseUrl . "/index.php?page=agree&action=$actionId&elf=$elfId";
                $disagreeLink = $baseUrl . "/index.php?page=disagree&action=$actionId&elf=$elfId";

                // Escape values inserted in the email HTML.
                $safeFirstname = htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8');
                $safeHostFirstname = htmlspecialchars($hostFirstname, ENT_QUOTES, 'UTF-8');
                $safeHostLastname = htmlspecialchars($hostLastname, ENT_QUOTES, 'UTF-8');
                $safeTargetValue = htmlspecialchars((string)$targetValue, ENT_QUOTES, 'UTF-8');
                $safeStartDate = htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8');
                $safeEndDate = htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8');
                $safeAgreeLink = htmlspecialchars($agreeLink, ENT_QUOTES, 'UTF-8');
                $safeDisagreeLink = htmlspecialchars($disagreeLink, ENT_QUOTES, 'UTF-8');

                // Keep image assets on the original image host until these assets are deployed with the PHP site.
                $agreeImage = "https://desde661.foxi.lu/project/WIchtel-Website_1/img/agreebutton.png";
                $disagreeImage = "https://desde661.foxi.lu/project/WIchtel-Website_1/img/disagreebutton.png";

                // Send the invitation email.
                $emailResult = emailSent(
                        "Wichtel",
                        "
                    <h2>Welcome to Wichteln</h2>
                    <p>
                        Hello " . $safeFirstname . ",
                        would you like to join the Wichtel action?
                    </p>
                    <p>
                        🎄 <strong>Hosted by:</strong> " . $safeHostFirstname . " " . $safeHostLastname . "<br>
                        📅 <strong>Start:</strong> " . $safeStartDate . "<br>
                        📅 <strong>End:</strong> " . $safeEndDate . "<br>
                        🎁 <strong>Target Value:</strong> " . $safeTargetValue . " €
                    </p>
                    <p>
                        <a href='" . $safeAgreeLink . "'>
                            <img src='" . htmlspecialchars($agreeImage, ENT_QUOTES, 'UTF-8') . "' alt='agree' style='width:80px;'>
                        </a>
                        <a href='" . $safeDisagreeLink . "'>
                            <img src='" . htmlspecialchars($disagreeImage, ENT_QUOTES, 'UTF-8') . "' alt='disagree' style='width:80px;'>
                        </a>
                    </p>
                    ",
                        $email
                );

                if ($emailResult !== true) {
                    $error = "The invitation was saved, but the email could not be sent. Please check the SMTP settings.";
                } else {
                    // Delete cookies after inviting.
                    foreach ($cookieFields as $field) {
                        // Expire the cookie.
                        setcookie($field, "", time() - 3600, "/");
                        // Remove it from this request.
                        unset($_COOKIE[$field]);
                    }

                    // Reload with empty inputs.
                    // This avoids the form being resubmitted if the user refreshes the page.
                    header("Location: index.php?page=invite");
                    exit();
                }
            }
        }
    }
}
?>
<body class="promo-body">
<?php
// Check if Head Elf is logged in.
if (isset($_SESSION["HeadElf"])) {
    ?>
    <!-- Logout form. -->
    <form method="POST"
          action="index.php?page=invite"
          style="position: fixed; top: 15px; right: 15px;">
        <!-- Logout button. -->
        <input type="submit"
               name="BUTTON_logout"
               value="Logout"
               style="
                    background-color: #a23a2e;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: bold;
                    cursor: pointer;
               ">
    </form>

    <!-- Main invitation box. -->
    <div class="promo-box">
        <!-- Page title. -->
        <h2>🎅 Invite Someone 🎅</h2>

        <?php if (isset($error)) { ?>
            <p style="color:red;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php } ?>

        <!-- Invitation form. -->
        <form method="POST" action="">
            <!-- First name field. -->
            <div class="promo-group">
                <label for="id_firstname">
                    First Name:
                </label>
                <input id="id_firstname"
                       name="DATA_firstname"
                       type="text"
                       value="<?php echo cookieVal("DATA_firstname"); ?>"
                       required>
            </div>

            <!-- Last name field. -->
            <div class="promo-group">
                <label for="id_lastname">
                    Last Name:
                </label>
                <input id="id_lastname"
                       name="DATA_lastname"
                       type="text"
                       value="<?php echo cookieVal("DATA_lastname"); ?>"
                       required>
            </div>

            <!-- Email field. -->
            <div class="promo-group">
                <label for="id_email">
                    Email:
                </label>
                <input id="id_email"
                       name="DATA_email"
                       type="email"
                       value="<?php echo cookieVal("DATA_email"); ?>"
                       required>
            </div>

            <!-- Send the invitation. -->
            <input type="submit"
                   name="BUTTON_invite"
                   value="Invite"
                   class="promo-submit">
        </form>

        <!-- Link to the draw page. -->
        <p>
            <a href="index.php?page=draw">
                Go to Draw
            </a>
        </p>

        <!-- Page footer. -->
        <footer id="footerindex">
            © Wichtel-Website
        </footer>
    </div>
    <?php
} else {
    ?>
    <!-- Login required box. -->
    <div class="promo-box">
        <!-- Page title. -->
        <h2>🎅 Invite Someone 🎅</h2>
        <hr>
        <!-- Login warning. -->
        <p>Please log in first.</p>
    </div>
    <?php
}
?>
</body>
