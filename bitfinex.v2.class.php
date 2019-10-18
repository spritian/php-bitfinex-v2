<?php 
// This is still a WIP - Not fully functional - YET. Only has the functions I needed to quickly display my wallet data, currency pairs, and open orders using Bitfinex's v2 API.

class Bitfinex {
	const API_TIMEOUT=60;
	const API_RETRIES=3; //not implemented yet, will use a counter in output function
	const API_URL="https://api.bitfinex.com/v2";
	const DISPLAY_ERRORS=false; //not implemented yet
	
	private $api_key="";
	private $api_secret="";

	public function __construct($api_key, $api_secret) {
		$this->api_key=$api_key;
		$this->api_secret=$api_secret;
	}
	
	/* Core api request functions */ 
	
	/* Build BFX Headers for v2 API */
	private function headers($path)
	{
		$nonce		=(string) number_format(round(microtime(true) * 100000), 0, ".", "");
		$body		="{}";
		$signature	="/api/v2".$path["request"].$nonce.$body;
		$h			=hash_hmac("sha384", utf8_encode($signature), utf8_encode($this->api_secret));

		return array(
			"content-type: application/json",
			"content-length: ".strlen($body),
			"bfx-apikey: ".$this->api_key,
			"bfx-signature: ".$h,
			"bfx-nonce: ".$nonce
		);
	}

	/* Authenticated Endpoints Request */
	private function send_auth_endpoint_request($data) {
		$ch=curl_init();
		$url=self::API_URL.$data["request"];
		$headers=$this->headers($data);

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);                                                                     
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                   
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);                                                                      
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);                                                                      
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::API_TIMEOUT);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "{}");

		if(!$result=curl_exec($ch)) {
			return $this->curl_error($ch);
		} else {    	
			return $this->output($result, $this->is_bitfinex_error($ch), $data);
		}
	}

	/* Public Endpoints Request */
	private function send_public_endpoint_request($request, $params=NULL) {
		$ch=curl_init();
		$query="";

		if (count($params)) {
			$query="?".http_build_query($params);
		}

		$url=self::API_URL . $request . $query;

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);  
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::API_TIMEOUT);

		if( !$result=curl_exec($ch) ) {
			return $this->curl_error($ch);
		} else {			
			return $this->output($result, $this->is_bitfinex_error($ch), $request);
		}
	}

	/* Handle CURL errors */
	private function curl_error($ch) {
		if ($errno=curl_errno($ch)) {
			$error_message=curl_strerror($errno);
			echo "CURL err ({$errno}): {$error_message}";
			return false;
		}

		return true;
	}

	/* Handle Bitfinex errors - but may move this to output...*/
	private function is_bitfinex_error($ch) {
		$http_code=(int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($http_code !== 200) {
			return true;
		}
	
		return false;
	}

	/* Retrieve CURL response, if API err or RATE LIMIT hit, recall routine. Need to implement max retries. */
	private function output($result, $is_error=false, $command) {
		$response=json_decode($result, true);

		if ($response[0]=="error" or $response["error"]=="ERR_RATE_LIMIT") {
			if (!is_array($command)) {
				//echo "Retrying... '".$command."' in 10 seconds.\n";
				sleep(10);
				return $this->send_public_endpoint_request($command);
			} else {
				//echo "Retrying... '".$command["request"]."' in 10 seconds.\n";
				sleep(10);
				return $this->send_auth_endpoint_request($command);
			}
		} else {
			if ($is_error) {
				$response["error"]=true;
			}

			return $response;
		}
	}

	/* Build URL path from functions */
	private function build_url_path($method, $params=NULL) {
		$parameters="";

		if ($params !== NULL) {
			$parameters="/";

			if (is_array($params)) {
				$parameters .= implode("/", $params);
			} else {
				$parameters .= $params;
			}
		}

		return "/$method$parameters";
	}  
	
	/* End core api request functions */
	
	/* v2 api requests */ 
	
	/* API: Get Tickers - Ugly, need to fix this.*/
	public function get_tickers($symbols) {
		$request=$this->build_url_path("tickers", "?symbols=".implode(",", $symbols));

		$tickers=$this->send_public_endpoint_request($request);
		$t=array();
		for ($z=0; $z<count($tickers); $z++) {
			if (substr($tickers[$z][0], 0, 1)=="t") {
				$t[substr($tickers[$z][0], 1, strlen($tickers[$z][0]))]["last_price"]=$tickers[$z][3];
				$t[substr($tickers[$z][0], 1, strlen($tickers[$z][0]))]["ask"]=$tickers[$z][7];
			} elseif (substr($tickers[$z][0], 0, 1)=="f") {
				$t[substr($tickers[$z][0], 1, strlen($tickers[$z][0]))]["last_price"]=$tickers[$z][5];
				$t[substr($tickers[$z][0], 1, strlen($tickers[$z][0]))]["ask"]=$tickers[$z][10];
			}
		}

		return $t;
	}

	/* API: Get Orders */
	public function get_orders() {
		$request=$this->build_url_path("auth/r/orders");
		$data=array("request" => $request);

		$orders=$this->send_auth_endpoint_request($data);
		$o=array();
		for ($z=0; $z<count($orders); $z++) {
			if (substr($orders[$z][3], 0, 1)=="t") {
				$sym_fix=substr($orders[$z][3], 1, strlen($orders[$z][3]));
					$o[$orders[$z][0]]["symbol"]=$sym_fix;
					$o[$orders[$z][0]]["type"]=$orders[$z][8];
					$o[$orders[$z][0]]["amount"]=$orders[$z][6];
					$o[$orders[$z][0]]["amount_orig"]=$orders[$z][7];
					$o[$orders[$z][0]]["price"]="$".$orders[$z][16];
					$o[$orders[$z][0]]["order_status"]=$orders[$z][13];
			}
		}

		return $o;
	}

	/* API: Get Orders - only handling exchange balances right now */
	public function get_balances() {
		$request=$this->build_url_path("auth/r/wallets");
		$data=array("request" => $request);

		$balances=$this->send_auth_endpoint_request($data);
		$b=array();
		$count=0;
		for ($z=0; $z<count($balances); $z++) {
			if ($balances[$z][0]=="exchange") {
				if ($balances[$z][2]!="0") {
					$b[$count]["currency"]=$balances[$z][1];
					$b[$count]["amount"]=$balances[$z][2];
					$count++;
				}
			}
		}

		return $b;
	}
	
	/* API: Get orders history - by symbol */
        public function get_orderhist($symbol){
                $request=$this->build_url_path("auth/r/orders/".$symbol."/hist");
                $data=array("request" => $request);
		$balances=$this->send_auth_endpoint_request($data);
                //format data
                $b=array();
		$count=0;
		for ($z=0; $z<count($balances); $z++) {
			$b[$count]["id"]=$balances[$z][0];
			$b[$count]["gid"]=$balances[$z][1];
                        $b[$count]["cid"]=$balances[$z][2];
                        $b[$count]["symbol"]=$balances[$z][3];
                        $b[$count]["created"]=$balances[$z][4];
                        $b[$count]["updated"]=$balances[$z][5];
                        $b[$count]["amount"]=$balances[$z][6];
                        $b[$count]["amount_orig"]=$balances[$z][7];
                        $b[$count]["type"]=$balances[$z][8];
                        $b[$count]["type_prev"]=$balances[$z][9];
                        $b[$count]["flags"]=$balances[$z][12];
                        $b[$count]["order_status"]=$balances[$z][13];
                        $b[$count]["price"]=number_format($balances[$z][16],10);
                        $b[$count]["price_avg"]=number_format($balances[$z][17],10);
                        $b[$count]["price_trailing"]=$balances[$z][18];
                        $b[$count]["price_aux_limit"]=$balances[$z][19];
                        $b[$count]["hidden"]=$balances[$z][24];
                        $b[$count]["placed_id"]=$balances[$z][25];
                        $b[$count]["routing"]=$balances[$z][28];
                        $b[$count]["meta"]=$balances[$z][31];
                        $count++;
		}
		return $b;
        }
}
?>
