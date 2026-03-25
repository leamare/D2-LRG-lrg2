ALTER TABLE `matchlines` ADD CONSTRAINT `matchlines_ibfk_1` FOREIGN KEY (`matchID`) REFERENCES `matches` (`matchid`);
