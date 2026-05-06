<?php
require 'config_api.php';

$lat = -15.8761; 
$lon = -48.0446; 
$raio = 5000; 
$categoria = 'catering.restaurant';

$url = "https://api.geoapify.com/v2/places?categories={$categoria}&filter=circle:{$lon},{$lat},{$raio}&limit=5&apiKey=" . GEOAPIFY_KEY;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

header('Content-Type: application/json');
echo $response;
?>