<?php

/**
 * Rotating Backup task
 * Dumping the Database to a directory not accessible from the web root
 * and keeps a number of hourly/daily/weeky/monthly and yearly backups
 * Can Only be run from the command line
 *
 * Example on how this could be run on a server:
 * 
 * sake /HourlyTask
 * or (as it should be run from crontab)
 * 5 * * * * /usr/bin/php -f /var/www/dev.downtownbentonville.org/public/framework/cli-script.php /HourlyTask >> /var/www/dev.downtownbentonville.org/logs/hourlytask.log
 * 
 * Can be installed like this:
 * sudo crontab -u www-data -e
 * 
 * 
 * Configuration
 * The following can be configured in _ss_environment.php - but she script can run out of the box
 * 
 * define('SS_BACKUP_DIR', '/var/backups/mysite');
 * define('SS_BACKUP_KEEP', '48,7,4,12,-1');
 * Number of hourly, daily, weekly, monthly and yearly backups to keep.
 * A value of `0` means that no backups will be made for that interval
 * and a value of `-1` means that a infinite amount will be kept.
 * 
 * define('SS_BACKUP_KEEP_ASSETS', '10,7,4,3,-1');
 * As asset dumps can take substantial space you'd usually want less asset dumps than db dumps
 * 
 * define('SS_BACKUP_MAMP', true);
 * Define this if running on mamp
 * 
 * 
 * NOTE:
 * DB backups when untar'ed on OSX will seem to just be a .tar file. 
 * Changing this to make them appear as .sql would make backup recovery less confusing.
 * 
 * 
 * Possible improvements to this script:
 * 
 * - as this script is triggered by cron, make sure the hourly backups are really run every hour (now some backups are only every other hour)
 * 
 * @author Anselm Christophersen <ac@anselm.dk>
 * 
 */
class RotatingBackupTask extends HourlyTask
{

    protected $title = "RotatingBackupTask";
    protected $description = "Hourly dump of the Database to a directory not accessible from the web root";
    protected $backupKeep = '48,7,4,12,-1';
    protected $backupKeepArr = array();
    protected $backupKeepAssets = '10,7,4,3,-1';
    protected $backupKeepAssetsArr = array();
    protected $backupDir = null;
    protected $isMamp = false;

    /**
     * Process
     */
    public function process()
    {
        if (!Director::is_cli()) {
            echo "This script can only be run from the command line";
            return false;
        }

        $this->ok("Running Rotating Backup Task at " . date('Y-m-d H:i T'));
        
        //Initialization
        $this->varInit();

        //Create directories
        $this->create_dirs();

        //Run backup:
        //DB
        $this->runRotatingBackup('db');
        //Assets
        $this->runRotatingBackup('assets');
        
        //Cleanup
        $this->cleanup('db');
        $this->cleanup('assets');
    }

    /**
     * Cleanup
     * Cleaning up after the backup
     * While this should have been taken care of in "runRotatingBackup",
     * this method makes sure that no stubs are left due to code changes
     */
    protected function cleanup($type)
    {

        //DB Mode
        $keepArr = $this->backupKeepArr;
        
        //Assets mode
        if ($type == 'assets') {
            $keepArr = $this->backupKeepAssetsArr;
        }
        
        $backupDir = $this->backupDir;

        foreach ($keepArr as $name => $keep) {
            $dir = "{$backupDir}/$name";
            $files = $this->runShell("ls -t $dir", true);
            
            foreach ($files as $file) {
                $f = "$dir/$file";
                if (is_dir($f)) {
                    //echo "dir: $f \n";
                } else {
                    //echo "$f \n";
                    //As per 26th March 2013 all backups should be either in a "db" or "assets" dir
                    //here we delete everything that's directly in a dir
                    $this->runShell("rm $f");
                }
            }
        }
    }
    
    
    /**
     * Initializing variables
     */
    protected function varInit()
    {

        //Backup Dir
        $backupDir = dirname(Director::baseFolder()) . '/rotatingbackups'; //we're expecting this to be under the site root
        if (defined('SS_BACKUP_DIR')) {
            $backupDir = SS_BACKUP_DIR;
        } else {
            //create backup dir if necessary
            if (!is_dir($backupDir)) {
                mkdir($backupDir);
                $this->ok("Created directory: $backupDir");
            }
        }

        $this->backupDir = $backupDir;

        //Backup Keep DB
        $backupKeep = $this->backupKeep;
        if (defined('SS_BACKUP_KEEP')) {
            $backupKeep = SS_BACKUP_KEEP;
        }
        $this->backupKeep = $backupKeep;
        $this->backupKeepArr = $this->parse_keep($backupKeep);

        //Backup Keep Assets
        $backupKeepAssets = $this->backupKeepAssets;
        if (defined('SS_BACKUP_KEEP_ASSETS')) {
            $backupKeep = SS_BACKUP_KEEP_ASSETS;
        }
        $this->backupKeepAssets = $backupKeepAssets;
        $this->backupKeepAssetsArr = $this->parse_keep($backupKeepAssets);

        //var_dump($this->backupKeepArr);
        //Are we on mamp? Should be set in the environment file
        if (defined('SS_BACKUP_MAMP')) {
            $this->isMamp = true;
        }
    }

    
    /**
     * Running the backup
     * Type either db or assets
     * 
     * Code adapted from:
     * https://github.com/runekaagaard/php-simple-backup/blob/master/php-simple-backup.php
     */
    protected function runRotatingBackup($type = 'db')
    {
        $this->ok("\nRotating $type backup:");
        
        global $databaseConfig;
        
        //DB Mode
        $keepArr = $this->backupKeepArr;
        $backupName = "db_" . $databaseConfig['database'] . '.tar.gz';
        
        //Assets mode
        if ($type == 'assets') {
            $keepArr = $this->backupKeepAssetsArr;
            $backupName = 'assets.tar.gz';
        }
        
        $backupDir = $this->backupDir;
        

        $seconds = array(
            'hourly' => 60 * 60,
            'daily' => 60 * 60 * 24,
            'weekly' => 60 * 60 * 24 * 7,
            'monthly' => 60 * 60 * 24 * 30,
            'yearly' => 60 * 60 * 24 * 365.25,
        );
        $time = time();

        $backupSizeLog = "";
        
        //Running DB Dump
        $old_file = false;
        foreach ($keepArr as $name => $keep) {
            $dir = "{$backupDir}/$name/$type";
            $minutes = $seconds[$name];
            
            //this only finds the exact backup name
            //$files = $this->runShell("ls -t $dir | grep '.{$backupName}'", true);
            //has been replaced with this that finds all files in the directory,
            //which is much more useful for occasions when the backup name has changed
            $files = $this->runShell("ls -t $dir", true);
            
            //var_dump($files);

            
            //calculating if a backup is due
            $backupDue = false;
            if (!empty($files)) {
                $filetime = filemtime("$dir/$files[0]");
                $dueSeconds = $seconds[$name];
                //$this->debugmsg("Testing if backup is due, time: $time, file time: $filetime, time minus file time: " . ($time - $filetime) . ", due seconds: $dueSeconds");

                $backupDue = $time - filemtime("$dir/$files[0]") >= $seconds[$name];
                if ($backupDue) {
                    $this->ok(ucfirst($name) . " backup due - last backup is " . ($time - $filetime) . " seconds old");
                } else {
                    $this->ok(ucfirst($name) . " backup not due - last backup is " . ($time - $filetime) . " seconds old - due at $dueSeconds seconds");
                }
            }
            
            $force = false;
            //only set this to true for debugging
            //$force = true;

            if ($force
                //|| $keep === -1 //keep infinite amounts - not needed here
                || empty($files) //no files present yet
                || $backupDue //the backup is due
                ) {
                $fileNameShort = date('Y-m-d_H-iT') . "_{$backupName}";
                $file = "$dir/" . $fileNameShort;
                //$this->debugmsg($file);

                if (empty($old_file)) {
                    //No old file - creating the actual dump
                    if ($type == 'db') {
                        $this->mysqlDump($file);
                    } elseif ($type == 'assets') {
                        $this->assetsDump($file);
                    }

                    $old_file = $file;
                    $old_fileNameShort = "$name/$type/$fileNameShort";
                } else {
                    //Additional files are copied over - see log
                    $this->runShell("cp $old_file $file");
                    $this->ok("Copied $old_fileNameShort to $name/$type/$fileNameShort");
                    $file = $old_file;
                }
                
                //Deleting unwanted backups according to the config
                if ($keep !== -1 && count($files) > $keep) {
                    $reversed_files = array_reverse($files);
                    $i = 0;
                    foreach ($files as $file) {
                        //$this->debugmsg("$file	$i");
                        if ($i >= $keep) {
                            $this->runShell("rm $dir/" . $file);
                            $this->ok("Deleted obsolete file $name/$type/$fileNameShort");
                        }
                        $i++;
                    }
                }
            }
            $keptBackups = $this->runShell("ls -t $dir", true);
            $dirSizeArr1 = $this->runShell("du -sh $dir", true);
            $dirSizeArr2 = explode('	', $dirSizeArr1[0]);
            $dirSize = $dirSizeArr2[0];
            
            $backupSizeLog .= "$name: $dirSize, ";
            $this->ok("Keeping " . count($keptBackups) . " out of max $keep $name backups totalling $dirSize");
        }
        
        $this->ok("Total $type backup size: " . rtrim($backupSizeLog, ", "));
    }
    

    /**
     * Run the actual mysql dump
     */
    protected function mysqlDump($file)
    {
        global $databaseConfig;

        //Mamp mode
        $socketInfo = null;
        if ($this->isMamp) {
            $socketInfo = ' --socket ' . '/Applications/MAMP/tmp/mysql/mysql.sock';
        }
        
        //$this->runShell("mysqldump {$opts['extra']} {$opts['connection']} $db | gzip -c > $file");
        $cmd = 'mysqldump -u'
            . $databaseConfig['username']
            //. ' --port 8889'
            . $socketInfo
            . ' -p' . escapeshellarg($databaseConfig['password'])
            . ' -h ' . $databaseConfig['server']
            . ' ' . $databaseConfig['database']
            . ' | gzip -c > ' . $file;

        //echo $cmd;
        $this->runShell($cmd);
        $this->ok("Created db dump $file");
    }

    /**
     * Run the actual assets dump
     */
    protected function assetsDump($file)
    {
        //$this->ok('TODO: implement asset dump');

        $baseFolder = Director::baseFolder();
        
        //simple version
        //$cmd = 'tar -zcvf ' . $file . ' ' . ASSETS_PATH;
        //$this->runShell($cmd);
        //
        //more advanced version, cding to base dir first to create a tar without all the sub dirs
        $cmd = "cd '{$baseFolder}'; nice -n 19 tar -zcf {$file} " . ASSETS_DIR . ";";
        exec($cmd);
        
        $this->ok("Created assets dump $file");
        
        
        //$this->ok($cmd);

        //explaination:
        //-z: filter the archive through gzip
        //-c: create a new archive
        //-v: verbosely list files processed
        //-f: use archive file
        //more info here: http://unixhelp.ed.ac.uk/CGI/man-cgi?tar
    }

    /**
     * Parsing keep string
     * @param string $value
     * @return array
     */
    protected function parse_keep($value)
    {
        $parts = explode(',', $value);
        if (count($parts) !== 5) {
            $this->error('keep: Must consist of 5 digits.');
        }
        $names = array('hourly', 'daily', 'weekly', 'monthly', 'yearly');
        $opts = array();
        foreach ($parts as $part) {
            if ($part !== '-1' && !ctype_digit($part)) {
                $this->error("--keep: Part '$part' must be a digit.");
            }
            if ($part !== "0") {
                $opts[array_shift($names)] = (int) $part;
            }
        }
        return $opts;
    }

    /**
     * Creating rotate dirs
     */
    protected function create_dirs()
    {
        if (!is_writable($this->backupDir)) {
            $this->error("backup-dir " . $this->backupDir . " is not writeable.");
        }
        foreach ($this->backupKeepArr as $k => $v) {
            if ($v === 0) {
                continue;
            }
            $dir = $this->backupDir . "/$k";
            $exists = is_dir($dir);
            if (!$exists && !mkdir($dir)) {
                $this->error("backup-dir: $dir is not writeable.");
            }
            if (!$exists && !mkdir($dir. '/assets')) {
                $this->error("backup-dir: $dir/assets is not writeable.");
            }
            if (!$exists && !mkdir($dir. '/db')) {
                $this->error("backup-dir: $dir/db is not writeable.");
            }
            
            if (!$exists) {
                $this->ok("Created directory: $dir");
            }
        }
    }

    protected function runShell($cmd)
    {
        $cmd = "nice -n 19 $cmd";

        exec($cmd, $output, $status);
        return $output;
    }

    protected function error($message, $status = 1)
    {
        echo "\033[31m$message\033[0m\n";
        DTLog::info('ERROR: '.$message);
        exit($status);
    }

    protected function ok($message)
    {
        DTLog::info($message);
        echo "\033[32m$message\033[0m\n";
    }
    
    /**
     * Debugs are printed in yellow
     * See this for reference:
     * http://www.bashguru.com/2010/01/shell-colors-colorizing-shell-scripts.html
     */
    protected function debugmsg($message)
    {
        DTLog::debug($message);
        echo "\033[33m$message\033[0m\n";
    }
}
