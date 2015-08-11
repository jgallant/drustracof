<?php

// I'm going to store the access token for the user in a session variable.
session_start();

// Here is some super secret stuff. This should be in an environmental config for sure.
$client_id = '7622';
$client_secret = 'e664b5ec214a9e114629df8d28402c9fd93e2393';
$client_access_token = '3d87915a4afa73762a65b9f26df7d290fd139deb';

// If we don't have a code or a token we'll start again.
// In a real app, we'd be setting a cookie and also logging some DB stuff.
if (empty($_GET['code']) && empty($_SESSION['access_token'])) {
  echo "<a href='https://www.strava.com/oauth/authorize?client_id=7622&response_type=code&redirect_uri=http://jgallant.ca/stracof'>Login with Strava</a>";
  die;
}

// Exchange the code for a delicious access token
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'https://www.strava.com/oauth/token');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
  'client_id' => 'value1',
  'client_secret' => '',
  'code' => $_GET['code']
)));