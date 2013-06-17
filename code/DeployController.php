<?php
/**
 * @author Mark Guinn <mark@adaircreative.com>
 * @package deploy
 * @date 3.15.13
 */
class DeployController extends Controller
{
	static $allowed_actions = array('index', 'commit_hook', 'install', 'InstallForm');
	static $log_path = '../logs'; // BASE_PATH is assumed

	/**
	 * Rejection
	 */
	public function index() {
		return $this->httpError(403);
	}


	/**
	 * @return string
	 */
	public function install() {
		if (!Permission::check('ADMIN')) return $this->httpError(403);

		return $this->customise(array(
			'Title'     => 'Install Deploy Tools',
			'Content'   => '<p>Some instructions would be nice.</p>',
			'Form'      => $this->InstallForm(),
		))->renderWith(array('Page','Page'));
	}


	/**
	 * @return Form
	 */
	public function InstallForm() {
		$fields = array();

		// find the repo slug and source
		chdir(BASE_PATH);
		exec(self::git() . ' remote -v', $out);
		foreach ($out as $line) {
			if (preg_match('#^origin\s+.*bitbucket\.org.(.+?)/(.+?)\.git#', $line, $matches)) {
				$fields[] = HiddenField::create('RepoUser', '', $matches[1]);
				$fields[] = HiddenField::create('RepoSlug', '', $matches[2]);
				$fields[] = LiteralField::create('repo', "<p>"
					. "You appear to be deploying from Bitbucket ({$matches[1]}/{$matches[2]}.git). "
					. "If you enter your username and password below we will set up a service hook to deploy automatically. "
					. "Your credentials will not be logged or saved."
					. "</p>");
				$fields[] = TextField::create('PostURL', 'Commit Hook URL (must be unique to this server)', self::default_hook_url());
				$fields[] = TextField::create('ApiUser', 'Bitbucket Username');
				$fields[] = PasswordField::create('ApiPassword', 'Bitbucket Password');
				break;
			}
		}

		// TODO: checkboxes to backup assets and database
		// TODO: backup destination options - local file, ftp, sftp, cloudfiles

		$actions = array(
			FormAction::create('doInstall', 'Install')
		);

		return new Form($this, 'InstallForm', FieldList::create($fields), FieldList::create($actions));
	}


	/**
	 * @param $data
	 * @param $form
	 * @return string
	 */
	public function doInstall($data, $form) {
		if (!Permission::check('ADMIN')) return $this->httpError(403);
		$actions = array();

		if (isset($data['ApiUser']) && !empty($data['ApiUser']) && !empty($data['ApiPassword'])) {
			$postURL = Director::absoluteURL(empty($data['PostURL']) ? self::default_hook_url() : $data['PostURL']);

			$json = file_get_contents('https://' . urlencode($data['ApiUser']) . ':' . urlencode($data['ApiPassword']) 
				. '@api.bitbucket.org/1.0/repositories/' . $data['RepoUser'] . '/' . $data['RepoSlug'] . '/services');
			$services = $json ? json_decode($json, true) : array();

			// if services already exist, make sure there's not already one for this site
			if ($services && count($services) > 0) {
				foreach ($services as $service) {
					if ($service['service']['type'] == 'POST'
							&& $service['service']['fields'][0]['value'] == $postURL) {
						$postURL = false; // indicate that we don't need a new one
					}
				}
			}

			// assuming we need to, post the request
			if ($postURL) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, 'https://' . urlencode($data['ApiUser']) . ':' . urlencode($data['ApiPassword']) 
					. '@api.bitbucket.org/1.0/repositories/' . $data['RepoUser'] . '/' . $data['RepoSlug'] . '/services');
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, 'type=POST&URL=' . urlencode($postURL));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$server_output = curl_exec ($ch);
				$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close ($ch);
				if ($http_status == 200 || $http_status == 201) {
					$actions[] = 'Bitbucket POST service for deployment was created.';
				} else {
					$actions[] = 'Bitbucket POST service failed. Response code=' . $http_status;
				}
			} else {
				$actions[] = 'Bitbucket POST service was not created because it already exists.';
			}
		}

		return $this->customise(array(
			'Title'     => 'Deploy Tools Install',
			'Content'   => '<p>The following actions were completed:</p><ul><li>'
							. implode('</li><li>', $actions) . '</li></ul>'
							. '<br /><br /><a href="/">go back to site</a>',
		))->renderWith(array('Page','Page'));
	}


	/**
	 * Handle commit hook post from bitbucket or github
	 * @return string
	 */
	public function commit_hook() {
		
		$logPath = BASE_PATH . '/' . self::$log_path . '/deploy.log';
		
		if (isset($_POST['payload'])) {
			// github and bitbucket use a 'payload' parameter
			$json = $_POST['payload'];
		} else {
			$json = file_get_contents('php://input');
		}
		if (!$json) {
			$this->log('ignored #1', 'DEBUG', $logPath);
			return 'ignored';
		}

		$data = ($json) ? json_decode($json, true) : null;
		if (!$data || !is_array($data['commits'])) {
			$this->log('ignored #2', 'DEBUG', $logPath);
			return 'ignored';
		}

		// look through the commits
		$found = false;
		$tags = array();
		foreach ($data['commits'] as $commit) {
			if (preg_match('/\[deploy(:.+)?\]/', $commit['message'], $matches)) {
				$found = true;
				if (count($matches) > 1 && $matches[1] != '') {
					$tags[] = substr($matches[1], 1);
				} else {
					$tags[] = 'live';
				}
			}
		}

		if (!$found) return 'ignored';
		if (defined('DEPLOY_TAG') && !in_array(DEPLOY_TAG, $tags)) {
			$this->log('ignored #3', 'DEBUG', $logPath);
			return 'ignored';			
		}

		// create the deployment
		increase_time_limit_to(600);
		$deploy = new Deploy(BASE_PATH, array(
			'log'   => $logPath
		));

		$deploy->post_deploy = function() use ($deploy) {
			global $_FILE_TO_URL_MAPPING;

			// composer install if detected
			if (file_exists(BASE_PATH . DIRECTORY_SEPARATOR . 'composer.json')) {
				if (file_exists('/usr/local/bin/composer')) {
					exec('composer install', $output);
					$deploy->log('Executing composer install...' . implode("\n", $output));
				//Checking for composer.phar
				} elseif (file_exists('/usr/local/bin/composer.phar')) {
					exec('/usr/local/bin/composer.phar install', $output);
					$deploy->log('Executing composer install...' . implode("\n", $output));
				} else {
					$deploy->log('composer.json detected but unable to locate composer.');
				}
			}

			// clear cache
			$deploy->log('Clearing cache...');
			DeployController::clear_cache();

			// update database
			if (isset($_FILE_TO_URL_MAPPING[BASE_PATH])) {
				exec('php framework/cli-script.php dev/build', $output2);
				$deploy->log('Updating database...' . implode("\n", $output2));
			} else {
				$deploy->log('Database not updated. $_FILE_TO_URL_MAPPING must be set for '.BASE_PATH);
			}

//    		SS_ClassLoader::instance()->getManifest()->regenerate();
//            ob_start();
//            DatabaseAdmin::create()->doBuild(false, true, false);
//            $deploy->log('dev/build complete: '.ob_get_contents());
//            ob_end_clean();
		};

		$deploy->execute();
		return 'ok';
	}


	/**
	 * Clears all the cache
	 * @static
	 */
	public static function clear_cache($dirname = '.') {
		$dirpath = TEMP_FOLDER . DIRECTORY_SEPARATOR . $dirname;
		if ($dir = opendir($dirpath)) {
			while (false !== ($file = readdir($dir))) {
				if ($file != '.' && $file != '..' && $file != '.svn' && $file != '.git') {
					if (is_dir($dirpath . DIRECTORY_SEPARATOR . $file)) {
						self::clear_cache($dirname . DIRECTORY_SEPARATOR . $file);
					} else {
						unlink($dirpath . DIRECTORY_SEPARATOR . $file);
					}
				}
			}
			closedir($dir);
		}
	}


	/**
	 * Somewhat janky way to detect absolute git path.
	 * This could use some work.
	 *
	 * @return string
	 */
	public static function git() {
		$check = array(
			'/Applications/Xcode.app/Contents/Developer/usr/bin/git',
			'/usr/bin/git',
			'/usr/local/bin/git',
		);

		foreach ($check as $fn) {
			if (file_exists($fn)) return $fn;
		}

		return 'git';
	}


	/**
	 * @return string
	 */
	public static function default_hook_url() {
		return Director::absoluteURL(DEPLOY_TOOLS_URL . '/commit-hook');
	}
	
	/**
	 * Basic logging
	 * NOTE: This duplicated logging functionality in {@see Deploy}
	 * It would make sense to move all loggin to a helper class
	 */
	public function log($message, $type = 'INFO', $logPath) {
		// Set the name of the log file
		$filename = $logPath;

		if (!file_exists($filename)) {
			// Create the log file
			file_put_contents($filename, '');

			// Allow anyone to write to log files
			chmod($filename, 0664);
		}

		// Write the message into the log file
		// Format: time --- type: message
		file_put_contents($filename, date($this->_date_format) . ' --- ' . $type . ': ' . $message . PHP_EOL, FILE_APPEND);
	}
	
	
}
