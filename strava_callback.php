<?php
session_start();
include '../db.php';  

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'athlete') {
    header("Location: ../login.php");
    exit();
}

$client_id = "152151";
$client_secret = "e1b5ec6169f8f1daf595fe6f4b0a07155ce52a2a";

if (!isset($_GET['code'])) {
    die("Authorization failed.");
}

$code = $_GET['code'];

$url = "https://www.strava.com/oauth/token";
$data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'code' => $code,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$response_data = json_decode($response, true);

if (!isset($response_data['access_token'])) {
    die("Failed to get access token.");
}

$_SESSION['strava_access_token'] = $response_data['access_token'];
$_SESSION['strava_refresh_token'] = $response_data['refresh_token'];
$_SESSION['strava_expires_at'] = $response_data['expires_at'];

header("Location: connect_strava.php");
exit();
?>