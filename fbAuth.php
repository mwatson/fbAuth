<?
/*
 * fbAuth v1.0.0
 * 
 * Copyright (c) 2012, Mike Watson, Mantis-Eye Labs
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met: 
 * 
 * 1. Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer. 
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution. 
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 * The views and conclusions contained in the software and documentation are those
 * of the authors and should not be interpreted as representing official policies, 
 * either expressed or implied, of the FreeBSD Project.
 *
 */

class fbAuth
{
        // App settings
        private $app_id         = '';
        private $app_secret     = '';
        private $app_callback   = '';
        
        // Access
        private $access_code    = false;
        private $access_token   = false;
        private $access_expires = 0;
        
        // Scope granted to the token
        private $scope = '';
        
        // Array of apps/pages
        private $apps = array();
        
        // Error data
        private $error = false;
        
        public function __construct($settings = false)
        {
                // Load settings
                if(isset($settings['app_id']))
                        $this->app_id = $settings['app_id'];
                        
                if(isset($settings['app_secret']))
                        $this->app_secret = $settings['app_secret'];
                
                if(isset($settings['app_callback']))
                        $this->app_callback = $settings['app_callback'];
                
                if(isset($settings['scope']))
                        $this->scope = $settings['scope'];
        }
        
        public function __destruct()
        {
        }
        
        // Load the access code from GET or an external source and return it
        public function loadAccessCode($code = false)
        {
                if($code)
                        $this->access_code = $code;
                else if(isset($_GET['code']) && strlen($_GET['code']))
                        $this->access_code = $_GET['code'];
                        
                return $this->access_code;
        }
        
        // Redirect to Facebook (if no access code present) to get an access code
        public function requestAccessCode()
        {
                if(!$this->loadAccessCode())
                {
                        $request_url = self::encodeURL(
                                'https://www.facebook.com/dialog/oauth?client_id=%s&redirect_uri=%s&scope=%s', 
                                array($this->app_id, $this->app_callback, $this->scope)
                        );
                
                        header("Location: {$request_url}");
                        exit();
                }
                
                return true;
        }
        
        // Request an access token using the access code and app settings
        public function requestAccessToken()
        {       
                if(!$this->loadAccessCode())
                        return false;
                
                $result = self::getURLContents(
                        'https://graph.facebook.com/oauth/access_token?client_id=%s&redirect_uri=%s&client_secret=%s&code=%s', 
                        array($this->app_id, $this->app_callback, $this->app_secret, $this->access_code)
                );
                
                $tokens = explode('&', $result);
                if(is_array($tokens))
                {
                        foreach($tokens as $token)
                        {
                                $t = explode('=', $token);
                                if($t[0] == 'access_token')
                                        $this->access_token = $t[1];
                                else if($t[0] == 'expires')
                                        $this->access_expires = time() + $t[1];
                        }
                }
                
                return true;
        }
        
        // Request app/page access tokens
        public function requestAppAccessTokens()
        {
                $result = self::getURLContents(
                        'https://graph.facebook.com/me/accounts?access_token=%s', 
                        array($this->access_token)
                );
                
                $account_data = json_decode($result);

                if($account_data && is_object($account_data) && !isset($account_data->error))
                {
                        foreach($account_data->data as $acct)
                                $this->apps[$acct->id] = $acct;
                                
                        return true;
                }
                else if(isset($account_data->error))
                {
                        $this->error = $account_data->error;
                }
                else if(!$account_data)
                {
                        $this->setErrorMessage('Malformed JSON');
                }
                else
                {
                        $this->setErrorMessage('Unknown error');
                }
                
                return false;
        }


        //
        // Helper functions for accessing private variables
        //

        public function getAccessToken()
        {
                return $this->access_token;
        }
        
        public function getAccessTokenExpires()
        {
                return $this->access_expires;
        }
        
        public function countApps()
        {
                return sizeof($this->apps);
        }
        
        public function getApps()
        {
                return $this->apps;
        }
        
        public function getAppAccessToken($app_id)
        {
                return is_object($this->apps[$app_id]) ? $this->apps[$app_id]->access_token : false;
        }
        
        public function getError()
        {
                return $this->error;
        }
        
        private function setErrorMessage($message)
        {
                $this->error = array('message' => $message);
                return true;
        }
        
        
        //
        // Static helper functions for encoding URLs and making requests
        //
        
        private static function encodeURL($base_url, $parameters)
        {
                if(!is_array($parameters))
                        return false;
                        
                foreach($parameters as $i => $param)
                        $parameters[$i] = rawurlencode($param);
                        
                return vsprintf($base_url, $parameters);
        }
        
        private static function getURLContents($url, $parameters = false)
        {
                if(is_array($parameters))
                        $url = self::encodeURL($url, $parameters);
        
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                
                $data = curl_exec($ch);
                if($data === false)
                {
                        $this->setErrorMessage('CURL Error: ' . curl_error($ch));
                }
                
                curl_close($ch);
                
                return $data;
        }
}
