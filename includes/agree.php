<?php
// Check if the action and Elf IDs exist.
if (isset($_GET["action"], $_GET["elf"])) {
    // Convert both IDs to numbers, so they're safe to use directly in the queries below.
    $actionId = (int) $_GET["action"];
    $elfId = (int) $_GET["elf"];

    // Connect to the database.
    $dbc = f_connect(false);

    if (!$dbc) {
        echo "<div class='promo-box'><p>Database connection failed. Please check the server configuration.</p></div>";
    } else {
        // Accept this Elf's invitation.
        // This updates the row for this elf + action pair to status "Accepted".
        $statement = mysqli_prepare($dbc, "UPDATE Invite SET status = 'Accepted' WHERE pkfk_action = ? AND pkfk_elf = ?");
        mysqli_stmt_bind_param($statement, "ii", $actionId, $elfId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        // Get the wishlist setting.
        $statement = mysqli_prepare($dbc, "SELECT allowWishlist FROM Action WHERE pk_action = ?");
        mysqli_stmt_bind_param($statement, "i", $actionId);
        mysqli_stmt_execute($statement);
        $actionResult = mysqli_stmt_get_result($statement);
        // Read the action data.
        $actionRow = mysqli_fetch_assoc($actionResult);
        // Store the wishlist setting.
        $allowWishlist = $actionRow["allowWishlist"] ?? 0;
        mysqli_free_result($actionResult);
        mysqli_stmt_close($statement);

        // Get this Elf's information.
        $statement = mysqli_prepare($dbc, "SELECT firstName, email FROM Elf WHERE pk_elf = ?");
        mysqli_stmt_bind_param($statement, "i", $elfId);
        mysqli_stmt_execute($statement);
        $elfResult = mysqli_stmt_get_result($statement);
        // Read the Elf data.
        $elfRow = mysqli_fetch_assoc($elfResult);
        mysqli_free_result($elfResult);
        mysqli_stmt_close($statement);

        // Check if a wish was submitted.
        if (isset($_POST["DATA_wish"])) {
            // Store the submitted wish.
            $wish = trim((string)$_POST["DATA_wish"]);
            // Save the wish with a prepared statement so apostrophes in the wish are safe.
            $statement = mysqli_prepare($dbc, "UPDATE Invite SET wishDescription = ? WHERE pkfk_action = ? AND pkfk_elf = ?");
            mysqli_stmt_bind_param($statement, "sii", $wish, $actionId, $elfId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        }

        // Store registration messages.
        $registerError = "";
        $registerSuccess = "";

        // Check if the registration form was sent.
        if (isset($_POST["BUTTON_register"], $_POST["DATA_username"], $_POST["DATA_password"])) {
            // Store the username and password.
            $username = trim((string)$_POST["DATA_username"]);
            $password = (string)$_POST["DATA_password"];

            // Encrypt the password.
            // password_hash() turns the plain password into a secure hash, so the real password is never stored.
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Search for the username.
            $statement = mysqli_prepare($dbc, "SELECT pk_user FROM sys_User WHERE userName = ?");
            mysqli_stmt_bind_param($statement, "s", $username);
            mysqli_stmt_execute($statement);
            $checkUser = mysqli_stmt_get_result($statement);

            // Show an error if it already exists or an input is missing.
            if ($username === "" || $password === "") {
                $registerError = "Please enter a username and password.";
            } else if (mysqli_num_rows($checkUser) > 0) {
                $registerError = "Username already taken, please choose another one.";
            } else if (!$elfRow) {
                $registerError = "The Elf profile could not be found.";
            } else {
                // Create the Elf account. This column name matches the database schema.
                $query = "INSERT INTO sys_User (userName, password, role, fk_elf_registers) VALUES (?, ?, 'Elf', ?)";
                // Prepare the insert query.
                $insertStatement = mysqli_prepare($dbc, $query);
                // Add values to the placeholders.
                // "ssi" means: string, string, integer (in that order).
                mysqli_stmt_bind_param($insertStatement, "ssi", $username, $hashedPassword, $elfId);
                // Run the insert query.
                mysqli_stmt_execute($insertStatement);
                // Close the statement.
                mysqli_stmt_close($insertStatement);

                // Create a success message.
                $registerSuccess = "✅ Account created! You can now log in anytime.";
            }

            mysqli_free_result($checkUser);
            mysqli_stmt_close($statement);
        }
        ?>

        <!-- Main confirmation box. -->
        <div class="promo-box">
            <!-- Page title. -->
            <h2>🎄 Welcome to Wichteln! 🎄</h2>
            <!-- Invitation confirmation. -->
            <p>✅ You have accepted the invite!</p>

            <!-- Show the wishlist form when allowed. -->
            <?php if ($allowWishlist == 1 && !isset($_POST["DATA_wish"])) { ?>
                <!-- Send the wish using POST. -->
                <form method="POST" action="index.php?page=agree&action=<?php echo $actionId; ?>&elf=<?php echo $elfId; ?>">
                    <!-- Wish text field. -->
                    <div class="promo-group">
                        <label for="id_wish">What do you wish for?</label>
                        <textarea id="id_wish" name="DATA_wish" rows="4" style="width:100%;" required></textarea>
                    </div>
                    <!-- Save the wish. -->
                    <input type="submit" value="Save Wish" class="promo-submit">
                </form>
                <!-- Show confirmation after saving. -->
            <?php } else if (isset($_POST["DATA_wish"])) { ?>
                <p style="color:green;">✅ Your wish has been saved!</p>
            <?php } ?>

            <!-- Separate both forms. -->
            <hr>

            <!-- Registration title. -->
            <h3>🧝 Want an account to log in anytime?</h3>

            <!-- Show a registration error. -->
            <?php if ($registerError !== "") { ?>
                <p style="color:red;"><?= htmlspecialchars($registerError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php } ?>

            <!-- Show registration success. -->
            <?php if ($registerSuccess !== "") { ?>
                <p style="color:green;"><?= htmlspecialchars($registerSuccess, ENT_QUOTES, 'UTF-8') ?></p>
            <?php } else { ?>
                <!-- Send the registration form. -->
                <form method="POST" action="index.php?page=agree&action=<?php echo $actionId; ?>&elf=<?php echo $elfId; ?>">
                    <!-- Username field. -->
                    <div class="promo-group">
                        <label for="id_username">Choose a username:</label>
                        <input id="id_username" name="DATA_username" type="text" required>
                    </div>

                    <!-- Password field. -->
                    <div class="promo-group">
                        <label for="id_password">Choose a password:</label>
                        <input id="id_password" name="DATA_password" type="password" required>
                    </div>

                    <!-- Create the account. -->
                    <input type="submit" name="BUTTON_register" value="Create Account" class="promo-submit">
                </form>
            <?php } ?>
        </div>

        <?php
        // Close the database connection.
        mysqli_close($dbc);
    }
} else {
    // Show an error when an ID is missing.
    ?>
    <!-- Invalid link message. -->
    <div class="promo-box">
        <p>❌ Invalid link.</p>
    </div>
    <?php
}
?>
</body>
