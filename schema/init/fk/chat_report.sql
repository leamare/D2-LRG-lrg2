ALTER TABLE `chat_report` ADD CONSTRAINT `chat_report` FOREIGN KEY (`matchid`) REFERENCES `matches` (`matchid`);
