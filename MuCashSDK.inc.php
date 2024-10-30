<?php
define('MC_SDK_DOLLAR_MULT', 100*100);
define('MC_SDK_URL_MAXLEN', 255);
define('MC_SDK_TITLE_MAXLEN', 60);

define("MUCASH_SEPARATOR", "|");

class MuCashSDK
{
    private $merchantid;
    private $api_key;
    private $mucash_public_key;
    private $kv_store_func;
    private $kv_get_func;
    
    private $config = array();
    
    public function __construct($merchantid, $api_key, $kv_store_func, $kv_get_func) 
    {
        $this->checkConfig();
        $this->merchantid = (int)$merchantid;
        $this->api_key = $api_key;
        $this->kv_store_func = $kv_store_func;
        $this->kv_get_func = $kv_get_func;
        
        if (!$this->merchantid) {
            throw new MuCashErrInvalidMerchantId;
        } 
        
        if (!$this->api_key) {
            throw new MuCashErrInvalidKey();
        }
    }
    
    private function loadMuCashPublicKey()
    {
        if (!($crt = $this->loadCachedMuCashPublicKey())) {
            $crt = $this->loadCaMuCashPublicKey();
        }

        if (!$crt) {
            //@codeCoverageIgnoreStart
            throw new MuCashErrInternal();
            //@codeCoverageIgnoreEnd
        }

        $this->mucash_public_key = openssl_pkey_get_public($crt);
        if (!$this->mucash_public_key) {
            //@codeCoverageIgnoreStart
            throw new MuCashErrInternal();
            //@codeCoverageIgnoreEnd
        }
    }

    private function loadCachedMuCashPublicKey()
    {
        $crt = call_user_func($this->kv_get_func, "mucash_signer_key");
        if($crt) {
            return openssl_x509_read($crt);
        } else {
            return false;
        }
    }
    
    private function loadCaMuCashPublicKey()
    {
        $root_crt = dirname(__FILE__) . "/mucash-root.crt";
        $url = MUCASH_URL . "/ca/" . MUCASH_CRT_NAME;
        if (!($crt = $this->fetchUrl($url))) {
            //@codeCoverageIgnoreStart
            return false;
            //@codeCoverageIgnoreEnd
        }
        
        if (!openssl_x509_checkpurpose($crt, X509_PURPOSE_ANY, array($root_crt))) {
            //@codeCoverageIgnoreStart
            return false;
            //@codeCoverageIgnoreEnd
        }

        call_user_func($this->kv_store_func, "mucash_signer_key", $crt);
        return openssl_x509_read($crt);
    }
    
    /**
        @return McPaymentCertificate
     */
    function checkCertificate($str)
    {
        if($this->config["openssl"]) {
            $this->loadMuCashPublicKey();

            $pieces = explode(MUCASH_SEPARATOR, trim($str), 2);
            if (count($pieces) != 2) {
                throw new MuCashErrBadCert();
            }
            
            $signature = base64_decode($pieces[1]);
            $info = json_decode(base64_decode($pieces[0]));
            if (is_null($info)) {
                throw new MuCashErrBadCert();
            }
            
            if (!openssl_verify($pieces[0], $signature, $this->mucash_public_key)) {
                throw new MuCashErrBadCert();
            } else if ($info->merchantid != $this->merchantid) {
                throw new MuCashErrBadCert();
            }
        } else {
            $url = MUCASH_URL . "/webint/cert/verify?cert=" . rawurlencode($str);
            $res = json_decode($this->fetchUrl($url));
            if(!$res || !$res->cert) {
                throw new MuCashErrBadCert();
            }
            $info = $res->cert;
        }    
        return new MuCashPaymentCertificate($info->itemcode, $info->price,
            $info->transid, $info->timestamp);
    }

    /** 
     * 
     * Generate a signed quote that can be passed to MuCash.  This will be
     * encode the pricing, terms, and description of the item.
     *
     * @param string $type Quote type.  Currently article or donate
     * @param int $itemcode MuCash item code.  
     * @param MuCashCurrency $price
     * @param string $description Short description (255 characters)
     * @param array $extra Extra arguments depending on type
     * @return string
     */
    public function generateQuote($type, $itemcode, $price, $extra = array())
    {
        $type = strtolower($type);
        if (!in_array($type, array("article", "donate", "promo"))) {
            throw new MuCashErrInvalidType();
        }
        
        $price = $price->getEncoded();
        if (($type != "promo" && $price <= 0) || $type == "promo" && $price >= 0) {
            throw new MuCashErrInvalidPrice();
        }
        
        $itemcode = (int)$itemcode;
        if(!$itemcode) {
            throw new MuCashErrInvalidItemCode();
        }
        
        $info = array(
            "type" => $type,
            "merchantid" => $this->merchantid,
            "itemcode" => $itemcode,
            "price" => $price,
            "timestamp" => time()
        );
        $info = array_merge($info, $extra);
        
        $quote = base64_encode(json_encode($info));
        return $quote . MUCASH_SEPARATOR . hash_hmac('sha1', $quote, $this->api_key); 
    }
    
    public function generateArticleQuote($itemcode, $price, $title, $url)
    {
        if (strlen($url) > MC_SDK_URL_MAXLEN ||
            !in_array(parse_url($url, PHP_URL_SCHEME), array("http", "https"))) 
        {
            throw new MuCashErrInvalidUrl();
        }
        
        $title = trim($title);
        if ($title == "") {
            throw new MuCashErrInvalidTitle();
        }
        if(strlen($title) > MC_SDK_TITLE_MAXLEN) {
        	$title = substr_replace($title, '...', MC_SDK_TITLE_MAXLEN - 3);
        }
        
        return $this->generateQuote(
            "article", $itemcode, $price, 
            array("title" => $title, "url" => $url));
    }
    
    public function generateDonateQuote($itemcode, $title, $url)
    {
        return $this->generateQuote("donate", $itemcode, MuCashCurrency::fromCents(25),
            array("title" => $title, "url" => $url));
    }
    
    public function generatePromoQuote($itemcode, $amount)
    {
        return $this->generateQuote(
            "promo", $itemcode, new MuCashCurrency(-($amount->getEncoded()))
        );
    }
    
    private function fetchUrl($url)
    {
        if($this->config['curl']) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            $data = curl_exec($ch);
            curl_close($ch);
            return $data;
        } else {
            return file_get_contents($url);
        }
    }
    
    private function checkConfig()
    {
        $fnchecks = array(
            'openssl' => 'openssl_pkey_get_public',
            'json' => 'json_encode',
            'curl' => 'curl_init',
        );
        
        foreach ($fnchecks as $key => $value) {
            $this->config[$key] = function_exists($value);
        }
        
        $this->config["url_fopen"] = ini_get('allow_url_fopen');

        if(!$this->config['json']) {
            throw new MuCashErrServerConfig();
        } else if (!$this->config['curl'] && !$this->config['url_fopen']) {
            throw new MuCashErrServerConfig();
        }
    }
}

class MuCashPaymentCertificate
{
    public $itemcode, $price, $transid, $timestamp;
    
    public function __construct($itemcode, $price, $transid, $timestamp)
    {
        $this->itemcode = $itemcode;
        $this->price = $price;
        $this->transid = $transid;
        $this->timestamp = $timestamp;
    }
}

class MuCashCurrency
{
    private $intval;
    public function __construct($intval)
    {
        $this->intval = (int)$intval;
    }
    
    public static function fromDollars($dollars)
    {
        return new MuCashCurrency(round($dollars * MC_SDK_DOLLAR_MULT));
    }

    public static function fromCents($cents)
    {
        return new MuCashCurrency(round($cents / 100.0 * MC_SDK_DOLLAR_MULT));
    }

    public function toDollars()
    {
        return (float)($this->intval) / MC_SDK_DOLLAR_MULT;
    }

    public function toCents()
    {
        return $this->toDollars() * 100;
    }
    
    public function __toString()
    {
        $val = $this->toDollars();
        if (abs($val) < 1) {
            $val = $val * 100;
            return "$val" . "&cent;";
        } else {
            return "$" . $val;
        }
    }
    
    public function getEncoded()
    {
        return $this->intval;
    }
}

class MuCashErrInternal extends Exception {}
class MuCashErrInvalidMerchantId extends Exception {}
class MuCashErrInvalidKey extends Exception {}
class MuCashErrBadCert extends Exception {}
class MuCashErrInvalidItemCode extends Exception {}
class MuCashErrInvalidPrice extends Exception {}
class MuCashErrInvalidType extends Exception {}
class MuCashErrInvalidUrl extends Exception {}
class MuCashErrInvalidTitle extends Exception {}
class MuCashErrCacheNotWritable extends Exception {}
class MuCashErrServerConfig extends Exception {}

?>