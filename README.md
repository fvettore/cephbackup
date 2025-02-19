# cephbackup
A very simple suite to perform full/inc backups of VM storage on CEPH and restore. Designed to support a KVM cluster with CEPH storage.
Every VM can have multiple disk images.

VM definition is not included. It is your care to save it (you can easily schedule the backup of XML VM definition with the command *virsh dumpxml* on the node where VM is running).

Simple crash consistent backup is created. If you wish application consistent backup you should add more complexity for example freezing an saving VRAM of your VM before the snapshot.

Create backup JOBs with thin provisioned full and incremental or differential backups of your kvm/qemu virtual machines

Supports for days and weeks schedules up to monthly (for example scheduling first day of first week only).

Simply add the definition of vm and jobs in the database and launch script (should be cronned daily).

A confirmation email with time, transfer rate, size, type of backup for every image is sent after operation ended.

![Immagine 2025-02-12 155202](https://github.com/user-attachments/assets/f34f4fdb-d8e8-4274-aa51-0538478085a2)


## How it works
The main script scans for all JOBs defined in DB and execute them.

It checks for day scheduled and week scheduled an decides if the job is to be skipped today. 

If the backup job is not to be skipped, if a prevoius instance is still running, the JOB terminate with email notification.

Otherwise a backup for each VMs image defined in the JOB is performed.

If the image have never been backed up, a FULL backup will be performed.

Otherwise the backup will be incremental until the *max_inc* thresold is reached.

After the threshold is reached the backup is rotated and a new full instance is located in a new folder.

When backup ends, a detailed report is sent by email.

## Prerequisites
PHP

Mysql/MariaDB

A bakup target (folder). Backup target must be mounted before backup starts.

The backup machine must be able to access to the pool on the CEPH cluster via *rbd* command (import keyrings and so on...)

## Getting started
Create your MySQL database. SQL  is available in the SQL folder for creating tables in your newly created DB.

Edit *config.php* accordingly.

Define a backup job in the *backup_jobs* table filling the relevant fields (name, max_inc, enabled and path).

Add VMs in *vms* table. the field vm is only mnemonic. The field image should match the image name on CEPH cluster. If a vm has more than one image simply keep equal vms field and ad more records (one for every image)

Ad VMs records in the *backup_vms* table filling *idbackup_jobs* (idbackup_job from backup_jobs table) and *idvms* (idvms from vms table).

Try to start the job runing the *bkexec.php* and monitor it.

If everything is ok you can add it to cron for daily execution. An email containing all relevant data is sent to the configured address.

## Retention

After the retention threshold is reached (max number of full backup performed), the older backup folders are deleted.
Simply launch *./bkretention.php* or better cron it daily to automate retention. If retention applied to some backups, a detailed email is sent to the configured address.

If a hardened (immutable) backup is set on the storage side (strongly suggested!!!!) setup immutability in synch with the above retention thresold otherwise folder cleanup will fail.

Otherwise you can setup IMMUTABLE backup with is own retention on your target with the scripts in the TARGETSIDE folder.

## Listing available restore points

Simply launch *./bklist.php* script with the name of the VM as unique arg. A list of available restore points will be displayed
![Immagine 2025-02-12 155453](https://github.com/user-attachments/assets/065cf3eb-0868-463c-9271-6020800f4c7d)

## Restore

Simply launch *./bkrest.php* with the following args (supplied by the above bklist command):  BACKUP-JOB IMAGE RESTPOINT RESTORED-NAME.

The RESTORED-NAME is the name of the NEW image that will be created on the CEPH cluster

Here an example of a restore process. First of all I ask for a list of the images and related restore points available for the VM *ROCKY9test*.
I choose an image *ROCKY9_01* and a restore point *000001-000003* and launch restore, giving a target image *ROCKY9test_rest*.
The process starts, the image is created and all the diff ar added to the image. At the end I got the restored image rbdpool01/ROCKY9test_rest and I can attach it as a storage to a running VM or create e new VM assigning it as storage.

![Immagine 2025-02-13 083209](https://github.com/user-attachments/assets/7d61b792-b6d8-4b62-bab1-289e84b8829a)

## Trimming old snapshots
Every backup action perform a snapshot. It is adiviceable to delete old unused snapshots frome the CEPH storage. simply launch *./bktrimsnap.php* to delete unused snapshot. The max number of snapshot in the field *max-snaps* of the record of your backup set in the tabel *backup_jobs* will be preserved.






