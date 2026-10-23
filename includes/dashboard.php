<?php
session_start();

if (isset($_POST["logout"])) {
    session_unset();
    session_destroy();
    header("Location: index.php?page=login");
    exit();
}

if (!isset($_SESSION["DATA_ADMIN"])) {
    header("Location: index.php?page=login");
    exit();
}

$dbc = f_connect(false);

if (!$dbc) {
    exit("Database connection failed.");
}

$escape = function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
};

$getRows = function ($sql) use ($dbc) {
    $statement = mysqli_prepare($dbc, $sql);

    if (!$statement) {
        throw new Exception(mysqli_error($dbc));
    }

    if (!mysqli_stmt_execute($statement)) {
        throw new Exception(mysqli_stmt_error($statement));
    }

    $result = mysqli_stmt_get_result($statement);
    $rows = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    return $rows;
};

$statusValue = function ($rows, $status, $field) {
    foreach ($rows as $row) {
        if (strcasecmp($row["status"], $status) === 0) {
            return (float)$row[$field];
        }
    }

    return 0;
};

$maximum = function ($rows, $field) {
    $max = 1;

    foreach ($rows as $row) {
        if ((int)$row[$field] > $max) {
            $max = (int)$row[$field];
        }
    }

    return $max;
};

$error = "";

try {
    $actions = $getRows("
        SELECT actionStatus AS status,
               COUNT(*) AS total,
               ROUND(COUNT(*) * 100.0 /
               NULLIF((SELECT COUNT(*) FROM `Action`), 0), 2) AS percent
        FROM `Action`
        GROUP BY actionStatus
    ");

    $elves = $getRows("
        SELECT elfStatus AS status,
               COUNT(*) AS total,
               ROUND(COUNT(*) * 100.0 /
               NULLIF((SELECT COUNT(*) FROM `Elf`), 0), 2) AS percent
        FROM `Elf`
        GROUP BY elfStatus
    ");

    $headElves = $getRows("
        SELECT e.lastName AS lastname,
               LEFT(e.firstName, 1) AS initial,
               COUNT(DISTINCT a.pk_action) AS actions,
               COUNT(i.pkfk_elf) AS participants
        FROM `Elf` e
        JOIN `Action` a ON a.fk_elf_create = e.pk_elf
        LEFT JOIN `Invite` i
          ON i.pkfk_action = a.pk_action
         AND i.status = 'Accepted'
        GROUP BY e.pk_elf, e.firstName, e.lastName
        ORDER BY participants DESC, actions DESC
        LIMIT 5
    ");

    $invitations = $getRows("
        SELECT statuses.status,
               COUNT(i.pkfk_elf) AS total,
               ROUND(
                   COUNT(i.pkfk_elf) * 100.0 /
                   NULLIF((
                       SELECT COUNT(*)
                       FROM `Invite`
                       WHERE status IN ('Accepted','Declined')
                   ), 0),
                   2
               ) AS percent
        FROM (
            SELECT 'Accepted' AS status
            UNION ALL
            SELECT 'Declined' AS status
        ) statuses
        LEFT JOIN `Invite` i ON i.status = statuses.status
        GROUP BY statuses.status
    ");

    $financialRows = $getRows("
        SELECT title, targetValue AS value
        FROM `Action`
        ORDER BY targetValue DESC, pk_action ASC
        LIMIT 1
    ");

    $participationRows = $getRows("
        SELECT a.title,
               COUNT(i.pkfk_elf) AS participants
        FROM `Action` a
        LEFT JOIN `Invite` i
          ON i.pkfk_action = a.pk_action
         AND i.status = 'Accepted'
        GROUP BY a.pk_action, a.title
        ORDER BY participants DESC, a.pk_action ASC
        LIMIT 1
    ");

    $participants = $getRows("
        SELECT e.lastName AS lastname,
               LEFT(e.firstName, 1) AS initial,
               COUNT(DISTINCT i.pkfk_action) AS actions
        FROM `Elf` e
        JOIN `Invite` i
          ON i.pkfk_elf = e.pk_elf
         AND i.status = 'Accepted'
        GROUP BY e.pk_elf, e.firstName, e.lastName
        ORDER BY actions DESC, e.lastName ASC
        LIMIT 5
    ");
} catch (Exception $exception) {
    $error = $exception->getMessage();
    $actions = $elves = $headElves = $invitations = $participants = array();
    $financialRows = $participationRows = array();
}

mysqli_close($dbc);

$financial = isset($financialRows[0]) ? $financialRows[0] : null;
$participation = isset($participationRows[0]) ? $participationRows[0] : null;

// The schema uses Started, ReadyForDraw and Drawn for active actions.
$activeAction = $statusValue($actions, "Started", "percent")
    + $statusValue($actions, "ReadyForDraw", "percent")
    + $statusValue($actions, "Drawn", "percent");
$closedAction = $statusValue($actions, "Closed", "percent");
$actionSecond = min(100, $activeAction + $closedAction);

$suspendedElf = $statusValue($elves, "Suspended", "percent");
$activeElf = $statusValue($elves, "Active", "percent");
$newElf = $statusValue($elves, "New", "percent");

$elfSecond = $suspendedElf + $activeElf;
$elfThird = $elfSecond + $newElf;

$accepted = $statusValue($invitations, "Accepted", "percent");
$declined = $statusValue($invitations, "Declined", "percent");

$headMaximum = $maximum($headElves, "participants");
$participantMaximum = $maximum($participants, "actions");
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secret-Santa Dashboard</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 12px;
            background: white;
            font-family: Arial, sans-serif;
            color: #111;
        }

        .dashboard {
            max-width: 1150px;
            margin: auto;
            padding: 12px;
            border: 1px solid #55963b;
        }

        h1 {
            margin: 0 0 38px;
            text-align: center;
            color: #18a5df;
            font-size: 32px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 28px 14px;
        }

        .card {
            position: relative;
            grid-column: span 2;
            min-height: 165px;
            padding: 25px 12px 12px;
            border: 1px solid #666;
            background: white;
        }

        .wide { grid-column: span 3; }
        .small { min-height: 90px; }

        .card h2 {
            position: absolute;
            top: -17px;
            left: 50%;
            margin: 0;
            padding: 0 7px;
            transform: translateX(-50%);
            background: white;
            font-size: 20px;
            white-space: nowrap;
        }

        .pie-box {
            position: relative;
            height: 125px;
        }

        .pie {
            position: absolute;
            left: 50%;
            top: 2px;
            width: 122px;
            height: 122px;
            transform: translateX(-50%);
            border: 2px solid #17a8e6;
            border-radius: 50%;
        }

        .action-pie {
            background: conic-gradient(
            #aaa 0 <?= $activeAction ?>%,
            #d0d0d0 <?= $activeAction ?>% <?= $actionSecond ?>%,
            #eee <?= $actionSecond ?>% 100%
            );
        }

        .elf-pie {
            background: conic-gradient(
            #aaa 0 <?= $suspendedElf ?>%,
            #bbb <?= $suspendedElf ?>% <?= $elfSecond ?>%,
            #ddd <?= $elfSecond ?>% <?= $elfThird ?>%,
            #eee <?= $elfThird ?>% 100%
            );
        }

        .label {
            position: absolute;
            color: #666;
            font-size: 13px;
        }

        .top-left { top: 0; left: 2px; }
        .top-right { top: 0; right: 2px; }
        .bottom-left { bottom: 3px; left: 2px; }
        .bottom-right { bottom: 3px; right: 2px; }

        .bars {
            position: relative;
            padding: 2px 0 16px;
        }

        .bar-row {
            display: grid;
            grid-template-columns: 78px 1fr 32px;
            align-items: center;
            gap: 4px;
            margin: 6px 0;
        }

        .name {
            overflow: hidden;
            font-size: 11px;
            text-align: right;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .track {
            height: 18px;
            border-left: 2px solid #222;
        }

        .fill {
            height: 18px;
            border: 2px solid #222;
            background: repeating-linear-gradient(
                    45deg,
                    #17a8e6 0,
                    #17a8e6 3px,
                    white 3px,
                    white 6px
            );
        }

        .value {
            color: #169fd8;
            font-weight: bold;
            font-size: 13px;
        }

        .invite-bar {
            display: flex;
            height: 40px;
            overflow: hidden;
            border: 2px solid #222;
            border-radius: 7px;
        }

        .accepted,
        .declined {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            font-weight: bold;
        }

        .accepted {
            color: #14749e;
            background: repeating-linear-gradient(
                    45deg,
                    #17a8e6 0,
                    #17a8e6 3px,
                    white 3px,
                    white 7px
            );
        }

        .declined {
            color: #df1f30;
            background: repeating-linear-gradient(
                    45deg,
                    #ef2736 0,
                    #ef2736 3px,
                    white 3px,
                    white 7px
            );
        }

        .invite-labels {
            display: flex;
            justify-content: space-around;
            margin-top: 5px;
            font-size: 10px;
        }

        .big-number {
            display: flex;
            min-height: 53px;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: bold;
        }

        .big-number small {
            max-width: 90%;
            overflow: hidden;
            color: #666;
            font-size: 11px;
            font-weight: normal;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .controls {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 15px;
        }

        .button {
            padding: 8px 12px;
            border: 0;
            border-radius: 3px;
            background: #18a5df;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }

        .logout { background: #d23b3b; }

        .error {
            padding: 12px;
            border: 1px solid red;
            color: darkred;
        }

        @media (max-width: 850px) {
            .card,
            .wide {
                grid-column: span 6;
            }
        }
    </style>
</head>

<body>
<main class="dashboard">
    <h1>Secret-Santa Dashboard</h1>

    <?php if ($error !== "") { ?>
        <p class="error">
            SQL error: <?= $escape($error) ?>
        </p>
    <?php } else { ?>

        <div class="grid">
            <section class="card">
                <h2>Actions</h2>

                <div class="pie-box">
                    <span class="label top-left">Archived</span>
                    <span class="label top-right">Active</span>
                    <span class="label bottom-left">Closed</span>
                    <div class="pie action-pie"></div>
                </div>
            </section>

            <section class="card">
                <h2>Elfs</h2>

                <div class="pie-box">
                    <span class="label top-left">Unsubscribed</span>
                    <span class="label top-right">Suspended</span>
                    <span class="label bottom-left">New</span>
                    <span class="label bottom-right">Active</span>
                    <div class="pie elf-pie"></div>
                </div>
            </section>

            <section class="card">
                <h2>Top 5 HeadElf</h2>

                <div class="bars">
                    <?php foreach ($headElves as $elf) {
                        $width = ((int)$elf["participants"] / $headMaximum) * 100;
                        ?>
                        <div class="bar-row">
                            <div class="name">
                                <?= $escape($elf["lastname"] . ", " . $elf["initial"] . ".") ?>
                            </div>

                            <div class="track">
                                <div class="fill" style="width:<?= $width ?>%"></div>
                            </div>

                            <div class="value">
                                <?= $escape($elf["participants"]) ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </section>

            <section class="card small">
                <h2>Invitations</h2>

                <div class="invite-bar">
                    <div class="accepted" style="width:<?= $accepted ?>%">
                        <?= round($accepted) ?>%
                    </div>

                    <div class="declined" style="width:<?= $declined ?>%">
                        <?= round($declined) ?>%
                    </div>
                </div>

                <div class="invite-labels">
                    <span>Accepted</span>
                    <span>Declined</span>
                </div>
            </section>

            <section class="card small">
                <h2>Top Financial Action</h2>

                <div class="big-number">
                    <?= $financial ? $escape($financial["value"]) : "0" ?> EUR

                    <?php if ($financial) { ?>
                        <small><?= $escape($financial["title"]) ?></small>
                    <?php } ?>
                </div>
            </section>

            <section class="card small">
                <h2>Top Participation Action</h2>

                <div class="big-number">
                    <?= $participation ? $escape($participation["participants"]) : "0" ?> Elfs

                    <?php if ($participation) { ?>
                        <small><?= $escape($participation["title"]) ?></small>
                    <?php } ?>
                </div>
            </section>

            <section class="card wide">
                <h2>Top 5 Action Participants</h2>

                <div class="bars">
                    <?php foreach ($participants as $elf) {
                        $width = ((int)$elf["actions"] / $participantMaximum) * 100;
                        ?>
                        <div class="bar-row">
                            <div class="name">
                                <?= $escape($elf["lastname"] . ", " . $elf["initial"] . ".") ?>
                            </div>

                            <div class="track">
                                <div class="fill" style="width:<?= $width ?>%"></div>
                            </div>

                            <div class="value">
                                <?= $escape($elf["actions"]) ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </section>

            <section class="card wide">
                <h2>Top 5 Chat Speaker</h2>

                <div class="big-number">
                    <small>No chat SQL was provided.</small>
                </div>
            </section>
        </div>
    <?php } ?>

    <div class="controls">
        <a href="index.php?page=admin_overview" class="button">
            Back
        </a>

        <form method="POST">
            <button type="submit" name="logout" class="button logout">
                Logout
            </button>
        </form>
    </div>
</main>
</body>
</html>
