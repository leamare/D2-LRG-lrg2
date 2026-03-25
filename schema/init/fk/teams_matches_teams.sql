ALTER TABLE `teams_matches` ADD CONSTRAINT `teams_matches_teams` FOREIGN KEY (`teamid`) REFERENCES `teams` (`teamid`);
