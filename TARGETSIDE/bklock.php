#!/usr/bin/php
<?php
/**************************************************************************
 *      bklock.php
 *      © 2025 by Fabrizio Vettore - fabrizio(at)vettore.org
 *      V 0.1
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
 * applica ricorsivamente IMMUTABLE flag
 * lato TARGET
 * a tutti i file del backup
 * lascia la possibilità di scrivere nelle
 * cartelle

 * da mettere in cron
 */

$bktime = file_get_contents(__DIR__ . "/lastbk.txt");
$locktime = file_get_contents(__DIR__ . "/lastlock.txt");
if ($bktime > $locktime) recurseFolder(__DIR__);

//toglie flag dai file che devono essere modificabili
shell_exec("chattr -i ".__DIR__." *.txt");
$endtime = date("Y-m-d H:i:s");
file_put_contents(__DIR__ . "/lastlock.txt", $endtime);

//recurse folder and apply +i flag to all files
function recurseFolder($path)
{
    $d = dir($path);
    while (false !== ($entry = $d->read())) {
        if ($entry <> ".." && $entry <> ".") {
            if (is_dir("$path/$entry")) {
                recurseFolder("$path/$entry");
            } else {
                echo "apply immutable flag to $entry\n";
                shell_exec("chattr +i $path/$entry");
            }
        }
    }
    $d->close();
}
