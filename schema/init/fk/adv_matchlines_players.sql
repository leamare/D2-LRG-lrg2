ALTER TABLE `adv_matchlines` ADD CONSTRAINT `adv_matchlines_pl` FOREIGN KEY (`playerID`) REFERENCES `players` (`playerID`);
