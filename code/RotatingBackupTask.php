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
 * - fix the problem with yearly backups
 * - clean up/document code and make it more readable
 * 
 * @author Anselm Christophersen <ac@anselm.dk>
 * 
 */
class RotatingBackupTask extends HourlyTask {

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
	function process() {
//		if (!Director::is_cli()) {
//			echo "This script can only be run from the command line";
//			return false;
//		}

		//Initialization
		$this->varInit();

		//Create directories
		$this->create_dirs();

		//Run backup:
		//DB
		$this->runRotatingBackup('db');
		//Assets
		$this->runRotatingBackup('assets');
	}

	/**
	 * Initializing variables
	 */
	protected function varInit() {

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
	protected function runRotatingBackup($type = 'db') {

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


		//Running DB Dump
		$old_file = false;
		foreach ($keepArr as $name => $keep) {
			$dir = "{$backupDir}/$name";
			$minutes = $seconds[$name];
			$files = $this->runShell("ls -t $dir | grep '.{$backupName}'", true);

			if ($name != 'yearly') { //currently there seems to be a bug with yearly - makig backups on each run
				if ($keep === -1
					|| empty($files)
					|| $time - filemtime("$dir/$files[0]") >= $seconds[$name]) {
					$file = "$dir/" . date('Y-m-d_H-iT') . "_{$backupName}";

					if (empty($old_file)) {
						if ($type == 'db') {
							$this->mysqlDump($file);
						} elseif ($type == 'assets') {
							$this->assetsDump($file);
						}

						$old_file = $file;
					} else {
						$this->runShell("cp $old_file $file");
						$file = $old_file;
					}
					$this->ok("Created $name $type backup $file");
					if ($keep !== -1 && count($files) > $keep) {
						$this->runShell("rm $dir/" . array_pop($files));
					}
				}
			}
		}		
		
		
		
	}
	

	/**
	 * Run the actual mysql dump
	 */
	protected function mysqlDump($file) {

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
		
	}

	/**
	 * Run the actual assets dump
	 */
	protected function assetsDump($file) {
		//$this->ok('TODO: implement asset dump');

		$baseFolder = Director::baseFolder();
		
		//simple version
		//$cmd = 'tar -zcvf ' . $file . ' ' . ASSETS_PATH;
		//$this->runShell($cmd);
		//
		//more advanced version, cding to base dir first to create a tar without all the sub dirs
		$cmd = "cd '{$baseFolder}'; nice -n 19 tar -zcf {$file} " . ASSETS_DIR . ";";
		exec($cmd);
		
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
	protected function parse_keep($value) {
		$parts = explode(',', $value);
		if (count($parts) !== 5)
			$this->error('keep: Must consist of 5 digits.');
		$names = array('hourly', 'daily', 'weekly', 'monthly', 'yearly');
		$opts = array();
		foreach ($parts as $part) {
			if ($part !== '-1' && !ctype_digit($part))
				$this->error("--keep: Part '$part' must be a digit.");
			if ($part !== "0")
				$opts[array_shift($names)] = (int) $part;
		}
		return $opts;
	}

	/**
	 * Creating rotate dirs
	 */
	protected function create_dirs() {
		if (!is_writable($this->backupDir))
			$this->error("backup-dir " . $this->backupDir . " is not writeable.");
		foreach ($this->backupKeepArr as $k => $v) {
			if ($v === 0)
				continue;
			$dir = $this->backupDir . "/$k";
			$exists = is_dir($dir);
			if (!$exists && !mkdir($dir))
				$this->error("backup-dir: $dir is not writeable.");
			if (!$exists)
				$this->ok("Created directory: $dir");
		}
	}

	protected function runShell($cmd) {
		$cmd = "nice -n 19 $cmd";

		exec($cmd, $output, $status);
		return $output;
	}

	protected function error($message, $status = 1) {
		echo "\033[31m$message\033[0m\n";
		exit($status);
	}

	protected function ok($message) {
		echo "\033[32m$message\033[0m\n";
	}

}
