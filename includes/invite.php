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
if (isset(
        $_SESSION["HeadElf"],
        $_POST["DATA_firstname"],
        $_POST["DATA_lastname"],
        $_POST["DATA_email"],
        $_POST["BUTTON_invite"]
)) {
    // Save each input for one day.
    foreach ($cookieFields as $field) {
        setcookie($field, $_POST[$field], time() + 86400, "/");
        $_COOKIE[$field] = $_POST[$field];
    }

    // Store the submitted values.
    $firstname = trim((string)$_POST["DATA_firstname"]);
    $lastname = trim((string)$_POST["DATA_lastname"]);
    $email = trim((string)$_POST["DATA_email"]);

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
            $query = "SELECT pk_elf FROM Elf WHERE email = '$emailDB'";
            $statement = mysqli_prepare($dbc, $query);
            mysqli_stmt_execute($statement);
            $result = mysqli_stmt_get_result($statement);

            // Check if the Elf exists.
            if (mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $elfId = (int)$row["pk_elf"];
                mysqli_free_result($result);
                mysqli_stmt_close($statement);
            } else {
                mysqli_free_result($result);
                mysqli_stmt_close($statement);

                // Insert a new Elf.
                $query = "INSERT INTO Elf (firstName, lastName, email) VALUES ('$firstnameDB', '$lastnameDB', '$emailDB')";
                $statement = mysqli_prepare($dbc, $query);
                mysqli_stmt_execute($statement);
                $elfId = (int)mysqli_insert_id($dbc);
                mysqli_stmt_close($statement);
            }

            // Get the Head Elf password from the session.
            $actionPasswordDB = mysqli_real_escape_string($dbc, (string)$_SESSION["HeadElf"]);

            // Get the action details.
            $query = "SELECT pk_action, targetValue, firstName, lastName, startDate, endDate
                      FROM Action
                      WHERE actionPassword = '$actionPasswordDB'";
            $statement = mysqli_prepare($dbc, $query);
            mysqli_stmt_execute($statement);
            $result = mysqli_stmt_get_result($statement);
            $row = mysqli_fetch_assoc($result);
            mysqli_free_result($result);
            mysqli_stmt_close($statement);

            if (!$row) {
                $error = "Action not found. Please log in again.";
            } else {
                $actionId = (int)$row["pk_action"];
                $targetValue = $row["targetValue"];
                $hostFirstname = $row["firstName"];
                $hostLastname = $row["lastName"];
                $startDate = $row["startDate"];
                $endDate = $row["endDate"];

                // Check if the Elf was invited before.
                $statement = mysqli_prepare($dbc, "SELECT pkfk_action FROM Invite WHERE pkfk_action = ? AND pkfk_elf = ?");
                mysqli_stmt_bind_param($statement, "ii", $actionId, $elfId);
                mysqli_stmt_execute($statement);
                $result = mysqli_stmt_get_result($statement);

                if (mysqli_num_rows($result) > 0) {
                    // Reset an existing invitation to the correct waiting status.
                    $statementUpdate = mysqli_prepare($dbc, "UPDATE Invite SET status = 'Invited' WHERE pkfk_action = ? AND pkfk_elf = ?");
                    mysqli_stmt_bind_param($statementUpdate, "ii", $actionId, $elfId);
                    mysqli_stmt_execute($statementUpdate);
                    mysqli_stmt_close($statementUpdate);
                } else {
                    // Create a new invitation.
                    $statementInsert = mysqli_prepare($dbc, "INSERT INTO Invite (pkfk_action, pkfk_elf, status) VALUES (?, ?, 'Invited')");
                    mysqli_stmt_bind_param($statementInsert, "ii", $actionId, $elfId);
                    mysqli_stmt_execute($statementInsert);
                    mysqli_stmt_close($statementInsert);
                }

                mysqli_free_result($result);
                mysqli_stmt_close($statement);

                // Close the database connection.
                mysqli_close($dbc);

                // Build accept/decline links for the host this app is running on.
                $basePath = rtrim(dirname($_SERVER["SCRIPT_NAME"] ?? "/index.php"), "/\\");
                $baseUrl = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off" ? "https://" : "http://")
                    . ($_SERVER["HTTP_HOST"] ?? "localhost")
                    . $basePath;
                $agreeLink = $baseUrl . "/index.php?page=agree&action=$actionId&elf=$elfId";
                $disagreeLink = $baseUrl . "/index.php?page=disagree&action=$actionId&elf=$elfId";
                $agreeImage = $baseUrl . "/img/agreebutton.png";
                $disagreeImage = $baseUrl . "/img/disagreebutton.png";

                // Escape values placed in the HTML email.
                $safeFirstname = htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8');
                $safeHostFirstname = htmlspecialchars($hostFirstname, ENT_QUOTES, 'UTF-8');
                $safeHostLastname = htmlspecialchars($hostLastname, ENT_QUOTES, 'UTF-8');
                $safeTargetValue = htmlspecialchars((string)$targetValue, ENT_QUOTES, 'UTF-8');
                $safeStartDate = htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8');
                $safeEndDate = htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8');
                $safeAgreeLink = htmlspecialchars($agreeLink, ENT_QUOTES, 'UTF-8');
                $safeDisagreeLink = htmlspecialchars($disagreeLink, ENT_QUOTES, 'UTF-8');
                $safeAgreeImage = htmlspecialchars($agreeImage, ENT_QUOTES, 'UTF-8');
                $safeDisagreeImage = htmlspecialchars($disagreeImage, ENT_QUOTES, 'UTF-8');

                // Send the invitation email.
                emailSent(
                    "Wichtel",
                    "
                    <h2>Welcome to Wichteln</h2>
                    <p>Hello " . $safeFirstname . ", would you like to join the Wichtel action?</p>
                    <p>
                        🎄 <strong>Hosted by:</strong> " . $safeHostFirstname . " " . $safeHostLastname . "<br>
                        📅 <strong>Start:</strong> " . $safeStartDate . "<br>
                        📅 <strong>End:</strong> " . $safeEndDate . "<br>
                        🎁 <strong>Target Value:</strong> " . $safeTargetValue . " €
                    </p>
                    <p>
                        <a href='" . $safeAgreeLink . "'><img src='" . $safeAgreeImage . "' alt='agree' style='width:80px;'></a>
                        <a href='" . $safeDisagreeLink . "'><img src='" . $safeDisagreeImage . "' alt='disagree' style='width:80px;'></a>
                    </p>
                    ",
                    $email
                );

                // Delete cookies after inviting.
                foreach ($cookieFields as $field) {
                    setcookie($field, "", time() - 3600, "/");
                    unset($_COOKIE[$field]);
                }

                // Reload with empty inputs to avoid form resubmission.
                header("Location: index.php?page=invite");
                exit();
            }

            // Close the database if action lookup did not succeed.
            if (isset($dbc) && $dbc instanceof mysqli) {
                mysqli_close($dbc);
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
            <div class="promo-group">
                <label for="id_firstname">First Name:</label>
                <input id="id_firstname"
                       name="DATA_firstname"
                       type="text"
                       value="<?php echo cookieVal("DATA_firstname"); ?>"
                       required>
            </div>

            <div class="promo-group">
                <label for="id_lastname">Last Name:</label>
                <input id="id_lastname"
                       name="DATA_lastname"
                       type="text"
                       value="<?php echo cookieVal("DATA_lastname"); ?>"
                       required>
            </div>

            <div class="promo-group">
                <label for="id_email">Email:</label>
                <input id="id_email"
                       name="DATA_email"
                       type="email"
                       value="<?php echo cookieVal("DATA_email"); ?>"
                       required>
            </div>

            <input type="submit"
                   name="BUTTON_invite"
                   value="Invite"
                   class="promo-submit">
        </form>

        <!-- Link to the draw page. -->
        <p><a href="index.php?page=draw">Go to Draw</a></p>

        <!-- Page footer. -->
        <footer id="footerindex">© Wichtel-Website</footer>
    </div>
    <?php
} else {
    ?>
    <!-- Login required box. -->
    <div class="promo-box">
        <h2>🎅 Invite Someone 🎅</h2>
        <hr>
        <p>Please log in first.</p>
    </div>
    <?php
}
?>
</body>
