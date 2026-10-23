<?php
// Start the session.
session_start();

// Check if logout was clicked.
if (isset($_POST["BUTTON_logout"])) {
    session_destroy();
    header("Location: index.php?page=login");
    exit();
}
?>
<body class="promo-body">
<?php
// Show logout when Head Elf is logged in.
if (isset($_SESSION["HeadElf"])) {
    ?>
    <form method="POST" action="index.php?page=draw" style="position: fixed; top: 15px; right: 15px;">
        <input type="submit" name="BUTTON_logout" value="Logout" style="background-color: #a23a2e; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer;">
    </form>
    <?php
}
?>
<div class="promo-box">
    <h2>🎄 Start Draw 🎄</h2>
    <hr>
    <?php
    if (isset($_SESSION["HeadElf"])) {
        $dbc = f_connect(false);
        $actionPassword = mysqli_real_escape_string($dbc, $_SESSION["HeadElf"]);
        $result = mysqli_query($dbc, "SELECT pk_action FROM Action WHERE actionPassword = '$actionPassword'");

        if (!$result || mysqli_num_rows($result) === 0) {
            die("Action not found.");
        }
        $row = mysqli_fetch_assoc($result);
        $actionId = (int)$row["pk_action"];

        $query = "SELECT Elf.pk_elf, Elf.firstName, Elf.lastName, Elf.email, Invite.status, Invite.wishDescription
                  FROM Invite JOIN Elf ON Invite.pkfk_elf = Elf.pk_elf
                  WHERE Invite.pkfk_action = $actionId";
        $statement = mysqli_prepare($dbc, $query);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $allElves = [];
        $accepted = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $allElves[] = $row;
            if($row["status"] === "Accepted"){
                $accepted[] = $row;
            }
        }
        mysqli_free_result($result);
        mysqli_stmt_close($statement);

        echo "<p>Total invited: <b>" . count($allElves) . "</b> | Accepted: <b>" . count($accepted) . "</b></p>";
        echo "<table><thead><tr><th>First Name</th><th>Last Name</th><th>Email</th><th>Status</th></tr></thead><tbody>";
        foreach ($allElves as $elf) {
            echo "<tr><td>" . htmlspecialchars($elf["firstName"], ENT_QUOTES, 'UTF-8') . "</td><td>" . htmlspecialchars($elf["lastName"], ENT_QUOTES, 'UTF-8') . "</td><td>" . htmlspecialchars($elf["email"], ENT_QUOTES, 'UTF-8') . "</td><td>" . htmlspecialchars($elf["status"], ENT_QUOTES, 'UTF-8') . "</td></tr>";
        }
        echo "</tbody></table>";

        $checkDraw = mysqli_query($dbc, "SELECT * FROM Draw WHERE pkfk_action = $actionId");
        $alreadyDrawn = mysqli_num_rows($checkDraw) > 0;

        if (isset($_POST["BUTTON_Start"])) {
            if ($alreadyDrawn) {
                echo "<p style='color:red;'>⚠️ You can only draw once for this action!</p>";
            } else {
                // In this schema the not-yet-answered state is "Invited", not "Pending".
                $checkPending = mysqli_query($dbc, "SELECT * FROM Invite WHERE pkfk_action = $actionId AND status = 'Invited'");
                if (mysqli_num_rows($checkPending) > 0) {
                    echo "<p style='color:red;'>⚠️ Not everyone has responded yet!</p>";
                } else if (count($accepted) < 2) {
                    echo "<p style='color:red;'>Need at least 2 accepted participants!</p>";
                } else {
                    $exclusionPairs = [];
                    if(isset($_POST["DATA_exclusion"]) && is_array($_POST["DATA_exclusion"])){
                        foreach($_POST["DATA_exclusion"] as $pair){
                            if (is_string($pair) && preg_match('/^\d+-\d+$/', $pair)) {
                                $exclusionPairs[] = $pair;
                            }
                        }
                    }

                    $validDraw = false;
                    $attempts = 0;
                    while(!$validDraw && $attempts < 200){
                        shuffle($accepted);
                        $peopleAccepted = count($accepted);
                        $foundBadPair = false;
                        for ($i = 0; $i < $peopleAccepted; $i++) {
                            $giver = $accepted[$i];
                            $receiver = $accepted[($i + 1) % $peopleAccepted];
                            $pairA = $giver["pk_elf"] . "-" . $receiver["pk_elf"];
                            $pairB = $receiver["pk_elf"] . "-" . $giver["pk_elf"];
                            foreach($exclusionPairs as $excluded){
                                if($pairA == $excluded || $pairB == $excluded){
                                    $foundBadPair = true;
                                }
                            }
                        }
                        if($foundBadPair == false){
                            $validDraw = true;
                        }
                        $attempts++;
                    }

                    if(!$validDraw){
                        echo "<p style='color:red;'>⚠️ Could not find a valid draw with these exclusions. Try removing some.</p>";
                    } else {
                        // Insert each giver-recipient pair using the column name from CREATE_TABLES+SYSM.sql.
                        $insertDraw = mysqli_prepare($dbc, "INSERT INTO Draw (pkfk_action, pkfk_elf_gives, pkfk_elf_receives) VALUES (?, ?, ?)");
                        for ($i = 0; $i < $peopleAccepted; $i++) {
                            $giver = $accepted[$i];
                            $receiver = $accepted[($i + 1) % $peopleAccepted];
                            $giverId = (int)$giver["pk_elf"];
                            $receiverId = (int)$receiver["pk_elf"];
                            mysqli_stmt_bind_param($insertDraw, "iii", $actionId, $giverId, $receiverId);
                            if (!mysqli_stmt_execute($insertDraw)) {
                                error_log("Wichtel draw insert failed: " . mysqli_stmt_error($insertDraw));
                                echo "<p style='color:red;'>Could not save the draw. Check the database schema and try again.</p>";
                                break;
                            }

                            $wishText = $receiver["wishDescription"] ?: "No wishlist provided";
                            $giverFirst = htmlspecialchars($giver["firstName"], ENT_QUOTES, 'UTF-8');
                            $receiverFirst = htmlspecialchars($receiver["firstName"], ENT_QUOTES, 'UTF-8');
                            $receiverLast = htmlspecialchars($receiver["lastName"], ENT_QUOTES, 'UTF-8');
                            $safeWish = htmlspecialchars($wishText, ENT_QUOTES, 'UTF-8');
                            emailSent(
                                "Your Wichteln Partner",
                                "<h2>Welcome to Wichteln 🎄</h2>
                                <p>Hello <b>" . $giverFirst . "</b>!</p>
                                <p>You are giving a gift to: <b>" . $receiverFirst . " " . $receiverLast . "</b></p>
                                <p>🎁 Their wish: <b>" . $safeWish . "</b></p>
                                <p>Happy Wichteln! 🎁</p>
                                <hr>
                                <p>Log in anytime to check your status:<br>
                                <a href='https://desde661.foxi.lu/project/WIchtel-Website_1/index.php?page=userLogin'>👉 Click here to log in</a></p>",
                                $giver["email"]
                            );
                        }
                        mysqli_stmt_close($insertDraw);
                        echo "<p style='color:green;'>🎄 Draw done! Everyone got an email.</p>";
                        $alreadyDrawn = true;
                    }
                }
            }
        }

        mysqli_close($dbc);
        ?>
        <?php if (!$alreadyDrawn) { ?>
            <form method="POST" action="index.php?page=draw">
                <hr>
                <h3>🚫 Mark pairs that should NOT get each other (e.g. couples, family)</h3>
                <?php
                $count = count($accepted);
                for($i = 0; $i < $count; $i++){
                    for($j = $i + 1; $j < $count; $j++){
                        $elf1 = $accepted[$i];
                        $elf2 = $accepted[$j];
                        $pairValue = $elf1["pk_elf"] . "-" . $elf2["pk_elf"];
                        echo "<label style='display:block; text-align:left;'><input type='checkbox' name='DATA_exclusion[]' value='" . htmlspecialchars($pairValue, ENT_QUOTES, 'UTF-8') . "' style='width:auto; margin-right:8px;'> " . htmlspecialchars($elf1["firstName"], ENT_QUOTES, 'UTF-8') . " ↔ " . htmlspecialchars($elf2["firstName"], ENT_QUOTES, 'UTF-8') . "</label>";
                    }
                }
                ?>
                <br>
                <input type="submit" name="BUTTON_Start" value="Start Draw" class="promo-submit">
            </form>
        <?php } ?>
        <?php
    } else {
        echo "<p>Please log in first.</p>";
    }
    ?>
    <footer id="footerindex">© Wichtel-Website</footer>
</div>
</body>