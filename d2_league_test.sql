-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Хост: 127.0.0.1
-- Время создания: Окт 29 2017 г., 14:26
-- Версия сервера: 10.1.16-MariaDB
-- Версия PHP: 5.6.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `d2_league_test`
--
CREATE DATABASE IF NOT EXISTS `d2_league_test` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `d2_league_test`;

-- --------------------------------------------------------

--
-- Структура таблицы `adv_matchlines`
--

DROP TABLE IF EXISTS `adv_matchlines`;
CREATE TABLE `adv_matchlines` (
  `matchid` int(10) UNSIGNED NOT NULL,
  `playerid` int(10) UNSIGNED NOT NULL,
  `heroid` smallint(5) UNSIGNED NOT NULL,
  `lh_at10` tinyint(3) UNSIGNED NOT NULL,
  `isCore` tinyint(1) NOT NULL,
  `lane` tinyint(3) UNSIGNED NOT NULL,
  `efficiency_at10` float NOT NULL,
  `wards` smallint(5) UNSIGNED NOT NULL,
  `sentries` smallint(5) UNSIGNED NOT NULL,
  `couriers_killed` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `roshans_killed` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `multi_kill` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `streak` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `stacks` tinyint(4) NOT NULL DEFAULT '0',
  `time_dead` int(10) UNSIGNED NOT NULL,
  `buybacks` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `wards_destroyed` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `pings` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `stuns` float NOT NULL,
  `teamfight_part` float NOT NULL,
  `damage_taken` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
-- --------------------------------------------------------

--
-- Структура таблицы `draft`
--

DROP TABLE IF EXISTS `draft`;
CREATE TABLE `draft` (
  `matchid` int(10) UNSIGNED NOT NULL,
  `is_radiant` tinyint(1) NOT NULL,
  `is_pick` tinyint(1) NOT NULL,
  `hero_id` smallint(5) UNSIGNED NOT NULL,
  `stage` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
-- --------------------------------------------------------

--
-- Структура таблицы `matches`
--

DROP TABLE IF EXISTS `matches`;
CREATE TABLE `matches` (
  `matchid` int(10) UNSIGNED NOT NULL,
  `radiantWin` tinyint(1) NOT NULL,
  `duration` int(11) NOT NULL,
  `modeID` tinyint(11) UNSIGNED NOT NULL,
  `leagueID` int(11) NOT NULL,
  `start_date` int(11) NOT NULL,
  `stomp` int(11) NOT NULL,
  `comeback` int(11) NOT NULL,
  `cluster` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
-- --------------------------------------------------------

--
-- Структура таблицы `matchlines`
--

DROP TABLE IF EXISTS `matchlines`;
CREATE TABLE `matchlines` (
  `matchid` int(11) UNSIGNED NOT NULL,
  `playerid` int(11) UNSIGNED NOT NULL,
  `heroid` smallint(6) NOT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL,
  `isRadiant` tinyint(1) NOT NULL,
  `kills` smallint(6) NOT NULL,
  `deaths` smallint(6) NOT NULL,
  `assists` smallint(6) NOT NULL,
  `networth` mediumint(9) NOT NULL,
  `gpm` smallint(6) NOT NULL,
  `xpm` smallint(6) NOT NULL,
  `heal` mediumint(9) NOT NULL,
  `heroDamage` mediumint(9) NOT NULL,
  `towerDamage` smallint(6) NOT NULL,
  `lastHits` smallint(6) NOT NULL,
  `denies` smallint(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Структура таблицы `players`
--

DROP TABLE IF EXISTS `players`;
CREATE TABLE `players` (
  `playerID` int(11) NOT NULL,
  `nickname` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `adv_matchlines`
--
ALTER TABLE `adv_matchlines`
  ADD PRIMARY KEY (`matchid`,`playerid`);

--
-- Индексы таблицы `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`matchid`),
  ADD UNIQUE KEY `matchid` (`matchid`),
  ADD KEY `matchid_2` (`matchid`);

--
-- Индексы таблицы `matchlines`
--
ALTER TABLE `matchlines`
  ADD PRIMARY KEY (`matchid`,`playerid`);

--
-- Индексы таблицы `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`playerID`);

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `adv_matchlines`
--
ALTER TABLE `adv_matchlines`
  ADD CONSTRAINT `adv_matchlines` FOREIGN KEY (`matchid`) REFERENCES `matches` (`matchid`);

--
-- Ограничения внешнего ключа таблицы `matchlines`
--
ALTER TABLE `matchlines`
  ADD CONSTRAINT `matchlines_ibfk_1` FOREIGN KEY (`matchID`) REFERENCES `matches` (`matchid`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
