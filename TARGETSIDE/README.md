# IMMUTABLE BACKUP

Install on the target side in the backup folder.

## LOCK BACKUP
Simply installing the bklock.php script and executing into the folder, immutable flag is added to ALL backup files. This way nothing can be modified/deleted from any client on the network.

The script check for the last backup date-time and perform lock only if the backup is more recent than last lock.

Add it to cron hourlu (or more often)

## RETENTION
Execute bkretention.php to delete old backup sets. The number of FULL to be retained is read from config.php.
The script remove immutable flag before deleting.
If some deletions performed a detailed email is sent to the addresses in config.php
