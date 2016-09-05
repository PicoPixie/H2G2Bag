<?php
/*
process.php

*/

include "adodb5/adodb.inc.php";

function consume($json) {

	$tweetlog = fopen("tweetLog.txt", "a");
	//$json is a complete Tweet
	//fwrite($tweetlog,date('r') . ' RAW: ' . $json . "\r\n");

	//pull data to assoc array
	if($tweet = json_decode($json,TRUE)) {
		//fwrite($tweetlog,date('r') . ' Tree: ' . print_r($tweet, TRUE) . "\r\n");
		//weed out the friends[] preamble and any heartbeats 
		//plus any other entities on pipe -- like a new "follow"
		//which we don't want to log in DB
		//then we build up useful bits to log
		if(isset($tweet["user"]["screen_name"])) {
			//we have a proper Tweet to log	
			$created_at = $tweet["created_at"];
			$id_str = $tweet["id_str"];
			$screen_name = $tweet["user"]["screen_name"];
			//pull off the leading @h2g2bag [@mention]
			//iff first 8 chars in text before first space
			//else @mention in middle of text str - leave it there
			//TODO: won't cope with "@not_us @h2g2bag <message>"
			//which should appear on our stream
			if(strpos($tweet["text"],"@") == 0)
				$mod_text = substr($tweet["text"], 9);
			else
				$mod_text = $tweet["text"];
			//build the log string
			$str = $created_at."::".$id_str."::@".$screen_name."::".$mod_text;
			//now make log entry
			fwrite($tweetlog,date("r") . "::" . $str . "\r\n");

			//setup ADOdb connection
			$driver = "mysqli";
			$db = newAdoConnection($driver);
			$db->connect("your_db_host","your_db_user","your_db_password","your_db_name");
			$table = "your_db_table_name";

			//build ADOdb insert
			$new_record = array();
			$new_record["log_at"] = date("r");
			$new_record["tweet_at"] = $created_at;
			$new_record["user_screen_name"] = $screen_name;
			$new_record["tweet_text"] = $mod_text;
			$new_record["tweet_id_str"] = $id_str;
			//insert tweet as new record
			$db->autoExecute($table,$new_record,"INSERT");
			//we don't close the db connection, ADOdb will clean up when collection script stops
			
		}
		/*else other entity on pipe like a "follow"
		 which doesn't have a username attached, so do nothing with it
		*/
	}

	fclose($tweetlog);
}

?>
