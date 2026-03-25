ALTER TABLE `teams_rosters` ADD CONSTRAINT `teams_rosters_teams` FOREIGN KEY (`teamid`) REFERENCES `teams` (`teamid`);
