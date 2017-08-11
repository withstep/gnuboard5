<?php
/* !
 * HybridAuth
 * http://hybridauth.sourceforge.net | http://github.com/hybridauth/hybridauth
 * (c) 2009-2012, HybridAuth authors | http://hybridauth.sourceforge.net/licenses.html
 */
/**
 * Hybrid_Providers_Facebook provider adapter based on OAuth2 protocol
 *
 *
 */
class Hybrid_Providers_Payco extends Hybrid_Provider_Model_OAuth2 {
	/**
	 * default permissions, and a lot of them. You can change them from the configuration by setting the scope to what you want/need
	 * {@inheritdoc}
	 */
	private $idNo;

	/**
	 * {@inheritdoc}
	 */
	function initialize() {

        parent::initialize();

		// Provider API end-points
        $this->api->api_base_url  = 'https://id.payco.com/oauth2.0/';
        $this->api->authorize_url = 'https://id.payco.com/oauth2.0/authorize';
        $this->api->token_url     = 'https://id.payco.com/oauth2.0/token';
        $this->api->token_info   = 'https://apis3.krp.toastoven.net/payco/friends/getIdNoByFriendsToken.json';
        $this->api->profile_url   = 'https://apis3.krp.toastoven.net/payco/friends/getMemberProfileByFriendsToken.json';

		if (!$this->config["keys"]["id"] || !$this->config["keys"]["secret"]) {
			throw new Exception("Your application id and secret are required in order to connect to {$this->providerId}.", 4);
		}

	}
	/**
	 * {@inheritdoc}
	 */
	function loginBegin() {
        
        $token = md5(uniqid(mt_rand(), true));
        Hybrid_Auth::storage()->set('payco_auth_token', $token);

        $parameters = array(
            "response_type" => "code",
            "client_id" => $this->api->client_id,
            "redirect_uri" => $this->api->redirect_uri,
            "state" => $token,
            "userLocale" => "ko_KR",
            "serviceProviderCode" => "FRIENDS",
            );
        
        Hybrid_Auth::redirect($this->api->authorizeUrl($parameters));

        exit;

	}
	/**
	 * {@inheritdoc}
	 */
	function loginFinish() {

		// in case we get error_reason=user_denied&error=access_denied
		if (isset($_REQUEST['error']) && $_REQUEST['error'] == "access_denied") {
			throw new Exception("Authentication failed! The user denied your request.", 5);
		}
        
        // try to authenicate user
        $code = (array_key_exists('code', $_REQUEST)) ? $_REQUEST['code'] : "";
        try{
            $response = $this->api->authenticate( $code );
        }
        catch( Exception $e ){
            throw new Exception( "User profile request failed! {$this->providerId} returned an error: $e", 6 );
        }
        
        // check if authenticated
        if ( ! $this->api->authenticated() ){
            throw new Exception( "Authentication failed! {$this->providerId} returned an invalid access token.", 5 );
        }
        // store tokens
        $this->token("access_token",  $this->api->access_token);
        $this->token("refresh_token", $this->api->refresh_token);
        $this->token("expires_in",    $this->api->access_token_expires_in);
        $this->token("expires_at",    $this->api->access_token_expires_at);
        
        $this->setUserConnected();

	}

    function check_valid_access_token(){

        $params = array(
            'body' => array(
                'client_id'=>$this->api->client_id, 
                'access_token'=>$this->api->access_token,
            ),
        );
        
        $this->api->curl_header = array(

            'Content-Type:application/json',
            'client_id: '.$this->api->client_id,
            'access_token: '.$this->api->access_token,

        );

        $response = $this->api->api( $this->api->token_info, 'POST', $params );

        if( is_object($response) && !empty($response->idNo) && $response->header->successful ){
            $this->idNo = $response->idNo;

            return true;
        }

        return false;
    }

	/**
	 * {@inheritdoc}
	 */
	function logout() {
		parent::logout();
	}
	/**
	 * {@inheritdoc}
	 */

    /**
    * set propper headers
    */
	function getUserProfile() {

        $data = null;

		// request user profile
		try {
            
            if( $this->check_valid_access_token() ){
                $params = array(
                    'body' => array(
                        'client_id'=>$this->api->client_id, 
                        'access_token'=>$this->api->access_token,
                        'MemberProfile'=>'idNo,id,name',
                        'idNo'=>$this->idNo,
                    ),
                );

                $this->api->curl_header = array(
                    'Content-Type:application/json',
                    'client_id: '.$this->api->client_id,
                    'access_token: '.$this->api->access_token,
                    'Authorization: Bearer ' . $this->api->access_token,
                );

                $response = $this->api->api( $this->api->profile_url, 'POST', $params );
            }

		} catch (Exception $e) {
			throw new Exception("User profile request failed! {$this->providerId} returned an error: {$e->getMessage()}", 6, $e);
		}
        
        if( ! is_object($response) || property_exists($response, 'error_code') ){
            $this->logout();

            throw new Exception( "Authentication failed! {$this->providerId} returned an invalid access token.", 5 );
        }

        if( is_object($response) ){
            $result = json_decode(json_encode($response), true);
            $data = $result['memberProfile'];
        }

		// if the provider identifier is not received, we assume the auth has failed
		if (!isset($data["id"])) {
            $this->logout();
			throw new Exception("User profile request failed! {$this->providerId} api returned an invalid response: " . Hybrid_Logger::dumpData( $data ), 6);
		}

		# store the user profile.
		$this->user->profile->identifier = (array_key_exists('idNo', $data)) ? $data['idNo'] : "";
		$this->user->profile->username = (array_key_exists('name', $data)) ? $data['name'] : "";
		$this->user->profile->displayName = (array_key_exists('name', $data)) ? $data['name'] : "";
        $this->user->profile->age = (array_key_exists('ageGroup', $data)) ? $data['ageGroup'] : "";

        include_once(G5_LIB_PATH.'/register.lib.php');

        $payco_no = substr(base_convert($this->user->profile->identifier, 16, 36), 0, 16);
		$email = (array_key_exists('id', $data)) ? $data['id'] : "";

		$this->user->profile->gender = (array_key_exists('sexCode', $data)) ? $data['sexCode'] : "";

		$this->user->profile->email = ! valid_mb_email($email) ? $email : "";
		$this->user->profile->emailVerified = ! valid_mb_email($email) ? $email : "";


		if (array_key_exists('birthdayMMdd', $data)) {
			$this->user->profile->birthMonth = substr($data['birthdayMMdd'], 0, 2);
			$this->user->profile->birthDay = substr($data['birthdayMMdd'], 2, 4);
		}

        $this->user->profile->sid         = get_social_convert_id( $this->user->profile->identifier, $this->providerId );

		return $this->user->profile;
	}
	/**
	 * Attempt to retrieve the url to the cover image given the coverInfoURL
	 *
	 * @param  string $coverInfoURL coverInfoURL variable
	 * @return string               url to the cover image OR blank string
	 */
	function getCoverURL($coverInfoURL) {
		try {
			$headers = get_headers($coverInfoURL);
			if (substr($headers[0], 9, 3) != "404") {
				$coverOBJ = json_decode(file_get_contents($coverInfoURL));
				if (array_key_exists('cover', $coverOBJ)) {
					return $coverOBJ->cover->source;
				}
			}
		} catch (Exception $e) {
		}
		return "";
	}
	/**
	 * {@inheritdoc}
	 */
	function getUserContacts() {
		$apiCall = '?fields=link,name';
		$returnedContacts = array();
		$pagedList = false;
		do {
			try {
				$response = $this->api->api('/me/friends' . $apiCall);
			} catch (Exception $e) {
				throw new Exception("User contacts request failed! {$this->providerId} returned an error {$e->getMessage()}", 0, $e);
			}
			// Prepare the next call if paging links have been returned
			if (array_key_exists('paging', $response) && array_key_exists('next', $response['paging'])) {
				$pagedList = true;
				$next_page = explode('friends', $response['paging']['next']);
				$apiCall = $next_page[1];
			} else {
				$pagedList = false;
			}
			// Add the new page contacts
			$returnedContacts = array_merge($returnedContacts, $response['data']);
		} while ($pagedList == true);
		$contacts = array();
		foreach ($returnedContacts as $item) {
			$uc = new Hybrid_User_Contact();
			$uc->identifier = (array_key_exists("id", $item)) ? $item["id"] : "";
			$uc->displayName = (array_key_exists("name", $item)) ? $item["name"] : "";
			$uc->profileURL = (array_key_exists("link", $item)) ? $item["link"] : "https://www.facebook.com/profile.php?id=" . $uc->identifier;
			$uc->photoURL = "https://graph.facebook.com/" . $uc->identifier . "/picture?width=150&height=150";
			$contacts[] = $uc;
		}
		return $contacts;
	}
	/**
	 * Update user status
	 *
	 * @param mixed  $status An array describing the status, or string
	 * @param string $pageid (optional) User page id
	 * @return array
	 * @throw Exception
	 */
	function setUserStatus($status, $pageid = null) {
		if (!is_array($status)) {
			$status = array('message' => $status);
		}
		if (is_null($pageid)) {
			$pageid = 'me';
			// if post on page, get access_token page
		} else {
			$access_token = null;
			foreach ($this->getUserPages(true) as $p) {
				if (isset($p['id']) && intval($p['id']) == intval($pageid)) {
					$access_token = $p['access_token'];
					break;
				}
			}
			if (is_null($access_token)) {
				throw new Exception("Update user page failed, page not found or not writable!");
			}
			$status['access_token'] = $access_token;
		}
		try {
			$response = $this->api->api('/' . $pageid . '/feed', 'post', $status);
		} catch (Exception $e) {
			throw new Exception("Update user status failed! {$this->providerId} returned an error {$e->getMessage()}", 0, $e);
		}
		return $response;
	}
	/**
	 * {@inheridoc}
	 */
	function getUserStatus($postid) {
		try {
			$postinfo = $this->api->api("/" . $postid);
		} catch (Exception $e) {
			throw new Exception("Cannot retrieve user status! {$this->providerId} returned an error: {$e->getMessage()}", 0, $e);
		}
		return $postinfo;
	}
	/**
	 * {@inheridoc}
	 */
	function getUserPages($writableonly = false) {
		if (( isset($this->config['scope']) && strpos($this->config['scope'], 'manage_pages') === false ) || (!isset($this->config['scope']) && strpos($this->scope, 'manage_pages') === false ))
			throw new Exception("User status requires manage_page permission!");
		try {
			$pages = $this->api->api("/me/accounts", 'get');
		} catch (Exception $e) {
			throw new Exception("Cannot retrieve user pages! {$this->providerId} returned an error: {$e->getMessage()}", 0, $e);
		}
		if (!isset($pages['data'])) {
			return array();
		}
		if (!$writableonly) {
			return $pages['data'];
		}
		$wrpages = array();
		foreach ($pages['data'] as $p) {
			if (isset($p['perms']) && in_array('CREATE_CONTENT', $p['perms'])) {
				$wrpages[] = $p;
			}
		}
		return $wrpages;
	}
	/**
	 * load the user latest activity
	 *    - timeline : all the stream
	 *    - me       : the user activity only
	 * {@inheritdoc}
	 */
	function getUserActivity($stream) {
		try {
			if ($stream == "me") {
				$response = $this->api->api('/me/feed');
			} else {
				$response = $this->api->api('/me/home');
			}
		} catch (Exception $e) {
			throw new Exception("User activity stream request failed! {$this->providerId} returned an error: {$e->getMessage()}", 0, $e);
		}
		if (!$response || !count($response['data'])) {
			return array();
		}
		$activities = array();
		foreach ($response['data'] as $item) {
			if ($stream == "me" && $item["from"]["id"] != $this->api->getUser()) {
				continue;
			}
			$ua = new Hybrid_User_Activity();
			$ua->id = (array_key_exists("id", $item)) ? $item["id"] : "";
			$ua->date = (array_key_exists("created_time", $item)) ? strtotime($item["created_time"]) : "";
			if ($item["type"] == "video") {
				$ua->text = (array_key_exists("link", $item)) ? $item["link"] : "";
			}
			if ($item["type"] == "link") {
				$ua->text = (array_key_exists("link", $item)) ? $item["link"] : "";
			}
			if (empty($ua->text) && isset($item["story"])) {
				$ua->text = (array_key_exists("link", $item)) ? $item["link"] : "";
			}
			if (empty($ua->text) && isset($item["message"])) {
				$ua->text = (array_key_exists("message", $item)) ? $item["message"] : "";
			}
			if (!empty($ua->text)) {
				$ua->user->identifier = (array_key_exists("id", $item["from"])) ? $item["from"]["id"] : "";
				$ua->user->displayName = (array_key_exists("name", $item["from"])) ? $item["from"]["name"] : "";
				$ua->user->profileURL = "https://www.facebook.com/profile.php?id=" . $ua->user->identifier;
				$ua->user->photoURL = "https://graph.facebook.com/" . $ua->user->identifier . "/picture?type=square";
				$activities[] = $ua;
			}
		}
		return $activities;
	}
}