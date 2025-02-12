# cephbackup
A simple suite to perform full/inc backups of VM on CEPH and restore. Designed to support a KVM cluster vith CEPH storage.
Every VM can have more disk imnages.

Create backup JOBs with thin provisioned full and incremental or differential backups of your kvm/qemu virtual machines

Supports for days and weeks schedules.

Simply add the definition of vm and jobs in the database and launch script (should be cronned daily).

A confirmation email with time, transfer rate, size, type of backup for every image is sent after operation ended.


## How it works
The main script scans for all JOBs defined and execute them.

It check for day scheduled and week scheduled an decide if the job is to skipped today. 

If the backup job is not to be skipped, if a prevoius instance is still running, the JOB terminate with email notification.

Otherwise a backup for each VMs defined in the JOB is performed.

If the VM have never been backed up, a FULL backup will be performed.

Otherwise the backup will be incremental until the max_inc thresold is reached.

After the threshold is reached the backup is rotated and a new full instance is located in a new folder.

When backup ends, a detailed report is sent by email.

## Prerequisites

Backup target must be mounted before backup starts.

You must be able to RBD the CEPH cluster from the backup machine (import keyrings and so on...)

## Getting started
Create your MySQL database. SQL is available in the SQL folder for creating tables in your newly created DB.

Edit *config.php* accordingly.

Define a backup job in the *backup_jobs* table filling the relevant fields (name, max_inc, enabled and path).

Add VMs in *vms* table. the field vm is only mnemonic. The field image should match the image name on CEPH cluster. If a vm has more than one image simply keep equal vm field and ad more recods (one for every image)

Ad VMs records in the *backup_vms* table filling *idbackup_jobs* (idbackup_job from backup_jobs_table) and *idvms* (idvms from vms table).

Try to start the job runing the *bkexec.php* and monitor it.

If everything is ok you can add it to cron for daily execution. An email containing all relevant data is sent to the configured address.

## Retention

After the retention threshold is reached (max number of full backup performed), the older backup folders are deleted.
Simply launch *./bkretention.php* or better cron it daily to automate retention. If retention applied to some backups, a detailed email is sent to the configured address.

If a hardened (immutable) backup is set on the storage side (strongly suggested!!!!) setup immutability in synch with the above retention thresold otherwise folder cleanup will fail.

## Listing available restore points

Simply launch *./bklist.php* script with the name of the VM as unique arg. A list of available restore points will be displayed

## Restore

Simply launch *./bkrest.php* with the following args (supplied by the above bklist command):  BACKUP-JOB IMAGE RESTPOINT RESTORED-NAME.

The RESTORED-NAME is the name of the NEW image that will be created on the CEPH cluster

## Trimming old snapshots
Every backup action perform a snapshot. It is adiviceable to delete old unused snapshots frome the CEPH storage. simply launch *./bktrimsnap.php* to delete unused snapshot. The max number of snapshot in the field *max-snaps* of teh record of your backup set will be preserved.






