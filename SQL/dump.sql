CREATE TABLE `backup_days` (
  `idbackup_job` int(11) DEFAULT NULL,
  `1` int(11) DEFAULT NULL,
  `2` int(11) DEFAULT NULL,
  `3` int(11) DEFAULT NULL,
  `4` int(11) DEFAULT NULL,
  `5` int(11) DEFAULT NULL,
  `6` int(11) DEFAULT NULL,
  `7` int(11) DEFAULT NULL,
  `id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
);
CREATE TABLE `backup_jobs` (
  `idbackup_jobs` int(11) NOT NULL,
  `name` varchar(128) DEFAULT NULL,
  `max_inc` int(11) DEFAULT 0 COMMENT 'Number of incremental backups before a new FULL',
  `enabled` int(11) DEFAULT 1,
  `lastrun` datetime DEFAULT NULL,
  `lastcompletion` datetime DEFAULT NULL,
  `path` varchar(128) DEFAULT NULL,
  `checkmount` int(11) DEFAULT 0,
  `retention` int(11) DEFAULT 10 COMMENT 'Number of full backup to retain (no deletion)',
  `mountpoint` varchar(45) DEFAULT NULL,
  `snap-prefix` varchar(10) DEFAULT NULL,
  `max-snaps` int(11) DEFAULT 5,
  PRIMARY KEY (`idbackup_jobs`)
);
CREATE TABLE `backup_log` (
  `idbackup_log` int(11) NOT NULL,
  `job` varchar(45) DEFAULT NULL,
  `vm` varchar(128) DEFAULT NULL,
  `timestart` datetime DEFAULT NULL,
  `timeend` datetime DEFAULT NULL,
  `type` varchar(6) DEFAULT NULL,
  `result` varchar(20) DEFAULT '0',
  `path` varchar(255) DEFAULT NULL,
  `error` text DEFAULT NULL,
  `backup_cmd` text DEFAULT NULL,
  PRIMARY KEY (`idbackup_log`)
);
CREATE TABLE `backup_vms` (
  `idbackup_jobs` int(11) DEFAULT NULL,
  `idvms` int(11) DEFAULT NULL,
  `id` int(11) NOT NULL,
  `inc` int(11) DEFAULT 0,
  `full` int(11) DEFAULT 0,
  `lastrun` datetime DEFAULT NULL,
  `success` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
);
CREATE TABLE `backup_weeks` (
  `idbackup_job` int(11) DEFAULT NULL,
  `1` int(11) DEFAULT NULL,
  `2` int(11) DEFAULT NULL,
  `3` int(11) DEFAULT NULL,
  `4` int(11) DEFAULT NULL,
  `5` int(11) DEFAULT NULL,
  `id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
);
CREATE TABLE `vms` (
  `idvms` int(11) NOT NULL,
  `vm` varchar(45) DEFAULT NULL,
  `image` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`idvms`)
);
