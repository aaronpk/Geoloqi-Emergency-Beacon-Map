CREATE TABLE `groups` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_name` VARCHAR(50) NOT NULL DEFAULT '',
  `sms_number` VARCHAR(20) NOT NULL DEFAULT '',
  `aim` VARCHAR(30) NOT NULL DEFAULT '',
  `jabber` VARCHAR(30) NOT NULL DEFAULT '',
  `twitter_username` VARCHAR(30) NOT NULL DEFAULT '',
  `twitter_token` VARCHAR(100) NOT NULL DEFAULT '',
  `twitter_secret` VARCHAR(100) NOT NULL DEFAULT '',
  `tropo_outbound_token` VARCHAR(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
);

CREATE TABLE `messages` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT(11) UNSIGNED NOT NULL DEFAULT '0',
  `user_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `date` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  `from` VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Phone number or Twitter username',
  `network` VARCHAR(50) NOT NULL DEFAULT '',
  `msg` VARCHAR(255) NOT NULL DEFAULT '',
  `lat` DOUBLE NOT NULL DEFAULT '0',
  `lng` DOUBLE NOT NULL DEFAULT '0',
  `remote_addr` VARCHAR(20) NOT NULL DEFAULT '',
  `is_join` TINYINT(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
);

CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL DEFAULT '0',
  `user` varchar(100) NOT NULL,
  `network` varchar(20) NOT NULL DEFAULT '' COMMENT 'SMS OR Jabber, see Tropo''s API',
  `date_subscribed` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_unsubscribed` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
);


