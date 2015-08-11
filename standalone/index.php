<!doctype html>

<html lang="en">
<head>
  <meta charset="utf-8">
  <title>StraCof | Riding for coffee points</title>
  <link rel="stylesheet" href="css/stracof.css">
</head>

<body>

<?php
require_once(dirname(__FILE__) . '/Config.php');
require_once(dirname(__FILE__) . '/StraCof.php');

// I'm going to store the access token for the user in a session variable.
session_start();

$stracof = new StraCof($client_id, $client_secret, $client_access_token);

// If we don't have a code or a token we'll start again.
// In a real app, we'd be setting a cookie and also logging some DB stuff.
if (empty($stracof->access_token)) {
  echo "<a href='https://www.strava.com/oauth/authorize?client_id=7622&response_type=code&redirect_uri=http://jgallant.ca/stracof'>Login with Strava</a>";
  die;
}

echo "<h2>Hello, {$stracof->get_user_info()->firstname}!</h2>";

$activities = $stracof->get_user_activities();

if (empty($activities)) {
  echo "You don't have any activities! Go ride a bike and come back.";
  die;
}

$activity_options = "<option value='' selected disabled>Select an activity</option>" . PHP_EOL;

foreach ($activities as $activity) {
  $activity_options .= "<option value='{$activity->id}'>{$activity->name}</option>" . PHP_EOL;
}

echo "<select id='activities'>$activity_options</select>";
?>

<script src="js/stracof.js"></script>
</body>
</html>
