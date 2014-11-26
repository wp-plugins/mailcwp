=== MailCWP === 
Contributors: CadreWorks Pty Ltd
Donate link: http://cadreworks.com/mailcwp-plugin/#support-development
Tags: mail, imap, smtp, email, pop3, message, communication, webmail
Requires at least: 3.8.0
Tested up to: 3.9.2
Stable tag: 1.8
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

MailCWP, Mail Client for WordPress. A full-featured mail client plugin
providing webmail access through your WordPress blog or website.

== Description ==

Allow your staff to access their email directly through your WordPress blog or
website.

Leverage your existing WordPress infrastructure to provide WebMail access to
your staff, avoiding the time, effort and cost of maintaining a separate WebMail service.

MailCWP is designed to be responsive and feature rich. Powered by jQuery and
AJAX the system responds quickly to user requests. Using jQuery UI (with
jui_theme_switch) it looks great. With the Composer add-on, poewred by
TinyMCE, rich-text WYSIWYG editing is possible. Attachments are managed using
plUpload to quickly add file attachments with no need for page refreshes.

See [MailCWP Plugin](http://cadreworks.com/mailcwp-plugin) for more information.

Several add-ons are available to enhance this plugin:
* [MailCWP Address Book](http://cadreworks.com/mailcwp-plugin/mailcwp-address-book) - Manage your email contacts. 
* [MailCWP Advanced Search](http://cadreworks.com/mailcwp-plugin/mailcwp-advanced-search) - Use a wide range of criteria to find mail messages.
* [MailCWP Composer](http://cadreworks.com/mailcwp-plugin/mailcwp-composer) - A WYSIWYG, rich-text editor.
* [MailCWP Folders](http://cadreworks.com/mailcwp-plugin/mailcwp-folders) - Create, rename, move and remove IMAP folders. Copy and move messages across folders.
* [MailCWP Signatures](http://cadreworks.com/mailcwp-plugin/mailcwp-signatures) - Add signatures to every email message.
* Many more add-ons coming soon including a number of enterprise features.

== Installation ==

1. Upload `mailcwp.zip` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Use the [mailcwp] shortcode on any page or post
1. Add one or more accounts
1. Start reading and writing email messages

== Frequently Asked Questions ==

= How do I add IMAP accounts to MailCWP? =
There are two ways to add accounts - through the WordPress User Profile admin page or through the MailCWP Options dialog. In WordPress admin go to the User Profile and look for the MailCWP Accounts section. Add and edit accounts there. In the front-end tap the options button in the toolbar and tap on the Accounts tab.

= What are the common IMAP settings? =
Ask the mail administrator or helpdesk to send you the mail server details. You should get the mail server name or IP address, the port, whether the server uses SSL and a username and password. Most IMAP servers use port 143 or 993 with SSL. 

For Gmail use your email address as the username. Port is 993 and toggle on SSL and Validate Cerificates.

== Screenshots ==

1. The INBOX with Toolbar and 3 accounts (company IMAP, Hotmail and Gmail).
2. The New Message Composer dialog. Tap CC/BCC to see those fields.
3. The Options dialog. Add/Edit IMAP accounts and setup Sent, Trash and Drafts folders.

== Changelog ==

= 1.9 =
Fix for character encoding issue causing mail open failure.
Reintroduce MailCWP account editor in user profile pages and fix conflicts.

= 1.8 =
v1.7 release did not included fix

= 1.7 =
Fix for no response when opening mail that includes HTML content or attachments

= 1.6 =
Add author, date and time of original email in replied and forwards

= 1.5 =
Fix for date issue when sending mail

= 1.4 =
Remove MailCWP settings from User Profile page to avoid conflicts.

= 1.3 =
* Minor corrections to readme.txt

= 1.2 =
* Fix for blank multipart/alternative messages

= 1.1 =
* Updates follow WordPress.org review

= 1.0 =
* First release

== Upgrade Notice ==

= 1.9 =
Fix for character encoding issue causing mail open failure.
Reintroduce MailCWP account editor in user profile pages and fix conflicts.

= 1.8 =
v1.7 release did not included fix

= 1.7 =
Fix for no response when opening mail that includes HTML content or attachments

= 1.6 =
Add author, date and time of original email in replied and forwards

= 1.5 =
Fix for date issue when sending mail

= 1.4 =
Remove MailCWP settings from User Profile page to avoid conflicts.

= 1.3 =
Minor corrections to readme.txt

= 1.2 =
Fix for blank multipart/alternative messages

= 1.1 =
Updates follwing WordPress.org review

= 1.0 =
First release.
