ALTER TABLE `matches_ext` ADD CONSTRAINT `matches_ext` FOREIGN KEY (`matchid`) REFERENCES `matches` (`matchid`);
