-- 1. ACTIONS
SELECT
    a.actionStatus AS status,
    COUNT(*) AS total,
    CASE
        WHEN (SELECT COUNT(*) FROM `Action`) = 0 THEN 0
        ELSE ROUND(
            COUNT(*) * 100.0 /
            (SELECT COUNT(*) FROM `Action`),
            2
        )
    END AS percent
FROM `Action` a
GROUP BY a.actionStatus
ORDER BY total DESC;

-- 2. ELFS
SELECT
    e.elfStatus AS status,
    COUNT(*) AS total,
    CASE
        WHEN (SELECT COUNT(*) FROM `Elf`) = 0 THEN 0
        ELSE ROUND(
            COUNT(*) * 100.0 /
            (SELECT COUNT(*) FROM `Elf`),
            2
        )
    END AS percent
FROM `Elf` e
GROUP BY e.elfStatus
ORDER BY total DESC;

-- 3. TOP 5 HEAD ELF
SELECT
    e.pk_elf AS id,
    e.lastName AS lastname,
    LEFT(e.firstName, 1) AS initial,
    COUNT(DISTINCT a.pk_action) AS actions,
    COUNT(i.pkfk_elf) AS participants
FROM `Elf` e
JOIN `Action` a
    ON a.fk_elf_create = e.pk_elf
LEFT JOIN `Invite` i
    ON i.pkfk_action = a.pk_action
   AND i.status = 'Accepted'
GROUP BY
    e.pk_elf,
    e.firstName,
    e.lastName
ORDER BY
    participants DESC,
    actions DESC
LIMIT 5;

-- 4. INVITATIONS
SELECT
    x.status,
    COUNT(i.pkfk_elf) AS total,
    CASE
        WHEN (
            SELECT COUNT(*)
            FROM `Invite`
            WHERE status IN ('Accepted', 'Declined')
        ) = 0 THEN 0
        ELSE ROUND(
            COUNT(i.pkfk_elf) * 100.0 /
            (
                SELECT COUNT(*)
                FROM `Invite`
                WHERE status IN ('Accepted', 'Declined')
            ),
            2
        )
    END AS percent
FROM
    (
        SELECT 'Accepted' AS status
        UNION ALL
        SELECT 'Declined' AS status
    ) x
LEFT JOIN `Invite` i
    ON i.status = x.status
GROUP BY x.status;

-- 5. TOP FINANCIAL ACTION
SELECT
    a.pk_action AS id,
    a.title AS title,
    a.targetValue AS value
FROM `Action` a
ORDER BY
    a.targetValue DESC,
    a.pk_action ASC
LIMIT 1;

-- 6. TOP PARTICIPATION ACTION
SELECT
    a.pk_action AS id,
    a.title AS title,
    COUNT(i.pkfk_elf) AS participants
FROM `Action` a
LEFT JOIN `Invite` i
    ON i.pkfk_action = a.pk_action
   AND i.status = 'Accepted'
GROUP BY
    a.pk_action,
    a.title
ORDER BY
    participants DESC,
    a.pk_action ASC
LIMIT 1;

-- 7. TOP 5 ACTION PARTICIPANTS
SELECT
    e.pk_elf AS id,
    e.lastName AS lastname,
    LEFT(e.firstName, 1) AS initial,
    COUNT(DISTINCT i.pkfk_action) AS actions
FROM `Elf` e
JOIN `Invite` i
    ON i.pkfk_elf = e.pk_elf
   AND i.status = 'Accepted'
GROUP BY
    e.pk_elf,
    e.firstName,
    e.lastName
ORDER BY
    actions DESC,
    e.lastName ASC
LIMIT 5;
