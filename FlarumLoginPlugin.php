<?php

namespace Craft;

class FlarumLoginPlugin extends BasePlugin
{
    public function getName()
    {
        return Craft::t('Flarum Login');
    }

    public function getVersion()
    {
        return '1.0';
    }

    public function getDeveloper()
    {
        return 'Yuri Salimovskiy';
    }

    public function getDeveloperUrl()
    {
        return 'http://www.intoeetive.com';
    }

    public function hasCpSection()
    {
        return true;
    }

    public function init()
    {
        craft()->on('userSession.onLogin', function(Event $event) { 

            craft()->flarumLogin->getUser($this->_cleanupUsername($event->params['username']));
            
        });
        
        craft()->on('userSession.onLogout', function(Event $event) { 

            craft()->flarumLogin->logoutUser();
            
        });
        
        craft()->on('users.onDeleteUser', function(Event $event) { 

            craft()->flarumLogin->deleteUser($this->_cleanupUsername($event->params['user']->getAttribute('username')), $event->params['transferContentTo']->getAttribute('username'));

        });
        
        
        craft()->on('users.onBeforeSaveUser', function(Event $event) { 

            //user – A UserModel object representing the user that was just saved.
            if ($event->params['isNewUser'])
            {
                $userdata = array('data'=> array(
                    'attributes.username' => $this->_cleanupUsername($event->params['user']->getAttribute('username')),
                    'attributes.email' => $event->params['user']->getAttribute('email'),
                    'attributes.password' => craft()->flarumLogin->generatePassword(), 
                    'attributes.isActivated' => !$event->params['user']->getAttribute('pending')                      
                ));
                $user_responce = craft()->flarumLogin->createUser($userdata);
            }
            else
            {
                $username = $this->_cleanupUsername($event->params['user']->getAttribute('username'));
                $userdata = array('data'=> array(
                    'attributes' => array(
                        'username' => $username,
                        'email' => $event->params['user']->getAttribute('email'), 
                        'isActivated' => 1//!$event->params['user']->getAttribute('pending')
                    )                   
                ));
                $CraftUserId = $event->params['user']->getAttribute('id');
                $old_userdata = $this->_getUserRecordById($CraftUserId);
                $user_responce = craft()->flarumLogin->updateUser($this->_cleanupUsername($old_userdata->username), $userdata);
            }

            /*
            $photo = $event->params['user']->photo;

            $photo_path = craft()->path->getUserPhotosPath().$username.'/100/'.$photo;
            
            $upload = craft()->flarumLogin->updateAvatar($user_responce->data->id, $photo_path);
            */
        });
        //
    }
    
    private function _cleanupUsername($username)
    {
        $username = str_replace('@', '-', $username);
        $username = str_replace('.', '-', $username);
        return $username;
    }
    
    private function _getUserRecordById($userId)
    {
        $userRecord = UserRecord::model()->findById($userId);
        if (!$userRecord) {
            throw new Exception(Craft::t('No user exists with the ID “{id}”.', array('id' => $userId)));
        }
        
        return $userRecord;
    }
    
    protected function defineSettings()
    {
        return array(
            'flarumDomain' => array(AttributeType::String, 'required'=>true),
            'flarumUrl' => array(AttributeType::String, 'required'=>true),
            'flarumApiLogin' => array(AttributeType::String, 'required'=>true),
            'flarumApiPass' => array(AttributeType::String, 'required'=>true),
            'flarumApiToken' => array(AttributeType::String)
        );
    }
    
    public function getSettingsHtml()
    {
       return craft()->templates->render('flarumlogin/settings', array(
           'settings' => $this->getSettings()
       ));
   }

}
