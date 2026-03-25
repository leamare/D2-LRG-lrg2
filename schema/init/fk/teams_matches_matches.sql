ALTER TABLE `teams_matches` ADD CONSTRAINT `teams_matches` FOREIGN KEY (`matchid`) REFERENCES `matches` (`matchid`);
