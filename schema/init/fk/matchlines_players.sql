ALTER TABLE `matchlines` ADD CONSTRAINT `matchlines_pl` FOREIGN KEY (`playerID`) REFERENCES `players` (`playerID`);
