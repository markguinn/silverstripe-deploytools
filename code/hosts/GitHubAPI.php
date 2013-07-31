<?php
/**
 * Github api driver
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @package deploytools
 * @subpackage hosts
 * @date 07.01.2013
 */
class GitHubAPI extends DeployHostAPI
{
	/**
	 * @param string $line
	 * @return GitHubAPI|bool
	 */
	public static function instance_if_matched($line) {
		if (preg_match('#^origin\s+.*github\.com.(.+?/.+?)\.git#', $line, $matches)) {
			return self::create($matches[1]);
		} else {
			return false;
		}
	}

	/**
	 * @return string
	 */
	function getHumanName() {
		return 'GitHub';
	}


	/**
	 * @param array $data - post data
	 * @return array $actions - text descriptions of any actions performed
	 */
	function processInstall(array $data) {
		if (isset($data['ApiUser']) && !empty($data['ApiUser']) && !empty($data['ApiPassword'])) {
			$postURL = $data['PostURL'];
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'https://' . urlencode($data['ApiUser']) . ':' . urlencode($data['ApiPassword'])
							. '@api.github.com/repos/' . $data['RepoID'] . '/hooks');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Silverstripe DeployTools Module / user='.$data['ApiUser']);
			$json = curl_exec ($ch);
			$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close ($ch);
			if ($http_status == 200 || $http_status == 201) {
				$services = $json ? json_decode($json, true) : array();
			} else {
				return array();
			}

			// if services already exist, make sure there's not already one for this site
			if ($services && count($services) > 0) {
				foreach ($services as $service) {
					if ($service['name'] == 'web' && $service['config']['url'] == $postURL) {
						$postURL = false; // indicate that we don't need a new one
					}
				}
			}

			// assuming we need to, post the request
			if ($postURL) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, 'https://' . urlencode($data['ApiUser']) . ':' . urlencode($data['ApiPassword'])
					. '@api.github.com/repos/' . $data['RepoID'] . '/hooks');
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
					'name'      => 'web',
					'events'    => array('push'),
					'active'    => 1,
					'config'    => array(
						'url'           => $postURL,
						'content_type'  => 'json',
					),
				)));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_exec ($ch);
				$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close ($ch);
				if ($http_status == 200 || $http_status == 201) {
					$actions[] = 'GitHub web hook for deployment was created.';
				} else {
					$actions[] = 'GitHub web hook failed. Response code=' . $http_status;
				}
			} else {
				$actions[] = 'GitHub web hook was not created because it already exists.';
			}
		}

		return $actions;
	}

}
 
