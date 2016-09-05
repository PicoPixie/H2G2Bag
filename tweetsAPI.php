<?php

/* 

tweetsAPI.php

RESTful API interface 

Input:
	$_POST['format'] = [ json ]
	$_POST['method'] = [ getTweets ]
	$_POST['max'] = [ Int ]
*/
// --- Initialise variables and functions

function deliver_response($format, $api_response) {

	//define HTTP responses
	$http_response_code = array(
		200 => 'OK',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		403 => 'Forbidden',
		404 => 'Not Found'
	);

	//set HTTP response
	header('HTTP/1.1 ' . $api_response['status'] . ' ' . $http_response_code[$api_response['status']]);

	//process JSON content type
	if(strcasecmp($format,'json')==0) {
		//set HTTP response content type
		header('Content-Type: application/json; charset=utf-8');
		//format data into JSON
		$json_response = json_encode($api_response);
		//deliver response
		echo $json_response;
	}
	//end script
	exit;
}

include "adodb5/adodb.inc.php";

//setup ADOdb connection
$driver = "mysqli";
$db = newAdoConnection($driver);
$db->connect("your_db_host","your_db_user","your_db_password","your_db_name");
$table = "your_db_table_name";

//define whether HTTPS required
$HTTPS_required = FALSE;

//define whether user auth required
$authentication_required = FALSE;

//define API response codes and related HTTP response
$api_response_code = array(
	0 => array('HTTP Response' => 400, 'Message' => 'Unknown Error'),
	1 => array('HTTP Response' => 200, 'Message' => 'Success'),
	2 => array('HTTP Response' => 403, 'Message' => 'HTTPS Required'),
	3 => array('HTTP Response' => 401, 'Message' => 'Authentication Required'),
	4 => array('HTTP Response' => 401, 'Message' => 'Authentication Failed'),
	5 => array('HTTP Response' => 404, 'Message' => 'Invalid Request'),
	6 => array('HTTP Response' => 400, 'Message' => 'Invalid Response Format'),
);

//set default HTTP response
$response['code'] = 0;
$response['status'] = 404;
$response['data'] = NULL;

// --- Authorization

//optionally require connections via HTTPS
if($HTTPS_required && $_SERVER['HTTPS'] != 'on') {
	$response['code'] = 2;
	$response['status'] = $api_response_code[$response['code']]['HTTP Response'];
	$response['data'] = $api_response_code[$response['code']]['Message'];
	//return response -- will exit script
	deliver_response($_POST['format'],$response);
}
//optionally require user auth -- naiive placeholder code
if($authentication_required) {
	if(empty($_POST['username']) || empty($_POST['password'])) {
		$response['code'] = 3;
		$response['status'] = $api_response_code[$response['code']]['HTTP Response'];
		$response['data'] = $api_response_code[$response['code']]['Message'];
		deliver_response($_POST['format'],$response);
	} elseif($_POST['username'] != 'FOO' && $_POST['password'] != 'BAR') {
		//return an error response if user fails auth
		$response['code'] = 4;
		$response['status'] = $api_response_code[$response['code']]['HTTP Response'];
		$response['data'] = $api_response_code[$response['code']]['Message'];
		deliver_response($_POST['format'],$response);
	}
}

// --- Process Request


if(strcasecmp($_GET['method'],'getTweets')==0) {
	//what Number did the client give us.?
	//retrieve from db, limit to next N tweets starting from Number+1
	//init call client sends max=0, client then needs to keep maximal db_id seen
	//and post this back in subsequent POSTs, db qry needs to select where db_id > max
	if(isset($_GET['max'])) {
		$max = $_GET['max'];
		//start to build DB query string
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
		$qry = "SELECT db_id, user_screen_name, tweet_text";
		$qry .= " FROM " . $table; 
		$qry .= " WHERE db_id>" . $max;
		//tweet_id_str == 'NULL' these are non-tweets on pipe (new follows etc.)
		//we weed these out before db insert, but incase any already IN
		$qry .= " AND tweet_id_str!='NULL'";
		//ensure we get them in neat order
		$qry .= " ORDER BY db_id ASC";
		//limit results returned 
		$qry .= " LIMIT 10";

		//try do the lookup
		if($recordSet = $db->getAll($qry)) {
			//crazy diego got DATA!!
			$response['code'] = 1; // success
			$response['status'] = $api_response_code[$response['code']]['HTTP Response'];
			//just throws the assoc array wholesale to json_encode fn.
			$response['data'] = $recordSet;
		} else { 
			//qry failed
			//overwrite defaults with something useful
			$response['data'] = "DB query failed.";
		}
	} else {
		//got no max id to perform sane lookup
		//overwrite defaults with something useful
		$response['data'] = "No Max ID supplied.";
	}
	
}

//inform client, so it can display, and poll again
// --- Deliver Response
deliver_response($_GET['format'],$response);
?>
