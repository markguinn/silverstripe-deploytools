<?php
/**
 * Abstract base class for different "host" drivers (github, bitbucket, etc)
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @package deploytools
 * @date 07.01.2013
 */
class DeployHostAPI extends Object
{
	static $drivers = array('BitbucketAPI', 'GitHubAPI');


	/**
	 * @var string $repoID - depends on driver what this means
	 */
	protected $repoID;


	/**
	 * Checks against all known drivers and returns an instance
	 * of the correct one if the line matches.
	 *
	 * @param string $line - a line from a 'git remote -v' output
	 * @return DeployHost|bool
	 */
	static function instance_if_matched($line) {
		foreach (Config::inst()->get('DeployHostAPI', 'drivers') as $driverClass) {
			$res = call_user_func(array($driverClass, 'instance_if_matched'), $line);
			if ($res !== false) return $res;
		}

		return false;
	}


	/**
	 * @param string $type - must in the drivers array
	 * @param string $repoID
	 * @return DeployHostAPI
	 * @throws Exception
	 */
	static function factory($type, $repoID) {
		if (in_array($type, Config::inst()->get('DeployHostAPI', 'drivers'))) {
			return Object::create($type, $repoID);
		} else {
			throw new Exception('Invalid DeployHostAPI driver type requested.');
		}
	}


	/**
	 * @param string $repoID
	 */
	function __construct($repoID) {
		$this->repoID = $repoID;
	}


	/**
	 * @return string
	 */
	function getType() {
		return get_class($this);
	}


	/**
	 * @return string
	 */
	function getHumanName() {
		return '';
	}


	/**
	 * This is a generic form, but it will probably work in most cases.
	 *
	 * @param array $fields
	 * @return array
	 */
	public function addInstallFormFields(array $fields) {
		$fields[] = HiddenField::create('RepoType', '', $this->getType());
		$fields[] = HiddenField::create('RepoID', '', $this->repoID);
		$fields[] = LiteralField::create('repo', sprintf("<p>"
			. "You appear to be deploying from %s (%s.git). "
			. "If you enter your username and password below we will set up a service hook to deploy automatically. "
			. "Your credentials will not be logged or saved."
			. "</p>", $this->getHumanName(), $this->repoID));
		$fields[] = TextField::create('PostURL', 'Commit Hook URL (must be unique to this server)', DeployController::default_hook_url());
		$fields[] = TextField::create('ApiUser', $this->getHumanName() . ' Username');
		$fields[] = PasswordField::create('ApiPassword', $this->getHumanName() . ' Password');
		return $fields;
	}

}
