ALTER TABLE `draft` ADD CONSTRAINT `draft` FOREIGN KEY (`matchid`) REFERENCES `matches` (`matchid`);
