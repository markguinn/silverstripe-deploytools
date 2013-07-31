<?php
/**
 * This is pretty much verbatim copied from:
 * http://brandonsummers.name/blog/2012/02/10/using-bitbucket-for-automated-deployments/
 */
class Deploy
{

	/**
	 * A callback function to call after the deploy has finished.
	 *
	 * @var callback
	 */
	public $post_deploy;

	/**
	 * The name of the branch to pull from.
	 *
	 * @var string
	 */
	private $_branch = 'master';

	/**
	 * The name of the remote to pull from.
	 *
	 * @var string
	 */
	private $_remote = 'origin';

	/**
	 * The directory where your website and git repository are located, can be
	 * a relative or absolute path
	 *
	 * @var string
	 */
	private $_directory;

	/**
	 * Sets up defaults.
	 *
	 * @param  string $directory  Directory where your website is located
	 * @param  array $options    Information about the deployment
	 */
	public function __construct($directory, $options = array()) {
		// Determine the directory path
		$this->_directory = realpath($directory) . DIRECTORY_SEPARATOR;

		$available_options = array('branch', 'remote');

		foreach ($options as $option => $value) {
			if (in_array($option, $available_options)) {
				$this->{'_' . $option} = $value;
			}
		}

		DTLog::info('Attempting deployment...');
	}

	/**
	 * Executes the necessary commands to deploy the website.
	 */
	public function execute() {
		try {
			// Make sure we're in the right directory
			//exec('cd '.$this->_directory, $output);
			chdir($this->_directory);
			DTLog::info("Changing working directory to {$this->_directory}...");

			// Discard any changes to tracked files since our last deploy
			exec('git reset --hard HEAD', $output);
			DTLog::info('Reseting repository... ' . implode("\n", $output));

			// Update the local repository
			exec('git pull ' . $this->_remote . ' ' . $this->_branch, $output2);
			DTLog::info('Pulling in changes... ' . implode("\n", $output2));

			// Secure the .git directory
			//exec('chmod -R o-rx .git');
			//DTLog::info('Securing .git directory... ');

			if (is_callable($this->post_deploy)) {
				call_user_func($this->post_deploy, $this->_data);
			}

			DTLog::info('Deployment successful.');
		} catch (Exception $e) {
			DTLog::info($e, 'ERROR');
		}
	}

}
