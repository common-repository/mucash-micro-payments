=== MuCash Micropayments ===
Contributors: mucash
Tags: payments, donations, micropayments, micro-payments, monetization, paywall, premium content
Requires at least: 3.0
Tested up to: 3.3.1
Stable tag: 1.0

MuCash is micropayments made simple.  With just a few clicks your readers can buy articles, download files, make donations, and more. Transactions can be as small as a single penny! 

== Description ==

[MuCash] is micropayments made simple.  Our plugin lets your readers support
your blog by buying articles, making small donations, buy e-books, MP3s or other
media, and more.  Transactions take as little as two clicks, and can be as small 
as a single penny.

[MuCash]: http://mucash.com
            "MuCash, micropayments simplified."
            
This version of our plugin offers the following features:

* **Paid articles**: Set a price on articles of your choosing.  Users will only
see an excerpt and have the option to purchase the full article.
* **Paid media downloads**: Quickly and easily add a button to any post or
page to let your users purchase media files from you, be an e-book, PDF, or MP3.
* **Donations in comments**: In addition to leaving a "kudos", your readers can
choose to leave a donation in any ammount from a penny to a dollar.
* **Per-post donations**: If you use an external comment system like Disqus, the
above option won't work for you, but you can also accept per-post donations
via a simple donate button.

MuCash is a small company based in New York City. We are committed to 
providing you with all the features and support you need, so please do not
hesitate to reach out to us at info@mucash.com if you have any questions or
comments.
 
== Installation ==

Installing the mucash plugin is easy.  You can upload the .zip file, or just
search and install it from the Plugin Directory via the Plugins page in your
Wordpress admin panel.

You will need to sign up for a MuCash publisher account.  This is easy and takes
only a couple of minutes:

http://mucash.com/account/sites/add

Once you sign up, and add your website, you'll receive a Site ID and API key.
Just paste them into the corresponding fields on the "MuCash" page of your
WordPress admin panel, and you're all set.

Users will automatically be able to leave donations along with comments on
your blog posts.  When you edit a post, you also have the option to require
a payment to unlock the full article.  To do this,

1. Type your post as you normally would.
2. Set a price for the post.  This can be done by selecting a price from the 
drop down menu in the MuCash options box on the post edit page.
3. Add a "more" break in the post.  This is where the MuCash button will appear 
in your post.  The rest of the post won’t be visible until the reader purchases 
the rest of the post.

Please contact us at info@mucash.com if you have any questions.

== Frequently Asked Questions ==

= Why micropayments? =
Creating quality content takes effort, and we believe that the people who create
value should get fairly rewareded, whether through voluntary donations or
direct purchases.  Subscriptions are one way for readers to directly purchase
your content, but lock out most casual users because they may only be interested
in one article.  A single ten cent purchase can easily exceed the avertising
revenue generated by hundreds of page views.

Our mission is to allow writers to focus on doing what they do best: writing,
researching, and creating quality content.

= Yes, but why would I want donations of just a few cents? =
Most people can't afford to  leave several dollars every time they enjoy a
post you created, or want to show appreciation.  All too often they'll plan on
leaving a donation "later".  MuCash enables transactions as small as a penny,
so they truly can reward the author every time they find something they like
without putting it off or thinking too hard about it.

= Do I have to put all my posts behind a paywall? =

No, you set prices individually on articles.  For example, you could keep the
bulk of your posts free but only charge for a few feature articles.
Alternatively, you can opt to keep all your content free and only use the
donation feature.

= Do I need a credit card merchant account? =

No, we handle all credit card transactions with end users.  When they sign up,
they will be prompted to purchase credits using a credit card.  They can use
purchased credits across all MuCash-enabled websites.

= How do I get paid? =

We will transfer money into a checking account of your choosing once a month.
You can specify your banking details on the settings page of your account on
MuCash.com.  For more details, including our commission rates, please see the 
full terms and conditions on our website, and shown during the sign up process.

= Who is behind MuCash? =

MuCash is founded by Vinay Pai, and Ben Oaks.  You can read more about us 
on our website here:  https://mucash.com/about-us/

Have any questions not answered here?  Please send us an e-mail at
info@mucash.com.


== Screenshots ==

1. Setting a price on an article from the edit post window in the admin dashboard. 
2. The user sees and excerpt (the porting before more tag) and a button to buy the 
full article.
3. A window allows them to confirm pricing and verify their intent to purchase.
They will then be instantly taken to the full article, with minimal disruption
of their browsing experience.
4. Users can also choose to leave a donation along with their comments.

== Changelog ==
= 1.0 =
* Option to add text to accompany donation buttons, and help improve conversions
* Improved configuration page allows much greater flexibility
* Significant under-the hood improvements for cleaner code and performance improvements.
* MuCash's WordPress plugin emerges from beta!

= 0.9.1 =
* Added download functionality
* Fixed handling of AJAX callback with some setups

= 0.9 =
* Switched out the backend to use a newer MuCash API that does not depend on iframes to display buttons, and will allow for greater flexibility and customization
* Improved purchase flow, so buying an article from an index page will take you directly to the article rather than displaying a "read more" link
* Fixed bug with RSS feed
* Improved method of adding the donate dropdown to comment forms which should be compatible with most templates.
* Fixed CSS issue with logo display in comments with donations.

= 0.2.5 =
* Instead of throwing an error if the title is too long, the plugin
will simply truncate it and add an ellipsis
* Also changed include files from .inc to .inc.php to keep them from
being served on servers not configured to recognize .inc as php files

= 0.2.4 =
* Added an API call and workaround for systems without OpenSSL support
* Improved checking and reporting of server configuration issues
* Added line number to error messages to make tracking problems easier  

= 0.2.3 =
* Fixed issue with some server setups without the cURL library.
* Improved error reporting a bit

= 0.2.2 =
* Fixed issues with setting options on some versions of WordPress
* Extracted more styles from inline to the css files
* Refactored code to reduce cyclometric complexity of a few functions

= 0.2.1 =
* Changed the way we get remote files to be more robust.  The plugin should
work across more system configurations now.

= 0.2 =
* Added option to accept per-post donations via a simple donate button in
lieu of through comments
* Added option to turn off per-post donations altogether 
* Make better use of WP Settings API to avoid deprecated code in admin page
* Extracted styles to a css file to make customization easier if desired

= 0.1 =
* Initial public release
* Added price per article feature
* Added comments donation feature

== Upgrade Notice ==
= 1.0 =
Several new features, under-the-hood improvements, and performance improvements.

= 0.9.1 =
Added new download feature.

= 0.9 =
Fixed several bugfixes including potential RSS breakages.  Switched out backend to newer API to allow more customization.

= 0.2.5 =
Minor feature adding more graceful handling of titles that exceed the length limit.

= 0.2.4 =
If you are running a system without OpenSSL support and the plugin did not
work before, this release works around that.  If the plugin is currently
working properly for you, you don't need to update.

= 0.2.3 =
If the plugin was working properly for you you don't need to update (although
it won't hurt in any way if you do).  If you got an "internal error" message,
this should fix your problem.

= 0.2.1 =
If you got an error because URL file-access is disabled in your server, this
should fix it for you without needing any changes to your server configuration.

= 0.2 =
Added new donate option and the ability to turn of per-post donations.
