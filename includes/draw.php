<?php
// Start the session.
session_start();

// Check if logout was clicked.
if (isset($_POST["BUTTON_logout"])) {
    // Delete the session.
    session_destroy();
    // Return to login.
    header("Location: index.php?page=login");
    // Stop the script.
    exit();
}
?>
<body class="promo-body">
<?php
// Show logout when Head Elf is logged in.
if (isset($_SESSION["HeadElf"])) {
    ?>
    <!-- Logout form. -->
    <form method="POST"
          action="index.php?page=draw"
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
    <?php
}
?>

<!-- Main draw box. -->
<div class="promo-box">
    <!-- Page title. -->
    <h2>🎄 Start Draw 🎄</h2>
    <hr>

    <?php
    // Check if Head Elf is logged in.
    if (isset($_SESSION["HeadElf"])) {
        // Connect to the database.
        $dbc = f_connect(false);

        if (!$dbc) {
            echo "<p>Please check the database configuration.</p>";
        } else {
            // Escape the action password.
            $actionPassword = mysqli_real_escape_string($dbc, $_SESSION["HeadElf"]);

            // Find the action using its password.
            $result = mysqli_query($dbc, "SELECT pk_action FROM Action WHERE actionPassword = '$actionPassword'");

            // Stop if the action does not exist.
            if (!$result || mysqli_num_rows($result) === 0) {
                echo "<p>Action not found.</p>";
            } else {
                // Get the action ID.
                $row = mysqli_fetch_assoc($result);
                $actionId = (int)$row["pk_action"];
                mysqli_free_result($result);

                // Get all invited Elves.
                $query = "SELECT Elf.pk_elf, Elf.firstName, Elf.lastName, Elf.email, Invite.status, Invite.wishDescription
                          FROM Invite
                          JOIN Elf ON Invite.pkfk_elf = Elf.pk_elf
                          WHERE Invite.pkfk_action = $actionId";

                // Prepare the query.
                $statement = mysqli_prepare($dbc, $query);

                // Run the query.
                mysqli_stmt_execute($statement);

                // Get the result.
                $result = mysqli_stmt_get_result($statement);

                // Store all invited Elves.
                $allElves = [];

                // Store accepted Elves.
                $accepted = [];

                // Read every Elf.
                while ($row = mysqli_fetch_assoc($result)) {
                    // Add Elf to the full list.
                    $allElves[] = $row;

                    // Add accepted Elf to the draw list.
                    if ($row["status"] === "Accepted") {
                        $accepted[] = $row;
                    }
                }

                // Free the result and close the statement.
                mysqli_free_result($result);
                mysqli_stmt_close($statement);

                // Show invitation totals.
                echo "<p>Total invited: <b>" . count($allElves) . "</b> | Accepted: <b>" . count($accepted) . "</b></p>";

                // Start the Elves table.
                echo "<table>";
                echo "<thead><tr><th>First Name</th><th>Last Name</th><th>Email</th><th>Status</th></tr></thead>";
                echo "<tbody>";

                // Show every Elf in a row.
                foreach ($allElves as $elf) {
                    echo "<tr>
                            <td>" . htmlspecialchars($elf["firstName"], ENT_QUOTES, 'UTF-8') . "</td>
                            <td>" . htmlspecialchars($elf["lastName"], ENT_QUOTES, 'UTF-8') . "</td>
                            <td>" . htmlspecialchars($elf["email"], ENT_QUOTES, 'UTF-8') . "</td>
                            <td>" . htmlspecialchars($elf["status"], ENT_QUOTES, 'UTF-8') . "</td>
                          </tr>";
                }

                // Close the table.
                echo "</tbody></table>";

                // Check if the draw already exists.
                $checkDraw = mysqli_query($dbc, "SELECT * FROM Draw WHERE pkfk_action = $actionId");

                // Store whether the draw was already done.
                $alreadyDrawn = $checkDraw && mysqli_num_rows($checkDraw) > 0;
                if ($checkDraw) {
                    mysqli_free_result($checkDraw);
                }

                // Check if Start Draw was clicked.
                if (isset($_POST["BUTTON_Start"])) {
                    // Stop another draw.
                    if ($alreadyDrawn) {
                        echo "<p style='color:red;'>⚠️ You can only draw once for this action!</p>";
                    } else {
                        // The schema uses "Invited" for a pending response.
                        $checkPending = mysqli_query($dbc, "SELECT * FROM Invite WHERE pkfk_action = $actionId AND status = 'Invited'");

                        // Wait until everyone responds.
                        if ($checkPending && mysqli_num_rows($checkPending) > 0) {
                            echo "<p style='color:red;'>⚠️ Not everyone has responded yet!</p>";
                        } else if (count($accepted) < 2) {
                            // Require at least two Elves.
                            echo "<p style='color:red;'>Need at least 2 accepted participants!</p>";
                        } else {
                            // Store excluded pairs.
                            $exclusionPairs = [];

                            // Check if exclusions were selected.
                            if (isset($_POST["DATA_exclusion"]) && is_array($_POST["DATA_exclusion"])) {
                                // Add only valid Elf ID pairs.
                                foreach ($_POST["DATA_exclusion"] as $pair) {
                                    if (is_string($pair) && preg_match('/^\d+-\d+$/', $pair)) {
                                        $exclusionPairs[] = $pair;
                                    }
                                }
                            }

                            // Start with no valid draw.
                            $validDraw = false;

                            // Count shuffle attempts.
                            $attempts = 0;

                            // Try up to 200 combinations.
                            while (!$validDraw && $attempts < 200) {
                                // Shuffle accepted Elves.
                                shuffle($accepted);

                                // Count accepted Elves.
                                $peopleAccepted = count($accepted);

                                // Assume the draw is valid.
                                $foundBadPair = false;

                                // Check every giver and receiver.
                                for ($i = 0; $i < $peopleAccepted; $i++) {
                                    // Current giver.
                                    $giver = $accepted[$i];

                                    // Next Elf is the receiver.
                                    $receiver = $accepted[($i + 1) % $peopleAccepted];

                                    // Create both pair directions.
                                    $pairA = $giver["pk_elf"] . "-" . $receiver["pk_elf"];
                                    $pairB = $receiver["pk_elf"] . "-" . $giver["pk_elf"];

                                    // Check all excluded pairs.
                                    foreach ($exclusionPairs as $excluded) {
                                        // Mark the draw as invalid.
                                        if ($pairA === $excluded || $pairB === $excluded) {
                                            $foundBadPair = true;
                                        }
                                    }
                                }

                                // Accept the draw if no bad pair exists.
                                if ($foundBadPair === false) {
                                    $validDraw = true;
                                }

                                // Increase the attempt count.
                                $attempts++;
                            }

                            // Show an error if no draw was found.
                            if (!$validDraw) {
                                echo "<p style='color:red;'>⚠️ Could not find a valid draw with these exclusions. Try removing some.</p>";
                            } else {
                                // Save each draw pair. The column name matches the SQL schema.
                                $insertStatement = mysqli_prepare(
                                    $dbc,
                                    "INSERT INTO Draw (pkfk_action, pkfk_elf_gives, pkfk_elf_receives) VALUES (?, ?, ?)"
                                );

                                // Use the current host and folder for the login link in the email.
                                $basePath = rtrim(dirname($_SERVER["SCRIPT_NAME"] ?? "/index.php"), "/\\");
                                $loginLink = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off" ? "https://" : "http://")
                                    . ($_SERVER["HTTP_HOST"] ?? "")
                                    . $basePath
                                    . "/index.php?page=userLogin";

                                $insertFailed = false;
                                for ($i = 0; $i < $peopleAccepted; $i++) {
                                    // Get the giver.
                                    $giver = $accepted[$i];

                                    // Get the receiver.
                                    $receiver = $accepted[($i + 1) % $peopleAccepted];

                                    // Convert both IDs to numbers.
                                    $giverId = (int)$giver["pk_elf"];
                                    $receiverId = (int)$receiver["pk_elf"];

                                    // Insert the draw pair using a prepared statement.
                                    mysqli_stmt_bind_param($insertStatement, "iii", $actionId, $giverId, $receiverId);
                                    if (!mysqli_stmt_execute($insertStatement)) {
                                        error_log("Wichtel draw insert failed: " . mysqli_stmt_error($insertStatement));
                                        $insertFailed = true;
                                        break;
                                    }

                                    // Use the receiver's wish.
                                    $wishText = $receiver["wishDescription"] ?: "No wishlist provided";

                                    // Escape values placed in the HTML email.
                                    $giverFirst = htmlspecialchars($giver["firstName"], ENT_QUOTES, 'UTF-8');
                                    $receiverFirst = htmlspecialchars($receiver["firstName"], ENT_QUOTES, 'UTF-8');
                                    $receiverLast = htmlspecialchars($receiver["lastName"], ENT_QUOTES, 'UTF-8');
                                    $safeWish = htmlspecialchars($wishText, ENT_QUOTES, 'UTF-8');
                                    $safeLoginLink = htmlspecialchars($loginLink, ENT_QUOTES, 'UTF-8');

                                    // Email the result to the giver.
                                    emailSent(
                                        "Your Wichteln Partner",
                                        "
                                        <h2>Welcome to Wichteln 🎄</h2>
                                        <p>Hello <b>" . $giverFirst . "</b>!</p>
                                        <p>You are giving a gift to: <b>" . $receiverFirst . " " . $receiverLast . "</b></p>
                                        <p>🎁 Their wish: <b>" . $safeWish . "</b></p>
                                        <p>Happy Wichteln! 🎁</p>
                                        <hr>
                                        <p>
                                            Log in anytime to check your status:
                                            <br>
                                            <a href='" . $safeLoginLink . "'>👉 Click here to log in</a>
                                        </p>
                                        ",
                                        $giver["email"]
                                    );
                                }

                                mysqli_stmt_close($insertStatement);

                                if ($insertFailed) {
                                    echo "<p style='color:red;'>The draw could not be saved completely. Please check the database before trying again.</p>";
                                } else {
                                    // Show success.
                                    echo "<p style='color:green;'>🎄 Draw done! Everyone got an email.</p>";

                                    // Hide the draw form.
                                    $alreadyDrawn = true;
                                }
                            }
                        }

                        if ($checkPending) {
                            mysqli_free_result($checkPending);
                        }
                    }
                }

                // Close the database.
                mysqli_close($dbc);
                ?>

                <!-- Show form before the draw. -->
                <?php if (!$alreadyDrawn) { ?>
                    <!-- Exclusions and draw form. -->
                    <form method="POST" action="index.php?page=draw">
                        <hr>

                        <!-- Exclusion title. -->
                        <h3>🚫 Mark pairs that should NOT get each other (e.g. couples, family)</h3>

                        <?php
                        // Count accepted Elves.
                        $count = count($accepted);

                        // Create every possible pair.
                        for ($i = 0; $i < $count; $i++) {
                            for ($j = $i + 1; $j < $count; $j++) {
                                // Get both Elves.
                                $elf1 = $accepted[$i];
                                $elf2 = $accepted[$j];

                                // Create the pair value.
                                $pairValue = $elf1["pk_elf"] . "-" . $elf2["pk_elf"];

                                // Show the pair checkbox.
                                echo "<label style='display:block; text-align:left;'>
                                        <input type='checkbox' name='DATA_exclusion[]' value='" . htmlspecialchars($pairValue, ENT_QUOTES, 'UTF-8') . "' style='width:auto; margin-right:8px;'>
                                        " . htmlspecialchars($elf1["firstName"], ENT_QUOTES, 'UTF-8') . " ↔ " . htmlspecialchars($elf2["firstName"], ENT_QUOTES, 'UTF-8') . "
                                      </label>";
                            }
                        }
                        ?>

                        <br>

                        <!-- Start the draw. -->
                        <input type="submit" name="BUTTON_Start" value="Start Draw" class="promo-submit">
                    </form>
                <?php } ?>
                <?php
            }
        }
    } else {
        // Ask the user to log in.
        echo "<p>Please log in first.</p>";
    }
    ?>

    <!-- Page footer. -->
    <footer id="footerindex">© Wichtel-Website</footer>
</div>
</body>
