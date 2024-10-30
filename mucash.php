<?php
/*
Plugin Name: MuCash
Plugin URI: https://mucash.com/wordpress-plugin-instructions/
Version: 1.0
Author: MuCash, Inc.
Author URI: https://mucash.com
Description: MuCash is micropayments made simple.  With just a few clicks your readers can buy articles, make donations, and more... as small as a single penny!
License: GPL2
*/

require_once dirname(__FILE__) . '/MuCashSDK.inc.php';
require_once dirname(__FILE__) . '/MuCashWP.inc.php';

if (get_cfg_var("mucash_env") == "dev") {
	require_once dirname(__FILE__) . '/dev-settings.inc';
} else {
	define("MUCASH_URL", "https://mucash.com");
	define("MUCASH_CRT_NAME", "certificate-signer.crt");
	define("MUCASH_CACHE_PATH", dirname(__FILE__));
}

define("MUCASH_CRT_URL", MUCASH_URL . "/ca/" . MUCASH_CRT_NAME);

add_action('init', 'mucash_wp_init');

function mucash_wp_init()
{
    global $mucash_wp;
    $mucash_wp = new MuCashWP();

    if ($mucash_wp->pageHandled()) {
        exit(0);
    }
}
?>