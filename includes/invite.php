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
        setcookie($field, "", time() - 3600, "/");
        unset($_COOKIE[$field]);
    }
    // Delete the session.
    session_destroy();
    header("Location: index.php?page=login");
    exit();
}

// Check login and submitted fields.
if (isset($_SESSION["HeadElf"], $_POST["DATA_firstname"], $_POST["DATA_lastname"], $_POST["DATA_email"], $_POST["BUTTON_invite"])) {
    // Save each input for one day.
    foreach ($cookieFields as $field) {
        setcookie($field, $_POST[$field], time() + 86400, "/");
        $_COOKIE[$field] = $_POST[$field];
    }

    $firstname = trim($_POST["DATA_firstname"]);
    $lastname = trim($_POST["DATA_lastname"]);
    $email = trim($_POST["DATA_email"]);

    if ($firstname === "" || $lastname === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a first name, last name and a valid email address.";
    } else {
        // Connect to the database.
        $dbc = f_connect(false);

        // Search for an existing Elf.
        $statement = mysqli_prepare($dbc, "SELECT pk_elf FROM Elf WHERE email = ?");
        mysqli_stmt_bind_param($statement, "s", $email);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $elfId = (int)$row["pk_elf"];
        } else {
            // Create a new Elf.
            $statementInsert = mysqli_prepare($dbc, "INSERT INTO Elf (firstName, lastName, email) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($statementInsert, "sss", $firstname, $lastname, $email);
            mysqli_stmt_execute($statementInsert);
            $elfId = (int)mysqli_insert_id($dbc);
            mysqli_stmt_close($statementInsert);
        }
        mysqli_free_result($result);
        mysqli_stmt_close($statement);

        // Find the action that belongs to the logged-in Head Elf.
        $actionPassword = $_SESSION["HeadElf"];
        $statement = mysqli_prepare($dbc, "SELECT pk_action, targetValue, firstName, lastName, startDate, endDate FROM Action WHERE actionPassword = ?");
        mysqli_stmt_bind_param($statement, "s", $actionPassword);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        mysqli_stmt_close($statement);

        if (!$row) {
            mysqli_close($dbc);
            $error = "Action not found. Please log in again.";
        } else {
            $actionId = (int)$row["pk_action"];
            $targetValue = $row["targetValue"];
            $hostFirstname = $row["firstName"];
            $hostLastname = $row["lastName"];
            $startDate = $row["startDate"];
            $endDate = $row["endDate"];

            // Reuse an existing invitation or add a new one.
            $statement = mysqli_prepare($dbc, "SELECT pkfk_action FROM Invite WHERE pkfk_action = ? AND pkfk_elf = ?");
            mysqli_stmt_bind_param($statement, "ii", $actionId, $elfId);
            mysqli_stmt_execute($statement);
            $result = mysqli_stmt_get_result($statement);
            if (mysqli_num_rows($result) > 0) {
                $statementUpdate = mysqli_prepare($dbc, "UPDATE Invite SET status = 'Invited' WHERE pkfk_action = ? AND pkfk_elf = ?");
                mysqli_stmt_bind_param($statementUpdate, "ii", $actionId, $elfId);
                mysqli_stmt_execute($statementUpdate);
                mysqli_stmt_close($statementUpdate);
            } else {
                $statementInsert = mysqli_prepare($dbc, "INSERT INTO Invite (pkfk_action, pkfk_elf, status) VALUES (?, ?, 'Invited')");
                mysqli_stmt_bind_param($statementInsert, "ii", $actionId, $elfId);
                mysqli_stmt_execute($statementInsert);
                mysqli_stmt_close($statementInsert);
            }
            mysqli_free_result($result);
            mysqli_stmt_close($statement);
            mysqli_close($dbc);

            // Create the accept and decline links for the existing PHP site.
            $agreeLink = "https://desde661.foxi.lu/project/WIchtel-Website_1/index.php?page=agree&action=$actionId&elf=$elfId";
            $disagreeLink = "https://desde661.foxi.lu/project/WIchtel-Website_1/index.php?page=disagree&action=$actionId&elf=$elfId";

            $safeFirstname = htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8');
            $safeHostFirstname = htmlspecialchars($hostFirstname, ENT_QUOTES, 'UTF-8');
            $safeHostLastname = htmlspecialchars($hostLastname, ENT_QUOTES, 'UTF-8');
            $safeTargetValue = htmlspecialchars((string)$targetValue, ENT_QUOTES, 'UTF-8');
            emailSent(
                "Wichtel",
                "<h2>Welcome to Wichteln</h2>
                <p>Hello " . $safeFirstname . ", would you like to join the Wichtel action?</p>
                <p>🎄 <strong>Hosted by:</strong> " . $safeHostFirstname . " " . $safeHostLastname . "<br>
                📅 <strong>Start:</strong> " . htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') . "<br>
                📅 <strong>End:</strong> " . htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') . "<br>
                🎁 <strong>Target Value:</strong> " . $safeTargetValue . " €</p>
                <p><a href='" . htmlspecialchars($agreeLink, ENT_QUOTES, 'UTF-8') . "'><img src='https://desde661.foxi.lu/project/WIchtel-Website_1/img/agreebutton.png' alt='agree' style='width:80px;'></a>
                <a href='" . htmlspecialchars($disagreeLink, ENT_QUOTES, 'UTF-8') . "'><img src='https://desde661.foxi.lu/project/WIchtel-Website_1/img/disagreebutton.png' alt='disagree' style='width:80px;'></a></p>",
                $email
            );

            // Delete cookies after inviting.
            foreach ($cookieFields as $field) {
                setcookie($field, "", time() - 3600, "/");
                unset($_COOKIE[$field]);
            }

            // Reload with empty inputs.
            header("Location: index.php?page=invite");
            exit();
        }
    }
}
?>
<body class="promo-body">
<?php if (isset($_SESSION["HeadElf"])) { ?>
    <form method="POST" action="index.php?page=invite" style="position: fixed; top: 15px; right: 15px;">
        <input type="submit" name="BUTTON_logout" value="Logout" style="background-color: #a23a2e; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer;">
    </form>
    <div class="promo-box">
        <h2>🎅 Invite Someone 🎅</h2>
        <?php if(isset($error)){ ?><p style="color:red;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php } ?>
        <form method="POST" action="">
            <div class="promo-group"><label for="id_firstname">First Name:</label><input id="id_firstname" name="DATA_firstname" type="text" value="<?php echo cookieVal("DATA_firstname"); ?>" required></div>
            <div class="promo-group"><label for="id_lastname">Last Name:</label><input id="id_lastname" name="DATA_lastname" type="text" value="<?php echo cookieVal("DATA_lastname"); ?>" required></div>
            <div class="promo-group"><label for="id_email">Email:</label><input id="id_email" name="DATA_email" type="email" value="<?php echo cookieVal("DATA_email"); ?>" required></div>
            <input type="submit" name="BUTTON_invite" value="Invite" class="promo-submit">
        </form>
        <p><a href="index.php?page=draw">Go to Draw</a></p>
        <footer id="footerindex">© Wichtel-Website</footer>
    </div>
<?php } else { ?>
    <div class="promo-box"><h2>🎅 Invite Someone 🎅</h2><hr><p>Please log in first.</p></div>
<?php } ?>
</body>
