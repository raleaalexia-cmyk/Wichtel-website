<?php
// Start the session.
session_start();

// Check if logout was clicked.
if(isset($_POST["BUTTON_logout"])){
    session_destroy();
    header("Location: index.php?page=login");
    exit();
}

// Check if an action update was submitted by the logged-in Head Elf.
if(isset($_SESSION["HeadElf"]) && isset($_POST["BUTTON_update"])){
    $dbc = f_connect(false);
    $actionId = (int)($_POST["DATA_actionId"] ?? 0);
    $title = trim((string)($_POST["DATA_title"] ?? ""));
    $firstname = trim((string)($_POST["DATA_firstname"] ?? ""));
    $lastname = trim((string)($_POST["DATA_lastname"] ?? ""));
    $email = trim((string)($_POST["DATA_email"] ?? ""));
    $start = (string)($_POST["DATA_start"] ?? "");
    $end = (string)($_POST["DATA_end"] ?? "");
    $targetValue = filter_var($_POST["DATA_value"] ?? null, FILTER_VALIDATE_INT);
    $allowWishlist = isset($_POST["DATA_wishlist"]) ? 1 : 0;
    $actionPassword = $_SESSION["HeadElf"];

    if ($actionId < 1 || $title === "" || $firstname === "" || $lastname === "" ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) || $targetValue === false || $targetValue < 0 ||
        $start === "" || $end < $start) {
        $updateError = "Please check the action details and try again.";
    } else {
        // Only update an action belonging to this Head Elf.
        $statement = mysqli_prepare($dbc, "SELECT startDate, endDate, targetValue FROM Action WHERE pk_action = ? AND fk_elf_create = (SELECT fk_elf_create FROM Action WHERE actionPassword = ?)");
        mysqli_stmt_bind_param($statement, "is", $actionId, $actionPassword);
        mysqli_stmt_execute($statement);
        $oldResult = mysqli_stmt_get_result($statement);
        $oldAction = mysqli_fetch_assoc($oldResult);
        mysqli_free_result($oldResult);
        mysqli_stmt_close($statement);

        if (!$oldAction) {
            $updateError = "The selected action was not found.";
        } else {
            $statement = mysqli_prepare($dbc, "UPDATE Action SET title = ?, firstName = ?, lastName = ?, email = ?, startDate = ?, endDate = ?, targetValue = ?, allowWishlist = ? WHERE pk_action = ?");
            mysqli_stmt_bind_param($statement, "ssssssiii", $title, $firstname, $lastname, $email, $start, $end, $targetValue, $allowWishlist, $actionId);
            $successUpdate = mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);

            if ($successUpdate) {
                $changes = [];
                if($oldAction["startDate"] != $start) $changes[] = "Start Date changed to: " . htmlspecialchars($start, ENT_QUOTES, 'UTF-8');
                if($oldAction["endDate"] != $end) $changes[] = "End Date changed to: " . htmlspecialchars($end, ENT_QUOTES, 'UTF-8');
                if($oldAction["targetValue"] != $targetValue) $changes[] = "Target Value changed to: " . htmlspecialchars((string)$targetValue, ENT_QUOTES, 'UTF-8') . " €";

                if(count($changes) > 0){
                    $changesHtml = "<ul>";
                    foreach($changes as $change) $changesHtml .= "<li>" . $change . "</li>";
                    $changesHtml .= "</ul>";

                    $statement = mysqli_prepare($dbc, "SELECT Elf.firstName, Elf.email FROM Invite JOIN Elf ON Invite.pkfk_elf = Elf.pk_elf WHERE Invite.pkfk_action = ?");
                    mysqli_stmt_bind_param($statement, "i", $actionId);
                    mysqli_stmt_execute($statement);
                    $invitedResult = mysqli_stmt_get_result($statement);
                    $invitedElves = mysqli_fetch_all($invitedResult, MYSQLI_ASSOC);
                    mysqli_free_result($invitedResult);
                    mysqli_stmt_close($statement);

                    foreach($invitedElves as $invitedElf){
                        $safeElf = htmlspecialchars($invitedElf["firstName"], ENT_QUOTES, 'UTF-8');
                        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
                        emailSent("Wichtel Action Updated", "<h2>🎄 Action Updated 🎄</h2><p>Hello <b>" . $safeElf . "</b>!</p><p>The following has changed for <b>" . $safeTitle . "</b>:</p>" . $changesHtml, $invitedElf["email"]);
                    }
                }
                $updateSuccess = true;
            } else {
                $updateError = "The action could not be updated.";
            }
        }
    }
    mysqli_close($dbc);
}
?>
<body class="promo-body">
<form method="POST" action="index.php?page=yourActions" style="position: fixed; top: 15px; right: 15px;">
    <input type="submit" name="BUTTON_logout" value="Logout" style="background-color: #a23a2e; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer;">
</form>
<div class="promo-box">
    <h2>🎅 Your Actions 🎅</h2>
    <hr>
    <?php
    if(isset($_SESSION["HeadElf"])){
        $dbc = f_connect(false);
        $actionPassword = $_SESSION["HeadElf"];
        $statement = mysqli_prepare($dbc, "SELECT pk_action, fk_elf_create FROM Action WHERE actionPassword = ?");
        mysqli_stmt_bind_param($statement, "s", $actionPassword);
        mysqli_stmt_execute($statement);
        $elfResult = mysqli_stmt_get_result($statement);
        $elfRow = mysqli_fetch_assoc($elfResult);
        mysqli_free_result($elfResult);
        mysqli_stmt_close($statement);
        $elfId = (int)($elfRow["fk_elf_create"] ?? 0);

        $statement = mysqli_prepare($dbc, "SELECT * FROM Action WHERE fk_elf_create = ? ORDER BY pk_action");
        mysqli_stmt_bind_param($statement, "i", $elfId);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);

        $editActionId = isset($_GET["edit"]) ? (int)$_GET["edit"] : null;
        if(mysqli_num_rows($result) === 0){
            echo "<p>You have no actions yet.</p>";
        } else {
            echo "<table><thead><tr><th>Title</th><th>Start Date</th><th>End Date</th><th>Target Value</th><th>Edit</th></tr></thead><tbody>";
            while($action = mysqli_fetch_assoc($result)){
                $id = (int)$action["pk_action"];
                echo "<tr><td>" . htmlspecialchars($action["title"], ENT_QUOTES, 'UTF-8') . "</td><td>" . htmlspecialchars($action["startDate"], ENT_QUOTES, 'UTF-8') . "</td><td>" . htmlspecialchars($action["endDate"], ENT_QUOTES, 'UTF-8') . "</td><td>" . htmlspecialchars((string)$action["targetValue"], ENT_QUOTES, 'UTF-8') . " €</td>";
                if($editActionId == $id){
                    echo "<td><a href='index.php?page=yourActions'><button style='background-color:#a23a2e;color:white;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;'>✏️ Edit</button></a></td>";
                } else {
                    echo "<td><a href='index.php?page=yourActions&edit=" . $id . "'><button style='background-color:#a23a2e;color:white;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;'>✏️ Edit</button></a></td>";
                }
                echo "</tr>";
            }
            echo "</tbody></table>";
        }
        mysqli_free_result($result);
        mysqli_stmt_close($statement);

        if(isset($updateSuccess)) echo "<p style='color:green;'>Action updated! ✅</p>";
        if(isset($updateError)) echo "<p style='color:red;'>" . htmlspecialchars($updateError, ENT_QUOTES, 'UTF-8') . "</p>";

        if($editActionId){
            $statement = mysqli_prepare($dbc, "SELECT * FROM Action WHERE pk_action = ? AND fk_elf_create = ?");
            mysqli_stmt_bind_param($statement, "ii", $editActionId, $elfId);
            mysqli_stmt_execute($statement);
            $editResult = mysqli_stmt_get_result($statement);
            $editAction = mysqli_fetch_assoc($editResult);
            mysqli_free_result($editResult);
            mysqli_stmt_close($statement);

            if ($editAction) {
            ?>
            <hr>
            <h3>✏️ Edit: <?= htmlspecialchars($editAction["title"], ENT_QUOTES, 'UTF-8') ?></h3>
            <form method="POST" action="index.php?page=yourActions&edit=<?php echo $editActionId; ?>">
                <input type="hidden" name="DATA_actionId" value="<?php echo (int)$editAction["pk_action"]; ?>">
                <div class="promo-group"><label for="id_actionName">Action Name:</label><input id="id_actionName" name="DATA_title" type="text" value="<?php echo htmlspecialchars($editAction['title'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="promo-group"><label for="id_firstName">First Name:</label><input id="id_firstName" name="DATA_firstname" type="text" value="<?php echo htmlspecialchars($editAction['firstName'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="promo-group"><label for="id_lastName">Last Name:</label><input id="id_lastName" name="DATA_lastname" type="text" value="<?php echo htmlspecialchars($editAction['lastName'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="promo-group"><label for="id_email">Email:</label><input id="id_email" name="DATA_email" type="email" value="<?php echo htmlspecialchars($editAction['email'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="promo-group"><label for="id_start">Start Date:</label><input id="id_start" name="DATA_start" type="date" value="<?php echo htmlspecialchars($editAction['startDate'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="promo-group"><label for="id_end">End Date:</label><input id="id_end" name="DATA_end" type="date" value="<?php echo htmlspecialchars($editAction['endDate'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="promo-group"><label for="id_value">Target Value:</label><input id="id_value" name="DATA_value" type="number" value="<?php echo htmlspecialchars((string)$editAction['targetValue'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="promo-group"><label for="id_wishlist">Allow Wishlist</label><input id="id_wishlist" type="checkbox" name="DATA_wishlist" value="1" style="width:auto;margin-right:8px;" <?php if($editAction['allowWishlist'] == 1) echo "checked"; ?>></div>
                <input type="submit" name="BUTTON_update" value="Update Action" class="promo-submit">
            </form>
            <?php
            }
        }
        mysqli_close($dbc);
    } else {
        echo "<p>Please log in first.</p>";
    }
    ?>
    <footer id="footerindex">© Wichtel-Website</footer>
</div>
</body>
