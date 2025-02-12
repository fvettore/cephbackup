#!/usr/bin/php
<?php
/**************************************************************************
 *	bkexec
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

/*******************************************
 * 
 * esegue tutti i job di backup definiti nel DB
 * rispettando eventuali schedulazioni
 * giornaliere e settimanali
 * 
 * da mettere in cron daily
 */


require_once __DIR__ . "/config.php";
$db = new mysqli($dbhost, $dbuser, $dbpwd, $dbdatabase);
if (!$db) die("Unable to connect to database\n");

$qu = "SELECT * FROM backup_jobs WHERE enabled = 1";
$r = $db->query($qu);
$p = 0; //index for increasing NDB port
while ($l = $r->fetch_array()) {
    $backupres = NULL;
    $BACKUPFAIL = FALSE;
    list($job_id, $job_name, $job_max_inc,  $job_enabled, $job_lastrun, $job_lastcompletion, $job_path, $job_checkmount, $retention, $mountpoint, $snap_prefix) = $l;

    $is_mounted = null;
    $SKIPBACKUP = FALSE;
    //check if scheduled for days
    $d = $db->query("select * from backup_days where idbackup_job=$job_id");
    if ($d->num_rows) { //a day schedule is defined
        $days = $d->fetch_array();
        $curday = dayOfWeek();
        echo "Checking scheduled for day $curday\n";
        $is_to_backup = $days[$curday];
        if ($is_to_backup) {
            echo "Backup is to be performed on day $curday\n";
        } else {
            echo "Backup is to be skipped on day $curday\n";
            $SKIPBACKUP = TRUE;
        }
    }
    //check if scheduled weeks
    if (!$SKIPBACKUP) {
        $d = $db->query("select * from backup_weeks where idbackup_job=$job_id");
        if ($d->num_rows) { //a week schedule is defined
            $weeks = $d->fetch_array();
            $curweek = WeekOfMonth();
            echo "Checking scheduled for week $curweek\n";
            $is_to_backup = $weeks[$curweek];
            if ($is_to_backup) {
                echo "Backup is to be performed on week $curweek\n";
            } else {
                echo "Backup is to be skipped on week $curweek\n";
                $SKIPBACKUP = TRUE;
            }
        }
    }
    if (!$SKIPBACKUP) {
        //if checkmount is set, check for mount    
        if ($job_checkmount) {
            echo "CHECKING mountpoint $mountpoint ... ";
            $is_mounted = boolval(trim(shell_exec("mount | grep -c $mountpoint")));
            if ($is_mounted) {
                echo "OK!\n";
            }
        }
        if ($job_checkmount && !$is_mounted) {
            //JOB already running
            $BACKUPFAIL = true;
            $report = "FAIL";
            $ERROR = "Mountpoint $job_path not mounted";
            echo "$ERROR\n";
        } else if ($job_lastrun > $job_lastcompletion) {
            //JOB already running
            $BACKUPFAIL = true;
            $report = "FAIL";
            $ERROR = "same JOB already running from $job_lastrun";
            echo "$ERROR\n";
        } else { //backup can start now 

            $backupstarted = date("Y-m-d H:i:s");

            //update Job record
            $db->query("update backup_jobs set                
                lastrun=now()
                where idbackup_jobs=$job_id");
            $r1 = $db->query("
           SELECT 
            vm, inc, full, b.idvms,image
            FROM
                backup_vms b
            INNER JOIN
                vms v ON v.idvms = b.idvms
            WHERE idbackup_jobs=$job_id");
            //number of objects to be backed-up        
            $numvms = $r1->num_rows;
            while ($l1 = $r1->fetch_array()) {
                list($vms, $vm_inc, $vm_full, $vms_id, $vm_image) = $l1;
                if ($vm_full == 0) {
                    echo "FIRST backup  $vm_inc / $job_max_inc for VM $vms $vms_id\n";
                    $backuptype = 'full';
                    $vm_inc = 0;
                    $vm_full++;
                } else if ($vm_inc >= $job_max_inc) {
                    echo "Max INC reached  $vm_inc / $job_max_inc for VM $vms $vms_id\n";
                    $backuptype = 'full';
                    $vm_inc = 0;
                    $vm_full++;
                } else {
                    echo "INC $vm_inc / $job_max_inc for VM $vms\n";
                    $backuptype = 'inc';
                    $vm_inc++;
                }
                $indir = str_pad($vm_full, 6, '0', STR_PAD_LEFT);
                $backupdir = "$job_path/$job_name/$vms/$vm_image";
                echo "performing $backuptype in $backupdir for $vms\n";
                $vmbackuptype = $backuptype;
                //check if folder exists
                if (!is_dir("$backupdir/$indir")) {
                    if (!mkdir("$backupdir/$indir", 0700, true)) die("cannot create DIR\n");
                }

                if ($backuptype === 'inc') {
                    //check if preexisting FULL/INC
                    $FULLEXISTS = FALSE;
                    echo "looking for existance of FULL in $backupdir/$indir/000000 \n";
                    $cmd = "ls $backupdir/$indir/000000";
                    passthru($cmd, $result_code);
                    if ($result_code == 0) {
                        $FULLEXISTS = TRUE;
                    }
                    if (!$FULLEXISTS) {
                        echo "Not previous FULL present, performing FULL instead of INC\n";
                        $FULLEXISTS = true;
                        $vmbackuptype = 'full';
                        $vm_inc = 0;
                    }
                }
                $incset = str_pad($vm_inc, 6, '0', STR_PAD_LEFT);
                $incset1 = str_pad(($vm_inc - 1), 6, '0', STR_PAD_LEFT);
                $db->query("update backup_vms set inc=$vm_inc, full=$vm_full, lastrun=now() where idbackup_jobs=$job_id and idvms=$vms_id ");
                $vmbackupstarted = date("Y-m-d H:i:s");
                $cmd1 = "rbd snap create $poolname/$vm_image --snap  $snap_prefix-$indir-$incset";
                echo "$cmd1\n";
                shell_exec($cmd1);
                if ($vmbackuptype === 'full') {
                    $cmd2 = "rbd export-diff  $poolname/$vm_image@$snap_prefix-$indir-$incset  $backupdir/$indir/$incset";
                } else if ($vmbackuptype === 'inc') {
                    $cmd2 = "rbd export-diff  --from-snap $snap_prefix-$indir-$incset1 $poolname/$vm_image@$snap_prefix-$indir-$incset  $backupdir/$indir/$incset";
                }
                echo "$cmd2\n";
                ob_start();
                passthru($cmd2 . " 2>&1", $result_code);
                $var = ob_get_contents();
                ob_end_clean();
                $vmbackupended = date("Y-m-d H:i:s");
                if ($result_code) {
                    //get last row of error message
                    $v = explode("\n", $var);
                    $bkerror = $v[count($v) - 2];
                    echo "error: " . escapeshellcmd($bkerror) . "\n";
                    $result = 'FAIL';
                    $BACKUPFAIL = TRUE;
                    $db->query("update backup_vms set success=0 where idbackup_jobs=$job_id and idvms=$vms_id");
                    //remove snapshot in case of fail
                    $cmd3 = "rbd snap rm $poolname/$vm_image@$snap_prefix-$indir-$incset";
                    echo "$cmd3\n";
                    shell_exec($cmd3);
                    $bksize = 0;
                } else {
                    $bksize = filesize("$backupdir/$indir/$incset");
                    echo "$vms backup success\n";
                    $result = 'SUCCESS';
                    $bkerror = "";
                    $db->query("update backup_vms set success=1 where idbackup_jobs=$job_id and idvms=$vms_id");
                }
                //update array with vms backup data for notification
                $bkres[] = array('vm' => $vms, 'start' => $vmbackupstarted, 'end' => $vmbackupended, 'result' => $result, 'error' => $bkerror, 'type' => $vmbackuptype, 'size' => $bksize);
                $err = $db->real_escape_string($bkerror);
                $qi = "insert into backup_log set vm=\"$vms\", job=\"$job_name\", 
                timestart='$vmbackupstarted',timeend='$vmbackupended',result='$result',type='$vmbackuptype',error='$err',path=\" $backupdir/$indir/\",backup_cmd=\"$cmd2\" ";
                $db->query($qi);
            }
        }
        //END OF SINGLE JOB
        if (isset($bkres)) { //the backup completed
            $db->query("update backup_jobs set lastcompletion=now() where idbackup_jobs=$job_id");
            //backup completed
            if ($BACKUPFAIL) {
                $color = "red";
                $report = "FAIL";
            } else {
                $color = "green";
                $report = "SUCCESS";
            }
            $thstyle = "style=\"padding-top: 12px;padding-bottom: 12px;text-align: left;background-color: $color;color: white; border: 1px solid #ddd;padding: 8px;\"";
            $tdstyle = "style=\"border: 1px solid #ddd; padding: 8px;\"";
            $tablestyle = "style=\"font-family: Arial, Helvetica, sans-serif; border-collapse: collapse;width: 100%;\"";
            $message = "
        <table $tablestyle>
            <tr>
                <th $thstyle>Name</th><th $thstyle>Start time</th><th $thstyle>End time</th><th $thstyle>Size</th><th $thstyle>Speed</th><th $thstyle>Status</th><th $thstyle>Type</th><th $thstyle>Duration</th><th $thstyle>Details</th>
            </tr>
        ";
            foreach ($bkres as $vmres) {
                $origin = date_create($vmres['start']);
                $target = date_create($vmres['end']);
                $interval = date_diff($origin, $target);
                $seconds = strtotime($vmres['end']) - strtotime($vmres['start']);
                $size = $vmres['size'];
                //patch division by zero
                if ($seconds) {
                    $speed = floor(($size / 1000000) / $seconds);
                    $speed .= " MB/s";
                } else $sped = "NA";
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
                }
                $size  .= " $unit";

                if ($vmres['result'] == 'SUCCESS') $rescolor = 'green';
                else $rescolor = 'red';
                $message .= "
            <tr>
                <td $tdstyle>" . $vmres['vm'] . "</td>
                <td $tdstyle> " . $vmres['start'] . "</td>
                <td $tdstyle>" . $vmres['end'] . "</td> 
                <td $tdstyle>$size</td> 
                <td $tdstyle>$speed</td> 
                <td $tdstyle><span style=\"color:$rescolor;\">" . $vmres['result'] . "</span></td>            
                <td $tdstyle>" . $vmres['type'] . "</td>
                <td $tdstyle>" . $interval->format("%H:%I:%S") . " ($seconds s)</td>
                <td $tdstyle>" . $vmres['error'] . "</td>
            </tr>    
            ";
            }
            $message .= "</table>";
            emailnotify("[$report] CEPH Backup $job_name ($numvms objects)", $message);
            unset($bkres);
        } else { //backup aboreted with error
            $message = "<h3>$ERROR</h3>";
            emailnotify("[$report] CEPH Backup $job_name (ALL objects)", $message);
        }
    }
}

function emailnotify($subject, $message)
{
    global $email_from;
    global $rcpt_to;

    $headers = "From: backup <$email_from>
User-Agent: PHP Mailer
MIME-Version: 1.0
Content-Type: text/html; charset=UTF-8
";
    foreach ($rcpt_to as $recipient) {
        mail($recipient, $subject, $message, $headers, "-f $email_from");
    }
}

function weekOfMonth()
{
    $date = date("Y-m-d");
    $firstOfMonth = date("Y-m-01", strtotime($date));
    return (intval(date("W", strtotime($date))) - intval(date("W", strtotime($firstOfMonth))) + 1);
}

function dayOfWeek()
{
    return date("w");
}
