@H2G2Bag : Twitter display using ESP8266-01
============================================

A promotional piece useful for "audience participation", uses an ESP8266-01 microcontroller and Adafruit Neomatrix to collect and display Tweets sent to Twitter user @H2G2Bag.

"Collection" server code connects to Twitter Streaming API using tokens (OAuth Lite.?)

"Processing" server code consumes JSON response object, inserts (partial) Tweet payload into MySQL database, uses ADOdb.

Wi-Fi connected client (ESP) polls webserver (60s freq.) for fresh batch (10 count) of Tweets. API performs lookup and hands JSON response back to client. Client uses ArduinoJson library to parse and displays:

	<twitter_user_screen_name> <message they sent to us>

followed by a sentinel Babelfish animation.

Startup and Stalling Behaviours
-------------------------------
Upon boot and in cases of comm errors, stalls, empty/nothing to do situation etc. client displays default message inviting viewers to Tweet @H2G2Bag with their message for us, followed with a Babelfish animation.

Notes
-----

We don't perform any kind of censorship on user contributed Tweets.

H2G2 = HitchHiker's Guide to the Galaxy.
Douglas Adams tribute piece.
https://en.wikipedia.org/wiki/Douglas_Adams
