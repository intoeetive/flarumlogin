<?php

namespace Craft;


class FlarumLogin_TokenController extends BaseController
{

    /**
    *
    * http://craft.dev/actions/flarumLogin/token/getToken
    * shoyld be added to Cron to run every 10-12 days
    * (the token lives for 14 days)
    *
    **/

    public function actionGetToken()
    {
        $plugin = craft()->plugins->getPlugin('flarumLogin');
        $settings = $plugin->getSettings();
        
        if (empty($settings))
        {
            $this->returnErrorJson('Plugin settings not defined');
        }
        
        //get the token
        $params = array(
            'identification'    => $settings->flarumApiLogin,
            'password'          => $settings->flarumApiPass
        );
        $headers = array(
            'Content-Type: application/vnd.api+json',
            'Accept: application/vnd.api+json'
        );
        $token_request = craft()->flarumLogin->doRequest(rtrim($settings->flarumUrl, '/').'/api/token', 'POST', $headers, [], json_encode($params));
        try {
            $json = json_decode($token_request);
            if ($json==null OR isset($json->errors))
            {
                $this->returnErrorJson('Could not get API token');
            }
            $new_settings = array(
                'flarumUrl' => $settings->flarumUrl,
                'flarumApiLogin' => $settings->flarumApiLogin,
                'flarumApiPass' => $settings->flarumApiPass,
                'flarumApiToken' => $json->token
            );
            $save = craft()->plugins->savePluginSettings($plugin, $new_settings);
            
            if ($save===true)
            {
                $this->returnJson(array('success'=>true));
            }
            else
            {
                $this->returnErrorJson('Could not save settings');
            }
        } 
        
        catch(Exception $e) {
            $this->returnErrorJson('Could not get API token');
        }

        
    }

}
