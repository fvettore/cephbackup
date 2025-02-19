#!/usr/bin/php
<?php
/**************************************************************************
 *	bkretention.php
 *	© 2025 by Fabrizio Vettore - fabrizio(at)vettore.org
 *	V 0.2
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
/**********************************
 * da installare sul TARGET (per esempio server NFS)
 * cancella le cartelle di backup
 * vecchie. 
 * Il massimo numero di backup FULL
 * da tenere è indicato
 * nel campo retention di config.php
 * del record del backupset (backup_jobs)
 * 
 * da mettere in cron
 */
require_once __DIR__ . "/config.php";
$purgedfolder = array();
$d = dir(__DIR__);
while (false !== ($entry = $d->read())) {
    if ((is_dir(__DIR__ . "/$entry")) && ($entry != "..") && ($entry != ".")) {
        echo "CHECKING VM $entry\n";

        $imagepath = __DIR__ . "/$entry";
        $d1 = dir($imagepath);
        while (false !== ($entry1 = $d1->read())) {
            if ((is_dir("$imagepath/$entry1")) && ($entry1 != "..") && ($entry1 != ".")) {
                echo "    Checking IMAGE $entry1\n";
                $bksetpath = "$imagepath/$entry1";
                $d2 = dir($bksetpath);
                $bs = array();
                $maxbkset = "000000";
                while (false !== ($entry2 = $d2->read())) {

                    if ((is_dir("$bksetpath/$entry2")) && ($entry2 != "..") && ($entry2 != ".")) {
                        echo "    BAckupset $entry2\n";
                        $bs[] = $entry2;
                        if ($entry2 > $maxbkset) $maxbkset = $entry2;
                    }
                }
                $tobepurged = $maxbkset - $retention;
                echo "maxbackup= $maxbkset to be purged <= $tobepurged\n";

                for ($x = 0; $x < count($bs); $x++) {
                    if ($bs[$x] <= $tobepurged) {
                        echo "       DELETING " . $bs[$x] . "\n";
                        //remove immutable flag
                        $cmd = "chattr -i $bksetpath/$entry2" . $bs[$x] . "/*";
                        echo "$cmd\n";
                        shell_exec("$cmd");
                        //DANGER. Be very careful
                        //double check to avoid problems with empty path
                        if (strlen("$bksetpath/$entry2") > 8) {
                            if (is_dir("$bksetpath/$entry2")) {
                                $cmd = "rm -rf $bksetpath/$entry2" . $bs[$x];
                                echo "executing $cmd\n";
                                shell_exec($cmd);
                                $purgedfolder['path'][] =  "$bksetpath/$entry2" . $bs[$x];
                                //Check if deletion succeded  (not working with is_dir())         
                                $cms = "ls $bksetpath/$entry2" . $bs[$x];
                                echo "cheking: $cms\n";
                                passthru($cms, $result_code);
                                if ($result_code == 0) {
                                    $purgedfolder['result'][] = 'FAIL';
                                } else {
                                    $purgedfolder['result'][] = 'SUCCESS';
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

if (isset($purgedfolder['result'])) {
    if (count($purgedfolder['result'])) {

        $thstyle = "style=\"padding-top: 12px;padding-bottom: 12px;text-align: left;background-color: blue;color: white; border: 1px solid #ddd;padding: 8px;\"";
        $tdstyle = "style=\"border: 1px solid #ddd; padding: 8px;\"";
        $tablestyle = "style=\"font-family: Arial, Helvetica, sans-serif; border-collapse: collapse;width: 100%;\"";
        $message = "<h3>The following bakup folders needed to be purged</h3>
<table $tablestyle>
    <tr>
        <th $thstyle>JOB</th><th $thstyle>PATH</th><th $thstyle>RESULT</th>
    </tr>
";
        for ($x = 0; $x < count($purgedfolder['result']); $x++) {

            if ($purgedfolder['result'][$x] === 'FAIL') {
                $rescolor = 'red';
            } else {
                $rescolor = 'green';
            }
            $message .= "
            <tr>
                <td $tdstyle>" . $jobname . "</td>
                <td $tdstyle>" . $purgedfolder['path'][$x] . "</td>                              
                <td $tdstyle><span style=\"color:$rescolor;\">" . $purgedfolder['result'][$x] . "</span></td>            
            </tr>\n ";
        }
        $message .= "</table>";
        emailnotify("CEPH Backup retention", $message);
    }
} else echo "NOTHING to purge!!\n";

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
