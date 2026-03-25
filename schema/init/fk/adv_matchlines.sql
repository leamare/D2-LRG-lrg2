ALTER TABLE `adv_matchlines` ADD CONSTRAINT `adv_matchlines` FOREIGN KEY (`matchid`) REFERENCES `matches` (`matchid`);
