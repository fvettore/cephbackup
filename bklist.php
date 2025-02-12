#!/usr/bin/php
<?php
/**************************************************************************
 *	bklist
 *	© 2025 by Fabrizio Vettore - fabrizio(at)vettore.org
 *	V 0.1
 *    This program is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation, either version 3 of the License, or
 *    (at your option) any later version.
 *
 *    This program is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    You should have received a copy of the GNU General Public License
 *    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 **************************************************************************/


/*****************************
 * visualizza i punti 
 * di ripristino disponibili 
 * per tutte le immagini
 * (può avere più dischi)
 * di una VM.
 *  
 */

$thisexec = $argv[0];
if (count($argv) < 2) die("USAGE: $thisexec VMNAME\n");

$VM = $argv[1];

require_once __DIR__ . "/config.php";
$db = new mysqli($dbhost, $dbuser, $dbpwd, $dbdatabase);
if (!$db) die("Unable to connect to database\n");

//estrae ID vm where 
$q = "SELECT idvms,`image` from vms where vm=?";
$stmt = $db->prepare($q);
$stmt->bind_param("s", $VM);
$stmt->execute();
$r = $stmt->get_result();
$stmt->close();
while ($l = $r->fetch_array()) {
    list($id_vm, $image) = $l;
    echo "VM $VM\nimage $image\n";

    //estrae i backup jobs
    $q = "
    SELECT 
        b.idbackup_jobs, j.`name`, j.`path`, j.mountpoint
    FROM
        cephbackup.backup_vms b
        INNER JOIN
        backup_jobs j ON j.idbackup_jobs = b.idbackup_jobs
    WHERE idvms=?";
    $stmt = $db->prepare($q);
    $stmt->bind_param("s", $id_vm);
    $stmt->execute();
    $r = $stmt->get_result();
    $stmt->close();
    while ($l = $r->fetch_array()) {
        list($id_job, $job_name, $job_path, $job_mountpoin) = $l;
        $backupdir = "$job_path/$job_name/$VM/$image";
        $d = dir($backupdir);
        echo "DATE                TYPE         RESTPOINT        SIZE  BACKUP-JOB \n";
        echo "-------------------------------------------------------------------\n";
        while (false !== ($bkfull = $d->read())) {
            if (strlen($bkfull) == 6) {
                //echo $bkfull . "\n";
                $d1 = dir("$backupdir/$bkfull");
                while (false !== ($bkfiter = $d1->read())) {
                    //print_r($d1);
                    while (false !== ($bkiter = $d1->read())) {
                        if (strlen($bkiter) == 6) {
                            if ($bkiter === "000000") $bktype = "FULL";
                            else $bktype = " INC";
                            $fdate = date("m-d-Y H:i:s", filemtime("$backupdir/$bkfull/$bkiter"));
                            $unit = "";
                            $size = filesize("$backupdir/$bkfull/$bkiter");
                            if ($size > 1000000000) {
                                $size = $size / 1000000000;
                                $size = number_format($size, 2);
                                $unit = "GB";
                            } else if ($size > 1000000) {
                                $size = $size / 1000000;
                                $size = number_format($size, 2);
                                $unit = "MB";
                            } else {
                                $unit = " B";
                                $size = floor($size);
                            }
                            $size  .= " $unit";
                            $size = str_pad($size, 12, " ", STR_PAD_LEFT);
                            echo "$fdate $bktype     $bkfull-$bkiter $size $job_name\n";
                        }
                    }
                }
            }
        }
        $d->close();
    }
}
