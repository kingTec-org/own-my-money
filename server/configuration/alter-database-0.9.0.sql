ALTER TABLE `transaction` ADD `isRecurring` BOOLEAN NOT NULL DEFAULT FALSE AFTER `note`;

UPDATE `version` SET `version` = '0.9.0';
