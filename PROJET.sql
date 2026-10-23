-- =============================================================
-- WSERS2 - Secret Santa : Dashboard SQL Queries (DQL)
-- =============================================================
-- Status columns already exist in CREATE_TABLES+SYSM.sql.
-- Action.actionStatus : 'Draft' | 'Started' | 'ReadyForDraw'
--                       | 'Drawn' | 'Closed' | 'Cancelled'
-- Elf.elfStatus        : 'New' | 'Active' | 'Suspended' | 'Unsubscribed'
-- Invite.status        : 'AddedToList' | 'Invited' | 'Accepted'
--                       | 'Declined' | 'Cancelled'
-- =============================================================


-- -------------------------------------------------------------
-- 1.  ACTIONS  –  Pie chart
--     Shows the percentage breakdown of all action statuses.
-- -------------------------------------------------------------
SELECT
    actionStatus AS status,
    COUNT(*)                                                       AS total,
    ROUND(COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM Action), 0), 2) AS percentage
FROM   Action
GROUP  BY actionStatus
ORDER  BY total DESC;


-- -------------------------------------------------------------
-- 2.  ELFS  –  Pie chart
--     Shows the percentage breakdown of all elf statuses.
-- -------------------------------------------------------------
SELECT
    elfStatus AS status,
    COUNT(*)                                                    AS total,
    ROUND(COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM Elf), 0), 2) AS percentage
FROM   Elf
GROUP  BY elfStatus
ORDER  BY total DESC;


-- -------------------------------------------------------------
-- 3.  TOP 5 HEADELF  –  Horizontal bar chart
--     Ranks the 5 head elves who have run the largest actions,
--     measured as the SUM of all participants (any invite status)
--     across ALL actions they created.
--     Name format : "Lastname, F."
-- -------------------------------------------------------------
SELECT
    CONCAT(e.lastname, ', ', LEFT(e.firstName, 1), '.')   AS headElfName,
    COUNT(i.pkfk_elf)                                      AS totalParticipants
FROM   Action   a
JOIN   Elf      e  ON  a.fk_elf_create = e.pk_elf
JOIN   Invite   i  ON  a.pk_action     = i.pkfk_action
GROUP  BY
    a.fk_elf_create,
    e.lastname,
    e.firstName
ORDER  BY totalParticipants DESC
LIMIT  5;


-- -------------------------------------------------------------
-- 4.  INVITATIONS  –  Horizontal bar chart
--     Percentage of accepted vs declined invitations.
--     (Pending / Cancelled invites are excluded.)
-- -------------------------------------------------------------
SELECT
    status,
    COUNT(*) AS total,
    ROUND(
        COUNT(*) * 100.0
        / (SELECT COUNT(*) FROM Invite WHERE status IN ('Accepted', 'Declined')),
        2
    )        AS percentage
FROM   Invite
WHERE  status IN ('Accepted', 'Declined')
GROUP  BY status;


-- -------------------------------------------------------------
-- 5.  TOP FINANCIAL ACTION  –  Single value (KPI card)
--     Highest gift target value (targetValue) across all actions.
-- -------------------------------------------------------------
SELECT
    a.title            AS actionTitle,
    a.targetValue      AS topFinancialValue
FROM   Action a
WHERE  a.targetValue = (SELECT MAX(targetValue) FROM Action)
LIMIT  1;


-- -------------------------------------------------------------
-- 6.  TOP PARTICIPATION ACTION  –  Single value (KPI card)
--     Action with the most accepted participants.
-- -------------------------------------------------------------
SELECT
    a.title                 AS actionTitle,
    COUNT(i.pkfk_elf)       AS participantCount
FROM   Action   a
JOIN   Invite   i  ON  a.pk_action = i.pkfk_action
WHERE  i.status = 'Accepted'
GROUP  BY a.pk_action, a.title
ORDER  BY participantCount DESC
LIMIT  1;


-- -------------------------------------------------------------
-- 7.  TOP 5 ACTION PARTICIPANTS  –  Horizontal bar chart
--     Top 5 elves ranked by the number of ACCEPTED actions
--     they joined.
--     Name format : "Lastname, F."
-- -------------------------------------------------------------
SELECT
    CONCAT(e.lastname, ', ', LEFT(e.firstName, 1), '.')  AS elfName,
    COUNT(i.pkfk_action)                                  AS actionsJoined
FROM   Invite  i
JOIN   Elf     e  ON  i.pkfk_elf = e.pk_elf
WHERE  i.status = 'Accepted'
GROUP  BY
    i.pkfk_elf,
    e.lastname,
    e.firstName
ORDER  BY actionsJoined DESC
LIMIT  5;


-- -------------------------------------------------------------
-- 8.  TOP 5 CHAT SPEAKER  (Optional – needs a Chat table)
--     Uncomment and adapt once you have a chat feature.
-- -------------------------------------------------------------
/*
SELECT
    CONCAT(e.lastname, ', ', LEFT(e.firstName, 1), '.')  AS elfName,
    COUNT(c.pk_message)                                   AS messageCount
FROM   ChatMessage  c
JOIN   Elf          e  ON  c.fk_elf = e.pk_elf
GROUP  BY
    c.fk_elf,
    e.lastname,
    e.firstName
ORDER  BY messageCount DESC
LIMIT  5;
*/
