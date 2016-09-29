SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE `cc` (
  `id` varchar(10) NOT NULL,
  `group_id` bigint(20) NOT NULL,
  `group_name` text NOT NULL,
  `user_token` text NOT NULL,
  `bot_id` text NOT NULL,
  `admin_id` bigint(20) NOT NULL,
  `admin_name` text NOT NULL,
  `admins` text NOT NULL,
  `clan_name` text NOT NULL,
  `clan_tag` text NOT NULL,
  `archive` tinyint(1) NOT NULL,
  `stacked_calls` tinyint(1) NOT NULL,
  `call_timer` int(11) NOT NULL,
  `cc` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


ALTER TABLE `cc`
  ADD PRIMARY KEY (`id`);
