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
// Show logout when an Elf is logged in.
if (isset($_SESSION["DATA_ELF"])) {
    ?>
    <!-- Logout form. -->
    <form method="POST"
          action="index.php?page=userDashboard"
          style="position: fixed; top: 15px; right: 15px;">
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

<!-- Main overview box. -->
<div class="promo-box">
    <h2>🧝 Your Personal Overview🧝</h2>
    <hr>

    <?php
    if (isset($_SESSION["DATA_ELF"])) {
        $username = (string)$_SESSION["DATA_ELF"];
        $dbc = f_connect(false);

        if (!$dbc) {
            echo "<p>Please check the database configuration.</p>";
        } else {
            // Link the account to its Elf. The schema column is fk_elf_registers.
            $statement = mysqli_prepare($dbc, "SELECT fk_elf_registers FROM sys_User WHERE userName = ?");
            mysqli_stmt_bind_param($statement, "s", $username);
            mysqli_stmt_execute($statement);
            $userResult = mysqli_stmt_get_result($statement);
            $userRow = mysqli_fetch_assoc($userResult);
            mysqli_free_result($userResult);
            mysqli_stmt_close($statement);

            $elfId = (int)($userRow["fk_elf_registers"] ?? 0);

            if (!$elfId) {
                echo "<p>No Elf profile linked to this account yet.</p>";
            } else {
                // Get the Elf's name.
                $statement = mysqli_prepare($dbc, "SELECT firstName, lastName FROM Elf WHERE pk_elf = ?");
                mysqli_stmt_bind_param($statement, "i", $elfId);
                mysqli_stmt_execute($statement);
                $elfResult = mysqli_stmt_get_result($statement);
                $elfRow = mysqli_fetch_assoc($elfResult);
                mysqli_free_result($elfResult);
                mysqli_stmt_close($statement);

                if ($elfRow) {
                    echo "<p>Welcome, <b>" .
                        htmlspecialchars($elfRow["firstName"], ENT_QUOTES, 'UTF-8') . " " .
                        htmlspecialchars($elfRow["lastName"], ENT_QUOTES, 'UTF-8') .
                        "</b>!</p>";
                }

                // Load the draw result, action details and partner wishlist.
                // Column names match CREATE_TABLES+SYSM.sql.
                $query = "SELECT Action.title,
                                 Action.targetValue,
                                 partnerElf.firstName AS partnerFirstName,
                                 partnerElf.lastName AS partnerLastName,
                                 Invite.wishDescription AS partnerWish
                          FROM Draw
                          JOIN Action ON Draw.pkfk_action = Action.pk_action
                          JOIN Elf AS partnerElf ON Draw.pkfk_elf_receives = partnerElf.pk_elf
                          JOIN Invite ON Invite.pkfk_action = Draw.pkfk_action
                                    AND Invite.pkfk_elf = Draw.pkfk_elf_receives
                          WHERE Draw.pkfk_elf_gives = ?";
                $statement = mysqli_prepare($dbc, $query);
                mysqli_stmt_bind_param($statement, "i", $elfId);
                mysqli_stmt_execute($statement);
                $result = mysqli_stmt_get_result($statement);

                if (mysqli_num_rows($result) === 0) {
                    echo "<p>You are not part of any completed draw yet.</p>";
                } else {
                    echo "<table>";
                    echo "<thead><tr><th>Action</th><th>Target Value</th><th>Your Partner</th><th>Their Wish</th></tr></thead>";
                    echo "<tbody>";

                    while ($row = mysqli_fetch_assoc($result)) {
                        $wish = $row["partnerWish"] ?: "No wishlist provided";
                        echo "<tr>
                                <td>" . htmlspecialchars($row["title"], ENT_QUOTES, 'UTF-8') . "</td>
                                <td>" . htmlspecialchars((string)$row["targetValue"], ENT_QUOTES, 'UTF-8') . " €</td>
                                <td>" . htmlspecialchars($row["partnerFirstName"] . " " . $row["partnerLastName"], ENT_QUOTES, 'UTF-8') . "</td>
                                <td>" . htmlspecialchars($wish, ENT_QUOTES, 'UTF-8') . "</td>
                              </tr>";
                    }

                    echo "</tbody></table>";
                }

                mysqli_free_result($result);
                mysqli_stmt_close($statement);
            }

            mysqli_close($dbc);
        }
    } else {
        echo "<p>Please log in first.</p>";
    }
    ?>

    <footer id="footerindex">© Wichtel-Website</footer>
</div>
</body>
