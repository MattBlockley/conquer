-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE IF NOT EXISTS `areas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `region_id` int(10) unsigned NOT NULL,
  `name` varchar(32) NOT NULL,
  `coordinates` text NOT NULL,
  `army_coordinates` varchar(32) DEFAULT NULL,
  `manpower` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=265 ;

-- --------------------------------------------------------

--
-- Table structure for table `armies`
--

CREATE TABLE IF NOT EXISTS `armies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `faction_id` int(10) unsigned NOT NULL,
  `game_id` int(10) unsigned NOT NULL,
  `game_area_id` int(10) unsigned NOT NULL,
  `type` enum('air','land') NOT NULL,
  `name` varchar(64) NOT NULL,
  `reserve` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `disbanded` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `user_id` (`faction_id`),
  KEY `game_id` (`game_id`),
  KEY `game_area_id` (`game_area_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=289 ;

-- --------------------------------------------------------

--
-- Table structure for table `army_actions`
--

CREATE TABLE IF NOT EXISTS `army_actions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` int(10) unsigned NOT NULL,
  `army_id` int(10) unsigned NOT NULL,
  `target_id` int(10) unsigned NOT NULL,
  `action` enum('attack','support_attack','move','redeployment','retreat','defend') NOT NULL,
  `action_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  KEY `army_id` (`army_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=585 ;

-- --------------------------------------------------------

--
-- Table structure for table `battles`
--

CREATE TABLE IF NOT EXISTS `battles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` int(10) unsigned NOT NULL,
  `game_area_id` int(10) unsigned NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `battle_name` varchar(64) NOT NULL,
  `battle_type` enum('Assault','Air Strike','Artillery Barrage') NOT NULL,
  `current_progress` float(8,2) unsigned NOT NULL,
  `result` enum('Attacker','Defender','InProgress','Starting','Aborted') NOT NULL DEFAULT 'Starting',
  `attacking_casualties` mediumint(8) unsigned DEFAULT NULL,
  `defending_casualties` mediumint(8) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  KEY `game_area_id` (`game_area_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=69 ;

-- --------------------------------------------------------

--
-- Table structure for table `battle_armies`
--

CREATE TABLE IF NOT EXISTS `battle_armies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `battle_id` int(10) unsigned NOT NULL,
  `army_id` int(10) unsigned NOT NULL,
  `side` enum('attacker','defender') NOT NULL,
  `joined_time` datetime NOT NULL,
  `left_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `battle_id` (`battle_id`),
  KEY `army_id` (`army_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=339 ;

-- --------------------------------------------------------

--
-- Table structure for table `borders`
--

CREATE TABLE IF NOT EXISTS `borders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `area_1_id` int(10) unsigned NOT NULL,
  `area_2_id` int(10) unsigned NOT NULL,
  `distance` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `area_1_id_2` (`area_1_id`,`area_2_id`),
  KEY `area_1_id` (`area_1_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1203 ;

-- --------------------------------------------------------

--
-- Table structure for table `chats`
--

CREATE TABLE IF NOT EXISTS `chats` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `status` enum('active','pending','inactive') NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

-- --------------------------------------------------------

--
-- Table structure for table `chat_contents`
--

CREATE TABLE IF NOT EXISTS `chat_contents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` int(10) unsigned NOT NULL,
  `chat_user_id` int(10) unsigned NOT NULL,
  `content` text NOT NULL,
  `posted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `unread` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=853 ;

-- --------------------------------------------------------

--
-- Table structure for table `chat_users`
--

CREATE TABLE IF NOT EXISTS `chat_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `joined` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `departed` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `status` enum('idle','typing','typed') NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=7 ;

-- --------------------------------------------------------

--
-- Table structure for table `factions`
--

CREATE TABLE IF NOT EXISTS `factions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `abbreviation` varchar(4) NOT NULL,
  `colour` varchar(6) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE IF NOT EXISTS `games` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `theatre_id` int(10) unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `game_date` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `paused` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=6 ;

-- --------------------------------------------------------

--
-- Table structure for table `game_areas`
--

CREATE TABLE IF NOT EXISTS `game_areas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` int(10) unsigned NOT NULL,
  `faction_id` int(10) unsigned NOT NULL,
  `area_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1321 ;

-- --------------------------------------------------------

--
-- Table structure for table `game_histories`
--

CREATE TABLE IF NOT EXISTS `game_histories` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `faction_id` int(11) unsigned NOT NULL,
  `game_id` int(11) unsigned NOT NULL,
  `history_id` int(10) unsigned NOT NULL,
  `history_date` datetime NOT NULL,
  `value` varchar(255) NOT NULL,
  `unread` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `faction_id` (`faction_id`,`game_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1231 ;

-- --------------------------------------------------------

--
-- Table structure for table `game_history_values`
--

CREATE TABLE IF NOT EXISTS `game_history_values` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `game_history_id` int(10) unsigned NOT NULL,
  `field` varchar(32) NOT NULL,
  `value` varchar(64) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `game_history_id` (`game_history_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5735 ;

-- --------------------------------------------------------

--
-- Table structure for table `game_structures`
--

CREATE TABLE IF NOT EXISTS `game_structures` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` int(10) unsigned NOT NULL,
  `game_area_id` int(10) unsigned NOT NULL,
  `structure_id` int(10) unsigned NOT NULL,
  `health` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  KEY `game_area_id` (`game_area_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1050 ;

-- --------------------------------------------------------

--
-- Table structure for table `game_units`
--

CREATE TABLE IF NOT EXISTS `game_units` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` int(10) unsigned NOT NULL,
  `army_id` int(10) unsigned NOT NULL,
  `unit_id` int(10) unsigned NOT NULL,
  `strength` smallint(5) unsigned NOT NULL,
  `organisation` smallint(5) unsigned NOT NULL,
  `disbanded` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  KEY `army_id` (`army_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1126 ;

-- --------------------------------------------------------

--
-- Table structure for table `histories`
--

CREATE TABLE IF NOT EXISTS `histories` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=27 ;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` int(10) unsigned NOT NULL,
  `recipient_id` int(10) unsigned NOT NULL,
  `sent_time` datetime NOT NULL,
  `title` varchar(64) NOT NULL,
  `content` text NOT NULL,
  `replied` tinyint(1) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `recipient_id` (`recipient_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `regions`
--

CREATE TABLE IF NOT EXISTS `regions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `theatre_id` int(10) unsigned NOT NULL,
  `faction_id` int(10) unsigned NOT NULL,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=18 ;

-- --------------------------------------------------------

--
-- Table structure for table `structures`
--

CREATE TABLE IF NOT EXISTS `structures` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `faction_id` int(10) unsigned NOT NULL,
  `structure_class_id` int(10) unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  `cost` smallint(5) unsigned NOT NULL,
  `development_time` smallint(5) unsigned NOT NULL,
  `armour` smallint(5) unsigned NOT NULL,
  `health` smallint(5) unsigned NOT NULL,
  `damage` smallint(5) unsigned NOT NULL,
  `initiative` tinyint(3) unsigned NOT NULL,
  `power` smallint(6) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=16 ;

-- --------------------------------------------------------

--
-- Table structure for table `structure_classes`
--

CREATE TABLE IF NOT EXISTS `structure_classes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=9 ;

-- --------------------------------------------------------

--
-- Table structure for table `structure_developments`
--

CREATE TABLE IF NOT EXISTS `structure_developments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `game_area_id` int(10) unsigned NOT NULL,
  `faction_id` int(10) unsigned NOT NULL,
  `structure_id` int(10) unsigned NOT NULL,
  `priority` tinyint(3) unsigned NOT NULL,
  `completion_date` datetime NOT NULL,
  `progressed` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `game_area_id` (`game_area_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

-- --------------------------------------------------------

--
-- Table structure for table `structure_prerequisites`
--

CREATE TABLE IF NOT EXISTS `structure_prerequisites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `structure_id` int(10) unsigned NOT NULL,
  `prerequisite_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=16 ;

-- --------------------------------------------------------

--
-- Table structure for table `theatres`
--

CREATE TABLE IF NOT EXISTS `theatres` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `active` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

-- --------------------------------------------------------

--
-- Table structure for table `theatre_factions`
--

CREATE TABLE IF NOT EXISTS `theatre_factions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `theatre_id` int(10) unsigned NOT NULL,
  `faction_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE IF NOT EXISTS `units` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `faction_id` int(10) unsigned NOT NULL,
  `unit_class_id` int(10) unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  `cost` smallint(5) unsigned NOT NULL,
  `manpower` tinyint(3) unsigned NOT NULL,
  `supply_consumption` smallint(5) unsigned NOT NULL,
  `fuel_consumption` smallint(5) unsigned NOT NULL,
  `development_time` tinyint(3) unsigned NOT NULL,
  `defensiveness` tinyint(3) unsigned NOT NULL,
  `toughness` tinyint(3) unsigned NOT NULL,
  `air_defence` tinyint(3) unsigned NOT NULL,
  `softness` tinyint(3) unsigned NOT NULL,
  `soft_attack` tinyint(3) unsigned NOT NULL,
  `hard_attack` tinyint(3) unsigned NOT NULL,
  `air_attack` tinyint(3) unsigned NOT NULL,
  `speed` tinyint(3) unsigned NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=28 ;

-- --------------------------------------------------------

--
-- Table structure for table `unit_classes`
--

CREATE TABLE IF NOT EXISTS `unit_classes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=9 ;

-- --------------------------------------------------------

--
-- Table structure for table `unit_developments`
--

CREATE TABLE IF NOT EXISTS `unit_developments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `game_area_id` int(10) unsigned NOT NULL,
  `faction_id` int(10) unsigned NOT NULL,
  `unit_id` int(10) unsigned NOT NULL,
  `priority` tinyint(3) unsigned NOT NULL,
  `completion_date` datetime NOT NULL,
  `progressed` smallint(5) unsigned NOT NULL DEFAULT '0',
  `type` enum('air','land') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `game_area_id` (`game_area_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=51 ;

-- --------------------------------------------------------

--
-- Table structure for table `unit_prerequisites`
--

CREATE TABLE IF NOT EXISTS `unit_prerequisites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` int(10) unsigned NOT NULL,
  `prerequisite_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=62 ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL,
  `password` varchar(32) NOT NULL,
  `handle` varchar(32) NOT NULL,
  `first_name` varchar(64) NOT NULL,
  `last_name` varchar(64) NOT NULL,
  `email` varchar(255) NOT NULL,
  `admin` char(1) NOT NULL DEFAULT '0',
  `status` enum('online','idle','busy','invisible','offline') NOT NULL DEFAULT 'offline',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=6 ;

-- --------------------------------------------------------

--
-- Table structure for table `user_games`
--

CREATE TABLE IF NOT EXISTS `user_games` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `game_id` int(10) unsigned NOT NULL,
  `faction_id` int(10) unsigned NOT NULL,
  `money` mediumint(8) unsigned NOT NULL,
  `supplies` mediumint(8) unsigned NOT NULL,
  `fuel` mediumint(8) unsigned NOT NULL,
  `manpower` float(6,2) unsigned NOT NULL,
  `status` enum('open','active','left','defeated','won') NOT NULL DEFAULT 'open',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `game_id` (`game_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=11 ;
