-- Initial schema for the PHP port. Import into an empty MySQL 8.0.16+ or MariaDB 10.4+ database.
-- This does not work with the current ASP.NET API, which uses SQLite.
-- Timestamps are UTC; Score stores millionths of a point.
-- The PHP backend must insert password_hash() results into Users.PasswordHash.

CREATE TABLE `Users` (
  `Username` VARCHAR(100) NOT NULL,
  `PasswordHash` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`Username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `LoginAccesses` (
  `Id` BIGINT NOT NULL AUTO_INCREMENT,
  `Username` VARCHAR(100) NOT NULL,
  `AccessedAtUtc` DATETIME(6) NOT NULL,
  `IpAddress` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`Id`),
  KEY `IX_LoginAccesses_Username_AccessedAtUtc` (`Username`, `AccessedAtUtc` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Topics` (
  `Id` INT NOT NULL AUTO_INCREMENT,
  `Name` VARCHAR(150) NOT NULL,
  `IsActive` TINYINT(1) NOT NULL DEFAULT 1,
  `CreatedAtUtc` DATETIME(6) NOT NULL,
  `UpdatedAtUtc` DATETIME(6) NOT NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `UX_Topics_Name` (`Name`),
  KEY `IX_Topics_IsActive` (`IsActive`),
  CONSTRAINT `CK_Topics_Name_Trimmed` CHECK (CHAR_LENGTH(`Name`) = CHAR_LENGTH(TRIM(`Name`)) AND CHAR_LENGTH(`Name`) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Questions` (
  `Id` INT NOT NULL AUTO_INCREMENT,
  `TopicId` INT NOT NULL,
  `Statement` LONGTEXT NOT NULL,
  `IsActive` TINYINT(1) NOT NULL DEFAULT 1,
  `CreatedAtUtc` DATETIME(6) NOT NULL,
  `UpdatedAtUtc` DATETIME(6) NOT NULL,
  PRIMARY KEY (`Id`),
  KEY `IX_Questions_TopicId_IsActive` (`TopicId`, `IsActive`),
  CONSTRAINT `FK_Questions_Topics` FOREIGN KEY (`TopicId`) REFERENCES `Topics` (`Id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `QuestionOptions` (
  `Id` INT NOT NULL AUTO_INCREMENT,
  `QuestionId` INT NOT NULL,
  `Text` VARCHAR(1000) NOT NULL,
  `Position` TINYINT UNSIGNED NOT NULL,
  `IsCorrect` TINYINT(1) NOT NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `UX_QuestionOptions_QuestionId_Position` (`QuestionId`, `Position`),
  CONSTRAINT `FK_QuestionOptions_Questions` FOREIGN KEY (`QuestionId`) REFERENCES `Questions` (`Id`) ON DELETE RESTRICT,
  CONSTRAINT `CK_QuestionOptions_Position` CHECK (`Position` BETWEEN 1 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `TestAttempts` (
  `Id` BIGINT NOT NULL AUTO_INCREMENT,
  `SubmissionId` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `CompletedAtUtc` DATETIME(6) NOT NULL,
  `TotalQuestions` INT NOT NULL,
  `CorrectCount` INT NOT NULL,
  `IncorrectCount` INT NOT NULL,
  `UnansweredCount` INT NOT NULL,
  `Score` BIGINT NOT NULL,
  `ScoringRuleVersion` SMALLINT NOT NULL DEFAULT 1,
  `Username` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `UX_TestAttempts_SubmissionId` (`SubmissionId`),
  KEY `IX_TestAttempts_CompletedAtUtc` (`CompletedAtUtc` DESC),
  KEY `IX_TestAttempts_Username_CompletedAtUtc` (`Username`, `CompletedAtUtc` DESC),
  CONSTRAINT `CK_TestAttempts_TotalQuestions` CHECK (`TotalQuestions` > 0),
  CONSTRAINT `CK_TestAttempts_Counts_NonNegative` CHECK (`CorrectCount` >= 0 AND `IncorrectCount` >= 0 AND `UnansweredCount` >= 0),
  CONSTRAINT `CK_TestAttempts_Counts_Total` CHECK (`CorrectCount` + `IncorrectCount` + `UnansweredCount` = `TotalQuestions`),
  CONSTRAINT `CK_TestAttempts_Score` CHECK (`Score` BETWEEN -2500000 AND 10000000),
  CONSTRAINT `CK_TestAttempts_ScoringRuleVersion` CHECK (`ScoringRuleVersion` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `TestAttemptTopics` (
  `TestAttemptId` BIGINT NOT NULL,
  `OriginalTopicId` INT NOT NULL,
  `TopicNameSnapshot` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`TestAttemptId`, `OriginalTopicId`),
  KEY `IX_TestAttemptTopics_OriginalTopicId` (`OriginalTopicId`),
  CONSTRAINT `FK_TestAttemptTopics_TestAttempts` FOREIGN KEY (`TestAttemptId`) REFERENCES `TestAttempts` (`Id`) ON DELETE CASCADE,
  CONSTRAINT `FK_TestAttemptTopics_Topics` FOREIGN KEY (`OriginalTopicId`) REFERENCES `Topics` (`Id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `TestAttemptQuestions` (
  `Id` BIGINT NOT NULL AUTO_INCREMENT,
  `TestAttemptId` BIGINT NOT NULL,
  `OriginalQuestionId` INT NOT NULL,
  `OriginalTopicId` INT NOT NULL,
  `DisplayOrder` INT NOT NULL,
  `StatementSnapshot` LONGTEXT NOT NULL,
  `TopicNameSnapshot` VARCHAR(150) NOT NULL,
  `Result` TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `UX_TestAttemptQuestions_Attempt_DisplayOrder` (`TestAttemptId`, `DisplayOrder`),
  UNIQUE KEY `UX_TestAttemptQuestions_Attempt_OriginalQuestion` (`TestAttemptId`, `OriginalQuestionId`),
  KEY `IX_TestAttemptQuestions_OriginalQuestionId` (`OriginalQuestionId`),
  KEY `IX_TestAttemptQuestions_OriginalTopicId` (`OriginalTopicId`),
  KEY `IX_TestAttemptQuestions_Attempt_OriginalTopicId` (`TestAttemptId`, `OriginalTopicId`),
  CONSTRAINT `FK_TestAttemptQuestions_TestAttempts` FOREIGN KEY (`TestAttemptId`) REFERENCES `TestAttempts` (`Id`) ON DELETE CASCADE,
  CONSTRAINT `FK_TestAttemptQuestions_Questions` FOREIGN KEY (`OriginalQuestionId`) REFERENCES `Questions` (`Id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_TestAttemptQuestions_Topics` FOREIGN KEY (`OriginalTopicId`) REFERENCES `Topics` (`Id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_TestAttemptQuestions_AttemptTopics` FOREIGN KEY (`TestAttemptId`, `OriginalTopicId`) REFERENCES `TestAttemptTopics` (`TestAttemptId`, `OriginalTopicId`) ON DELETE NO ACTION,
  CONSTRAINT `CK_TestAttemptQuestions_DisplayOrder` CHECK (`DisplayOrder` > 0),
  CONSTRAINT `CK_TestAttemptQuestions_Result` CHECK (`Result` BETWEEN 0 AND 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `TestAttemptOptions` (
  `Id` BIGINT NOT NULL AUTO_INCREMENT,
  `TestAttemptQuestionId` BIGINT NOT NULL,
  `OriginalOptionId` INT NOT NULL,
  `TextSnapshot` VARCHAR(1000) NOT NULL,
  `DisplayOrder` TINYINT UNSIGNED NOT NULL,
  `IsCorrect` TINYINT(1) NOT NULL,
  `IsSelected` TINYINT(1) NOT NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `UX_TestAttemptOptions_Question_DisplayOrder` (`TestAttemptQuestionId`, `DisplayOrder`),
  UNIQUE KEY `UX_TestAttemptOptions_Question_OriginalOption` (`TestAttemptQuestionId`, `OriginalOptionId`),
  KEY `IX_TestAttemptOptions_OriginalOptionId` (`OriginalOptionId`),
  CONSTRAINT `FK_TestAttemptOptions_AttemptQuestions` FOREIGN KEY (`TestAttemptQuestionId`) REFERENCES `TestAttemptQuestions` (`Id`) ON DELETE CASCADE,
  CONSTRAINT `FK_TestAttemptOptions_QuestionOptions` FOREIGN KEY (`OriginalOptionId`) REFERENCES `QuestionOptions` (`Id`) ON DELETE RESTRICT,
  CONSTRAINT `CK_TestAttemptOptions_DisplayOrder` CHECK (`DisplayOrder` BETWEEN 1 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
