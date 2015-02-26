=== MailCWP === 
Contributors: CadreWorks Pty Ltd
Donate link: http://cadreworks.com/mailcwp-plugin/#support-development
Tags: mail, imap, smtp, email, pop3, message, communication, webmail
Requires at least: 3.8.0
Tested up to: 4.1
Stable tag: 1.96
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
jui_theme_switch) it looks great. Attachments are managed using
plUpload to quickly add file attachments with no need for page refreshes.

Several add-ons are available to make MailCWP even more powerful.
1. [MailCWP Address Book](http://cadreworks.com/mailcwp-plugin/mailcwp-address-book) – Manage all your email contacts.
1. [MailCWP Advanced Search](http://cadreworks.com/mailcwp-plugin/mailcwp-advanced-search) – Find messages quickly.
1. [MailCWP Composer](http://cadreworks.com/mailcwp-plugin/mailcwp-composer) – An advanced text editor.
1. [MailCWP Folders](http://cadreworks.com/mailcwp-plugin/mailcwp-folders) – Manage email folders.
1. [MailCWP Signatures](http://cadreworks.com/mailcwp-plugin/mailcwp-signatures) – Automatically add a signature to email messages.
1. [MailCWP Macros](http://cadreworks.com/mailcwp-plugin/mailcwp-macros) – Quickly insert standard templates into email messages.

See [MailCWP Plugin](http://cadreworks.com/mailcwp-plugin) for more information.

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

= 1.96 =
Add localisation and translation files
Fix for utf8 conversion issue when reading incoming mail
Modify compose toolbar filter to support macros plugin
Fix for pagination truncating header list 

= 1.95 =
Strip slashes from email body when sending via SMTP
Fix for pagination presentation issues
Fix for formatting when composing replies to plain text messages

= 1.94 =
Fix for error when sending a message from an account with no configured Sent folder

= 1.93 =
Fix for account with no timezone set causing compose to fail

= 1.92 =
Add support for SMTP - optionally send mail via SMTP
Add support for timezone setting on each account.
Improve handling of expired sessions - automatically display WordPress login for expires sessions

= 1.91 =
Fix for emzpng (and other file formats) causing attachment processing to fail.

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

= 1.96 =
Add localisation and translation files
Fix for utf8 conversion issue when reading incoming mail
Modify compose toolbar filter to support macros plugin
Fix for pagination truncating header list 

= 1.95 =
Strip slashes from email body when sending via SMTP
Fix for pagination presentation issues
Fix for formatting when composing replies to plain text messages

= 1.94 =
Fix for error when sending a message from an account with no configured Sent folder

= 1.93 =
Fix for account with no timezone set causing compose to fail

= 1.92 =
Add support for SMTP - optionally send mail via SMTP
Add support for timezone setting on each account.
Improve handling of expired sessions - automatically display WordPress login for expires sessions

= 1.91 =
Fix for emzpng (and other file formats) causing attachment processing to fail.

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
