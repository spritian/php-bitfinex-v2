<?php
require "bitfinex.v2.class.php";

$api_key        ="MY_BFX_KEY";
$api_secret     ="MY_BFX_SECRET";
$bfx            =new Bitfinex($api_key, $api_secret);

/* Fetch wallet balances */
$balances=$bfx->get_balances();
print_r($balances);

/* Sample for fetching multiple tickers, based on populated wallet values: */
$t_data=array();
for ($z=0; $z<count($balances); $z++) {
        if ($balances[$z]["currency"]=="USD") {
                array_push($t_data, "fUSD");
        } else {
                array_push($t_data, "t".$balances[$z]["currency"]."USD");
        }
}

$tickers=$bfx->get_tickers($t_data);
print_r($tickers);

/* Fetch open orders */
$orders=$bfx->get_orders();
print_r($orders);
?>
