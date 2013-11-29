DROP TABLE IF EXISTS `games`;
CREATE TABLE `games` (
  `game_id` varchar(8) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `time_created` int(11) NOT NULL DEFAULT '0',
  `status` varchar(50) NOT NULL DEFAULT 'Pre-game',
  `scenario` varchar(50) NOT NULL DEFAULT '',
  `game_data` blob,
  UNIQUE KEY `game_id` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `game_id` varchar(8) NOT NULL,
  `type` ENUM('message','chat','alert','error') DEFAULT 'message',
  `recipients` varchar(255) DEFAULT NULL,
  `message` varchar(1024) DEFAULT NULL,
  `time_created` double NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `players`;
CREATE TABLE `players` (
  `game_id` varchar(8) NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `party_name` varchar(255) NOT NULL DEFAULT '',
  `time_joined` int(11) NOT NULL DEFAULT '0',
  UNIQUE KEY `game_id` (`game_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `saved_games`;
CREATE TABLE `saved_games` (
  `game_id` varchar(8) NOT NULL,
  `time_saved` double NOT NULL DEFAULT '0',
  `turn` int(3) unsigned NOT NULL DEFAULT 1,
  `phase` varchar(64) NOT NULL DEFAULT '',
  `subPhase` varchar(64) NOT NULL DEFAULT '',
  `game_data` blob
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `salt` varchar(255) NOT NULL DEFAULT '',
  `roles` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL DEFAULT '',
  `time_created` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

