ALTER TABLE `runes` ADD CONSTRAINT `runes` FOREIGN KEY (`matchid`) REFERENCES `matches` (`matchid`);
