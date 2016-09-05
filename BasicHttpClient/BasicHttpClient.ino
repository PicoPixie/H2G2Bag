#include <Adafruit_NeoPixel.h>

#include <pgmspace.h>
extern "C" {
#include "scroller.h"
}

#include "babelfish.h"
#include "basic.h"

#include <ArduinoJson.h>
#include <Arduino.h>

#include <ESP8266WiFi.h>
#include <ESP8266WiFiMulti.h>

#include <ESP8266HTTPClient.h>

//which digital pin on chip ctrls lights.?
#define PIN 0
//debugging -- useful
#define USE_SERIAL Serial
#define TEXT_COLOUR 0x00ff0000

//creds for the WiFi network we should try to connect to
#define NETWORK "Your_SSID"
#define PASSWD "Your_Password"

//where's the API endpoint at.?
#define HOST "http://your_server.your_domain"
#define USERPATH "/~your_username/H2G2Bag/"
#define ENDPOINT "tweetsAPI.php"
//send along w/GET request
#define PARAMS "?format=json&method=getTweets"

/*
* Globals
*/

ESP8266WiFiMulti WiFiMulti;

Adafruit_NeoPixel strip = Adafruit_NeoPixel(64, PIN, NEO_GRB + NEO_KHZ800);

int top_id; //the internal DB_ID How High We Been.?
unsigned long time_now;
unsigned long last_lookup = 0; //governor for HTTP GETs

/*
* Helper fns.
*/

//convert x,y into pixel number
int grid_xy(int x, int y) {

	return(y * 8) + x;
}

//split the 16-bit colour data to RGB values
void splitRGB(uint16_t c, uint8_t *r, uint8_t *g, uint8_t *b) {

	*r = (c & 0x001F) << 3; //5 MSBits
	*g = (c & 0x03E0) >> (5-3); //6 bits
	*b = (c & 0x7C00) >> (10-3); //5 LSBits
}


//plot one frame of anim. onto matrix
void plot_anim(Adafruit_NeoPixel &strip, const uint16_t * data) {
	
	int p;
	
	for(p=0; p<64; p++) {
		uint16_t c = pgm_read_word_near(data + p);
		uint8_t r, g, b;
		splitRGB(c, &r, &g, &b);
		strip.setPixelColor(p, r, g, b);
	}
}

//plot one frame of text onto matrix
void plot_mono(Adafruit_NeoPixel &strip, const unsigned char* data, uint32_t colour) {

	int r, x;
	
	//each row
	for(r=0; r<8; r++) {
		//reverse bit order for each pixel
		for(x=7; x>=0; x--) {
			//if pixel has data for it, colour it, else wipe it
			if( data[r] & (1<<x) )
				strip.setPixelColor( grid_xy(7-x, r), colour);
			else
				strip.setPixelColor( grid_xy(7-x, r), 0);
		}
	}
}

//input a value 0-255 to get a color value
uint32_t Wheel(byte WheelPos) {
	
	if(WheelPos < 85) {
		return strip.Color(WheelPos*3, 255-WheelPos*3, 0);
	} else if(WheelPos < 170) {
		WheelPos -= 85;
		return strip.Color(255-WheelPos*3, 0, WheelPos*3);
	} else {
		WheelPos -= 170;
		return strip.Color(0, WheelPos*3, 255-WheelPos*3);
	}
}

void plot(const char* message) {

	int i;
	int len = scroll_length(message);

	//scrolls the Tweet
	for(i=0; i<len; i++) {
		const unsigned char* data = scroll_display(message, basic, i);
		plot_mono(strip, data, Wheel(i));
		strip.show();
		//pause between frames
		delay(100);
	}
}

void babel() {
	
	int i;

	//scrolls the Babelfish
	for(i=0; i<animationFrames; i++) {
		plot_anim(strip, animation[i]);
		strip.show();
		//duration set in animation editor
		delay(animationDelays[i]);
	}
}

void setup() {

	strip.begin();
	strip.setBrightness(20);
	strip.show();

	USE_SERIAL.begin(115200);

	USE_SERIAL.println();
	USE_SERIAL.println();
	USE_SERIAL.println();

	for(uint8_t t = 4; t > 0; t--) {
		USE_SERIAL.printf("[SETUP] WAIT %d...\n", t);
		USE_SERIAL.flush();
		delay(1000);
	}

	WiFiMulti.addAP(NETWORK, PASSWD);
}

void loop() {

	//wait for WiFi connection
	if((WiFiMulti.run() == WL_CONNECTED)) {
		//time for a fresh lookup.?
		time_now = millis();

		if((last_lookup == 0) || (time_now - last_lookup > 60000)) {  
			//if never done lookup, or last lookup over 60s ago -- get fresh

			HTTPClient http;

			USE_SERIAL.print("[HTTP] begin...\n");

			String Request = HOST;
			Request += USERPATH;
			Request += ENDPOINT;
			Request += PARAMS;
			Request += "&max=";
			Request += top_id;

			http.begin(Request);

			
			String Posted = "[HTTP] GET ";
			Posted += Request;
			Posted += "\r\n";

			USE_SERIAL.println(Posted);
			
//TODO: keep count of connect attempts, give up after say 5, and default display

			//start connection and send HTTP header
			int httpCode = http.GET();

			//httpCode will be negative on error
			if(httpCode > 0) {
				//HTTP header has been sent and Server response header has been handled
				USE_SERIAL.printf("[HTTP] GET... code: %d\n", httpCode);

				//json of Tweets found at server
				if(httpCode == 200) {
					//got a #WIN, timestamp that fact so we don't keep trying
					last_lookup = time_now;
		
					String response = http.getString();
					const char* payload;
				
					payload = response.c_str();
					//USE_SERIAL.printf("Payload: %s\n", payload);

					DynamicJsonBuffer jsonBuffer;
					DynamicJsonBuffer tweetsBuffer;
				
					JsonObject& jsonObject = jsonBuffer.parseObject(payload);
					USE_SERIAL.println(jsonObject.success()?"ParseSuccess":"ParseFailure");
				
					jsonObject.remove("code");
					jsonObject.remove("status");
					JsonArray& tweets = jsonObject.get<JsonArray&>("data");
				
					//jsonObject.prettyPrintTo(USE_SERIAL);
					//tweets.prettyPrintTo(USE_SERIAL);

					//how many Tweets did we get
					int len = tweets.size();
					//the highest internal ID seen, db query sorts asc for us
					int last = len-1;
					USE_SERIAL.printf("Tweet count: %d\n",len);
						
					//read db_id at the last array slot
					JsonObject& latestTweet = tweets.get<JsonObject&>(last);
					top_id = latestTweet.get("db_id");
					USE_SERIAL.printf("Highest ID Seen: %d\n",top_id);
					
					for(int i=0; i<len; i++) {
						JsonObject& nextTweet = tweets.get<JsonObject&>(i);
						//nextTweet.prettyPrintTo(USE_SERIAL);
						String Tweet = "@";
						Tweet += nextTweet.get<String>("user_screen_name");
						Tweet += " ";
						Tweet += nextTweet.get<String>("tweet_text");
						const char* msg = Tweet.c_str();
						plot(msg);
					}  

				} else { 
					//didnt get a 200 response -- scroll defaults
					USE_SERIAL.println("Non200 rx'd -- Query Failed");
					const char* msg = "Tweet @H2G2Bag with your message";
					babel();
					plot(msg);
				}
			    
			} else {
				//GET request failed -- scroll defaults
				USE_SERIAL.printf("[HTTP] GET... failed, error: %s\n", http.errorToString(httpCode).c_str());
				const char* msg = "Tweet @H2G2Bag with your message";
				babel();
				plot(msg);
			}

			http.end();

		} else {
			//else not time for a fresh GET request -- stall
			const char* msg = "Tweet @H2G2Bag with your message";
			babel();
			plot(msg);
		}
	} else {
		const char* msg = "No Wi-Fi.";
		babel();
		plot(msg);
		//wait before attempting network connection again
		delay(1000);
	}
}

