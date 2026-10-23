<body class="promo-body">
<?php
// Check if the action and Elf IDs exist.
if (isset($_GET["action"], $_GET["elf"])) {
    // Convert both IDs to numbers, so they're safe to use directly in the queries below.
    $actionId = (int) $_GET["action"];
    $elfId = (int) $_GET["elf"];

    // Connect to the database.
    $dbc = f_connect(false);

    // Accept this Elf's invitation.
    $statement = mysqli_prepare($dbc, "UPDATE Invite SET status = 'Accepted' WHERE pkfk_action = ? AND pkfk_elf = ?");
    mysqli_stmt_bind_param($statement, "ii", $actionId, $elfId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    // Get the wishlist setting.
    $statement = mysqli_prepare($dbc, "SELECT allowWishlist FROM Action WHERE pk_action = ?");
    mysqli_stmt_bind_param($statement, "i", $actionId);
    mysqli_stmt_execute($statement);
    $actionResult = mysqli_stmt_get_result($statement);
    $actionRow = mysqli_fetch_assoc($actionResult);
    $allowWishlist = $actionRow["allowWishlist"] ?? 0;
    mysqli_stmt_close($statement);

    // Get this Elf's information.
    $statement = mysqli_prepare($dbc, "SELECT firstName, email FROM Elf WHERE pk_elf = ?");
    mysqli_stmt_bind_param($statement, "i", $elfId);
    mysqli_stmt_execute($statement);
    $elfResult = mysqli_stmt_get_result($statement);
    $elfRow = mysqli_fetch_assoc($elfResult);
    mysqli_stmt_close($statement);

    // Save a wish without allowing the submitted text to alter the SQL query.
    if (isset($_POST["DATA_wish"])) {
        $wish = $_POST["DATA_wish"];
        $statement = mysqli_prepare($dbc, "UPDATE Invite SET wishDescription = ? WHERE pkfk_action = ? AND pkfk_elf = ?");
        mysqli_stmt_bind_param($statement, "sii", $wish, $actionId, $elfId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
    }

    // Store registration messages.
    $registerError = "";
    $registerSuccess = "";

    // Check if the registration form was sent.
    if(isset($_POST["BUTTON_register"], $_POST["DATA_username"], $_POST["DATA_password"])){
        $username = $_POST["DATA_username"];
        $password = $_POST["DATA_password"];
        $usernameDB = mysqli_real_escape_string($dbc, $username);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $checkUserStatement = mysqli_prepare($dbc, "SELECT pk_user FROM sys_User WHERE userName = ?");
        mysqli_stmt_bind_param($checkUserStatement, "s", $usernameDB);
        mysqli_stmt_execute($checkUserStatement);
        $checkUser = mysqli_stmt_get_result($checkUserStatement);

        if(mysqli_num_rows($checkUser) > 0){
            $registerError = "Username already taken, please choose another one.";
        } else {
            // The schema calls this column fk_elf_registers.
            $query = "INSERT INTO sys_User (userName, password, role, fk_elf_registers) VALUES (?, ?, 'Elf', ?)";
            $statement = mysqli_prepare($dbc, $query);
            mysqli_stmt_bind_param($statement, "ssi", $usernameDB, $hashedPassword, $elfId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            $registerSuccess = "✅ Account created! You can now log in anytime.";
        }
        mysqli_stmt_close($checkUserStatement);
    }
    ?>

    <div class="promo-box">
        <h2>🎄 Welcome to Wichteln! 🎄</h2>
        <p>✅ You have accepted the invite!</p>

        <?php if($allowWishlist == 1 && !isset($_POST["DATA_wish"])){ ?>
            <form method="POST" action="index.php?page=agree&action=<?php echo $actionId; ?>&elf=<?php echo $elfId; ?>">
                <div class="promo-group">
                    <label for="id_wish">What do you wish for?</label>
                    <textarea id="id_wish" name="DATA_wish" rows="4" style="width:100%;" required></textarea>
                </div>
                <input type="submit" value="Save Wish" class="promo-submit">
            </form>
        <?php } else if(isset($_POST["DATA_wish"])){ ?>
            <p style="color:green;">✅ Your wish has been saved!</p>
        <?php } ?>

        <hr>
        <h3>🧝 Want an account to log in anytime?</h3>
        <?php if($registerError !== ""){ ?>
            <p style="color:red;"><?= htmlspecialchars($registerError, ENT_QUOTES, 'UTF-8') ?></p>
        <?php } ?>
        <?php if($registerSuccess !== ""){ ?>
            <p style="color:green;"><?= htmlspecialchars($registerSuccess, ENT_QUOTES, 'UTF-8') ?></p>
        <?php } else { ?>
            <form method="POST" action="index.php?page=agree&action=<?php echo $actionId; ?>&elf=<?php echo $elfId; ?>">
                <div class="promo-group">
                    <label for="id_username">Choose a username:</label>
                    <input id="id_username" name="DATA_username" type="text" required>
                </div>
                <div class="promo-group">
                    <label for="id_password">Choose a password:</label>
                    <input id="id_password" name="DATA_password" type="password" required>
                </div>
                <input type="submit" name="BUTTON_register" value="Create Account" class="promo-submit">
            </form>
        <?php } ?>
    </div>
    <?php
    mysqli_close($dbc);
} else {
    ?>
    <div class="promo-box">
        <p>❌ Invalid link.</p>
    </div>
    <?php
}
?>
</body>