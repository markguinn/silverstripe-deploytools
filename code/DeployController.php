<?php
/**
 * @author Mark Guinn <mark@adaircreative.com>
 * @package deploy
 * @date 3.15.13
 */
class DeployController extends Controller
{
	static $allowed_actions = array('index', 'commit_hook', 'install', 'InstallForm');

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
		if (!Permission::check('ADMIN')) return Security::permissionFailure($this);

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
			if ($host = DeployHostAPI::instance_if_matched($line)) {
				$fields = $host->addInstallFormFields($fields);
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

		// install hooks if needed
		if (isset($data['RepoType']) && !empty($data['RepoType'])) {
			$data['PostURL'] = Director::absoluteURL(empty($data['PostURL']) ? self::default_hook_url() : $data['PostURL']);
			$host = DeployHostAPI::factory($data['RepoType'], $data['RepoID']);
			$actions = array_merge($actions, $host->processInstall($data));
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
			DTLog::debug('ignored #1');
			return 'ignored';
		}

		$data = ($json) ? json_decode($json, true) : null;
		if (!$data || !is_array($data['commits'])) {
			DTLog::debug('ignored #2');
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
			DTLog::debug('ignored #3');
			return 'ignored';			
		}

		// create the deployment
		increase_time_limit_to(600);
		$deploy = new Deploy(BASE_PATH, array());

		$deploy->post_deploy = function() use ($deploy) {
			global $_FILE_TO_URL_MAPPING;

			// composer install if detected
			if (file_exists(BASE_PATH . DIRECTORY_SEPARATOR . 'composer.json')) {
				if (file_exists('/usr/local/bin/composer')) {
					// TODO: more flexible composer detection
					exec('composer install', $output);
					DTLog::info('Executing composer install...' . implode("\n", $output));
					//Checking for composer.phar
				} elseif (file_exists('/usr/local/bin/composer.phar')) {
					exec('/usr/local/bin/composer.phar install', $output);
					DTLog::info('Executing composer install...' . implode("\n", $output));
				} else {
					DTLog::info('composer.json detected but unable to locate composer.');
				}
			}

			// clear cache
			DTLog::info('Clearing cache...');
			DeployController::clear_cache();

			// update database
			if (isset($_FILE_TO_URL_MAPPING[BASE_PATH])) {
				exec('php framework/cli-script.php dev/build', $output2);
				DTLog::info('Updating database...' . implode("\n", $output2));
			} else {
				DTLog::info('Database not updated. $_FILE_TO_URL_MAPPING must be set for '.BASE_PATH);
			}

//    		SS_ClassLoader::instance()->getManifest()->regenerate();
//            ob_start();
//            DatabaseAdmin::create()->doBuild(false, true, false);
//            DTLog::info('dev/build complete: '.ob_get_contents());
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
	
}
