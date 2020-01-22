<?php

namespace Group;

define('APPLICATION_NAME', 'PHP Groupmanage');
define('CREDENTIALS_PATH', __DIR__ . '/credentials/groupmanage-creds.json');
define('CLIENT_SECRET_PATH', __DIR__ . '/client_secret.json');
define('SCOPES', implode(' ', array(
        \Google_Service_Directory::ADMIN_DIRECTORY_GROUP)
));

/*
if (php_sapi_name() != 'cli') {
  throw new Exception('This application must be run on the command line.');
}
*/

class GroupManage
{
    
    private $client;
    private $service;
    
    public function __construct()
    {
        $this->client = $this->getClient();
        $this->service = $service = new \Google_Service_Directory($this->client);
    }
    
    /**
     * Returns an authorized API client.
     * @return \Google_Client the authorized client object
     */
    private function getClient()
    {
        $client = new \Google_Client();
        $client->setApplicationName(APPLICATION_NAME);
        $client->setScopes(SCOPES);
        $client->setAuthConfig(CLIENT_SECRET_PATH);
        $client->setAccessType('offline');
        
        // Load previously authorized credentials from a file.
        $credentialsPath = $this->expandHomeDirectory(CREDENTIALS_PATH);
        if (file_exists($credentialsPath)) {
            $accessToken = json_decode(file_get_contents($credentialsPath), true);
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));
            
            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            
            // Store the credentials to disk.
            if (!file_exists(dirname($credentialsPath))) {
                mkdir(dirname($credentialsPath), 0700, true);
            }
            file_put_contents($credentialsPath, json_encode($accessToken));
            printf("Credentials saved to %s\n", $credentialsPath);
        }
        $client->setAccessToken($accessToken);
        
        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
        }
        return $client;
    }
    
    /**
     * Expands the home directory alias '~' to the full path.
     * @param string $path the path to expand.
     * @return string the expanded path.
     */
    private function expandHomeDirectory($path)
    {
        $homeDirectory = getenv('HOME');
        if (empty($homeDirectory)) {
            $homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
        }
        return str_replace('~', realpath($homeDirectory), $path);
    }
    
    public function addToGroup($email, $role, $group)
    {
        
        $member = new \Google_Service_Directory_Member();
        $email = strtolower(str_replace(' ','', $email));
        $member->setEmail($email);
        $member->setRole($role);
        
        if(!$this->inGroup($email, $group)) {
            $this->service->members->insert($group, $member);
            return true;
        }
        return false;
    }
    
    public function removeFromGroup($email, $group)
    {
        if($this->inGroup($email, $group)) {
            $this->service->members->delete($group, $email);
            return true;
        }
        return false;
    }
    
    public function inGroup($email, $group)
    {
        
        foreach ($this->listGroup($group) as $person) {
            if ($person['email'] === $email) {
                return true;
            }
        }
        
        return false;
    }
    
    public function listGroup($group, $count = 200)
    {
        if ($count <= 200) {
            return $this->service->members->listMembers($group)['members'];
        }

        $max_per_page = 200;
        $members = array();
        $fetched = 0;
        $parameters = array('maxResults' => $max_per_page);

        do {
            $m = $this->service->members->listMembers($group, $parameters);
            $members = array_merge($members, $m['members']);
            $fetched += count($m['members']);
            $page_token = $m['nextPageToken'];

            if($count - $fetched <= 0) {
                break;
            }

            $parameters['maxResults'] = min(200, $count - $fetched);
            $parameters['pageToken'] = $page_token;
        } while (strlen($page_token) > 0 || $fetched > $count);

        return $members;
    }
}