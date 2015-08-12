<?php

// One time I wrote an OAuth 1.0a token exchange in C# and I'm not in a huge
// hurry to do it again.
require_once(dirname(__FILE__) . '/lib/OAuth.php');

class StraCof {
  // Session keys
  CONST SESSION_STRAVA_ACCESS_TOKEN = 'stracof_access_token';
  CONST SESSION_STRAVA_ATHLETE = 'stracof_athlete';

  // Strava URLs
  CONST STRAVA_AUTH_URL = 'https://www.strava.com/oauth/token';
  CONST STRAVA_API_URL = 'https://www.strava.com/api/v3';

  // Yelp YRL
  CONST YELP_API_URL = 'http://api.yelp.com/v2';

  // Strava access token
  private $strava_access_token;

  // Yelp OAuth parameters
  private $yelp_token;
  private $yelp_token_secret;
  private $yelp_consumer_key;
  private $yelp_consumer_secret;

  public function __construct($config) {
    if (!empty($_SESSION[self::SESSION_STRAVA_ACCESS_TOKEN])) {
      $this->strava_access_token = $_SESSION[self::SESSION_STRAVA_ACCESS_TOKEN];
    }
    else if (!empty($_GET['code'])) {
      $this->get_strava_access_token($config['strava_client_id'], $config['strava_client_secret']);
    }

    $this->yelp_token = $config['yelp_token'];
    $this->yelp_token_secret = $config['yelp_token_secret'];
    $this->yelp_consumer_key = $config['yelp_consumer_key'];
    $this->yelp_consumer_secret = $config['yelp_consumer_secret'];
  }

  public function is_loaded() {
    return empty(!$this->strava_access_token);
  }

  function get_user_info() {
    return $_SESSION[self::SESSION_STRAVA_ATHLETE];
  }

  function get_user_activities() {
    return $this->strava_get('/athlete/activities');
  }

  function get_activity($id) {
    return $this->strava_get('/activities/' . $id);
  }

  function get_coffee_shops($latitude, $longitude) {
    return $this->yelp_get('/search', array(
      'term' => 'coffee',
      'll' => "{$latitude},{$longitude}"
    ));
  }

  function get_strava_access_token($client_id, $client_secret) {
    $response = $this->strava_post(self::STRAVA_AUTH_URL, array(
      'client_id' => $client_id,
      'client_secret' => $client_secret,
      'code' => $_GET['code']
    ));

    // Just going to var_dump errors for now. These should be handled.
    if (!empty($response->errors)) {
      foreach ($response->errors as $error) {
        var_dump($error);
      }

      die;
    }

    $this->strava_access_token = $response->access_token;

    // It'd be best to not need to load these things again.
    $_SESSION[self::SESSION_STRAVA_ACCESS_TOKEN] = $response->access_token;
    $_SESSION[self::SESSION_STRAVA_ATHLETE] = $response->athlete;
  }

  /***
   * @param $url
   * @param array $parameters An array of query parameter key value pairs
   * @return mixed
   */
  private function strava_post($url, $parameters) {
    // Exchange the code for a delicious access token.
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $server_output = curl_exec($ch);

    curl_close($ch);

    // If Strava doesn't send us some json that would be bad. Check that in the future.
    return json_decode($server_output);
  }

  private function strava_get($path, $parameters = array()) {
    $ch = curl_init();

    // We need to send the access_token to make authenticated calls.
    $auth_header = "Authorization: Bearer {$this->strava_access_token}";
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $auth_header));
    curl_setopt($ch, CURLOPT_URL, self::STRAVA_API_URL . $path);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $server_output = curl_exec($ch);

    curl_close($ch);

    // If Strava doesn't send us some json that would be bad. Check that in the future.
    return json_decode($server_output);
  }

  /**
   * @param $path
   * @param array $parameters
   * @return mixed
   *
   * The majority of this was adapted from: https://github.com/Yelp/yelp-api/blob/master/v2/php/sample.php
   */
  private function yelp_get($path, $parameters = array()) {
    // Build the URL
    $unsigned_url = self::YELP_API_URL . $path . '?' . http_build_query($parameters);

    // Token object built using the OAuth library
    $token = new OAuthToken($this->yelp_token, $this->yelp_token_secret);

    // Consumer object built using the OAuth library
    $consumer = new OAuthConsumer($this->yelp_consumer_key, $this->yelp_consumer_secret);

    // Yelp uses HMAC SHA1 encoding
    $signature_method = new OAuthSignatureMethod_HMAC_SHA1();
    $oauthrequest = OAuthRequest::from_consumer_and_token(
      $consumer,
      $token,
      'GET',
      $unsigned_url
    );

    // Sign the request
    $oauthrequest->sign_request($signature_method, $consumer, $token);

    // Get the signed URL
    $signed_url = $oauthrequest->to_url();

    // Send Yelp API Call
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $signed_url);
    curl_setopt($ch, CURLOPT_HEADER, 0);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $server_output = curl_exec($ch);

    curl_close($ch);

    return json_decode($server_output);
  }
}