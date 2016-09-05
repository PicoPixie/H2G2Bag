<?php

/* 
oauth.php 

getAuthorization() 
	returns the OAuth 'Authorization:' header string used in HTTP 
	requests to the Twitter user stream.
	Performs all the encoding and hashing required given
	API key/secret pair, app token/secret pair, and some
	OAuth stuffz [Signature Method, Version]
	The URL and track terms [$data] passed in are used 
	for hashing mathemagics.
	The actual HTTP POSTing happens in collection.php
*/

function getAuthorization($APIKey,$APISecret,$accessToken,$tokenSecret,$url,$sig,$version,$data) {

	//generate a nonce
	$nonce = md5(mt_rand());
	//get the current timestamp
	$timestamp = time();
	//generate base string
	$baseStr = 'POST&' . 
		rawurlencode($url) . '&' .
		rawurlencode('oauth_consumer_key=' . $APIKey . '&' .
			'oauth_nonce=' . $nonce . '&' .
			'oauth_signature_method=' . $sig . '&' .
			'oauth_timestamp=' . $timestamp . '&' .
			'oauth_token=' . $accessToken . '&' .
			'oauth_version=' . $version . '&' .
			$data);
	//generate secret key to use to hash
	$secret = rawurlencode($APISecret) . '&' .
			rawurlencode($tokenSecret);

	//generate sig using HMAC-SHA1
	$rawHash = hash_hmac('sha1',$baseStr,$secret,TRUE); 	
	//base64 then urlencode the raw hash
	$oauthSig = rawurlencode(base64_encode($rawHash));
	//build the OAuth Authorization header
	$OAuth = 'OAuth oauth_consumer_key="' . $APIKey . '", ' .
			'oauth_nonce="' . $nonce . '", ' .
			'oauth_signature="' . $oauthSig . '", ' .
			'oauth_signature_method="' . $sig . '", ' .
			'oauth_timestamp="' . $timestamp . '", ' .
			'oauth_token="' . $accessToken . '", ' .
			'oauth_version="' . $version . '"';
	return $OAuth;
}
?>
