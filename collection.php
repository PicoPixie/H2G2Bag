<?php
include "config.php";
include "process.php";

/* collection.php */
//set_time_limit(0);
//all in seconds
//time elapsed since last stream activity [Tweet or \r\n keepalive]
$idleTime = 0;
//90s, should have rx'd 3 x "\r\n" keepalives by now
$idleTimeout = 90; 
//keep count of connect attempt, if Twitter not having it, stop harrassing her
$connectAttempt = 0; 
$maxConnectAttempt = 8;

//init backoff rate for TCP errors
$tcpBackOff = 1;
$tcpBackOffMax = 16;
//init backoff rates for HTTP response codes other than 200:OK || 420:Rate Limited
$httpBackOff = 5;
$httpBackOffMax = 320;
//init backoff rate for HTTP response code 420
$_420BackOff = 60;
$_420BackOffMax = 480;
$conn = 0;
//while no established connection to Twitter Streaming API
//or non-200 OK response
//keep trying until connected or max-connect-failures reached
//if we've not reached max attempts yet, ask her out again!
if($connectAttempt <= $maxConnectAttempt) {
	//close socket here?
	if(is_resource($conn)) fclose($conn);
	do {
		$connectAttempt += 1;
		//open TCP socket to make HTTP POST request
		$conn = fsockopen($userStreamURL,$port,$errno,$errstr);
		if($conn) {
			//we have TCP socket connection, we can POST HTTP request to it 
			//log it
			fwrite($logfile,date('r') . ' : ' . $requestStr);
			//POST it
			//write request for matching Tweets down fp
			fwrite($conn,$requestStr);
			//first line is response
			$firstline = fgets($conn,1024);
			fwrite($logfile, "First: $firstline\n");
			list($httpVer,$httpCode,$httpMessage) = preg_split('/\s+/',trim($firstline),3);
			//response buffers
			$respHeaders = $respBody = "";
			$isChunking = FALSE;
			//consume headers until we get to body
			while($headLine = trim(fgets($conn,4096))) {
				fwrite($logfile, "Consume: $headLine\n");
				$respHeaders .= $headLine."\n";
				if(strtolower($headLine) == "transfer-encoding: chunked")
					$isChunking = TRUE;
			}

			//check for non-200 responses
			if($httpCode != 200) {
				while($bodyLine = trim(fgets($conn,4096)))
					$respBody .= $bodyLine;
				//consume error and log it
				$errorStr = 'HTTP Error ' . $httpCode . ': ' . $httpMessage . ' (' . $respBody . ")\r\n";
				fwrite($logfile,date('r') . ' : ' . $errorStr);			
				//was it the special 420 -- backoff hard
				if($httpCode == 420) {
					$_420BackOff = ($_420BackOff < $_420BackOffMax) ? $_420BackOff * 2 : $_420BackOffMax;
					//rate limiting error - log it
					fwrite($logfile,date('r') . ' : HTTP Rate Limited Failure. Sleeping for ' . $_420BackOff . " seconds.\r\n");
					sleep($_420BackOff);
					continue;
				} else {
					//else not rate limited, but not 200:OK either
					$httpBackOff = ($httpBackOff < $httpBackOffMax) ? $httpBackOff * 2 : $httpBackOffMax;
					//http error - log it
					fwrite($logfile,date('r') . ' : HTTP Failure. Sleeping for ' . $httpBackOff . " seconds.\r\n");
					sleep($httpBackOff);
					continue;
				}
			} else { //end of non-200:OK response handlers
	//$tweetlog = fopen("tweetLog.txt", "a");
				//so we're good if we got here, got socket, got 200OK response
				$errorStr = 'HTTP Response ' . $httpCode . ': ' . $httpMessage . ' (' . $respBody . ")\r\n";
				fwrite($logfile,date('r') . ' : ' . $errorStr);			
				//switch to non-blocking to consume the stream
				//stream_set_blocking($conn,FALSE);
				stream_set_timeout($conn, 30); // blocking read but with 30s timeout
				//consume the stream

				$whole_json = "";
				while(!feof($conn)) {
					//have we timed out?
					if($idleTime < $idleTimeout) {
						fwrite($logfile,date('r') . ' : Idle for ' . $idleTime . " seconds.\r\n");
						//try and read a line from the socket
						if($json = fread($conn, 8192)) {
							//is this the hex of chunk size to follow.?
							if(strlen($json) < 7) {
								//the hex of tweet length
								//fwrite($tweetlog, "Chunk size? $json");
								if(($chunk = hexdec($json)) > 0) {
									//fwrite($tweetlog, "chunk read $chunk bytes.\n");
									$json = fread($conn,$chunk);
								} else
									continue;
							}

							$whole_json .= $json;
							//fwrite($tweetlog, "Chunk Ends:".bin2hex(substr($json, -4))." ");
							if (($off=strpos($json, "\r\n"))===FALSE) {
								//fwrite($tweetlog, "Append ".strlen($json)." bytes to whole_json.\n");
								continue;
							} else {
								//fwrite($tweetlog, "Processing...\n");
								$off -= strlen($json) - 2;
								if ($off > 0) {
									//fwrite($tweetlog, "WARNING: Extra $off chars after end of tweet\n");
								}

							}
							fwrite($logfile,date('r') . " : Got a line to process.\r\n");
							//fwrite($logfile,date('r') . " : ".var_dump(json_decode($json))."\r\n");
							//reset timer on receipt of new data
							$idleTime = 0;
							fwrite($logfile,date('r') . " : Reset IdleTime.\r\n");
							//now process what we got
							fwrite($logfile,date('r') . " : Processing Line.\r\n");
							consume($whole_json);
							$whole_json = "";
						} else {
							//nothing not even newline?
							fwrite($logfile,date('r') . " : Failed to get a Line.\r\n");
							$idleTime += 30;
							fwrite($logfile,date('r') . " : Sleeping for " . $idleTime . "seconds.\r\n");
							sleep($idleTime);
							continue;
						}
					} else { //timed out, dead stream
						fwrite($logfile,date('r') . " : Stream Timed Out\r\n");
					}
				} //shouldn't get an EOF on a stream
				//drop connection, something wrong.
				//fclose($conn);
				fwrite($logfile,date('r') . " : EOF on Stream.\r\n");
			}
		} else { 
			//TCP connection error
			$tcpBackOff = ($tcpBackOff < $tcpBackOffMax) ? $tcpBackOff * 2 : $tcpBackOffMax;
			//log TCP errno and errstr
			fwrite($logfile,date('r') . ' : Error No: ' . $errno . ' ' . $errstr . "\r\n");
			//log it
			fwrite($logfile,date('r') . ' : TCP Failure. Failed to connect to Host: ' . $userStreamURL . ' Port: ' . $port . ' Sleeping for ' . $tcpBackOff . " seconds.\r\n");
			sleep($tcpBackOff);
			continue;
		}
	} while (!is_resource($conn) || $httpCode != 200);
} //maxConnectAttempts reached GIVE UP!
?>
