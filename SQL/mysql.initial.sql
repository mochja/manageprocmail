-- Adminer 4.7.1 MySQL dump

SET NAMES utf8;

CREATE TABLE `manageprocmail_filters` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8 DEFAULT '',
  `forward_to` text CHARACTER SET utf8,
  `move_to` text CHARACTER SET utf8,
  `copy_to` text CHARACTER SET utf8,
  `created` datetime DEFAULT '1000-01-01 00:00:00',
  `enabled` int(11) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `manageprocmail_filters_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


CREATE TABLE `manageprocmail_rules` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `filter_id` int(11) unsigned NOT NULL,
  `type` varchar(255) CHARACTER SET utf8 NOT NULL,
  `op` int(11) NOT NULL,
  `against` text CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `filter_id` (`filter_id`),
  CONSTRAINT `manageprocmail_rules_ibfk_1` FOREIGN KEY (`filter_id`) REFERENCES `manageprocmail_filters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


CREATE TABLE `manageprocmail_vacations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `from` date NOT NULL,
  `to` date NOT NULL,
  `exceptions` text CHARACTER SET utf8,
  `subject` text CHARACTER SET utf8 NOT NULL,
  `reason` text CHARACTER SET utf8 NOT NULL,
  `ingorelist` int(11) NOT NULL DEFAULT '0',
  `days` int(11) NOT NULL DEFAULT '7',
  `enabled` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `manageprocmail_vacations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


-- 2019-05-13 19:43:26
