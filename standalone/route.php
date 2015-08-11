<?php
$response = new stdClass();

$activity_id = $_GET['id'];

if (empty($activity_id)) {
  $response->error = true;
  $response->message = "No activity specified.";

  echo json_encode($response);
  die;
}
// I'm going to store the access token for the user in a session variable.
session_start();

require_once(dirname(__FILE__) . '/Config.php');
require_once(dirname(__FILE__) . '/StraCof.php');


$stracof = new StraCof($client_id, $client_secret, $client_access_token);

if (empty($stracof->access_token)) {
  $response->error = true;
  $response->message = "There was an error accessing Strava. Please login again.";

  echo json_encode($response);
  die;
}

echo json_encode($stracof->get_activity($activity_id));