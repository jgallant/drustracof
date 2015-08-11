<?php

class StraCof {
  CONST SESSION_ACCESS_TOKEN = 'stracof_access_token';
  CONST SESSION_ATHLETE = 'stracof_athlete';

  public $access_token;
  public $client_access_token;

  public function __construct($client_id, $client_secret, $client_access_token) {
    $this->client_access_token = $client_access_token;

    if (!empty($_SESSION[self::SESSION_ACCESS_TOKEN])) {
      $this->access_token = $_SESSION[self::SESSION_ACCESS_TOKEN];
    }
    else if (!empty($_GET['code'])) {
      $this->get_access_token($client_id, $client_secret, $client_access_token);
    }
  }

  function get_user_info() {
    return $_SESSION[self::SESSION_ATHLETE];
  }

  function get_user_activities() {
    return $this->strava_get('https://www.strava.com/api/v3/athlete/activities');
  }

  function get_activity($id) {
    return $this->strava_get('https://www.strava.com/api/v3/activities/' . $id);
  }

  function get_access_token($client_id, $client_secret, $client_access_token) {
    $response = $this->strava_post('https://www.strava.com/oauth/token', array(
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

    $this->access_token = $response->access_token;

    // It'd be best to not need to load these things again.
    $_SESSION[self::SESSION_ACCESS_TOKEN] = $response->access_token;
    $_SESSION[self::SESSION_ATHLETE] = $response->athlete;
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

  private function strava_get($url, $parameters = array()) {
    $ch = curl_init();

    // We need to send the access_token to make authenticated calls.
    $auth_header = "Authorization: Bearer {$this->access_token}";
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $auth_header));
    curl_setopt($ch, CURLOPT_URL, $url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $server_output = curl_exec($ch);

    curl_close($ch);

    // If Strava doesn't send us some json that would be bad. Check that in the future.
    return json_decode($server_output);
  }
}