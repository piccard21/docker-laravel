<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use phpDocumentor\Reflection\Types\Integer;

class BinanceApiService {

    protected $url;
    protected $urlLive;

    private $key;
    private $secret;

    public function __construct() {

        $this->urlLive = rtrim(env("BINANCE_URL"), '/') . '/';

        if (env('APP_ENV') === 'live') {
            $this->url = $this->urlLive;
            $this->key = env("BINANCE_API_KEY");
            $this->secret = env("BINANCE_SECRET_KEY");
        } else {
            $this->url = rtrim(env("BINANCE_URL_TEST"), '/') . '/';
            $this->key = env("BINANCE_API_KEY_TEST");
            $this->secret = env("BINANCE_SECRET_KEY_TEST");
        }
    }
    public function buyTest(string $symbol, float $quoteOrderQty) {
        $params = [
            "symbol" => $symbol,
            "side" => "BUY",
            "type" => "MARKET",
            "timestamp" => $this->getTimestamp(),
            "quoteOrderQty" => $quoteOrderQty,
        ];

        $qs = http_build_query($params);
        $params["signature"] = $this->signature($qs);
        $url = $this->url . "order/test";
        $headers = [
            'X-MBX-APIKEY' => $this->key
        ];

        return $this->query($url, "POST", $params, $headers);
    }
    public function sellTest(string $symbol, float $quantity) {
        $params = [
            "symbol" => $symbol,
            "side" => "SELL",
            "type" => "MARKET",
            "timestamp" => $this->getTimestamp(),
            "quantity" => $quantity,
        ];

        $qs = http_build_query($params);
        $params["signature"] = $this->signature($qs);
        $url = $this->url . "order/test";
        $headers = [
            'X-MBX-APIKEY' => $this->key
        ];

        return $this->query($url, "POST", $params, $headers);
    }

    public function buy(string $symbol, float $quoteOrderQty) {
        $params = [
            "symbol" => $symbol,
            "side" => "BUY",
            "type" => "MARKET",
            "timestamp" => $this->getTimestamp(),
            "quoteOrderQty" => $quoteOrderQty,
        ];

        $qs = http_build_query($params);
        $params["signature"] = $this->signature($qs);
        $url = $this->url . "order";
        $headers = [
            'X-MBX-APIKEY' => $this->key
        ];

        return $this->query($url, "POST", $params, $headers);
    }

    public function sell(string $symbol, float $quantity) {
        $params = [
            "symbol" => $symbol,
            "side" => "SELL",
            "type" => "MARKET",
            "timestamp" => $this->getTimestamp(),
            "quantity" => $quantity,
        ];

        $qs = http_build_query($params);
        $params["signature"] = $this->signature($qs);
        $url = $this->url . "order";
        $headers = [
            'X-MBX-APIKEY' => $this->key
        ];

        return $this->query($url, "POST", $params, $headers);
    }

    public function accountInfo() {
        $params = [
            "timestamp" => $this->getTimestampMillisecs()
        ];

        $qs = http_build_query($params);
        $params["signature"] = $this->signature($qs);

        $url = $this->url . "account";
        $response = Http::withHeaders([
            'X-MBX-APIKEY' => $this->key
        ])->get($url, $params);

        if ($response->successful()) {
            return $response->json();
        } else {
            // TODO exception ---> was macht response->throw()
            Log::error("ERROR while Bincance accountInfo request");
            Log::error($response->throw());

            $response->throw();
        }
    }

    /**
     * get exchange infos
     *
     * @return array|mixed
     */
    public function exchangeInfo() {
        Log::info("Fetching exchangeInfo ...");

        $url = $this->url . 'exchangeInfo';
        return $this->query($url, "GET");
    }

    /**
     * get order information
     *
     * @param string $symbol
     * @param int $orderId
     * @return array|mixed
     */
    public function orderInfo(string $symbol, int $orderId) {
        $params = [
            "symbol" => $symbol,
            "orderId" => $orderId,
            "timestamp" => $this->getTimestamp()
            //,
            //"recvWindow" => 5000    // TODO
        ];

        $qs = http_build_query($params);
        $params["signature"] = $this->signature($qs);
        $url = $this->url . "order";
        $headers = [
            'Content-Type: application/json',
            'X-MBX-APIKEY' => $this->key
        ];

        return  $this->query($url, "GET", $params, $headers);
    }

    /**
     * get current price for a symbol
     *
     * @param string $symbol
     * @return array|mixed
     */
    public function getCurrentPrice(string $symbol) {
        $params = [
            "symbol" => $symbol
        ];

        $url = $this->urlLive . "ticker/price";
        return $this->query($url, "GET", $params);
    }

    /**
     * @param string $symbol BTCUSDT, ETHBTC, ...
     * @param string $timeframe 1m, 1h, 4h, 1d, ...
     * @param Integer $startTime unixtimestamp in milliseconds
     * @return array
     */
    public function gethistoricaldata(string $symbol, string $timeframe, int $startTime = null) {
        //time when Binance started with BTCUSDT
        if(empty($startTime)) {
            $startTime = 1502928000000;
        }

        $klines = [];
        $url = $this->urlLive . "klines";
        $keys = [
            'open_time',
            'open',
            'high',
            'low',
            'close',
            'volume',
            'close_time',
            'quote_asset_volume',
            'trades_nr',
            'taker_buy_base_asset_volume',
            'taker_buy_quote_asset_volume'
        ];

            $params = [
                "symbol" => $symbol,
                "interval" => $timeframe,
                "limit" => 1000,
                "startTime" => $startTime
            ];

            Log::info("Fetching historcial data of " . $symbol);
            $response = $this->query($url, "GET", $params);
            Log::info("Successfully fetched historcial data of " . $symbol);

            foreach ($response as &$row) {
                $klinesNamed = [];

                foreach ($row as $i => &$col) {
                    // last one is deprecated
                    if ($i === 11) {
                        continue;
                    }
                    $klinesNamed[$keys[$i]] = $col;
                    $klinesNamed['symbol'] = $symbol;
                    $klinesNamed['timeframe'] = $timeframe;
                }
                $klines[] = $klinesNamed;
            }

        return $klines;

    }

    /**
     * get current millisecs timestamp
     *
     * @return float
     */
    private function getTimestampMillisecs() {
        return round(microtime(true) * 1000);
    }

    /**
     * base query
     *
     * @param string $url
     * @param array $params
     */
    protected function query(string $url, string $type = "GET", array $params = [], array $headers = []) {
        $response = null;
        if ($type === "GET") {
            if(!empty($headers)) {
                $response = Http::withHeaders($headers)->get($url, $params);
            } else {
                $response = Http::get($url, $params);
            }
        } else if ($type === "POST") {
            if(!empty($headers)) {
                $response = Http::asForm()->withHeaders($headers)->post($url, $params);
            } else {
                $response = Http::asForm()->post($url, $params);
            }
        }

        if (!$response->successful()) {
            // TODO ... error-handling besser und testen
            Log::error("ERROR while querying in binanceApi -> QUERY: $url ===> " . json_encode($params));
            Log::error("ERROR while querying in binanceApi -> RESPONSE: " . json_encode($response->json()));
        }

        return $response->json();
    }

    /**
     * get signature for query
     *
     * @param $query_string
     * @return false|string
     */
    private function signature($query_string) {
        return hash_hmac('sha256', $query_string, $this->secret);
    }

    private function getTimestamp() {
        return round(microtime(true) * 1000);
    }
}
