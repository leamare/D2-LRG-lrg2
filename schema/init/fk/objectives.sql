ALTER TABLE `objectives` ADD CONSTRAINT `objectives` FOREIGN KEY (`matchid`) REFERENCES `matches` (`matchid`);
