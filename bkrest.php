#!/usr/bin/php
<?php
/**************************************************************************
 *	backrest
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


/***************************************
 * ripristina una immagine
 * 
 * (usa bklist per visualizzare
 * i punti di ripristiono disponibili)
 * 
 * 
 */

$thisexec = $argv[0];
if (count($argv) < 5) die("USAGE: $thisexec BACKUP-JOB IMAGE RESTPOINT RESTORED-NAME \n");

require_once __DIR__ . "/config.php";
$db = new mysqli($dbhost, $dbuser, $dbpwd, $dbdatabase);
if (!$db) die("Unable to connect to database\n");

$backup_job = $argv[1];
$vm_image = $argv[2];
$restore_point = $argv[3];
$restored_name = $argv[4];

//recupera dati dell'immagine nel backup
$q = "SELECT 
    j.mountpoint, j.`path`, j.`snap-prefix`,v.vm
FROM
    cephbackup.vms v
        INNER JOIN
    backup_vms bv ON bv.idvms = v.idvms
        INNER JOIN
    backup_jobs j ON j.idbackup_jobs = bv.idbackup_jobs
WHERE
    v.image = ?
        AND j.`name` = ?";

$stmt = $db->prepare($q);
$stmt->bind_param("ss", $vm_image, $backup_job);
$stmt->execute();
$r = $stmt->get_result();
if ($r->num_rows) {
    $l = $r->fetch_array();
    list($mountpoint, $bk_path, $snap_prefix, $vm_name) = $l;
    $expl = explode("-", $restore_point);
    $instance = $expl[0];
    $point = $expl[1];
    echo "$mountpoint,$bk_path,$snap_prefix,$restore_point,$instance,$point,$vm_name\n";
    //check esiste dir
    $path = "$bk_path/$backup_job/$vm_name/$vm_image/$instance";
    echo "$path\n";
    if (is_dir($path)) {
        echo "creation empty $restored_name image\n";
        $cmd = "rbd create $restored_name --size 1024 -p $poolname";
        echo "$cmd\n";
        echo shell_exec($cmd);
        for ($x = 0; $x <= $point; $x++) {
            $rest = str_pad($x, 6, "0", STR_PAD_LEFT);
            echo "import differential $path/$rest\n";
            $cmd = "rbd import-diff  $path/$rest $poolname/$restored_name";
            echo "$cmd\n";
            echo shell_exec($cmd);
        }
    } else die("Backup folder not found\n");
} else die("VM image not found in backupset\n");
