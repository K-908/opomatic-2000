-- Apply to an existing OpoMatic 2000 database before deploying the PHP change.
-- Widen the topic name and both historical snapshots; rerunning is safe.
ALTER TABLE `Topics` MODIFY COLUMN `Name` VARCHAR(300) NOT NULL;
ALTER TABLE `TestAttemptTopics` MODIFY COLUMN `TopicNameSnapshot` VARCHAR(300) NOT NULL;
ALTER TABLE `TestAttemptQuestions` MODIFY COLUMN `TopicNameSnapshot` VARCHAR(300) NOT NULL;
