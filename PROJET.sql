-- Dashboard statistics for Wichtel.
SELECT status, COUNT(*) AS total,
       ROUND(COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM Action), 0), 2) AS percentage
FROM Action GROUP BY status ORDER BY total DESC;

SELECT status, COUNT(*) AS total,
       ROUND(COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM Elf), 0), 2) AS percentage
FROM Elf GROUP BY status ORDER BY total DESC;

SELECT CONCAT(e.lastname, ', ', LEFT(e.firstName, 1), '.') AS headElfName,
       COUNT(i.pkfk_elf) AS totalParticipants
FROM Action a JOIN Elf e ON a.fk_elf_create = e.pk_elf
JOIN Invite i ON a.pk_action = i.pkfk_action
GROUP BY a.fk_elf_create, e.lastname, e.firstName
ORDER BY totalParticipants DESC LIMIT 5;

SELECT status, COUNT(*) AS total,
       ROUND(COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM Invite WHERE status IN ('Accepted', 'Declined')), 0), 2) AS percentage
FROM Invite WHERE status IN ('Accepted', 'Declined') GROUP BY status;

SELECT title AS actionTitle, targetValue AS topFinancialValue
FROM Action WHERE targetValue = (SELECT MAX(targetValue) FROM Action) LIMIT 1;

SELECT a.title AS actionTitle, COUNT(i.pkfk_elf) AS participantCount
FROM Action a JOIN Invite i ON a.pk_action = i.pkfk_action
WHERE i.status = 'Accepted' GROUP BY a.pk_action, a.title
ORDER BY participantCount DESC LIMIT 1;

SELECT CONCAT(e.lastname, ', ', LEFT(e.firstName, 1), '.') AS elfName,
       COUNT(i.pkfk_action) AS actionsJoined
FROM Invite i JOIN Elf e ON i.pkfk_elf = e.pk_elf
WHERE i.status = 'Accepted'
GROUP BY i.pkfk_elf, e.lastname, e.firstName
ORDER BY actionsJoined DESC LIMIT 5;
