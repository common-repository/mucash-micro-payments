MuCashWP = {
    ajax_url : (function(){ 
        var url = window.location.href.split('#')[0];
        url += url.indexOf('?') == -1 ? "?" : "&"; 
        return url + "mucash_callback=1"; })(),
    showDownloadConfirmation : function(mc_elem, download_url) {
        var elem = jQuery('<a href="' + download_url + '">Download again</a>')[0];
        MuCash.showConfirmation(mc_elem, null, null, elem);
    }
};

MuCashConfig = {
    onPurchase : function(mc_elem, quote, cert) {
        jQuery.post(
            MuCashWP.ajax_url, 
            {func:"check_cert", cert:cert.raw}, 
            function(data) {
                switch(data.itemcode_type) {
                case 0:  // Article
                    var cur = window.location.href.split("#")[0]
                    if(window.location.href.split("#")[0] == data.permalink) {
                        window.location.reload();
                    } else {
                        window.location = data.permalink;
                    }
                    break;
                case 2: // Comment donation
                    var form = jQuery('form[action*="wp-comments-post.php"]').first();
                    var hidden = jQuery('input[name="mucash_cert"]', form).val(cert.raw);
                    var submit = jQuery('input[type="submit"], input[type="image"], button[type="submit"]', form);
                    submit.unbind('click');
                    submit.click();
                    break;
                case 4: // Download
                    MuCashWP.showDownloadConfirmation(mc_elem, data.download_url);
                    window.location = data.download_url;
                    break;
                default:
                    MuCash.showConfirmation(mc_elem, quote, cert);
                } 
            }
        );
    },
    onExistingPurchase : function(mc_elem, quote, cert) {
        jQuery.post(
            MuCashWP.ajax_url, 
            {func:"check_cert", cert:cert.raw}, 
            function(data) {
                switch(data.itemcode_type) {
                case 0: // Article
                    var link = document.createElement("a");
                    link.innerHTML = "Continue reading";
                    link.href = data.permalink;
                    mc_elem.parentNode.insertBefore(link, mc_elem);
                    break;
                case 4:
                    MuCashWP.showDownloadConfirmation(mc_elem, data.download_url);
                    break;
                default:
                    MuCash.showConfirmation(mc_elem, quote, cert);
                }
            }
        );
    }
};

jQuery(document).ready(function(){
    if(typeof mucash_comment_donate_quote !== "undefined") {
        MuCash.checkQuote({"quote":mucash_comment_donate_quote});
        
        var form = jQuery('form[action*="wp-comments-post.php"]').first();
        var p = document.createElement("p");
        p.className = "comment-form-mucash-donation";
        p.style.whiteSpace = "nowrap";
        
        var select = MuCash.createPriceSelect();
        var dummyopt = document.createElement("option");
        dummyopt.innerHTML = "(Donate)";
        dummyopt.value = "";

        select.insertBefore(dummyopt, select.firstChild);
        select.id = "mucash_donate_amount";
        p.appendChild(select);

        var label = document.createElement("label");
        label.innerHTML = '&nbsp;<img src="https://mucash.com/media/images/logo-16.png" class="mucash_logo_16"/> Powered by <a href="https://mucash.com/how-it-works/">MuCash</a>';
        p.appendChild(label);

        var hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = "mucash_cert";
        p.appendChild(hidden);

        var submit = jQuery('input[type="submit"], input[type="image"], button[type="submit"]', form);
        submit.first().parent().before(p);
        submit.click(function(){
            var amount = jQuery('#mucash_donate_amount option:selected').val();
            if (amount) {
                MuCash.buyItem(null, {
                    quote : mucash_comment_donate_quote,
                    amount : amount 
                }); 
                return false;
            } else {
                return true;
            }
        });
    }
});

