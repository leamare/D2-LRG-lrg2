ALTER TABLE `items` ADD CONSTRAINT `items` FOREIGN KEY (`matchid`) REFERENCES `matches` (`matchid`);
