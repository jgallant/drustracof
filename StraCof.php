<?php
// One time I wrote an OAuth 1.0a token exchange in C# and I'm not in a huge
// hurry to do it again.
require_once(dirname(__FILE__) . '/lib/OAuth.php');
require_once(dirname(__FILE__) . '/lib/polyline.php');

class StraCof {
  // Session keys
  CONST SESSION_STRAVA_ACCESS_TOKEN = 'stracof_access_token';
  CONST SESSION_STRAVA_ATHLETE = 'stracof_athlete';

  // Strava
  CONST STRAVA_AUTH_URL = 'https://www.strava.com/oauth/token';
  CONST STRAVA_API_URL = 'https://www.strava.com/api/v3';

  // Yelp
  CONST YELP_API_URL = 'http://api.yelp.com/v2';
  CONST YELP_METRES_PER_CALL = 5000;
  CONST YELP_NEUTRAL_RATING = 2.8;
  CONST YELP_MAX_METRES_FOR_POINTS = 600;

  // Strava access token
  private $strava_access_token;

  // Yelp OAuth parameters
  private $yelp_token;
  private $yelp_token_secret;
  private $yelp_consumer_key;
  private $yelp_consumer_secret;

  /**
   * @param $config
   */
  public function __construct($config) {
    if (!empty($_SESSION[self::SESSION_STRAVA_ACCESS_TOKEN])) {
      $this->strava_access_token = $_SESSION[self::SESSION_STRAVA_ACCESS_TOKEN];
    }
    else {
      if (!empty($_GET['code'])) {
        $this->get_strava_access_token($config['strava_client_id'], $config['strava_client_secret']);
      }
    }

    $this->yelp_token = $config['yelp_token'];
    $this->yelp_token_secret = $config['yelp_token_secret'];
    $this->yelp_consumer_key = $config['yelp_consumer_key'];
    $this->yelp_consumer_secret = $config['yelp_consumer_secret'];
  }

  /**
   * @return bool
   */
  public function is_loaded() {
    return empty(!$this->strava_access_token);
  }

  /**
   * @return mixed
   */
  function get_user_info() {
    return $_SESSION[self::SESSION_STRAVA_ATHLETE];
  }

  /**
   * @return mixed
   */
  function get_user_activities() {
    return $this->strava_get('/athlete/activities');
  }

  /**
   * @param $id
   * @return bool|\stdClass
   */
  function get_activity($id) {
    $map_return = new stdClass();

    $activity = $this->strava_get('/activities/' . $id);

    if (empty($activity->map->polyline)) {
      return FALSE;
    }
    else {
      $points = decodePolylineToArray($activity->map->polyline);

      if (empty($points)) {
        return FALSE;
      }

      $businesses = array();
      $last_api_point = null;

      // Collect businesses along the route every YELP_METRES_PER_CALL metres.
      foreach ($points as $point) {
        if (empty($last_api_point) || $this->haversineGreatCircleDistance($last_api_point, $point) > self::YELP_METRES_PER_CALL) {
          $last_api_point = $point;

          $locations = $this->get_locations($point);

          // Build an array of unique business locations.
          if (!empty($locations) && !empty($locations->businesses)) {
            foreach ($locations->businesses as $business) {
              if (empty($businesses[$business->id])) {
                $businesses[$business->id] = $business;
              }
            }
          }
        }
      }

      // For each point on the route, check the distance to each business and store
      // the lowest distance for each.
      foreach ($points as $point) {
        foreach ($businesses as &$business) {
          $distance = $this->haversineGreatCircleDistance(array($business->location->coordinate->latitude, $business->location->coordinate->longitude), $point);

          if (empty($business->stracof_distance) || $distance < $business->stracof_distance) {
            $business->stracof_distance = round($distance);
          }
        }
      }

      $total_points = 0;

      // Calculate distance under YELP_MAX_METRES_FOR_POINTS and multiply by points
      // value minus YELP_NEUTRAL_RATING to give a positive or negative score per business.
      foreach ($businesses as &$business) {
        $distance = self::YELP_MAX_METRES_FOR_POINTS - $business->stracof_distance;

        if ($distance > 0 && $business->rating > 0) {
          $points = $business->rating - self::YELP_NEUTRAL_RATING;

          $business->stracof_points = round($points * $distance);

          $total_points += $business->stracof_points;
        }
      }

      // Return the polyline, total and array values for businesses, as it won't
      // be an array in JS if we leave the array keys in place.
      $map_return->polyline = $activity->map->polyline;
      $map_return->businesses = array_values($businesses);
      $map_return->total_points = $total_points;
    }

    return $map_return;
  }

  /**
   * @param $point
   * @return mixed
   *
   * We're only searching for 'coffee' right now, which doesn't guarantee a coffee shop result.
   */
  function get_locations($point) {
    return $this->yelp_get('/search', array(
      'term' => 'coffee',
      'll' => "{$point[0]},{$point[1]}"
    ));
  }

  /**
   * @param $client_id
   * @param $client_secret
   */
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

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    $server_output = curl_exec($ch);

    curl_close($ch);

    // If Strava doesn't send us some json that would be bad. Check that in the future.
    return json_decode($server_output);
  }

  /**
   * @param $path
   * @param array $parameters
   * @return mixed
   */
  private function strava_get($path, $parameters = array()) {
    $ch = curl_init();

    // We need to send the access_token to make authenticated calls.
    $auth_header = "Authorization: Bearer {$this->strava_access_token}";
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      $auth_header
    ));
    curl_setopt($ch, CURLOPT_URL, self::STRAVA_API_URL . $path);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

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
   * https://github.com/Yelp/yelp-api/blob/master/v2/php/sample.php
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

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    $server_output = curl_exec($ch);

    curl_close($ch);

    return json_decode($server_output);
  }

  /**
   * @param $point_from
   * @param $point_to
   * @param int $earthRadius
   * @return int
   *
   * http://stackoverflow.com/questions/14750275/haversine-formula-with-php
   */
  private function haversineGreatCircleDistance($point_from, $point_to, $earthRadius = 6371000) {
    // convert from degrees to radians
    $latFrom = deg2rad($point_from[0]);
    $lonFrom = deg2rad($point_from[1]);
    $latTo = deg2rad($point_to[0]);
    $lonTo = deg2rad($point_to[1]);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    return $angle * $earthRadius;
  }
}