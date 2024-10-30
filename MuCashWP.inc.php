<?php
require_once dirname(__FILE__) . '/MuCashSDK.inc.php';

define("MUCASH_WP_ITEMCODE_ID_BITS", 24);
define("MUCASH_WP_ITEMCODE_TYPE_BITS", 31 - MUCASH_WP_ITEMCODE_ID_BITS);

define("MUCASH_COOKIE_ADSFREE", "mucash_adsfree");

class MuCashWP
{
    const PLUGIN_VER = '1.0'; 
    const DB_VER = 1;

    const MUCASH_SID_COOKIE = "mucash_id";
    const COOKIE_EXP = 31536000; // One year
    const EXCERPT_REGEX = '/<!--more(.*?)?-->/'; // From wp-includes/post-template.php

    const IT_ARTICLE = 0;
    const IT_ADSFREE = 1;
    const IT_DONATE_COMMENT = 2;
    const IT_DONATE_BUTTON = 3;
    const IT_DOWNLOAD = 4;

    const S_DB_VERSION = "mucash_db_version";
    const S_SECTION_MAIN = "mucash_options";
    const S_MERCHANT_ID = "mucash_merchant_id";
    const S_API_KEY = "mucash_api_key";
    const S_PRICE = "mucash_price";
    const S_DONATE_BUTTON = "mucash_donate_button";
    const S_DONATE_COMMENT = "mucash_donate_comment";
    const S_DONATE_APPEAL = "mucash_donate_appeal";
    const S_SHOW_COMMENT_APPEAL = "mucash_show_comment_appeal";
    
    // Obsolete options, db version 0
    const S_DONATION_TYPE = "mucash_donation_type";
    const DT_COMMENT = "comment";
    const DT_BUTTON = "button";
    const DT_DISABLED = "disable";

    // Other strings
    const STR_DEFAULT_APPEAL = '<a href="https://mucash.com/how-it-works/">MuCash</a> lets you make donations quicky in increments as small as a single penny. By leaving a small donation every time you find something of value to you, you can help me keep creating content like this for you to enjoy.';
    public $sdk;

    private $ok = true;
    private $page_handled = false;
    private $sid;
    private $donate_type;
    private $exception;

    public function __construct()
    {
        $this->setSid();

        try {
            $this->sdk = new MuCashSDK(
                get_option(self::S_MERCHANT_ID),
                get_option(self::S_API_KEY),
                array("MuCashWP", "kvStore"),
                array("MuCashWP", "kvGet")
            );
        } catch(Exception $e) {
            $this->ok = false;
            $this->exception = $e;
            add_action("admin_notices", array($this, "noticeInitFailed"));
        }

        // Deal with MuCash specific form stuff
        if (isset($_GET["mucash_callback"])) {
            $this->handleCallback();
        }

        if (is_admin()) {
            $this->upgradeDb();
            add_action("admin_menu", array($this, "addOptionsMenu"));
            add_action("admin_init", array($this, "adminInit"));
        }
         
        if ($this->ok) {
            wp_enqueue_script("jquery");
            wp_enqueue_script("mucash_api", MUCASH_URL . "/media/js/api.js");
            wp_enqueue_script("mucash", plugins_url('mucash.js', __FILE__));
            wp_enqueue_style("mucash", plugins_url('mucash.css', __FILE__));
            add_action("save_post", array($this, "saveMeta"));
            add_action("the_posts", array($this, "thePosts"), 1);

            if (get_option(self::S_DONATE_COMMENT, 1)) {
                add_action("comment_post", array($this, "saveComment"));
                add_filter("comment_text", array($this, "commentText"));
                add_filter("the_content", array($this, "addCommentDonateQuote"));
            }
            if (get_option(self::S_DONATE_BUTTON, 1)) {
                add_filter("the_content", array($this, "addDonateButton"));
            }
            add_shortcode("mucash_download", array($this, "downloadShortcode"));
        }
    }
    
    public function thePosts($posts)
    {
        if(is_admin()) {
            return $posts;
        }
        
        foreach($posts as $key=>$post) {
            $price = get_post_meta($post->ID, self::S_PRICE, true);
            if($price) {
                if(is_attachment()) {
                    if(current_user_can('edit_post', $post->ID)) {
                        $post->post_content = self::makeDiv(
                        	"This file is protected by MuCash, you are able to see " .
                        	"this because you're logged in as an editor.");
                    } else {
                        unset($posts[$key]);
                    }
                    continue;
                }
                $this->addBuyArticleButton($post, $price);
            }
        }

        return $posts;
    }

    private function addBuyArticleButton(&$post, $price)
    {
        $itemcode = MuCashWP::packItemcode(self::IT_ARTICLE, $post->ID);
        $cert = $this->getCert($itemcode);
        if (!$cert) {
            $price = new MuCashCurrency($price);
            $error = "";

            try {
                $permalink = get_permalink($post);
                $quote = $this->sdk->generateArticleQuote(
                $itemcode, $price, $post->post_title, $permalink);
                $cburl = self::getCbUrl();

                if (preg_match(self::EXCERPT_REGEX, $post->post_content, $matches)) {
                    $parts = explode($matches[0], $post->post_content, 2);
                    $post->post_content = force_balance_tags($parts[0]);

                    if (is_feed()) {
                        $post->post_content .= self::makeDiv("This article costs $price. <a href=\"$permalink\">Click here</a> to purchase the full article.  Powered by <a href=\"https://mucash.com\">MuCash</a>.");
                    } else {
                        $post->post_content .= self::makeBuyButton($quote);
                    }
                } else {
                    $error = "WARNING: This article has a MuCash price but no more tag.";
                }
            } catch (MuCashErrInvalidTitle $e) {
                $error .= "WARNING: You must set a title when locking an article.";
            } catch (Exception $e) {
                $error .= sprintf("Internal error %s (%s). Please contact support@mucash.com.", $e->getMessage(), get_class($e));
            }

            if (current_user_can('edit_post', $post->ID) && !empty($error)) {
                $post->post_content .= self::makeDiv($error, "mucash_warning");
            }
        }
    }

    public function addDonateButton($content)
    {
        // Don't show on paid posts
        if(get_post_meta(get_the_ID(), self::S_PRICE)) {
            return $content;
        } 

        $itemcode = MuCashWP::packItemcode(self::IT_DONATE_BUTTON, get_the_ID());
        if ($cert = $this->getCert($itemcode)) {
            $post->post_content .= self::makeDiv(
                "Thank you for your donation.", "mucash_thankyou"
            );
        } else {
            $quote = $this->sdk->generateDonateQuote($itemcode, get_the_title(),
                get_permalink());
            $cburl = self::getCbUrl();
            $content .= self::makeBuyButton($quote);
            $content .= self::makeDiv(
                get_option(self::S_DONATE_APPEAL, self::STR_DEFAULT_APPEAL), 
            	"mucash_appeal");
        }
        return $content;
    }
    
    public function addCommentDonateQuote($content)
    {
        $itemcode = MuCashWP::packItemcode(self::IT_DONATE_COMMENT, $post->ID);
        $quote = $this->sdk->generateDonateQuote($itemcode, $post->post_title,
            get_permalink($post));
        $content .= "<script type=\"text/javascript\">" .
            "var mucash_comment_donate_quote = '$quote';</script>";
        
        return $content;
    }

    public function downloadShortcode($atts)
    {
        extract(shortcode_atts(array("id" => 0), $atts));
        
        if(!$id) {
            return;
        } 
        
        global $post; 
        $att_post = get_post($id);
        $price = get_post_meta($id, self::S_PRICE, true);
        if(!$post || !$att_post || !$price) {
            return;
        }

        $itemcode = MuCashWP::packItemcode(self::IT_DOWNLOAD, $att_post->ID);
        $cert = $this->getCert($itemcode);
        if($cert) {
            return '<a href="'. self::getDownloadUrl($itemcode) .'">Download again</a>';
        } else {
            $price = new MuCashCurrency($price);
        
            $quote = $this->sdk->generateArticleQuote(
                $itemcode, $price, $att_post->post_title, get_permalink($post));

            return self::makeBuyButton($quote, array("button-label"=>"Download"));
        }
    }
    
    public function adminInit()
    {
        register_setting(self::S_SECTION_MAIN, self::S_MERCHANT_ID);
        register_setting(self::S_SECTION_MAIN, self::S_API_KEY);
        register_setting(self::S_SECTION_MAIN, self::S_DONATE_BUTTON);
        register_setting(self::S_SECTION_MAIN, self::S_DONATE_COMMENT);
        register_setting(self::S_SECTION_MAIN, self::S_DONATE_APPEAL);
        register_setting(self::S_SECTION_MAIN, self::S_SHOW_COMMENT_APPEAL);

        if ($this->isOk()) {
            add_meta_box("mucash_post_options", "MuCash Options",
                array($this, "addMetaBox"), "post", "normal", "high");
            add_filter("attachment_fields_to_edit", array($this, attachmentFields), 99, 2);
            add_filter("attachment_fields_to_save", array($this, attachmentFieldsSave), 99, 2);
            add_filter("pre-upload-ui", array($this, preUploadUi));
            add_filter("media_send_to_editor", array($this, mediaSendToEditor), 99, 3);
            add_filter("wp_handle_upload_prefilter", array($this, protectUploadFilename));
        }
    }

    public function mediaSendToEditor($html, $attachment_id, $attachment)
    {
        $price = isset($attachment[self::S_PRICE]) ? (int)$attachment[self::S_PRICE] : 0; 
        if(!$price) {
            return $html;
        }
        return "[mucash_download id=\"$attachment_id\"]";
    }
    
    public function preUploadUi()
    {
        ?>
        <div style="margin-bottom: 1em">
        <input name="protect_filename" value="1" type="checkbox"/> 
        <label for="protect_filename">
          Protect filename to prevent guessing the URL.
          <a href="https://mucash.com/protecting-filenames/" target="_blank">Learn more</a>. <img style="position: relative; top: 3px;" src="https://mucash.com/media/images/logo-16.png"/>
        </label>
        <script type="text/javascript">
          jQuery("input[name='protect_filename']").change(function() {
              if(this.checked) {
                  wpUploaderInit.multipart_params.protect_filename = 1;
              } else {
                  delete wpUploaderInit.multipart_params.protect_filename;
              }
          });
        </script>
        </div>
        <?php
    }
    
    public function protectUploadFilename($file)
    {
        if(!isset($_POST["protect_filename"])) {
            return $file;
        }
        
        $info = pathinfo($file["name"]);
        $ph = $this->getHasher();
        $rand = bin2hex($ph->get_random_bytes(8));
        $file["name"] = $info["filename"] . "_mc_$rand";
        
        if(isset($info["extension"])) {
            $file["name"] .= ".$info[extension]";
        }
        
        return $file;
    }
    
    public function attachmentFields($fields, $post)
    {
    	$html = '<select name="attachments['.$post->ID.'][mucash_price]">';
    	$html .= '<option value="">(None)</option>';
    	$meta_price = get_post_meta($post->ID, self::S_PRICE, true);
        foreach (array(1, 2, 5, 10, 15, 20, 25, 50, 75, 99) as $price) {
            $cur = MuCashCurrency::fromCents($price);
            $label = (string)$cur;
            $intval = $cur->getEncoded();
            $sel = $intval == $meta_price ? 'selected="selected"' : '';
            $html .= "<option $sel value=\"$intval\">$label</option>";            
        }
    	$html .= '</select>';

        $fields[self::S_PRICE] = array(
            "label" => "MuCash Price",
            "input" => "html",
            "html" => $html,
            "helps" => "If a price is set, inserting into as post or page will"
            	. " generate a MuCash download button instead of a normal image or link.",
        );
        return $fields;
    }
    public function attachmentFieldsSave($post, $attachment)
    {
        $mucash_price = 0;
        if(isset($attachment[self::S_PRICE])) {
            $mucash_price = (int)$attachment[self::S_PRICE];
        }
        if($mucash_price) {
            update_post_meta($post["ID"], self::S_PRICE, $mucash_price);
        } else {
            delete_post_meta($post["ID"], self::S_PRICE);
        }
        
        return $post;
    }
    
    public function handleCallback()
    {
        $res = new stdClass();
        $res->ok = false;
        
        $func = isset($_REQUEST["func"]) ? $_REQUEST["func"] : null;
        switch($func) {
            case "check_cert": $this->handleCallbackCheckCert($res); break;
            case "download": $this->handleCallbackDownload($res); break;
            case "inspect": $this->handleCallbackInspect($res); break;
            case "reset_pubkey": $this->handleCallbackResetPubkey($res); break;
            default: $res->error = "INVALID_FUNCTION"; break;
        }
        header("Content-type: application/json");
        echo json_encode($res);
        $this->page_handled = true;
    }

    public function handleCallbackCheckCert(&$res)
    {
        if (isset($_POST["cert"])) {
            try {
                $cert = $this->sdk->checkCertificate($_POST["cert"]);
                $this->addCert($cert);
                $res->cert = $cert;
                $res->itemcode_type = self::getItemcodeType($cert->itemcode);
                $res->itemcode_id = self::getItemcodeId($cert->itemcode);
                
                if($res->itemcode_type == self::IT_DOWNLOAD) {
                    $res->download_url = self::getDownloadUrl($cert->itemcode);
                } else {
                    $res->permalink = get_permalink($res->itemcode_id);
                }

                $res->ok = true;
            } catch (MuCashErrBadCert $e) {
                $res->error = "CERT_INVALID";
            }
        } else {
            $res->error = "CERT_MISSING";
        }
    }
    
    public function handleCallbackDownload(&$res)
    {
        if(!isset($_GET["itemcode"]) || !($itemcode=(int)$_GET["itemcode"])) {
            $res->error = "ITEMCODE_INVALID";
            return;
        }
        if(self::getItemcodeType($itemcode) != self::IT_DOWNLOAD) {
            $res->error = "ITEMCODE_INVALID";
            return;
        }

        $cert = $this->getCert($itemcode);
        if(!$cert) {
            $res->error = "PAYMENT_REQUIRED";
            return;
        }
        
        $id = self::getItemcodeId($cert->itemcode);
        $post = get_post($id);
        if(!$post) {
            $res->error = "ITEMCODE_INVALID";
            return;
        }
        $res->post = $post;
        $filename = get_attached_file($post->ID);
        $info = pathinfo($filename);
        $dlname = preg_replace("/_mc_[0-9a-fA-F]*$/", "", $info["filename"]);
        if(isset($info["extension"])) {
            $dlname .= '.' . $info["extension"];
        }
        
        header("Content-disposition: attachment; filename=$dlname");
        header("Content-type: $post->post_mime_type");
        readfile($filename);
    }

    /*
     * A simple callback to return diagnostic information about the MuCash
     * plugin to authorized users.  It can't be called without knowing the API
     * key.
     */
    public function handleCallbackInspect(&$res)
    {
        if(isset($_GET["passwd"]) && $_GET["passwd"] == sha1(get_option(self::S_API_KEY))) {
            $res->ok = true;
            $res->version = self::PLUGIN_VER;
        }
    }
       
    /*
     * The MuCash plugin might have temporary issue when the public key used
     * to sign purchase certificates expires in March and sites have the old
     * one cached.  A future plugin release will handle this more gracefully
     * by using two certs with overlapping exprations.  This temporary kludge
     * will let MuCash staff manually clear the cached key on sites that are
     * tardy with upgrading their plugins.
     */
    public function handleCallbackResetPubkey(&$res)
    {
        if(isset($_GET["passwd"]) && $_GET["passwd"] == sha1(get_option(self::S_API_KEY))) {
            delete_transient("mucash_signer_key");
            $res->ok = true;
        }
    }
    
    public function noticeInitFailed()
    {
        $class = get_class($this->exception);
        switch($class) {
            case "MuCashErrInvalidMerchantId":
            case "MuCashErrInvalidKey":
                $msg = "Please visit the MuCash settings page and set your " .
                    "Site ID and API Key to complete the plugin installation.";
                break;
            case "MuCashErrServerConfig":
                $msg = "We're sorry, the MuCash plugin does not support your " .
                    "server configuration.  Please contact us at support@mucash.com ". 
                    "and we will work with you to find a solution.";
                break;
            default:
                $msg = "We're sorry, the MuCash plugin has suffered an internal " .
                    "error.  Please contact us at support@mucash.com and we will " .
                    "help you resolve this as quickly as possible.";
            break;
        }

        $msg = sprintf("%s (%s at %s:%d)", $msg, $class,
        basename($this->exception->getFile()), $this->exception->getLine());

        echo self::makeDiv($msg, "updated fade");
    }

    public function addOptionsMenu()
    {
        add_options_page('MuCash Options', 'MuCash', 'manage_options',
        self::S_SECTION_MAIN, array($this, 'showAdminOptions'));
    }

    public function showAdminOptions()
    {
        include dirname(__FILE__) . '/html/options.php';
    }

    public function addMetaBox($data)
    {
        include dirname(__FILE__) . '/html/meta_box.php';
    }

    public function saveMeta($postid)
    {
        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }
        if (!wp_verify_nonce($_POST["mucash_meta_nonce"], "mucash_meta")) {
            return;
        }
        if(!current_user_can("edit_post", $postid)) {
            return;
        }

        $mucash_price = (int)$_POST[self::S_PRICE];
        if($mucash_price) {
            update_post_meta($postid, self::S_PRICE, $mucash_price);
        } else {
            delete_post_meta($postid, self::S_PRICE);
        }
    }

    public function saveComment($commentid)
    {
        if(isset($_POST["mucash_cert"])) {
            try {
                $cert = $this->sdk->checkCertificate($_POST["mucash_cert"]);
                update_comment_meta($commentid, "mucash_donation", $cert);
            } catch (MuCashErrBadCert $e) {
                // Nothing to do here, just don't attach a meta
            }
        }
    }

    public function commentText($comment_text)
    {
        global $comment;
        if(!isset($comment)) {
            return $comment_text;
        }
        $cert = get_comment_meta($comment->comment_ID, "mucash_donation", true);
        if($cert) {
            $amt = new MuCashCurrency($cert->price);
            $comment_text .= '<p class="mucash_donation_notice">' . (string)$amt . ' donated via <a href="https://mucash.com">MuCash</a></p>';
        }
        return $comment_text;
    }

    static public function packItemcode($type, $id)
    {
        $type = (int)$type;
        $id = (int)$id;

        if ($id >= (1 << MUCASH_WP_ITEMCODE_ID_BITS)) {
            throw new MuCashWPErrItemIDTooLarge();
        }
        return ($type << MUCASH_WP_ITEMCODE_ID_BITS) | $id;
    }

    static public function getItemcodeId($itemcode)
    {
        $mask = (1 << MUCASH_WP_ITEMCODE_ID_BITS) - 1;
        return $itemcode & $mask;
    }

    static public function getItemcodeType($itemcode)
    {
        $mask = (1 << MUCASH_WP_ITEMCODE_TYPE_BITS) - 1;
        return ($itemcode >> MUCASH_WP_ITEMCODE_ID_BITS) & $mask;
    }

    private function getItemKey($itemcode)
    {
        return implode('_', array("mucash", $this->sid, $itemcode));
    }

    public function addCert(MuCashPaymentCertificate $cert)
    {
        $old_cert = $this->getCert($cert->itemcode);
        if ($old_cert && $old_cert->timestamp >= $cert->timestamp) {
            return;
        }
        set_transient($this->getItemKey($cert->itemcode), $cert, 30 * 86400);
    }

    public function getCert($itemcode)
    {
        return get_transient($this->getItemKey($itemcode));
    }

    public function delCert($itemcode)
    {
        delete_transient($this->getItemKey($itemcode));
    }

    public function pageHandled()
    {
        return $this->page_handled;
    }

    public function isOk()
    {
        return $this->ok;
    }

    static public function kvStore($key, $value)
    {
        set_transient($key, $value, 30 * 24 * 3600);
    }

    static public function kvGet($key)
    {
        return get_transient($key);
    }

    static public function getCbUrl($args = array())
    {
        $args["mucash_callback"] = 1;
        return add_query_arg($args);
    }

    static public function getDownloadUrl($itemcode)
    {
        return self::getCbUrl(
            array("func" => "download", "itemcode" => $itemcode));    
    }
    
    private function setSid()
    {
        if(isset($_COOKIE[self::MUCASH_SID_COOKIE])) {
            $this->sid = $_COOKIE[self::MUCASH_SID_COOKIE];
        } else {
            $ph = $this->getHasher();
            $this->sid = bin2hex($ph->get_random_bytes(8));
        }
        setcookie(self::MUCASH_SID_COOKIE, $this->sid, time() + self::COOKIE_EXP,
        SITECOOKIEPATH, COOKIE_DOMAIN);
    }

    private function getHasher()
    {
        global $wp_hasher;

        if (empty($wp_hasher)) {
            require_once( ABSPATH . 'wp-includes/class-phpass.php');
            $ph = new PasswordHash(8, true);
        } else {
            $ph = $wp_hasher;
        }
        
        return $ph;
    }
    
    private function upgradeDb()
    {
        add_option(self::S_DB_VERSION, self::S_DB_VERSION);
        $db_ver = get_option(self::S_DB_VERSION, 0);
        
        // Upgrade from version 0 to 1
        if($db_ver == 0) {
            // Transition donation options
            $dt = get_option(self::S_DONATION_TYPE);
            switch($dt) {
                case self::DT_COMMENT: 
                    add_option(self::S_DONATE_COMMENT, 1);
                    break;
                case self::DT_BUTTON: 
                    add_option(self::S_DONATE_BUTTON, 1);
                    break;
                case self::DT_DISABLED:                 
                    add_option(self::S_DONATE_COMMENT, 0);
                    add_option(self::S_DONATE_BUTTON, 0);
                    break;
            }
            delete_option(self::S_DONATION_TYPE);
            update_option(self::S_DB_VERSION, 1);
        }
    }
    
    static protected function makeDiv($content, $class = "", $id = "")
    {
        if ($class != "") {
            $class = "class=\"$class\"";
        }
        if ($id != "") {
            $id = "id=\"$id\"";
        }
        return "<div $class $id>$content</div>";
    }

    static protected function makeBuyButton($quote, $options = array())
    {
        $opts = "";
        foreach($options as $key=>$value) {
            $opts .= "$key=\"$value\" ";
        }
        return sprintf('<mc:button quote="%s" '.$opts.'></mc:button>',
        $quote, self::getCbUrl());
    }
}

class MuCashWPErrItemIDTooLarge extends Exception {}

?>