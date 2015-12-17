<?php
/**
 * Bitbucket api driver
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @package deploytools
 * @subpackage hosts
 * @date 07.01.2013
 */
class BitbucketAPI extends DeployHostAPI
{
    /**
     * @param string $line
     * @return BitbucketAPI|bool
     */
    public static function instance_if_matched($line)
    {
        if (preg_match('#^origin\s+.*bitbucket\.org.(.+?/.+?)\.git#', $line, $matches)) {
            return self::create($matches[1]);
        } else {
            return false;
        }
    }


    /**
     * @return string
     */
    public function getHumanName()
    {
        return 'Bitbucket';
    }


    /**
     * @param array $data - post data
     * @return array $actions - text descriptions of any actions performed
     */
    public function processInstall(array $data)
    {
        if (isset($data['ApiUser']) && !empty($data['ApiUser']) && !empty($data['ApiPassword'])) {
            $postURL = $data['PostURL'];
            $json = file_get_contents('https://' . urlencode($data['ApiUser']) . ':' . urlencode($data['ApiPassword'])
                . '@api.bitbucket.org/1.0/repositories/' . $data['RepoID'] . '/services');
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
                    . '@api.bitbucket.org/1.0/repositories/' . $data['RepoID'] . '/services');
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, 'type=POST&URL=' . urlencode($postURL));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($http_status == 200 || $http_status == 201) {
                    $actions[] = 'Bitbucket POST service for deployment was created.';
                } else {
                    $actions[] = 'Bitbucket POST service failed. Response code=' . $http_status;
                }
            } else {
                $actions[] = 'Bitbucket POST service was not created because it already exists.';
            }
        }

        return $actions;
    }
}
