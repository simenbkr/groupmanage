<?php
//require_once __DIR__ . '/vendor/autoload.php';

namespace GroupManage;

define('APPLICATION_NAME', 'PHP Groupmanage');
define('CREDENTIALS_PATH', __DIR__ . '/credentials/groupmanage-creds.json');
define('CLIENT_SECRET_PATH', __DIR__ . '/client_secret.json');
define('SCOPES', implode(' ', array(
        Google_Service_Directory::ADMIN_DIRECTORY_GROUP)
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
        $this->service = $service = new Google_Service_Directory($this->client);
    }
    
    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    private function getClient()
    {
        $client = new Google_Client();
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
        
        $member = new Google_Service_Directory_Member();
        $member->setEmail($email);
        $member->setRole($role);
        
        $added = $this->service->members->insert($group, $member);
        
        return $added;
    }
    
    public function removeFromGroup($email, $group)
    {
        return $this->service->members->delete($group, $email);
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
    
    public function listGroup($group)
    {
        return $this->service->members->listMembers($group)['members'];
    }
}


?>