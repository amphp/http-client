<?php

/*
 * An array of files to automatically include at startup.
 * The autoload operation takes place after system app dirs
 * have been declared, so it's safe to use those constants in
 * the autoload paths specified below. For example, if you want
 * to include a file that resides in the system's "vendors"
 * directory you would do the following:
 * 
 *   $autoload = array("$cfg->app_dir_vendors/MyLib.php");
 */

$autoload = array();

?>