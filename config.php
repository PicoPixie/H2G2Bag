<?php
include "oauth.php";

/* 
config.php 

Contains all parameters for Twitter user stream:
API key/secret, app token/secret pairs, 
endpoint for Twitter user stream, port, OAuth signature method and 
OAuth version to use.

Here is where we state the keywords to track in the stream.

Calls oauth.php/getAuthorization() to fill out the OAuth header
Builds HTTP POST request to track keywords in stream.

Maintains logfile.

To test backoff strategy - use invalid auth creds, examine log
*/

//params for Twitter service
$userStreamScheme = "tls://";
$postStreamScheme = "https://";
$userStreamHost = "userstream.twitter.com";
$userStreamPath = "/1.1/user.json";
$userStreamURL = $userStreamScheme . $userStreamHost;
$postStreamURL = $postStreamScheme . $userStreamHost . $userStreamPath;
$host = $postStreamScheme . $userStreamHost;
$port = 443;
$sigMethod = "HMAC-SHA1";
$oauthV = "1.0";

//consumer (API) Key
$APIKey = "Insert_Unique_Twitter_Consumer_Key";
$APISecret = "Insert_Unique_Twitter_Consumer_Secret";

//make API requests on own account's behalf
$accessToken = "Insert_Unique_Twitter_App_Access_Token";
$tokenSecret = "Insert_Unique_Twitter_App_Access_Token_Secret";


//HTTP request headers
//do NOT send Connection: close header
//we request compressed stream
$userAgent = "H2G2Bag/0.1";

//limit stream to Tweets containing..
$params = 'with=followings';

$authCreds = getAuthorization($APIKey,$APISecret,$accessToken,$tokenSecret,$postStreamURL,$sigMethod,$oauthV,$params);

//build the request
$requestStr = "POST " . $userStreamPath . " HTTP/1.1\r\n";
$requestStr .= "Host: " . $userStreamHost . "\r\n";
$requestStr .= 'Authorization: ' . $authCreds . "\r\n";
$requestStr .= "Content-Length: " . strlen($params) . "\r\n";
$requestStr .= "Content-Type: application/x-www-form-urlencoded\r\n";
#$requestStr .= "Accept-Encoding: deflate, gzip\r\n";
$requestStr .= "Accept: */*\r\n";
$requestStr .= 'User-Agent: ' . $userAgent . "\r\n";
$requestStr .= "\r\n";
$requestStr .= $params ; //. "\r\n";

//maintain a log
$logfile = fopen("twitterLog.txt", "a");

?>
