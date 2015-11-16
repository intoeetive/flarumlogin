<?php

namespace Craft;

require 'flarum/vendor/autoload.php';

use Flarum\Api\Middleware;
use Flarum\Forum\Controller\AuthenticateUserTrait;
use Illuminate\Contracts\Bus\Dispatcher;

use Flarum\Core\AuthToken;
/**
 * Cocktail Recipes Service
 *
 * Provides a consistent API for our plugin to access the database
 */
class FlarumLoginService extends BaseApplicationComponent
{
    
    use AuthenticateUserTrait;
    
    protected $dispatcher;
    
    private $settings;

    /**
     * Create a new instance of the Cocktail Recpies Service.
     * Constructor allows IngredientRecord dependency to be injected to assist with unit testing.
     *
     * @param @ingredientRecord IngredientRecord The ingredient record to access the database
     */
    public function __construct($ingredientRecord = null)
    {
        $this->settings = craft()->plugins->getPlugin('flarumLogin')->getSettings();
    }
    
    public function getUser($username)
    {
        
        $headers = array(
            'Content-Type: application/vnd.api+json',
            'Accept: application/vnd.api+json',
            'Authorization: Token '.$this->settings->flarumApiToken
        );
        $request = $this->doRequest(rtrim($this->settings->flarumUrl, '/').'/api/users?filter[q]='.$username, 'GET', $headers);
        $data = json_decode($request);    
 
        if (empty($data->data[0]))
        {
            $this->_refreshToken();
            $new_user = array('data'=> array(
                'attributes' => array(
                    'username' => $username,
                    'email' => craft()->userSession->getUser()->email,
                    'password' => $this->generatePassword()
               )        
            ));

            $create = $this->createUser($new_user);
            
            if (isset($create->errors))
            {
                //showstopper
                return false;
            }
        }
        
        $login = $this->loginUser($username);

    }
    
    
    
    public function createUser($data)
    {
        $headers = array(
            'Content-Type: application/vnd.api+json',
            'Accept: application/vnd.api+json',
            'Authorization: Token '.$this->settings->flarumApiToken
        );
        $request = $this->doRequest(rtrim($this->settings->flarumUrl, '/').'/api/users', 'POST', $headers, [], json_encode($data));    
        
        if ($request=='')
        {
            if ($this->_refreshToken())
            {
                $this->doRequest(rtrim($this->settings->flarumUrl, '/').'/api/users', 'POST', $headers, [], json_encode($data));  
            }    
        }
        
        return json_decode($request);
        
    }
    
    public function updateUser($username, $userdata)
    {
        $headers = array(
            'Content-Type: application/vnd.api+json',
            'Accept: application/vnd.api+json',
            'Authorization: Token '.$this->settings->flarumApiToken
        );
        
        $request = $this->doRequest(rtrim($this->settings->flarumUrl, '/').'/api/users?filter[q]='.$username, 'GET', $headers);
        $data = json_decode($request);    
        
        if (empty($data->data[0]))
        {
            return false;
        }
        else
        {
            $userid = $data->data[0]->id;
        }

        $request = $this->doRequest(rtrim($this->settings->flarumUrl, '/').'/api/users/'.$userid, 'PATCH', $headers, [], json_encode($userdata));
        
        if ($request=='')
        {
            if ($this->_refreshToken())
            {
                $this->doRequest(rtrim($this->settings->flarumUrl, '/').'/api/users/'.$userid, 'PATCH', $headers, [], json_encode($userdata));
            }    
        }     
        
        return json_decode($request);
    }
    
    function updateAvatar($userid, $path)
    {
        $headers = array(
            'Content-Type: application/vnd.api+json',
            'Accept: application/vnd.api+json',
            'Authorization: Token '.$this->settings->flarumApiToken
        );
        
        $data = array(
            'avatar'    => new \CurlFile($path, mime_content_type($path))
        );

        $request = $this->doRequest(rtrim($this->settings->flarumUrl, '/').'/api/users/'.$userid.'/avatar', 'POST', $headers, [], $data);

        if ($request=='')
        {
            if ($this->_refreshToken())
            {
                $this->doRequest(rtrim($this->settings->flarumUrl, '/').'/api/users/'.$userid.'/avatar', 'POST', $headers, [], $data);
            }    
        }     
        
        return json_decode($request);
    }
    
    public function deleteUser($username, $reassign)
    {
        $headers = array(
            'Content-Type: application/vnd.api+json',
            'Accept: application/vnd.api+json',
            'Authorization: Token '.$this->settings->flarumApiToken
        );
        
        $request = $this->doRequest(rtrim($this->settings->flarumUrl, '/').'/api/users?filter[q]='.$username, 'GET', $headers);
        $data = json_decode($request);    
        
        if (empty($data->data[0]))
        {
            return false;
        }
        else
        {
            $userid = $data->data[0]->id;
        }
        
        $request = $this->doRequest(rtrim($this->settings->flarumUrl, '/').'/api/users/'.$userid, 'DELETE ', $headers);
        
        if ($request=='')
        {
            if ($this->_refreshToken())
            {
                $this->doRequest(rtrim($this->settings->flarumUrl, '/').'/api/users/'.$userid, 'DELETE ', $headers);
            }    
        }     
    }
    
    public function loginUser($username)
    {
        $headers = array(
            'Content-Type: application/vnd.api+json',
            'Accept: application/vnd.api+json',
            'Authorization: Token '.$this->settings->flarumApiToken
        );
        
        $data = array(
            'username'  => $username
        );

        $request = $this->doRequest(rtrim($this->settings->flarumUrl, '/').'/api/login/craft', 'POST', $headers, [], json_encode($data)); 
        
        if ($request!='')
        {
            setcookie('flarum_remember',$request,time() + 14 * 24 * 60 * 60);
        }
        else
        {
            if ($this->_refreshToken())
            {
                $this->loginUser($username);
            }
        }
        
        return $request;

    }
    
    public function logoutUser()
    {
            
        setcookie('flarum_remember', '', 0);

    }
    
    
   	public static function doRequest($url, $method = 'GET', $headers = array(), $curlOptions = array(), $payload = '')
	{

        $ch = curl_init($url);

		if ($method == 'HEAD')
		{
			curl_setopt($ch, CURLOPT_NOBODY, 1);
		}
		else
		{
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}

		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		foreach ($curlOptions as $option => $value)
		{
			curl_setopt($ch, $option, $value);
		}

		if ($method == "POST" OR $method == "PATCH")
		{
			curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		}


		$response = curl_exec($ch);
        $e = curl_error($ch);

		curl_close($ch);

		return $response;
	}

    public function generatePassword($length = 16)
    {
        $chars = array_merge(range(0,9), range('a','z'), range('A','Z'));
        shuffle($chars);
        $password = implode(array_slice($chars, 0, $length));
        return $password;        
            
    }    
    
    
    private function _refreshToken()
    {
        //if not logged in yet, then token must be expired.
        //get the new ones
        $params = array(
            'identification'    => $this->settings->flarumApiLogin,
            'password'          => $this->settings->flarumApiPass
        );
        $headers = array(
            'Content-Type: application/vnd.api+json',
            'Accept: application/vnd.api+json'
        );
        $token_request = $this->doRequest(rtrim($this->settings->flarumUrl, '/').'/api/token', 'POST', $headers, [], json_encode($params));
        try {
            $json = json_decode($token_request);
            if ($json==null OR isset($json->errors))
            {
                return false;
            }
            $new_settings = array(
                'flarumUrl' => $this->settings->flarumUrl,
                'flarumApiLogin' => $this->settings->flarumApiLogin,
                'flarumApiPass' => $this->settings->flarumApiPass,
                'flarumApiToken' => $json->token
            );
            $plugin = craft()->plugins->getPlugin('flarumLogin');
            $save = craft()->plugins->savePluginSettings($plugin, $new_settings);
            
            if ($save===true)
            {
                $this->settings = $plugin->getSettings();
                return true;
            }
            else
            {
                return false;
            }
        }
        catch(Exception $e) {
            return false;
        } 
    }
}
