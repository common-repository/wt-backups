<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	die("Protected By WebTotem! Not plugin init");
}

/**
 * WebTotem API class.
 *
 * Mostly contains wrappers for API methods. Check and send methods.
 *
 * @version 1.0
 * @copyright (C) 2022 WebTotem team (http://wtotem.com)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 */
class WT_Backups_API extends WT_Backups_Helper {

  /**
   * Method for getting an auth token.
   *
   * @param string $api_key
   *   Application programming interface key.
   *
   * @return bool|string
   *   Returns auth status
   */
  public static function auth($api_key) {
    $domain = WT_BACKUPS_SITE_DOMAIN;

    if(substr($api_key, 1, 1) == "-"){
      $prefix = substr($api_key, 0, 1);
      if($api_url = self::getApiUrl($prefix)){
        WT_Backups_Option::setOptions(['api_url' => $api_url]);
      } else {
          WT_Backups_Option::setNotification('error', __('Invalid API key', 'wt-backups'));
        return FALSE;
      }
      $api_key = substr($api_key, 2);
    }

    if(empty($api_key)) { return FALSE; }
    $payload = '{"query":"mutation{ guest{ apiKeys{ auth(apiKey:\"' . $api_key . '\", source:\"' . $domain . '\"),{ token{ value, refreshToken, expiresIn } } } } }"}';
    $result = self::sendRequest($payload, FALSE, TRUE);

    if (isset($result['data']['guest']['apiKeys']['auth']['token']['value'])) {
      $auth_token = $result['data']['guest']['apiKeys']['auth']['token'];
        WT_Backups_Option::login(['token' => $auth_token, 'api_key' => $api_key]);
      return 'success';
    } elseif($result['errors'][0]['message'] == 'INVALID_API_KEY') {
        WT_Backups_Option::logout();
    }

    return FALSE;
  }

  /**
   * Method for getting API url.
   *
   * @param string $prefix
   *
   * @return string|bool
   *   API url
   */
  public static function getApiUrl($prefix){
    $urls = [
        'P' => '.wtotem.com',
        'C' => '.webtotem.kz',
    ];

    if(array_key_exists($prefix, $urls)){
      return 'https://api' . $urls[$prefix] . '/graphql';
    }
    return false;
  }

  /**
   * Function sends GraphQL request to API server.
   *
   * @param string $payload
   *   Payload to be sent to API server.
   * @param bool $token
   *   Whether a token is needed when sending a request.
   * @param bool $repeat
   *   Required to avoid recursion.
   *
   * @return array
   *   Returns response from WebTotem API.
   */
  protected static function sendRequest($payload, $token = FALSE, $repeat = FALSE) {

    $api_key = WT_Backups_Option::getOption('api_key');

    // Remote URL where the public WebTotem API service is running.
    $api_url = WT_Backups_Option::getOption('api_url');
    if(!$api_url){
      $api_url = self::getApiUrl('P');
        WT_Backups_Option::setOptions(['api_url' => $api_url]);
    }

    // Checking whether a token is needed.
    if ($token) {
      $auth_token = WT_Backups_Option::getOption('auth_token');
      $auth_token_expired = WT_Backups_Option::getOption('auth_token_expired');

      // Checking whether the token has expired.
      if ($auth_token_expired <= time() && !$repeat) {
        $result = self::auth($api_key);
        if ($result === 'success') {
          return self::sendRequest($payload, $token, TRUE);
        }
        else {
        	if(isset($result['errors'])){
		        $message = WT_Backups_Helper::messageForHuman($result['errors'][0]['message']);
                WT_Backups_Option::setNotification('error', $message);
	        }
        }
      }
    }

    if (function_exists('wp_remote_post')) {

	    $args = [
		    'body' => $payload,
		    'timeout' => '60',
		    'sslverify' => false,
		    'headers' => [
		    	'Content-Type:application/json',
			    'Content-Type' => 'application/json',
			    'Accept: application/json',
			    'source: WORDPRESS',
		    ],
	    ];

	    if (isset($auth_token)) {
		    $auth = "Bearer " . $auth_token;
		    $args['headers'] = array_merge($args['headers'], ["Authorization" => $auth]);
	    }

	    $response = wp_remote_post($api_url, $args);
	    $response = wp_remote_retrieve_body($response);
	    $response = json_decode($response, true);

    }
    else {
      $error = 'WP_REMOTE_POST_NOT_EXIST';
    }

    // Checking if there are errors in the response.
    if (isset($response['errors'][0]['message'])) {
      $message = WT_Backups_Helper::messageForHuman($response['errors'][0]['message']);
      if (stripos($response['errors'][0]['message'], "INVALID_TOKEN") !== FALSE && !$repeat) {
        $response = self::auth($api_key);
        if ($response === 'success') {
          return self::sendRequest($payload, $token, TRUE);
        }
      }
      else {
          WT_Backups_Option::setNotification('error', $message);
      }
    }

    if (!empty($error)) {
      WT_Backups_Option::setNotification('error', $error);
    }

    return  $response;
  }

}
