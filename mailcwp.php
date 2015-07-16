<?php
/*
Plugin Name: MailCWP
Plugin URI: http://wordpress.org/plugins/mailcwp/
Description: A full-featured mail client for WordPress.
Author: CadreWorks Pty Ltd
Version: 1.100
Author URI: http://cadreworks.com
*/

define ('MAILCWP_VERSION', '1.100');
define ('COMPOSE_REPLY', 0);
define ('COMPOSE_REPLY_ALL', 1);
define ('COMPOSE_FORWARD', 2);
define ('COMPOSE_EDIT_DRAFT', 3);

if(!class_exists('WP_List_Table')){
  require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

require_once("lib/util.php");
require_once("classes/MailAdminTable.class.php");
require_once("classes/MailMessage.class.php");
require_once("libs/Smarty.class.php");

//$smarty->setConfigDir('/configs');

function mailcwp_submit() {
  echo "Called mailcwp_submit";
}

add_action('admin_menu', 'mailcwp_admin_menu');

add_action('admin_enqueue_scripts', 'mailcwp_admin_scripts');
add_action('wp_enqueue_scripts', 'mailcwp_scripts', 1000);
add_action("admin_print_footer_scripts", "mailcwp_pointer_script");

//Admin AJAX Action
add_action('wp_ajax_mailcwp_get_account', 'mailcwp_get_account_callback');
add_action('wp_ajax_mailcwp_edit_account', 'mailcwp_edit_account_callback');
add_action('wp_ajax_mailcwp_delete_account', 'mailcwp_delete_account_callback');
add_action('wp_ajax_mailcwp_test_account', 'mailcwp_test_account_callback');
add_action('wp_ajax_mailcwp_login', 'mailcwp_login_callback');

add_action('wp_ajax_mailcwp_get_headers', 'mailcwp_get_headers_callback');
add_action('wp_ajax_mailcwp_get_message', 'mailcwp_get_message_callback');
add_action('wp_ajax_mailcwp_compose_message', 'mailcwp_compose_callback');
add_action('wp_ajax_mailcwp_send_message', 'mailcwp_send_callback');
add_action('wp_ajax_mailcwp_save_draft', 'mailcwp_save_draft_callback');
add_action('wp_ajax_mailcwp_delete_draft', 'mailcwp_delete_draft_callback');
add_action('wp_ajax_mailcwp_mark_read', 'mailcwp_mark_read_callback');
add_action('wp_ajax_mailcwp_mark_unread', 'mailcwp_mark_unread_callback');
add_action('wp_ajax_mailcwp_delete', 'mailcwp_delete_callback');
add_action('wp_ajax_mailcwp_set_page_size', 'mailcwp_set_page_size_callback');

add_action('wp_ajax_mailcwp_change_theme', 'mailcwp_change_theme_callback');
add_action('wp_ajax_mailcwp_compose_close', 'mailcwp_compose_close_callback');

add_action('wp_ajax_mailcwp_download_attachment', 'mailcwp_download_attachment_callback');
add_action('wp_ajax_mailcwp_options_dialog', 'mailcwp_options_dialog');
add_action('wp_ajax_mailcwp_get_account_folders', 'mailcwp_get_account_folders_callback');

add_action( 'show_user_profile', 'mailcwp_profile_fields' );
add_action( 'edit_user_profile', 'mailcwp_profile_fields' );

add_action( 'init', 'mailcwp_init' );

add_filter('mailcwp_options_dialog_tabs', 'mailcwp_account_options_tab', 10);
add_filter('mailcwp_options_dialog_content', 'mailcwp_account_options_content', 10);
add_filter('mailcwp_options_dialog_javascript', 'mailcwp_account_options_javascript', 10);

register_activation_hook( __FILE__, 'mailcwp_install' );
register_deactivation_hook( __FILE__, 'mailcwp_uninstall' );

function mailcwp_init() {
load_plugin_textdomain('mailcwp', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

function mailcwp_install() {
  $upload_dir = wp_upload_dir();
  mkdir("$upload_dir[basedir]/mailcwp/uploads", 0777, true); 
  mkdir("$upload_dir[basedir]/mailcwp/downloads", 0777, true); 
}

function mailcwp_uninstall() {
  /*$users = get_users();
  foreach ($users as $user) {
    delete_user_meta($user->ID, "mailcwp_contacts");
    delete_user_meta($user->ID, "mailcwp_session");
    delete_user_meta($user->ID, "mailcwp_accounts");
    delete_user_meta($user->ID, "mailcwp_options");
  }

  delete_option("mailcwp_settings");

  $upload_dir = wp_upload_dir();
  rmdir("$upload_dir[basedir]/mailcwp/uploads"); 
  rmdir("$upload_dir[basedir]/mailcwp/uploads"); 
  rmdir("$upload_dir[basedir]/mailcwp"); */
}

function mailcwp_profile_fields( $user ) {
  $options = get_option("mailcwp_settings", array());
  $user_can_manage_accounts = isset($options["user_manage_accounts"]) ? $options["user_manage_accounts"] : true;
//write_log("USER CAN MANAGE " . is_super_admin() . "/" . $user_can_manage_accounts);
  if (is_super_admin() || $user_can_manage_accounts) { 
    echo "<h3>Mail Accounts<a href=\"javascript:void(0);\" class=\"add-new-h2\" id=\"mailcwp-add-account\" data=\"$user->ID\">Add New</a></h3>";
    echo "<div id=\"account-profile-table\">";
    $account_table = new MailAdminTable($user->ID);
    $account_table->prepare_items();
    $account_table->display();
    echo "</div>";
    echo mailcwp_account_edit_dialog($user->ID);
  }
}

function mailcwp_get_session() {
  $current_user = wp_get_current_user();
  $mailcwp_session = get_user_meta($current_user->ID, "mailcwp_session", true);
  return $mailcwp_session;
}

function mailcwp_imap_connect($account, $options, $folder) {
//write_log("IMAP CONNECT " . print_r($account, true));
  $mbox_name = "$account[host]:$account[port]";
  $use_ssl = $account["use_ssl"];
  $validate_cert = $account["validate_cert"];
  $username = $account["username"];
  $password = $account["password"];
  $use_tls = $account["use_tls"];

  $use_ssl_flag = $use_ssl === "true" ? "/ssl" : "";
  $validate_cert_flag = $validate_cert === "true" ? "" : "/novalidate-cert";
  $use_tls_flag = $use_tls === "true" ? "/tls" : "";
  
//write_log("FLAGS " . $use_ssl_flag . " " . $validate_cert_flag);
  error_reporting(E_ERROR);
  $mbox = @imap_open("{" . $mbox_name . $use_ssl_flag . $validate_cert_flag . $use_tls_flag . "}$folder", $username, $password, $options);
  return $mbox;
}

function mailcwp_login_dialog($aAccountId) {
  $login_id = "mailcwp_login_" . $aAccountId;
  $username_id = "mailcwp_username_" . $aAccountId;
  $password_id = "mailcwp_password_" . $aAccountId;
  $result = 
  '<div id="login-dialog-form">' .
  '<form>' .
  '<fieldset>' .
  '  <label for="' . $username_id . '">' . __("Username", "mailcwp") . '</label>' .
  '  <input type="text" name="' . $username_id . '" id="' . $username_id . '" class="text ui-widget-content ui-corner-all" value="">' .
  '  <label for="' . $password_id . '">' . __("Password", "mailcwp") . '</label>' .
  '  <input type="password" name="' . $password_id . '" id="' . $password_id . '" class="text ui-widget-content ui-corner-all" value="">' .
  '</fieldset>' .
  '<button id="' . $login_id . '">' . __("Login", "mailcwp") . '</button>' .
  '</form>' .
  '</div>' .
  '<script type="text/javascript">' .
  '  jQuery("#' . $login_id . '").button({text: "Login"}).click(function(e) {login("' . $aAccountId . '", jQuery("#' . $username_id . '").val(), jQuery("#' . $password_id . '").val()); e.preventDefault();});' .
  '</script>';
  return $result;
}

function mailcwp_get_account_folders_callback() {
  $data = array();

  if (isset($_POST["account_id"])) {
    $account_id = $_POST["account_id"];
    $user_id = get_current_user_id();
    $user_mail_accounts = get_user_meta($user_id, "mailcwp_accounts", true);
    foreach($user_mail_accounts as $user_mail_account) {
      if ($user_mail_account["id"] == $account_id) {
	$data["account"] = $user_mail_account;
      }
    }
  } else {
    $mailcwp_session = mailcwp_get_session();
    $data["account"] = $mailcwp_session["account"];
  }

  if (isset($data["account"])) {
    $data["folders"] = mailcwp_get_account_folders($data["account"]);
  }
  $data["result"] = "OK";

  echo json_encode($data);
  die();
}

function mailcwp_get_account_folders($account) {
  $folders = array();
  if (!empty($account)) {
    $mbox = mailcwp_imap_connect($account, OP_HALFOPEN, "");

    $mbox_name = $account["host"] . ":" . $account["port"];
    $use_ssl_flag = $account["use_ssl"] === "true" ? "/ssl" : "";
    $folders["list"] = imap_listmailbox($mbox, "{" . $mbox_name . $use_ssl_flag . "}", "*");
    $count_folders = count($folders["list"]);
    for ($i = 0; $i < $count_folders; $i++) {
      $folders["list"][$i] = substr($folders["list"][$i], strpos($folders["list"][$i], "}") + 1);
    }
    $folders["auto_default_folder"] = mailcwp_find_folder($mbox, $account, "INBOX");
    $folders["auto_sent_folder"] = mailcwp_find_folder($mbox, $account, "Sent");
    $folders["auto_trash_folder"] = mailcwp_find_folder($mbox, $account, "Trash");
    $folders["auto_draft_folder"] = mailcwp_find_folder($mbox, $account, "Draft");

  }
  return $folders;
}

function mailcwp_account_edit_dialog($user_id, $account = null, $full_form = false) {
  $before_form = apply_filters("mailcwp_account_edit_before_form", "", !empty($account) ? $account["id"] : -1);
  $after_form = apply_filters("mailcwp_account_edit_after_form", "", !empty($account) ? $account["id"] : -1);
  if ($full_form) {
    if (isset($account)) {
      $account_folders = mailcwp_get_account_folders($account);
      $send_select = "<select id=\"mailcwp_sent_folder\" name=\"mailcwp_sent_folder\" class=\"text ui-widget-content ui-corner-all\"></select>";
    }
  }

  $options = get_option("mailcwp_settings", array());
  $store_passwords = isset($options["user_store_password"]) ? $options["user_store_password"] : true;

  $result = 
  '<div id="account-edit-form" style="display: none; border: 1px solid black; padding: 10px;">' .
  //'<form>' .
  '<input type="hidden" id="mailcwp_user_id" name="mailcwp_user_id" value="' . $user_id . '"/>' .
  '<input type="hidden" id="mailcwp_account_id" name="mailcwp_account_id" value="' . (isset($account) ? $account["id"] : "") . '"/>' .
  '<table>' .
  $before_form .
  '  <tr><td>Name</td>' .
  '  <td><input type="text" name="mailcwp_name" id="mailcwp_name" value="' . (isset($account) ? $account["name"] : "") . '"></td></tr>' .
  '  <tr><td>Host</td>' .
  '  <td><input type="text" name="mailcwp_host" id="mailcwp_host" value="' . (isset($account) ? $account["host"] : "") . '"></td></tr>' .
  '  <tr><td>Port</td>' .
  '  <td><input type="text" name="mailcwp_port" id="mailcwp_port" value="' . (isset($account) ? $account["port"] : "") . '"></td></tr>' .
  '  <tr><td>Use SSL</td>' .
  '  <td><input type="checkbox" name="mailcwp_use_ssl" id="mailcwp_use_ssl" ' . (isset($account) && $account["use_ssl"] === "true" ? " checked" : "") . '></td></tr>' .
  '  <tr><td>Validate Certificates</td>' .
  '  <td><input type="checkbox" name="mailcwp_validate_cert" id="mailcwp_validate_cert" ' . (isset($account) && $account["validate_cert"] === "true" ? " checked" : "") . '></td</tr>' .
  '  <tr><td>Email</td>' .
  '  <td><input type="text" name="mailcwp_email" id="mailcwp_email" value="' . (isset($account["email"]) ? $account["email"] : "") . '"></td></tr>' .
  '  <tr><td>Username</td>' .
  '  <td><input type="text" name="mailcwp_username" id="mailcwp_username" value="' . (isset($account) ? $account["username"] : "") . '"></td></tr>';
  if ($store_passwords) {
    $result .= '  <tr><td>Password</td>' .
    '  <td><input type="password" name="mailcwp_password" id="mailcwp_password" value="' . (isset($account) ? $account["password"] : "") . '"></td></tr>' .
    '  <tr><td>Confirm Password</td>' .
    '  <td><input type="password" name="mailcwp_confirm_password" id="mailcwp_confirm_password" value="' . (isset($account) ? $account["password"] : "") . '"></td></tr>' .
    '  <tr><td>Use start-TLS</td>' .
    '  <td><input type="checkbox" name="mailcwp_use_tls" id="mailcwp_use_tls" ' . (isset($account) && $account["use_tls"] === "true" ? " checked" : "") . '></td></tr>';
  }
  $timezone_options = "";
  $timezones = timezone_identifiers_list();
  foreach ($timezones as $timezone) {
    $timezone_options .= " <option " . (isset($account) && $account["timezone"] == $timezone ? "checked" : "") . ">$timezone</option>";
  }
  $result .= '  <tr><td>Timezone</td>' .
  '  <td><select name="mailcwp_timezone" id="mailcwp_timezone">' .
  $timezone_options .
  '  </select></td></tr>' .
  '  <tr><td>SMTP Host</td>' .
  '  <td><input type="text" name="mailcwp_smtp_host" id="mailcwp_smtp_host" value="' . (isset($account) ? $account["smtp_host"] : "") . '"></td></tr>' .
  '  <tr><td>SMTP Port</td>' .
  '  <td><input type="text" name="mailcwp_smtp_port" id="mailcwp_smtp_port" value="' . (isset($account) ? $account["smtp_port"] : "") . '"></td></tr>' .
  '  <tr><td>SMTP server requires authenatication</td>' .
  '  <td><input type="checkbox" name="mailcwp_smtp_auth" id="mailcwp_smtp_auth" ' . (isset($account) && $account["smtp_auth"] === "true" ? " checked" : "") . '></td></tr>' .
  '  <tr><td>SMTP Username</td>' .
  '  <td><input type="text" name="mailcwp_smtp_username" id="mailcwp_smtp_username" value="' . (isset($account) ? $account["smtp_username"] : "") . '"></td></tr>';
  if ($store_passwords) {
    $result .= '  <tr><td>SMTP Password</td>' .
    '  <td><input type="password" name="mailcwp_smtp_password" id="mailcwp_smtp_password" value="' . (isset($account) ? $account["smtp_password"] : "") . '"></td></tr>';
  }
  $result .= '  <tr><td>Sound alert when new mail arrives</td><td><input type="checkbox" name="mailcwp_alert_sound" id="mailcwp_alert_sound" ' . (isset($account) && $account["alert_sound"] === "true" ? " checked" : "") . '></td></tr>';
  if (isset($account) && $full_form) {
    $result .= '  <tr><td>Sent Folder</td><td>' .
    $send_select .
    '  </td></tr><tr><td>Trash Folder</td><td>' .
    $trash_select .
    '  </td></tr><tr><td>Draft  Folder</td><td>' .
    $draft_select;
  }
  $result .= '</td></tr><tr><td colspan="2">';
  $result .= $after_form;
  $result .= '</td></tr><tr><td colspan="2"><input type="button" class="button button-primary" name="Test" value="Test" id="account-edit-test"/>&nbsp;<input type="button" class="button button-primary" name="Save" value="Save" id="account-edit-save" onclick="submitAccountEditForm(); return false;"/>&nbsp;<input type="button" class="button button-primary" name="Cancel" value="Cancel" id="account-edit-cancel" onclick="jQuery(\'#account-edit-form\').hide(); jQuery(\'#account-profile-table\').show();"/></td></tr>' .
  //'</form>' .
  '</table>' .
  '</div>';
  return $result;
}

function mailcwp_options_dialog() {
  $html = 
  '<div id="dialog-form-options" title="' . __("Options", "mailcwp") . '">' .
  '  <div id="options-tabs">' .
  '    <ul>' .
  apply_filters("mailcwp_options_dialog_tabs", "") .
  '    </ul>' .
  apply_filters("mailcwp_options_dialog_content", "") .
  '  </div>' .
  '</div>';

  $javascript = "jQuery(\"#options-tabs\").tabs(); jQuery(\"#dialog-form-options\").dialog({ width: 480, maxHeight: 520, modal: false, close: function() {jQuery(\"#dialog-form-options\").remove();}, buttons: {\"Close\": function() {jQuery(\"#dialog-form-options\").dialog(\"close\"); document.location.reload();}}});";
  $javascript = apply_filters("mailcwp_options_dialog_javascript", $javascript);
  $result = array("result" => "OK", "html" => $html, "javascript" => $javascript);
  echo json_encode($result);
  die();
}

function mailcwp_account_options_tab($text) {
  $text .= "<li><a href=\"#accounts\">" . __("Accounts", "mailcwp") . "</a></li>";
  $text .= "<li><a href=\"#folders\">" . __("Folders", "mailcwp") . "</a></li>";
  return $text;
}

function mailcwp_account_options_content($text) {
  $current_user = wp_get_current_user();
  $accounts = get_user_meta($current_user->ID, "mailcwp_accounts", true);

  $options = get_option("mailcwp_settings", array());
  $user_can_manage_accounts = isset($options["user_manage_accounts"]) ? $options["user_manage_accounts"] : true;

  $text .= "<div id=\"accounts\">";
  $text .= "  <p><select id=\"account_menu\" class=\"ui-widget ui-widget-content\">";
  $text .= "  <option value=\"\">" . __("Select account", "mailcwp") . "</option>";
  if (is_array($accounts) && count($accounts) > 0) {
    foreach ($accounts as $account) {
      if (!empty($account["id"])) {
        $text .= "    <option value=\"$account[id]\">$account[name]</option>";
      }
    }
  }
  $text .= "  </select><p/>";
  $text .= "  <div id=\"account_detail\">";
  $text .= "<p>";
  $text .= "  <button id=\"mailcwp_account_test\">Test</button>";
  $text .= "  <button id=\"mailcwp_account_save\" disabled>Save</button>";
  if ($user_can_manage_accounts) {
    $text .= "  <button id=\"mailcwp_account_add\">Add</button>";
    $text .= "  <button id=\"mailcwp_account_remove\" disabled>Remove</button>";
  }
  $text .= "</p>";
  $text .= "    <input type=\"hidden\" name=\"mailcwp_account_id\" id=\"mailcwp_account_id\"/>";
  if ($user_can_manage_accounts) {
    $style = "";
  } else {
    $style = "display: none;";
  }
  $text .= "    <p><span style=\"$style\">Name: <input type=\"text\" name=\"mailcwp_account_name\" id=\"mailcwp_account_name\"/></span>";
  $text .= "    <span style=\"$style\">Email: <input type=\"text\" name=\"mailcwp_account_email\" id=\"mailcwp_account_email\"/></span>";
  $text .= "    <span style=\"$style\">Host: <input type=\"text\" name=\"mailcwp_account_host\" id=\"mailcwp_account_host\"/></span>";
  $text .= "    <span style=\"$style\">Port: <input type=\"text\" name=\"mailcwp_account_port\" id=\"mailcwp_account_port\"/></span>";
  $text .= "    <span style=\"$style\">Use SSL: <input type=\"checkbox\" name=\"mailcwp_account_use_ssl\" id=\"mailcwp_account_use_ssl\"/></span>";
  $text .= "    <span style=\"$style\">Validate&nbsp;Certificates: <input type=\"checkbox\" name=\"mailcwp_account_validate_cert\" id=\"mailcwp_account_validate_cert\"/></span><p/>";
  $text .= "    <span>Username: <input type=\"text\" name=\"mailcwp_account_username\" id=\"mailcwp_account_username\"/></span>";
  $text .= "    <span>Password: <input type=\"password\" name=\"mailcwp_account_password\" id=\"mailcwp_account_password\"/></span>";
  $text .= "    <span style=\"$style\">Use start-TLS: <input type=\"checkbox\" name=\"mailcwp_account_use_tls\" id=\"mailcwp_account_use_tls\"/></span></p>";
  $text .= "    <span style=\"$style\">Timezone <select name=\"mailcwp_account_timezone\" id=\"mailcwp_account_timezone\">";
  $timezones = timezone_identifiers_list();
  foreach ($timezones as $timezone) {
    $text .= " <option>$timezone</option>";
  }
  $text .= "</select></span>";
  $text .= "    <span style=\"$style\">SMTP Host: <input type=\"text\" name=\"mailcwp_account_smtp_host\" id=\"mailcwp_account_smtp_host\"/></span>";
  $text .= "    <span style=\"$style\">SMTP Port: <input type=\"text\" name=\"mailcwp_account_smtp_port\" id=\"mailcwp_account_smtp_port\"/></span>";
  $text .= "    <span style=\"$style\">SMTP server requires authentication: <input type=\"checkbox\" name=\"mailcwp_account_smtp_auth\" id=\"mailcwp_account_smtp_auth\"/></span></p>";
  $text .= "    <span>SMTP Username: <input type=\"text\" name=\"mailcwp_account_smtp_username\" id=\"mailcwp_account_smtp_username\"/></span>";
  $text .= "    <span>SMTP Password: <input type=\"password\" name=\"mailcwp_account_smtp_password\" id=\"mailcwp_account_smtp_password\"/></span>";
  $text .= "    <span style=\"$style\">Sound alert when new mail arrives: <input type=\"checkbox\" name=\"mailcwp_account_alert_sound\" id=\"mailcwp_account_alert_sound\"/></span></p>";
  $text .= "    <span style=\"$style\">Default account: <input type=\"checkbox\" name=\"mailcwp_account_default\" id=\"mailcwp_account_default\"/></span></p>";
  $text .= "  </div>";
  $text .= "</div>";

  $text .= "<div id=\"folders\">";
  $text .= "  <p><select id=\"folder_account_menu\" class=\"ui-widget ui-widget-content\">";
  $text .= "  <option value=\"\">" . __("Select account", "mailcwp") . "</option>";
  if (is_array($accounts) && count($accounts) > 0) {
    foreach ($accounts as $account) {
      if (!empty($account["id"])) {
        $text .= "    <option value=\"$account[id]\">$account[name]</option>";
      }
    }
  }
  $text .= "  </select><p/>";
  $text .= "  <input type=\"hidden\" id=\"mailcwp_folder_account_id\">";
  $text .= "  <p><button id=\"mailcwp_folder_save\" disabled>" . __("Save", "mailcwp") . "</button></p>";
  $text .= "  <p>Default Folder: <select id=\"mailcwp_options_default_folder\" class=\"ui-widget ui-widget-content\"></select></p>";
  $text .= "  <p>Sent Folder: <select id=\"mailcwp_options_sent_folder\" class=\"ui-widget ui-widget-content\"></select></p>";
  $text .= "  <p>Trash Folder: <select id=\"mailcwp_options_trash_folder\" class=\"ui-widget ui-widget-content\"></select></p>";
  $text .= "  <p>Draft Folder: <select id=\"mailcwp_options_draft_folder\" class=\"ui-widget ui-widget-content\"></select></p>";
  $text .= "</div>";
  return $text;
}

function mailcwp_account_options_javascript($text) {
  $text .= "jQuery(\"#mailcwp_account_test\").button().click(function () {";
  $text .= "  jQuery.ajax({";
  $text .= "    type: \"POST\",";
  $text .= "    url: ajax_object.ajax_url,";
  $text .= "    data: {";
  $text .= "      action: \"mailcwp_test_account\",";
  $text .= "      mailcwp_name: jQuery(\"#mailcwp_account_name\").val(),";
  $text .= "      mailcwp_host: jQuery(\"#mailcwp_account_host\").val(),";
  $text .= "      mailcwp_port: jQuery(\"#mailcwp_account_port\").val(),";
  $text .= "      mailcwp_use_ssl: jQuery(\"#mailcwp_account_use_ssl\").prop(\"checked\"),";
  $text .= "      mailcwp_validate_cert: jQuery(\"#mailcwp_account_validate_cert\").prop(\"checked\"),";
  $text .= "      mailcwp_email: jQuery(\"#mailcwp_account_email\").val(),";
  $text .= "      mailcwp_username: jQuery(\"#mailcwp_account_username\").val(),";
  $text .= "      mailcwp_password: jQuery(\"#mailcwp_account_password\").val(),";
  $text .= "      mailcwp_use_tls: jQuery(\"#mailcwp_account_use_tls\").prop(\"checked\"),";
  $text .= "    },";
  $text .= "    success: function(aData) {";
  $text .= "      try {";
  $text .= "        vData = jQuery.parseJSON(aData);";
  $text .= "        if (vData.hasOwnProperty(\"result\") && vData.result == \"OK\") {";
  $text .= "          display_notice(\"Test Succeeded\");";
  $text .= "        } else {";
  $text .= "          if (vData.hasOwnProperty(\"message\")) {";
  $text .= "            display_notice(\"Test Failed: \" + vData.message);";
  $text .= "          } else {";
  $text .= "            display_notice(\"Test Failed: Unknown error\");";
  $text .= "          }";
  $text .= "        }";
  $text .= "      } catch (e) {";
  $text .= "        display_notice(\"Error \" + e.message);";
  $text .= "      }";
  $text .= "    },";
  $text .= "    error: function(aObject, aTextStatus, aErrorThrown) {";
  $text .= "      display_notice(\"Error: \" + aTextStatus + \"/\" + aErrorThrown);";
  $text .= "    },";
  $text .= "  });";
  $text .= "});";
  $text .= "jQuery(\"#mailcwp_account_save\").button().click(function () {";
  $text .= "  saveAccount(jQuery(\"#mailcwp_account_id\").val());";
  $text .= "});";
  $text .= "jQuery(\"#mailcwp_account_add\").button().click(function () {";
  $text .= "  saveAccount(null);";
  $text .= "});";
  $text .= "function saveAccount(aAccountId) {";
  $text .= "  if (jQuery(\"#mailcwp_account_name\").val() == '' ||";
  $text .= "      jQuery(\"#mailcwp_account_email\").val() == '' ||";
  $text .= "      jQuery(\"#mailcwp_account_host\").val() == '' ||";
  $text .= "      jQuery(\"#mailcwp_account_username\").val() == '') {";
  $text .= "    display_notice(\"Please enter all details before saving.\");";
  $text .= "  } else {";
  $text .= "    jQuery.ajax({";
  $text .= "      type: \"POST\",";
  $text .= "      url: ajax_object.ajax_url,";
  $text .= "      data: {";
  $text .= "        action: \"mailcwp_edit_account\",";
  $text .= "        mailcwp_user_id: " . get_current_user_id() . ",";
  $text .= "        mailcwp_account_id: aAccountId,";
  $text .= "        mailcwp_name: jQuery(\"#mailcwp_account_name\").val(),";
  $text .= "        mailcwp_host: jQuery(\"#mailcwp_account_host\").val(),";
  $text .= "        mailcwp_port: jQuery(\"#mailcwp_account_port\").val(),";
  $text .= "        mailcwp_use_ssl: jQuery(\"#mailcwp_account_use_ssl\").prop(\"checked\"),";
  $text .= "        mailcwp_validate_cert: jQuery(\"#mailcwp_account_validate_cert\").prop(\"checked\"),";
  $text .= "        mailcwp_email: jQuery(\"#mailcwp_account_email\").val(),";
  $text .= "        mailcwp_username: jQuery(\"#mailcwp_account_username\").val(),";
  $text .= "        mailcwp_password: jQuery(\"#mailcwp_account_password\").val(),";
  $text .= "        mailcwp_use_tls: jQuery(\"#mailcwp_account_use_tls\").prop(\"checked\"),";
  $text .= "        mailcwp_timezone: jQuery(\"#mailcwp_account_timezone\").val(),";
  $text .= "        mailcwp_smtp_host: jQuery(\"#mailcwp_account_smtp_host\").val(),";
  $text .= "        mailcwp_smtp_port: jQuery(\"#mailcwp_account_smtp_port\").val(),";
  $text .= "        mailcwp_smtp_username: jQuery(\"#mailcwp_account_smtp_username\").val(),";
  $text .= "        mailcwp_smtp_password: jQuery(\"#mailcwp_account_smtp_password\").val(),";
  $text .= "        mailcwp_smtp_auth: jQuery(\"#mailcwp_account_smtp_auth\").prop(\"checked\"),";
  $text .= "        mailcwp_alert_sound: jQuery(\"#mailcwp_account_alert_sound\").prop(\"checked\"),";
  $text .= "        mailcwp_account_default: jQuery(\"#mailcwp_account_default\").prop(\"checked\"),";
  $text .= "      },";
  $text .= "      success: function(aData) {";
  //$text .= "         jQuery(\"#remove_account_dialog_\" + aAccountId).dialog(\"close\");";
  $text .= "         document.location.reload();";
  /*$text .= "        try {";
  $text .= "          vData = jQuery.parseJSON(aData);";
  $text .= "          if (vData.hasOwnProperty(\"result\") && vData.result == \"OK\") {";
  $text .= "            display_notice(\"Changes saved\");";
  $text .= "            if (aAccountId == \"\") {";
  $text .= "              jQuery(\"#account_menu\").html(jQuery(\"#account_menu\").html() + \"<option value='\" + vData.result.account_id + \"'>\" + jQuery(\"#mailcwp_account_name\").val() + \"</option>\");";
  $text .= "            }";
  $text .= "            jQuery(\"#account_menu\").val(\"\");";
  $text .= "            jQuery(\"#mailcwp_account_id\").val(\"\");";
  $text .= "            jQuery(\"#mailcwp_account_name\").val(\"\");";
  $text .= "            jQuery(\"#mailcwp_account_email\").val(\"\");";
  $text .= "            jQuery(\"#mailcwp_account_host\").val(\"\");";
  $text .= "            jQuery(\"#mailcwp_account_port\").val(\"\");";
  $text .= "            jQuery(\"#mailcwp_account_use_ssl\").prop(\"checked\", false);";
  $text .= "            jQuery(\"#mailcwp_account_validate_cert\").prop(\"checked\", false);";
  $text .= "            jQuery(\"#mailcwp_account_username\").val(\"\");";
  $text .= "            jQuery(\"#mailcwp_account_password\").val(\"\");";
  $text .= "            jQuery(\"#mailcwp_account_use_tls\").prop(\"checked\", false);";
  //$text .= "            jQuery(\"#mailcwp_account_test\").button(\"disable\");";
  $text .= "            jQuery(\"#mailcwp_account_save\").button(\"disable\");";
  $text .= "            jQuery(\"#mailcwp_account_remove\").button(\"disable\");";
  $text .= "          } else {";
  $text .= "            if (vData.hasOwnProperty(\"message\")) {";
  $text .= "              display_notice(\"Save Failed: \" + vData.message);";
  $text .= "            } else {";
  $text .= "              display_notice(\"Save Failed: Unknown error\");";
  $text .= "            }";
  $text .= "          }";
  $text .= "        } catch (e) {";
  $text .= "          display_notice(\"Error \" + e.message);";
  $text .= "        }";*/
  $text .= "      },";
  $text .= "      error: function(aObject, aTextStatus, aErrorThrown) {";
  $text .= "        display_notice(\"Error: \" + aTextStatus + \"/\" + aErrorThrown);";
  $text .= "      },";
  $text .= "    });";
  $text .= "  }";
  $text .= "}";
  $text .= "jQuery(\"#mailcwp_account_remove\").button().click(function () {";
  $text .= "  removeAccount(jQuery(\"#mailcwp_account_id\").val(), jQuery(\"#mailcwp_account_name\").val());";
  $text .= "});";
  $text .= "jQuery(\"#account_menu\").change(function () {";
  $text .= "  account_id = jQuery(\"#account_menu\").val();";
  $text .= "  jQuery.ajax({";
  $text .= "    type: \"POST\",";
  $text .= "    url: ajax_object.ajax_url,";
  $text .= "    data: {";
  $text .= "      action: \"mailcwp_get_account\",";
  $text .= "      account_id: account_id,";
  $text .= "    },";
  $text .= "    success: function(aData) {";
  $text .= "      vData = jQuery.parseJSON(aData);";
  $text .= "      if (vData.hasOwnProperty(\"result\") && vData.result == \"OK\") {";
  $text .= "        jQuery(\"#mailcwp_account_id\").val(vData.account.id);";
  $text .= "        jQuery(\"#mailcwp_account_name\").val(vData.account.name);";
  $text .= "        jQuery(\"#mailcwp_account_email\").val(vData.account.email);";
  $text .= "        jQuery(\"#mailcwp_account_host\").val(vData.account.host);";
  $text .= "        jQuery(\"#mailcwp_account_port\").val(vData.account.port);";
  $text .= "        jQuery(\"#mailcwp_account_use_ssl\").prop(\"checked\", vData.account.use_ssl === \"true\");";
  $text .= "        jQuery(\"#mailcwp_account_validate_cert\").prop(\"checked\", vData.account.validate_cert === \"true\");";
  $text .= "        jQuery(\"#mailcwp_account_username\").val(vData.account.username);";
  $text .= "        jQuery(\"#mailcwp_account_password\").val(vData.account.password);";
  $text .= "        jQuery(\"#mailcwp_account_use_tls\").prop(\"checked\", vData.account.use_tls === \"true\");";
  $text .= "        jQuery(\"#mailcwp_account_timezone\").val(vData.account.timezone),";
  $text .= "        jQuery(\"#mailcwp_account_smtp_host\").val(vData.account.smtp_host),";
  $text .= "        jQuery(\"#mailcwp_account_smtp_port\").val(vData.account.smtp_port),";
  $text .= "        jQuery(\"#mailcwp_account_smtp_username\").val(vData.account.smtp_username),";
  $text .= "        jQuery(\"#mailcwp_account_smtp_password\").val(vData.account.smtp_password),";
  $text .= "        jQuery(\"#mailcwp_account_smtp_auth\").prop(\"checked\", vData.account.smtp_auth === \"true\"),";
  $text .= "        jQuery(\"#mailcwp_account_alert_sound\").prop(\"checked\", vData.account.alert_sound === \"true\"),";
  $text .= "        jQuery(\"#mailcwp_account_default\").prop(\"checked\", vData.account.default === \"true\"),";
  //$text .= "        jQuery(\"#mailcwp_account_test\").button(\"enable\");";
  $text .= "        jQuery(\"#mailcwp_account_save\").button(\"enable\");";
  $text .= "        jQuery(\"#mailcwp_account_remove\").button(\"enable\");";
  $text .= "      } else {";
  $text .= "        processAjaxResponse(aData);";
  $text .= "      }";
  $text .= "    },";
  $text .= "    error: function(aObject, aTextStatus, aErrorThrown) {";
  $text .= "      display_notice(\"Error: \" + aTextStatus + \"/\" + aErrorThrown);";
  $text .= "    }";
  $text .= "  });";
  $text .= "});";
  $text .= "jQuery(\"#folder_account_menu\").change(function () {";
  $text .= "  account_id = jQuery(\"#folder_account_menu\").val();";
  $text .= "  if (account_id != \"\") {";
  $text .= "    jQuery.ajax({";
  $text .= "      type: \"POST\",";
  $text .= "      url: ajax_object.ajax_url,";
  $text .= "      data: {";
  $text .= "        action: \"mailcwp_get_account_folders\",";
  $text .= "        account_id: account_id,";
  $text .= "      },";
  $text .= "      success: function(aData) {";
  $text .= "        vData = jQuery.parseJSON(aData);";
  $text .= "        if (vData.hasOwnProperty(\"result\") && vData.result == \"OK\") {";
  $text .= "          jQuery(\"#mailcwp_folder_account_id\").val(vData.account.id);";
  $text .= "          vOptions = \"\";";
  $text .= "          vData.folders.list.forEach(function(aElement) {";
  $text .= "            vOptions += \"<option value='\" + aElement + \"'>\" + aElement + \"</option>\";";
  $text .= "          });";
  $text .= "          jQuery(\"#mailcwp_options_default_folder\").html(vOptions);"; 
  $text .= "          jQuery(\"#mailcwp_options_default_folder\").val(vData.account.default_folder == '' ? vData.folders.auto_default_folder : vData.account.default_folder);";
  $text .= "          jQuery(\"#mailcwp_options_sent_folder\").html(vOptions);"; 
  $text .= "          jQuery(\"#mailcwp_options_sent_folder\").val(vData.account.sent_folder == '' ? vData.folders.auto_sent_folder : vData.account.sent_folder);";
  $text .= "          jQuery(\"#mailcwp_options_trash_folder\").html(vOptions);"; 
  $text .= "          jQuery(\"#mailcwp_options_trash_folder\").val(vData.account.trash_folder == '' ? vData.folders.auto_trash_folder : vData.account.trash_folder);";
  $text .= "          jQuery(\"#mailcwp_options_draft_folder\").html(vOptions);"; 
  $text .= "          jQuery(\"#mailcwp_options_draft_folder\").val(vData.account.draft_folder == '' ? vData.folders.auto_draft_folder : vData.account.draft_folder);";
  $text .= "          jQuery(\"#mailcwp_folder_save\").button(\"enable\");";
  $text .= "        }";
  $text .= "      }";
  $text .= "    });";
  $text .= "  } else {";
  $text .= "    jQuery(\"#mailcwp_options_default_folder\").html(\"\");"; 
  $text .= "    jQuery(\"#mailcwp_options_sent_folder\").html(\"\");"; 
  $text .= "    jQuery(\"#mailcwp_options_trash_folder\").html(\"\");"; 
  $text .= "    jQuery(\"#mailcwp_options_draft_folder\").html(\"\");"; 
  $text .= "    jQuery(\"#mailcwp_folder_save\").button(\"disable\");";
  $text .= "  }";
  $text .= "});";
  $text .= "jQuery(\"#mailcwp_folder_save\").button().click(function () {";
  $text .= "  jQuery.ajax({";
  $text .= "    type: \"POST\",";
  $text .= "    url: ajax_object.ajax_url,";
  $text .= "    data: {";
  $text .= "      action: \"mailcwp_edit_account\",";
  $text .= "      mailcwp_user_id: " . get_current_user_id() . ",";
  $text .= "      mailcwp_account_id: jQuery(\"#mailcwp_folder_account_id\").val(),";
  $text .= "      mailcwp_default_folder: jQuery(\"#mailcwp_options_default_folder\").val(),";
  $text .= "      mailcwp_sent_folder: jQuery(\"#mailcwp_options_sent_folder\").val(),";
  $text .= "      mailcwp_draft_folder: jQuery(\"#mailcwp_options_draft_folder\").val(),";
  $text .= "      mailcwp_trash_folder: jQuery(\"#mailcwp_options_trash_folder\").val(),";
  $text .= "    },";
  $text .= "    success: function(aData) {";
  $text .= "      try {";
  $text .= "        vData = jQuery.parseJSON(aData);";
  $text .= "        if (vData.hasOwnProperty(\"result\") && vData.result == \"OK\") {";
  $text .= "          display_notice(\"Changes saved\");";
  $text .= "          jQuery(\"#folder_account_menu\").val(\"\");";
  $text .= "          jQuery(\"#mailcwp_options_default_folder\").html(\"\");"; 
  $text .= "          jQuery(\"#mailcwp_options_sent_folder\").html(\"\");"; 
  $text .= "          jQuery(\"#mailcwp_options_trash_folder\").html(\"\");"; 
  $text .= "          jQuery(\"#mailcwp_options_draft_folder\").html(\"\");"; 
  $text .= "          jQuery(\"#mailcwp_folder_account_id\").val(\"\");";
  $text .= "          jQuery(\"#mailcwp_folder_save\").button(\"disable\");";
  $text .= "        } else {";
  $text .= "          if (vData.hasOwnProperty(\"message\")) {";
  $text .= "            display_notice(\"Save Failed: \" + vData.message);";
  $text .= "          } else {";
  $text .= "            display_notice(\"Save Failed: Unknown error\");";
  $text .= "          }";
  $text .= "        }";
  $text .= "      } catch (e) {";
  $text .= "        display_notice(\"Error \" + e.message);";
  $text .= "      }";
  $text .= "    },";
  $text .= "    error: function(aObject, aTextStatus, aErrorThrown) {";
  $text .= "      display_notice(\"Error: \" + aTextStatus + \"/\" + aErrorThrown);";
  $text .= "    },";
  $text .= "  });";
  $text .= "});";
  return $text;
}

add_shortcode('mailcwp', 'mailcwp_handler');

function mailcwp_admin_menu() {
  add_menu_page("MailCWP Settings", "MailCWP", "manage_options", "mailcwp-admin-menu", "mailcwp_admin_settings", "dashicons-email");

  
}

function mailcwp_admin_settings() {
  if ( !current_user_can( 'manage_options' ) )  {
    wp_die( __( 'You do not have sufficient permissions to access this page.' , "mailcwp") );
  }

  $options = get_option("mailcwp_settings", array());
  if( isset($_POST[ "mailcwp_admin_submit" ]) && $_POST[ "mailcwp_admin_submit" ] == 'Y' ) {
    $user_can_manage_accounts = isset($_POST["user_can_manage_accounts"]);
    $hide_admin_bar = isset($_POST["hide_admin_bar"]);
    $hide_page_title = isset($_POST["hide_page_title"]);
    $user_can_change_theme = isset($_POST["user_can_change_theme"]);
    $default_theme = $_POST["default_theme"];
    $user_can_store_password = isset($_POST["user_can_store_password"]);
    $max_contacts = $_POST["max_contacts"];
    $imap_open_timeout = $_POST["imap_open_timeout"];
    $imap_read_timeout = $_POST["imap_read_timeout"];
    $imap_write_timeout = $_POST["imap_write_timeout"];
    $imap_close_timeout = $_POST["imap_close_timeout"];
    $smtp_connect_timeout = $_POST["smtp_connect_timeout"];
    $auto_refresh = isset($_POST["auto_refresh"]);
    $refresh_interval = $_POST["refresh_interval"];

    $options["hide_admin_bar"] = $hide_admin_bar;
    $options["hide_page_title"] = $hide_page_title;
    $options["user_manage_accounts"] = $user_can_manage_accounts;
    $options["user_change_theme"] = $user_can_change_theme;
    $options["default_theme"] = $default_theme;
    $options["user_store_password"] = $user_can_store_password;
    $options["max_contacts"] = $max_contacts;
    $options["imap_open_timeout"] = $imap_open_timeout;
    $options["imap_read_timeout"] = $imap_read_timeout;
    $options["imap_write_timeout"] = $imap_write_timeout;
    $options["imap_close_timeout"] = $imap_close_timeout;
    $options["smtp_connect_timeout"] = $smtp_connect_timeout;
    $options["refresh_interval"] = $refresh_interval;
    $options["auto_refresh"] = $auto_refresh;

    update_option("mailcwp_settings", $options);

    imap_timeout($imap_open_timeout, IMAP_OPENTIMEOUT);
    imap_timeout($imap_read_timeout, IMAP_READTIMEOUT);
    imap_timeout($imap_write_timeout, IMAP_WRITETIMEOUT);
    imap_timeout($imap_close_timeout, IMAP_CLOSETIMEOUT);

    echo "<div class=\"updated\"><p><strong>" . __("Settings saved.", "mailcwp") . "</strong></p></div>";
  } else {
    $imap_open_timeout = isset($options["imap_open_timeout"]) ? $options["imap_open_timeout"] : 15;
    $imap_read_timeout = isset($options["imap_read_timeout"]) ? $options["imap_read_timeout"] : 15;
    $imap_write_timeout = isset($options["imap_write_timeout"]) ? $options["imap_write_timeout"] : 15;
    $imap_close_timeout = isset($options["imap_close_timeout"]) ? $options["imap_close_timeout"] : 15;
    $smtp_connect_timeout = isset($options["smtp_connect_timeout"]) ? $options["smtp_connect_timeout"] : 10;
    $auto_refresh = isset($options["auto_refresh"]) ? $options["auto_refresh"] : false;
    $refresh_interval = isset($options["refresh_interval"]) ? $options["refresh_interval"] : 10;
    $hide_admin_bar = isset($options["hide_admin_bar"]) ? $options["hide_admin_bar"] : true;
    $hide_page_title = isset($options["hide_page_title"]) ? $options["hide_page_title"] : true;
    $user_can_manage_accounts = isset($options["user_manage_accounts"]) ? $options["user_manage_accounts"] : true;
    $user_can_change_theme = isset($options["user_change_theme"]) ? $options["user_change_theme"] : true;
    $default_theme = isset($options["default_theme"]) ? $options["default_theme"] : "pepper-grinder";
    $user_can_store_password = isset($options["user_store_password"]) ? $options["user_store_password"] : true;
    $max_contacts = isset($options["max_contacts"]) ? $options["max_contacts"] : 100;
  }

  echo "<h2>MailCWP " . __("Settings", "mailcwp") . "</h2>";

  echo "<form name=\"mailcwp_settings_form\" method=\"post\" action=\"\">"; 
  $plugin_base_path = plugin_dir_path(__FILE__) . "../";
  $address_book_installed = file_exists($plugin_base_path . "mailcwp-address-book");
  $advanced_search_installed = file_exists($plugin_base_path . "mailcwp-advanced-search");
  $folders_installed = file_exists($plugin_base_path . "mailcwp-folders");
  $composer_installed = file_exists($plugin_base_path . "mailcwp-composer");
  $signatures_installed = file_exists($plugin_base_path . "mailcwp-signatures");
  if (!$address_book_installed || !$advanced_search_installed || !$folders_installed || !$composer_installed || !$signatures_installed) { 
    echo "  <h3>" . __("MailCWP AddOns", "mailcwp") . "</h3>";
    if (!$address_book_installed) {
      echo "<p><strong><a href=\"\">MailCWP Address Book</a></strong> - a full contact address book for your mail users.</p>";
    }
    if (!$advanced_search_installed) {
      echo "<p><strong><a href=\"\">MailCWP Advanced Search</a></strong> - Allow mail users to find mail messages using a wide variety of seach criteria</p>";
    }
    if (!$folders_installed) {
      echo "<p><strong><a href=\"\">MailCWP Folders</a></strong> - Create, remove and rename folders. Copy and move messages between folders.</p>";
    }
    if (!$composer_installed) {
      echo "<p><strong><a href=\"\">MailCWP Composer</a></strong> - A WYSIWYG editor for MailCWP.</p>";
    }
    if (!$signatures_installed) {
      echo "<p><strong><a href=\"\">MailCWP Signatures</a></strong> - Add signatures to every email message.</p>";
    }
  }
  echo "  <input type=\"hidden\" name=\"mailcwp_admin_submit\" value=\"Y\">";
  echo "  <h3>" . __("Display Options", "mailcwp") . "</h3>";
  echo "  <table class=\"form-table\"><tbody>";
  echo "  <tr><th>" . __("Hide WordPress Admin Bar", "mailcwp") . "</th><td> <input type=\"checkbox\" name=\"hide_admin_bar\"" . ($hide_admin_bar ? " checked" : "") . "></td></tr>";
  echo "  <tr><th>" . __("Hide Page Title", "mailcwp") . "</th><td> <input type=\"checkbox\" name=\"hide_page_title\"" . ($hide_page_title ? " checked" : "") . "></td</tr>";
  echo "  </tbody></table><h3>" . __("User Options", "mailcwp") . "</h3>";
  echo "  <table class=\"form-table\"><tbody><tr><th>" . __("Allow user to manage accounts", "mailcwp") . "</th><td> <input type=\"checkbox\" name=\"user_can_manage_accounts\"" . ($user_can_manage_accounts ? " checked" : "") . "></td></tr>";
  echo "  <tr><th>" . __("Allow user to change theme", "mailcwp") . "</th><td> <input type=\"checkbox\" name=\"user_can_change_theme\"" . ($user_can_change_theme ? " checked" : "") . "></td></tr>";
  echo "  <tr><th>" . __("Default theme", "mailcwp") . "</th><td> <select name=\"default_theme\" id=\"default_theme\"></select></td></tr>";
  echo "  <tr><th>" . __("Store passwords:", "mailcwp") . "</th><td> <input type=\"checkbox\" name=\"user_can_store_password\"" . ($user_can_store_password ? " checked" : "") . "></td></tr>";
  echo "  <tr><th>" . __("Maximum drop-down contacts", "mailcwp") . "</th><td> <select name=\"max_contacts\">";
  for ($i = 100; $i <= 1000; $i += 100) {
    echo "    <option value=\"$i\"" . ($i == $max_contacts ? " selected" : "") . ">$i</option>";
  }
  echo "  </select></td></tr>";
  echo "";
  echo "  </tbody></table><h3>IMAP " . __("Timeouts", "mailcwp") . "</h3>";
  echo "  <table class=\"form-table\"><tbody><tr><th>" . __("Open", "mailcwp") . "</th><td> <input type=\"text\" size=\"3\" name=\"imap_open_timeout\" value=\"$imap_open_timeout\">&nbsp;sec</td></tr>";
  echo "  <tr><th>" . __("Read", "mailcwp") . "</th><td> <input type=\"text\" size=\"3\" name=\"imap_read_timeout\" value=\"$imap_read_timeout\">&nbsp;sec</td></tr>";
  echo "  <tr><th>" . __("Write", "mailcwp") . "</th><td> <input type=\"text\" size=\"3\" name=\"imap_write_timeout\" value=\"$imap_write_timeout\">&nbsp;sec</td></tr>";
  echo "  <tr><th>" . __("Close", "mailcwp") . "</th><td> <input type=\"text\" size=\"3\" name=\"imap_close_timeout\" value=\"$imap_close_timeout\">&nbsp;sec</td></tr></tbody></table>";

  echo "  <h3>SMTP " . __("Timeouts", "mailcwp") . "</h3>";
  echo "  <table class=\"form-table\"><tbody><th>" . __("Connect", "mailcwp") . "</th><td> <input type=\"text\" size=\"3\" name=\"smtp_connect_timeout\" value=\"$smtp_connect_timeout\">&nbsp;sec</td></tr></tbody></table>";

  echo "  <h3>" . __("Refresh", "mailcwp") . "</h3>";
  echo "  <table class=\"form-table\"><tbody><th>" . __("Auto Refresh", "mailcwp") . "</th><td> <input type=\"checkbox\" name=\"auto_refresh\"" . ($auto_refresh ? " checked" : "") . "></td></tr>";
  echo "  <tr><th>" . __("Refresh Interval", "mailcwp") . "</th><td> <select name=\"refresh_interval\">";
  for ($i = 1; $i <= 10; $i++) {
    echo "    <option value=\"$i\"" . ($i == $refresh_interval ? " selected" : "") . ">$i</option>";
  }
  echo "  </select>&nbsp;min</td></tr></tbody></table>";
  echo "  <p class=\"submit\">";
  echo "    <input type=\"submit\" name=\"Submit\" class=\"button-primary\" value=\"" . __('Save Changes', "mailcwp") . "\"/>";
  echo "  </p>";
  echo "</form>"; 
  echo "<script type=\"text/javascript\">";
  echo "  jQuery.ajax ({";
  echo "    url: \"../wp-content/plugins/mailcwp/js/json/themes.json\",";
  echo "    dataType: \"JSON\",";
  echo "    cache: false,";
  echo "    success: function(aData) {";
  echo "      console.log(aData);";
  echo "      vHtml = \"\";";
  echo "      aData.forEach(function(aElement) {";
  echo "        vHtml += \"<option value='\";";
  echo "        vHtml += aElement.theme_name;";
  echo "        vHtml += \"'\";";
  echo "        if (aElement.theme_name == \"$default_theme\") {";
  echo "          vHtml += \" selected\";";
  echo "        }";
  echo "        vHtml += \">\";";
  echo "        vHtml += aElement.theme_name;";
  echo "        vHtml += \"</option>\";";
  echo "      });";
  echo "      jQuery(\"#default_theme\").html(vHtml);";
  echo "    },";
  echo "    error: function(aObject, aStatus, aError) {";
  echo "      alert(aStatus + \"/\" + aError);";
  echo "    },";
  echo "  });";
  echo "</script>";
}

function mailcwp_admin_scripts() {
  /*wp_register_style('mailcwp_ui_styles', plugins_url('/css/jquery-ui-themes/pepper-grinder/jquery-ui.css', __FILE__));
  wp_enqueue_style('mailcwp_ui_styles');*/

  //wp_register_style('mailcwp_admin_styles', plugins_url('/css/mailcwp-admin.css', __FILE__));
  //wp_enqueue_style('mailcwp_admin_styles');

  //wp_enqueue_script('mailcwp_admin_ui_script', plugins_url('/js/jquery-ui-1.10.4.custom.min.js', __FILE__));
  wp_enqueue_script('mailcwp_util_script', plugins_url('/js/mailcwp-util.js', __FILE__));
  wp_enqueue_script('mailcwp_admin_script', plugins_url('/js/mailcwp-admin.js', __FILE__));
  wp_enqueue_script( 'jquery' );
  wp_enqueue_script( 'jquery-ui-core' );
  //wp_enqueue_script( 'jquery-ui-widget' );
  //wp_enqueue_script( 'jquery-ui-dialog' );
  wp_enqueue_script( 'jquery-ui-button' );

  wp_enqueue_style( 'wp-pointer' );
  wp_enqueue_script( 'wp-pointer' );	
}

function mailcwp_pointer_script() {
  $options = get_option("mailcwp_settings", array());
  if (empty($options)) {
    $content = '<h3>MailCWP: A Brief Introduction</h3>';
    $content .= '<p>Welcome to <a href=\"http://cadreworks.com/mailcwp-plugin\" target=\"MailCWP\">MailCWP</a>, Mail Client for WordPress!</p>';
    $content .= '<hr/>';
    $content .= '<p>Now that the plugin is installed and activated, follow';
    $content .= ' these steps to get started:';
    $content .= '  <ol>';
    $content .= '    <li>Open the admin page and confirm the settings suit your needs.</li>';
    $content .= '    <li>Create a new page with the <strong>[mailcwp]</strong> shortcode.</li>';
    $content .= '    <li>If users are allowed to manage accounts the <strong>MailCWP Options</strong> dialog will be opened initially to allow them to create an account.</li>';
    $content .= '    <li>If users are not allowed to manage accounts the site admin can manage mail accounts in each user\'s profile. Look for the MailCWP Accounts section.</li>';
    $content .= '  </ol>';
    $content .= '</p>';
    $content .= '<p>';
    $content .= 'That\'s it! You\'re up and running.<br/>There\'s a <a href=\"http://cadreworks.com/mailcwp-plugin\" target=\"MailCWP\">a range of add-ons</a> that enhance <strong>MailCWP</strong> with great features like <a href=\"http://cadreworks.com/mailcwp-address-book\" target=\"MailCWP\">Address Book</a>, <a href=\"http://cadreworks.com/mailcwp-advanced-search\" target=\"MailCWP\">Advanced Search</a> and several others.';
    $content .= '</p>';
?>
	  
    <script>
      jQuery(document).ready( function() {
	jQuery("#toplevel_page_mailcwp-admin-menu").pointer({
	  content: "<?php echo $content ?>",
	  position: {
	    edge: 'left',
	    align: 'bottom',
	  },
          pointerWidth: 640,
	  close: function() {
	  }
	}).pointer('open');
      });
    </script>

<?php
    update_option("mailcwp_settings", array("installed", true));
  }
}

function mailcwp_scripts() {
//write_log("wp_enqueue_scripts");
  global $post;
  if (isset($post) && is_object($post) && property_exists($post, "post_content") && has_shortcode($post->post_content, "mailcwp")) {
    wp_register_style('jquery_ui_styles', plugins_url('/css/jquery-ui.min.css', __FILE__));
    wp_enqueue_style('jquery_ui_styles');

    wp_register_style('cssreset_styles', plugins_url('/css/reset-context-min.css', __FILE__));
    wp_enqueue_style('cssreset_styles');

    wp_register_style('theme_switch_ui_styles', plugins_url('/css/jquery.jui_theme_switch.css', __FILE__));
    wp_enqueue_style('theme_switch_ui_styles');

    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'jquery-ui-core' );
    wp_enqueue_script( 'jquery-ui-widget' );
    wp_enqueue_script( 'jquery-ui-accordion' );
    wp_enqueue_script( 'jquery-ui-autocomplete' );
    wp_enqueue_script( 'jquery-ui-tabs' );
    //wp_enqueue_script( 'jquery-ui-selectable' );
    wp_enqueue_script( 'jquery-ui-tooltip' );
    wp_enqueue_script( 'jquery-ui-dialog' );
    wp_enqueue_script( 'jquery-ui-button' );
    wp_enqueue_script('theme_switch_ui_script', plugins_url('/js/jquery.jui_theme_switch.min.js', __FILE__));
    //We are using the plUpload jquery-ui widget
    //WP's built in scripts don't appear to support it so we are loading our
    //own.
    wp_enqueue_script('plupload');
    wp_enqueue_script('plupload-all');
    //wp_enqueue_script('plupload_script', plugins_url('/js/plupload.full.min.js', __FILE__));
    //wp_enqueue_script('plupload_jquery_ui_script', plugins_url('/js/jquery.ui.plupload.min.js', __FILE__));

    wp_register_style('mailcwp_styles', plugins_url('/css/mailcwp.css', __FILE__));
    wp_enqueue_style('mailcwp_styles');
    wp_register_style('jquery.ui.plupload_styles', plugins_url('/css/jquery.ui.plupload.css', __FILE__));
    wp_enqueue_style('jquery.ui.plupload_styles');

    wp_enqueue_script('mailcwp_util_script', plugins_url('/js/mailcwp-util.js', __FILE__));
    wp_enqueue_script('mailcwp_ajax_script', plugins_url('/js/mailcwp.js', __FILE__));
    $options = get_option("mailcwp_settings", array());
    $default_theme = isset($options["default_theme"]) ? $options["default_theme"] : "pepper-grinder";
    wp_localize_script('mailcwp_ajax_script', 'ajax_object', array( 'ajax_url' => admin_url('admin-ajax.php'), 'default_theme' => $default_theme));
  }
}

function mailcwp_get_account_callback () {
  $data = array();
  if (isset($_POST["account_id"]) && !empty($_POST["account_id"])) {
    if (isset($_POST["user_id"])) {
      $user_id = $_POST["user_id"];
    } else {
      $user_id = get_current_user_id();
    }
    $user_mail_account = mailcwp_get_account($user_id, $_POST["account_id"]);
    if (empty($user_mail_account)) {
      $data["result"] = "Failed";
      $data["message"] = "No account with ID $_POST[account_id] found.";
    } else {
      $data["account"] = $user_mail_account;
      $data["result"] = "OK";
    }
  } else {
    $data["result"] = "Failed";
    $data["message"] = "No account ID provided.";
  }

  echo json_encode($data);
  die();
}

function mailcwp_get_account ($user_id, $account_id) {
  $account = null;

  if (isset($account_id) && !empty($account_id)) {
    //$account_id = $account_id;
    $user_mail_accounts = get_user_meta($user_id, "mailcwp_accounts", true);
    $default_account_id = get_user_meta($user_id, "mailcwp_account_default", true);
    foreach($user_mail_accounts as $user_mail_account) {
      if ($user_mail_account["id"] == $account_id) {
	$account = $user_mail_account;
	if ($default_account_id == $account_id) {
	  $account["default"] = "true";
	}
      }
    }
  }
  return $account;
}

function mailcwp_edit_account_callback () {
//write_log("MAILCWP_EDIT_ACCOUNT " . print_r($_POST, true));
  $result = array();
  if (isset($_POST["mailcwp_account_id"]) && !empty($_POST["mailcwp_account_id"])) {
    $account_id = $_POST["mailcwp_account_id"];
  } else {
    //adding an account
    $account_id = uniqid();
  }

  $user_id = $_POST["mailcwp_user_id"];
  if (isset($user_id) && $user_id != "") {
    $user_mail_accounts = get_user_meta($user_id, "mailcwp_accounts", true);
    if (!is_array($user_mail_accounts)) {
      $user_mail_accounts = array();
    } else {
      $data = isset($user_mail_accounts[$account_id]) ? $user_mail_accounts[$account_id] : array();
      
    }
//write_log("DATA BEFORE ASSIGNMENT " . print_r($data, true));
    $data["id"] = $account_id;
    if (isset($_POST["mailcwp_name"])) {
      $data["name"] = $_POST["mailcwp_name"];
    }
    if (isset($_POST["mailcwp_host"])) {
      $data["host"] = $_POST["mailcwp_host"];
    }
    if (isset($_POST["mailcwp_port"])) {
      $data["port"] = $_POST["mailcwp_port"];
    }
    if (isset($_POST["mailcwp_use_ssl"])) {
      $data["use_ssl"] = $_POST["mailcwp_use_ssl"];
    }
    if (isset($_POST["mailcwp_validate_cert"])) {
      $data["validate_cert"] = $_POST["mailcwp_validate_cert"];
    }
    if (isset($_POST["mailcwp_timezone"])) {
      $data["timezone"] = $_POST["mailcwp_timezone"];
    }
    if (isset($_POST["mailcwp_email"])) {
      $data["email"] = $_POST["mailcwp_email"];
    }
    if (isset($_POST["mailcwp_username"])) {
      $data["username"] = $_POST["mailcwp_username"];
    }
    if (isset($_POST["mailcwp_use_tls"])) {
      $data["use_tls"] = $_POST["mailcwp_use_tls"];
    }
    if (isset($_POST["mailcwp_smtp_auth"])) {
      $data["smtp_auth"] = $_POST["mailcwp_smtp_auth"];
    }
    if (isset($_POST["mailcwp_smtp_host"])) {
      $data["smtp_host"] = $_POST["mailcwp_smtp_host"];
    }
    if (isset($_POST["mailcwp_smtp_port"])) {
      $data["smtp_port"] = $_POST["mailcwp_smtp_port"];
    }
    if (isset($_POST["mailcwp_smtp_username"])) {
      $data["smtp_username"] = $_POST["mailcwp_smtp_username"];
    }
    if (isset($_POST["mailcwp_smtp_password"])) {
      $data["smtp_password"] = $_POST["mailcwp_smtp_password"];
    }
    if (isset($_POST["mailcwp_alert_sound"])) {
      $data["alert_sound"] = $_POST["mailcwp_alert_sound"];
    }
    if (isset($_POST["mailcwp_default_folder"])) {
      $data["default_folder"] = $_POST["mailcwp_default_folder"];
    }
    if (isset($_POST["mailcwp_sent_folder"])) {
      $data["sent_folder"] = $_POST["mailcwp_sent_folder"];
    }
    if (isset($_POST["mailcwp_trash_folder"])) {
      $data["trash_folder"] = $_POST["mailcwp_trash_folder"];
    }
    if (isset($_POST["mailcwp_draft_folder"])) {
      $data["draft_folder"] = $_POST["mailcwp_draft_folder"];
    }

    $options = get_option("mailcwp_settings", array());
    $store_passwords = isset($options["user_store_password"]) ? $options["user_store_password"] : true;
    if ($store_passwords) {
      if (isset($_POST["mailcwp_password"])) {
        $data["password"] = $_POST["mailcwp_password"];
      }
    }
//write_log("DATA AFTER ASSIGNMENT " . print_r($data, true));
    $user_mail_accounts[$account_id] = $data;
//write_log("USER ACCOUNTS AFTER ASSIGNMENT " . print_r($user_mail_accounts, true));

    update_user_meta($user_id, "mailcwp_accounts", $user_mail_accounts);
    if (isset($_POST["mailcwp_account_default"]) && $_POST["mailcwp_account_default"] == "true") {
      update_user_meta($user_id, "mailcwp_account_default", $account_id);
    }
    do_action("mailcwp_account_edited", $account_id);
    $result["result"] = "OK";
    $result["account_id"] = $account_id;
  } else {
    $result["result"] = "Failed";
    $result["message"] = "Account could not be created or updated.";
  }

  echo json_encode($result);
  die();
}

function mailcwp_get_account_password ($account) {
//write_log("mailcwp_get_password");
  $options = get_option("mailcwp_settings", array());
  $store_passwords = isset($options["user_store_password"]) ? $options["user_store_password"] : true;
  if ($store_passwords) {
    if (isset($account["password"]) && !empty($account["password"])) {
      $password = $account["password"];
    } else {
      $password = "";
    }
  } else {
    $password = null;
//write_log("check session");
    //check session
    if (!isset($_SESSION)) {
      session_start();
    }
//write_log("REFRESH_SESSION " . print_r($_SESSION, true));
    if (isset($_SESSION["mailcwp"])) {
//write_log("session " . print_r($_SESSION["mailcwp"], true));
      $session_mailcwp_values = $_SESSION["mailcwp"];
      if (isset($session_mailcwp_values[$account["id"]])) {
        $session_account_values = $session_mailcwp_values[$account["id"]];
        if (isset($session_account_values["password"]) && !empty($session_account_values["password"])) {
          $password = $session_account_values["password"];
        }
      }
    } 
  }

  return $password;
}

function mailcwp_login_callback () {
  $result = null;

  $account_id = $_POST["account_id"];
  $username = $_POST["username"];
  $password = $_POST["password"];
//write_log("LOGIN POST " . print_r($_POST, true));
  if (!isset($_SESSION)) {
    session_start();
  }

  if (isset($_SESSION["mailcwp"])) {
    $session_mailcwp_values = $_SESSION["mailcwp"];
  } else {
    $session_mailcwp_values = array();
  }

  if (isset($session_mailcwp_values[$account_id])) {
    $session_account_values = $session_mailcwp_values[$account_id];
  } else {
    $session_account_values = array();
  }

  $session_account_values["password"] = $password;
  $session_mailcwp_values[$account_id] = $session_account_values;
  $_SESSION["mailcwp"] = $session_mailcwp_values;

  $user_id = get_current_user_id();
  $user_mail_accounts = get_user_meta($user_id, "mailcwp_accounts", true);
  foreach($user_mail_accounts as $user_mail_account) {
    if ($user_mail_account["id"] == $account_id) {
      $mailcwp_session = array();
      $mailcwp_session["account"] = $user_mail_account;
      $mailcwp_session["folder"] = "INBOX";
    }
  }
  update_user_meta($current_user->ID, "mailcwp_session", $mailcwp_session);

  
  $mailcwp_session = mailcwp_get_session();
  if (isset($mailcwp_session) && !empty($mailcwp_session)) {
    $result["result"] = "OK";
  } else {
    $result["result"] = "Failed";
    $result["message"] = "Login failed.";
  }
  echo json_encode($result);
  die();
}

function mailcwp_test_account_callback () {
//write_log($_POST);
  $result = array();
  try {
    $name = $_POST["mailcwp_name"];
    $host = $_POST["mailcwp_host"];
    $port = $_POST["mailcwp_port"];
    $use_ssl = $_POST["mailcwp_use_ssl"];
    $validate_cert = $_POST["mailcwp_validate_cert"];
    $email = $_POST["mailcwp_email"];
    $username = $_POST["mailcwp_username"];
    if (isset($_POST["mailcwp_password"])) {
      $password = $_POST["mailcwp_password"];
    } else {
      $password = null;
    }
    $use_tls = $_POST["mailcwp_use_tls"];

    $mbox_name = $host . ":" . $port;
    $ssl_flag = $use_ssl === "true" ? "/ssl" : "";
    $validate_cert_flag = $validate_cert === "true" ? "" : "/novalidate-cert";
    $tls_flag = $use_tls === "true" ? "/tls" : "";
    error_reporting(E_ERROR);
    ob_start();
    $mbox = @imap_open("{" . $mbox_name . $ssl_flag . $validate_cert_flag . $tls_flag . "}", $username, $password, OP_HALFOPEN);
    ob_end_clean();

    if ($mbox !== FALSE) {
      $folders = imap_listmailbox($mbox, "{" . $mbox_name . "}", "*");
      if ($folders !== FALSE) {
        $result["result"] = "OK";
      } else {
        $result["result"] = "Failed";
        $result["message"] =  "Test Failed: Can't fetch folders.";
      }
    } else {
      $result["result"] = "Failed";
      $result["message"] = "Test Failed: Can't connect to server.";
    }
  } catch (Exception $e) {
    $result["result"] = "Failed";
    $result["message"] = "Test Failed: " . print_r($e, true);
  }
  echo json_encode($result);
  die();
}

function mailcwp_delete_account_callback () {
  $account_id = $_POST["account_id"];
//write_log("delete account $account_id");
  if (isset($_POST["user_id"])) {
    $user_id = $_POST["user_id"];
  } else {
    $user_id = get_current_user_id();
  }

  if (isset($user_id) && $user_id != "") {
    $user_mail_accounts = get_user_meta($user_id, "mailcwp_accounts", true);
    if (is_array($user_mail_accounts) && isset($user_mail_accounts[$account_id])) {
      unset($user_mail_accounts[$account_id]);
      update_user_meta($user_id, "mailcwp_accounts", $user_mail_accounts);
      echo "Account deleted.";
    } else {
      echo "Account not found.";
    }
  } else {
    echo "Account could not be deleted";
  }

  die();
}

function mailcwp_get_unseen_messages($aAccount = null) {
//write_log("CHECKING UNSEEN");
  $current_user = wp_get_current_user();
  $accounts = get_user_meta($current_user->ID, "mailcwp_accounts", true);

  if (is_array($accounts)) {
    $mustDing = false;
    echo "<script type=\"text/javascript\">";
    echo "  jQuery(document).ready(function() {";
    foreach ($accounts as $account) {
      if (isset($account["id"])) {
        if (empty($aAccount) || $account["id"] == $aAccount["id"]) {
          $password = mailcwp_get_account_password($account);
	  $heading_id = $account["id"];
          if (empty($password)) {
	    echo "  jQuery(\"#$heading_id\").html(\"<div style='display: inline-block; margin-left: -20px;' class='ui-icon ui-icon-alert'></div> $account[name]\");";
          } else {
  	    $mbox = mailcwp_imap_connect($account, 0, "");
	    $unseen = imap_search($mbox, "UNSEEN");
	    if (is_array($unseen) && count($unseen) > 0) {
	      echo "  jQuery(\"#$heading_id\").html(\"$account[name] (" . count($unseen) . ")\");";
              $mustDing = empty($aAccount) && ($mustDing || (isset($account["alert_sound"]) && $account["alert_sound"] == "true"));
	    } else {
	      echo "  jQuery(\"#$heading_id\").html(\"$account[name]\");";
	    }
	    imap_close($mbox);
          }
	}
      }
    }
    if ($mustDing) {
      echo "  playSound('" . plugins_url("sounds/ding.mp3", __FILE__) . "');";
    }
    echo "  });";
    echo "</script>";
  }
}

function mailcwp_get_headers_callback () {
  do_action("mailcwp_log", 10, "Fetching headers for someone");
  $mailcwp_session = mailcwp_get_session();
//write_log("GET HEADERS POST: ". print_r($_POST, true));
//write_log("MAILCWP SESSION: ". print_r($mailcwp_session, true));
  
  if (!is_user_logged_in()) {
     echo "NOT LOGGED IN";
     die();
  }

  if (!isset($mailcwp_session) || empty($mailcwp_session)) {
     echo __("No accounts found. Please contact the system administrator for assistance.", "mailcwp");
     die();
  }

  if (isset($_POST["folder"]) && !empty($_POST["folder"])) {
    $mailcwp_session["folder"] = $_POST["folder"];
    $current_user = wp_get_current_user();
    update_user_meta($current_user->ID, "mailcwp_session", $mailcwp_session);
  }

  if (isset($_POST["account"]) && !empty($_POST["account"])) {
    $current_user = wp_get_current_user();
    $accounts = get_user_meta($current_user->ID, "mailcwp_accounts", true);
/*if (isset($accounts[""])) {
write_log("FIXING EMPTy KEY");
$accounts[$accounts[""]["id"]] = $accounts[""];
unset($accounts[""]);
update_user_meta($current_user->ID, "mailcwp_accounts", $accounts);
}*/
    if (isset($accounts[$_POST["account"]])) {
      $mailcwp_session["account"] = $accounts[$_POST["account"]];
      update_user_meta($current_user->ID, "mailcwp_session", $mailcwp_session);
    }
  }

  $account = $mailcwp_session["account"];
  if (!isset($account)) {
    echo __("No accounts found. Please contact the system administrator for assistance.", "mailcwp");
    die();
  }
  if (isset($_POST["all"]) && $_POST["all"] == "true") {
    mailcwp_get_unseen_messages();
  } else {
    mailcwp_get_unseen_messages($account);
  }

  $password = mailcwp_get_account_password($account);
  if (empty($password)) {
    echo __("Please login", "mailcwp");
    die();
  }
  $folder = $mailcwp_session["folder"];

  $page = $_POST["page"];
  $page_size = (isset($account["page_size"]) ? $account["page_size"] : 20);
//print_r($account);
  if (isset($_POST["all"]) && $_POST["all"] == "true") {
    do_action("mailcwp_clear_search_criteria");
  }
  $must_search = apply_filters("mailcwp_must_search", false);
  if (isset($_POST["search"]) && !empty($_POST["search"]) || $must_search) {
    $search = $_POST["search"];
    $criteria = array("SUBJECT \"$search\"", "FROM \"$search\"", "TO \"$search\"", "CC \"$search\"");
    $criteria = apply_filters("mailcwp_search_criteria", $criteria);
  } else {
    $criteria = array("ALL");
  }
  $search_result = array();

  $mbox = mailcwp_imap_connect($account, 0, $folder);

  $sent_folder = "";
  if (is_array($account)) {
    $sent_folder = isset($account["sent_folder"]) ? $account["sent_folder"] : null;
    if (empty($sent_folder)) {
      $sent_folder = mailcwp_find_folder($mbox, $account, "Sent");
    }
  }

  $search_result = array();
  foreach ($criteria as $criterion) {
//write_log(print_r($criterion, true));
    $single_result = imap_search($mbox, $criterion);
//write_log(print_r($single_result, true));
    if (is_array($single_result)) {
      $search_result = array_merge($search_result, $single_result); 
    }
  }
//write_log(print_r($search_result, true));
  $num_msg = count($search_result);
  $num_page = ceil($num_msg / $page_size);
  $start = $num_msg - (($page - 1) * $page_size);
  if ($start < 1) {
    $start = $num_msg;
  }
  $start -= 1;
  $end = $start - $page_size;
  if ($end < 0) {
    $end = 0;
  }
//write_log($num_msg . "/" . $start . "/" . $end);
//write_log(print_r($search_result, true));
  $html = " <div id=\"mailcwp_headers_title\" class=\"ui-widget-header ui-corner-top ui-state-active\">";
  if (isset($_POST["search"]) && !empty($_POST["search"])) {
    $html .= $folder . __(" Search: ", "mailcwp") . $_POST["search"];
  } else if ($must_search) {
    $html .= $folder . __(" (Search)", "mailcwp");
  } else {
    $html .= $folder;
  }
  $html .= "</div>";

  $paging_toolbar = "<div class=\"mailcwp_paging_toolbar\" class=\"ui-widget-header\">";
  $paging_toolbar .= "Page&nbsp;&nbsp;";
  for ($i = 1; $i <= 3 && $i <= $num_page; $i++) {
    $paging_toolbar .= "<a class=\"mailcwp_page_number" . ($i == $page ? "_selected" : "") . "\" href=\"javascript:void(0)\" onclick=\"getHeaders(false, $i)\">$i</a>&nbsp;&nbsp;";
  }
  if ($page < 6) {
    for ($i = 4; $i <= 6 && $i <= $num_page; $i++) {
      $paging_toolbar .= "<a class=\"mailcwp_page_number" . ($i == $page ? "_selected" : "") . "\" href=\"javascript:void(0)\" onclick=\"getHeaders(false, $i)\">$i</a>&nbsp;&nbsp;";
    }
  } else {
    if ($page > 4 && $page < $num_page - 4) {
      $paging_toolbar .= "&hellip;&nbsp;&nbsp";
      for ($i = $page - 1; $i <= $page + 1; $i++) {
        $paging_toolbar .= "<a class=\"mailcwp_page_number" . ($i == $page ? "_selected" : "") . "\" href=\"javascript:void(0)\" onclick=\"getHeaders(false, $i)\">$i</a>&nbsp;&nbsp;";
      }
    }
  }
  if ($page >= $num_page - 4 && $num_page > 3) {
    $start_page = $num_page - 5;
    while ($start_page <= 0) {
      $start_page += 1;
    }

    if ($start_page < $num_page) {
      $paging_toolbar .= "&hellip;&nbsp;&nbsp";
      for ($i = $start_page; $i <= $num_page; $i++) {
        $paging_toolbar .= "<a class=\"mailcwp_page_number" . ($i == $page ? "_selected" : "") . "\" href=\"javascript:void(0)\" onclick=\"getHeaders(false, $i)\">$i</a>&nbsp;&nbsp;";
      }
    }
  } else if ($num_page > 3) {
    $paging_toolbar .= "&hellip;&nbsp;&nbsp";
    for ($i = $num_page - 2; $i <= $num_page; $i++) {
      $paging_toolbar .= "<a class=\"mailcwp_page_number" . ($i == $page ? "_selected" : "") . "\" href=\"javascript:void(0)\" onclick=\"getHeaders(false, $i)\">$i</a>&nbsp;&nbsp;";
    }
  }
  $paging_toolbar .= "&nbsp|&nbsp;Items&nbsp;per&nbsp;page&nbsp;<select style=\"max-width:100px;\" onchange=\"setPageSize(this.value);\">";
  $itemsPerPageOptions = array(10, 20, 30, 40, 50, 100);
  foreach ($itemsPerPageOptions as $option) {
    $selected = "";
    if ($option == $page_size) {
      $selected = " selected";
    }
    $paging_toolbar .= "<option$selected>$option</option>";
  }
  $paging_toolbar .= "</select>";
  $paging_toolbar .= "</div>";
//write_log($folder . "===" . $sent_folder);
  $html .= apply_filters("mailcwp_paging_toolbar", $paging_toolbar);
  $html .= "<table><thead><th class=\"ui-widget-header\"><input type=\"checkbox\" id=\"select-all\" onclick=\"selectAll()\"></th><th class=\"ui-widget-header\">Date</th><th class=\"ui-widget-header\">" . ($folder == $sent_folder ? "To" : "From") . "</th><th class=\"ui-widget-header\">Subject</th></thead><tbody>";
  for ($i = $start; $i > $end; $i--) {
    $header_info = imap_headerinfo($mbox, $search_result[$i]);
    $class = "";
    if ($header_info->Recent == 'N' || $header_info->Unseen == 'U') {
      $class = "mailcwp_unseen";
    } 
    if ($header_info->Answered == 'A') {
      $class .= " mailcwp_answered";
    }
    if ($header_info->Deleted == 'D') {
      $class .= " mailcwp_deleted";
    }

    $date_str = date("D, j M Y g:i:m a", strtotime(substr($header_info->MailDate, 0, strrpos($header_info->MailDate, " "))));
    if (property_exists($header_info, "subject")) {
      $decoded_subject = mailcwp_get_imap_decoded_string($header_info->subject);
    } else {
      $decoded_subject = "";
    }
    if ($folder == $sent_folder) {
      if (property_exists($header_info, "toaddress")) {
        $decoded_address = mailcwp_get_imap_decoded_string($header_info->toaddress);
      } else {
        $decoded_address = "";
      }
    } else {
      if (property_exists($header_info, "fromaddress")) {
        $decoded_address = mailcwp_get_imap_decoded_string($header_info->fromaddress);
      } else {
        $decoded_address = "";
      }
    }

    if (isset($header_info->Draft) && $header_info->Draft == "X") {
      $header_click_action = "composeMessage('$header_info->Msgno', 3)";
    } else {
      $header_click_action = "getMessage('$folder', '$header_info->Msgno', '" . base64_encode($decoded_subject) . "')";
    }

    $html .= "<tr id=\"mailcwp_header_row_$header_info->Msgno\" class=\"mailcwp_header_row $class\"><td class=\"ui-widget-content\"><input type=\"checkbox\" id=\"action-$header_info->Msgno\" onclick=\"checkSelection(this.id)\"><br/><span class=\"ui-icon ui-icon-arrowreturnthick-1-w\" style=\"display: none\"></span></td><td class=\"ui-widget-content\"><a href=\"javascript:void(0)\" onclick=\"$header_click_action\"><div>$date_str</div></a></td><td class=\"ui-widget-content\"><a href=\"javascript:void(0)\" onclick=\"$header_click_action\"><div>$decoded_address</div></a></td><td class=\"ui-widget-content\"><a href=\"javascript:void(0)\" onclick=\"$header_click_action\"><div>$decoded_subject</div></a></td></tr>";
  }
  $html .= "</tbody></table>";
  $html .= apply_filters("mailcwp_paging_toolbar", $paging_toolbar);
  imap_close($mbox);
  echo $html;
  die();
}

function my_error_handler($errno, $errstr, $errfile, $errline) {
  if ( preg_match('/iconv/', $errstr) ) {
    throw new Exception('iconv error');
  } else {
    restore_error_handler();
    trigger_error($errstr, $errno);
    set_error_handler('my_error_handler', E_ALL);
  }
}

function mailcwp_get_message_callback() {
  $mailcwp_session = mailcwp_get_session();
  if (!isset($mailcwp_session) || empty($mailcwp_session)) {
    return;
  }
//write_log("GET MESSAGE SESSION " . print_r($mailcwp_session, true));
//write_log("GET MESSAGE POST " . print_r($_POST, true));
  $account = $mailcwp_session["account"];
  $mbox_name = "$account[host]:$account[port]";
  $use_ssl = $account["use_ssl"];
  $validate_cert = $account["validate_cert"];
  $username = $account["username"];
  $password = $account["password"];
  $use_tls = $account["use_tls"];
  $folder = $mailcwp_session["folder"];

  $msg_number = (int)$_POST["msg_number"];
  $subject = $_POST["subject"];
  if (empty($subject)) {
    $subject = __("[No Subject]", "mailcwp");
  } else {
    $subject = base64_decode($subject);
  }

  $mbox = mailcwp_imap_connect($account, 0, $folder);
  $message = new MailMessage($mbox, $msg_number);  
//write_log($message);
  $html_message = $message->getHtmlMessage();
  //$html_message = apply_filters("mailcwp_get_html_message", $msg_number, $html_message, false);
  $plain_message = $message->getPlainMessage();
  //$plain_message = apply_filters("mailcwp_get_plain_message", $msg_number, $plain_message, false);
//error_log("HTML MESSAGE: " . $html_message);
//error_log("PLAIN MESSAGE: " .  $plain_message);
  $attachments = $message->getAttachments();
  //$attachments = apply_filters("mailcwp_get_attachments", $msg_number, $attachments, false);
  $headers = $message->getHeaders();
//error_log("HEADERS " . print_r($headers, true));
  $char_set = $message->getCharSet();
//error_log("CHARSET: " . $char_set);
//exit;

  //process inline images
  if (isset($html_message) && !empty($html_message)) {
    $vStartSrc = -1;
    while (($vStartSrc = strpos($html_message, "src=\"cid:", $vStartSrc + 1)) !== FALSE) {
      $vEndSrc = strpos($html_message, "\"", $vStartSrc + 5);
      if ($vEndSrc !== FALSE) {
        $vCidName = substr($html_message, $vStartSrc + 9, $vEndSrc - $vStartSrc - 9);
        $vPartId = strpos($vCidName, "@");
        if ($vPartId !== FALSE) {
          $vCidName = substr($vCidName, 0, $vPartId);
        }

//write_log($vCidName);
        if (isset($attachments[$vCidName])) {
	  $path_parts = pathinfo($vCidName);
	  if (isset($path_parts['extension'])) {
	    $extension = strtolower($path_parts['extension']);
	  } else {
	    $extension = "";
	  }
          $html = "";
	  if (preg_match("(png|gif|jpg)", $extension) == 1) {
	    $encoded_image = base64_encode($attachments[$vCidName]);
	    $html = "src=\"data:image/$extension;base64,$encoded_image\"";
	  }
          $html_message = substr($html_message, 0, $vStartSrc) . $html . substr($html_message, $vEndSrc + 1);
          unset($attachments[$vCidName]);
        }
      }
    }
  }

  $dialog_id = "message-$msg_number";
  $html = "<div id=\"$dialog_id\" title=\"$subject\" class=\"mailcwp_message_dialog\">";
  if (property_exists($headers, "fromaddress")) {
    $decoded_from = htmlentities(mailcwp_get_imap_decoded_string($headers->fromaddress));
  } else {
    $decoded_from = "";
  }
  if (property_exists($headers, "toaddress")) {
    $decoded_to = htmlentities(mailcwp_get_imap_decoded_string($headers->toaddress));
  } else {
    $decoded_to = "";
  }

  if (property_exists($headers, "subject")) {
    $decoded_subject = mailcwp_get_imap_decoded_string($headers->subject);
  } else {
    $decoded_subject = "";
  }

  if (!empty($char_set)) {
    set_error_handler('my_error_handler', E_ALL);
    try {
      $decoded_subject = iconv($char_set, "utf-8", $decoded_subject);
    } catch (exception $e) {
      //$decoded_subject .= $decoded_subject;
    }
    restore_error_handler();
  }
   
  $date_str = date("D, j M Y g:i:m a", strtotime(substr($headers->MailDate, 0, strrpos($headers->MailDate, " "))));
  //$date_str = date("D, j M Y H:i:m", $headers->udate);
  $html .= "  <span>Date:&nbsp;&nbsp;$date_str<br/>";
  $html .= "  <span>From:&nbsp;&nbsp;<span id=\"mailcwp_received_from_$msg_number\">$decoded_from</span>";
  $html .= apply_filters("mailcwp_received_from_address", "", "mailcwp_received_from_$msg_number");
  $html .= "<br/>";
  $html .= "  <span>To:&nbsp;&nbsp;&nbsp;&nbsp;$decoded_to<br/>";
  if (property_exists($headers, "ccaddress")) {
    $decoded_cc = mailcwp_get_imap_decoded_string($headers->ccaddress);
    $html .= "  <span>CC: $decoded_cc<br/>";
  }
  $html .= "  <p/><span>Subject: $decoded_subject<p/>";
  $message_toolbar_html = "  <div id=\"mailcwp_message_toolbar\" class=\"ui-widget-header ui-corner-all\">" .
  "    <button id=\"mailcwp_reply\">" . __("Reply", "mailcwp") . "</button>" .
  "    <button id=\"mailcwp_reply_all\">" . __("Reply to All", "mailcwp") . "</button>" .
  "    <button id=\"mailcwp_forward\">" . __("Forward", "mailcwp") . "</button>" .
  "    <button id=\"mailcwp_message_delete\">" . __("Delete", "mailcwp") . "</button>";
  if (isset($attachments) && sizeof($attachments) > 0) {
    $message_toolbar_html .= "<div id=\"download_forms\" style=\"display: none\">";
    $upload_dir = wp_upload_dir();
    $mailcwp_download_dir = "$upload_dir[basedir]/mailcwp/downloads"; 
    $download_data = base64_encode("$mbox_name::$use_ssl::$validate_cert::$username::$password::$use_tls::$folder::$msg_number::*");
    $message_toolbar_html .= "<form id=\"download-all-$msg_number\" action=\"";
    $message_toolbar_html .= plugins_url("mailcwp-download.php", __FILE__);
    $message_toolbar_html .= "\" method=\"post\">";
    $message_toolbar_html .= "<input type=\"hidden\" name=\"download_data\" value=\"$download_data\">";
    $message_toolbar_html .= "<input type=\"hidden\" name=\"download_dir\" value=\"$mailcwp_download_dir\">";
    $message_toolbar_html .= "</form>";
    foreach ($attachments as $filename=>$data) {
      $download_data = base64_encode("$mbox_name::$use_ssl::$validate_cert::$username::$password::$use_tls::$folder::$msg_number::$filename");
      $message_toolbar_html .= "<form id=\"download-" . preg_replace("/[ .]/", "_", $filename) . "-$msg_number\" action=\"";
      $message_toolbar_html .= plugins_url("mailcwp-download.php", __FILE__);
      $message_toolbar_html .= "\" method=\"post\">";
      $message_toolbar_html .= "  <input type=\"hidden\" name=\"download_data\" value=\"$download_data\">";
      $message_toolbar_html .= "  <input type=\"hidden\" name=\"download_dir\" value=\"$mailcwp_download_dir\">";
      $message_toolbar_html .= "</form>";
    }
    $message_toolbar_html .= "</div>";
    $message_toolbar_html .= "    <div id=\"attachment_set\" style=\"display: inline\">" .
    "      <button id=\"save_attachment\" onclick=\"jQuery('#download-all-$msg_number').submit();\">" . __("Save Attachments", "mailcwp") . "</button>" .
    "      <button id=\"select_attachment\">" . __("Select an attachment", "mailcwp") . "</button>" .
    "    </div>" .
    "    <ul id=\"attachment_list\">";
    foreach ($attachments as $filename=>$data) {
      $message_toolbar_html .= "      <li><a href=\"javascript:void(0)\" onclick=\"jQuery('#download-" . preg_replace("/[ .]/", "_", $filename) . "-$msg_number').submit();\">$filename</a></li>";
    }
    $message_toolbar_html .= "    </ul>";
  }
  $message_toolbar_html .= "  </div>";
  $html .= apply_filters("mailcwp_message_toolbar", $message_toolbar_html);
  $html .= "  <div id=\"mailcwp-content-$dialog_id\" class=\"mailcwp_content ui-widget-content ui-corner-bottom\">";
  //$html .= (isset($message->getHtmlMessage()) ? $message->getHtmlMessage() : $message->getPlainMessage());
  if (isset($html_message) && !empty($html_message)) {
$conv_flag = "[none]";
    $html .= "<div class=\"mailcwp_html_message yui3-cssreset\">";
    if (!empty($char_set)) {
      set_error_handler('my_error_handler', E_ALL);
      try {
        ini_set('mbstring.substitute_character', "none"); 
        $conv_msg = @iconv($char_set, "utf-8//IGNORE", $html_message);
        if ($conv_msg == null) {
          $conv_msg = @mb_convert_encoding($html_message, 'UTF-8', $char_set); 
        }
        if ($conv_msg == null) {
          $conv_msg = @utf8_encode($html_message);
        }
        if ($conv_msg == null) {
          $conv_msg = $html_message;
        }
        $html .= $conv_msg;
      } catch (exception $e) {
        $html .= htmlspecialchars($html_message);
      }
      restore_error_handler();
    } else {
      $html .= $html_message;
    }
    $html .= "</div>";
  } else if (isset($plain_message) && !empty($plain_message)) {
    //$html .= "<pre>" . $plain_message . "</pre>";
    if (strlen($plain_message) > 16384) {
      $html .= "The following message has been truncated:<br/>";
    }
    $html .= "<pre>" . substr($plain_message, 0, 16384) . "</pre>";
  } else {
    $html .= "<pre>NO HTML OR PLAIN TEXT\n\n" . print_r($message, true) . "</pre>";
  }

  if (isset($attachments) && sizeof($attachments) > 0) {
    foreach ($attachments as $filename=>$data) {
      $path_parts = pathinfo($filename);
      if (isset($path_parts['extension'])) {
        $extension = strtolower($path_parts['extension']);
      } else {
        $extension = "";
      }
      if (preg_match("(png|gif|jpg)", $extension) == 1) {
        $encoded_image = base64_encode($data);
        $html .= "<img src=\"data:image/$extension;base64,$encoded_image\"/>";
      } else if (preg_match("(wav|mp3|ogg)", $extension) == 1) {
        $encoded_audio = base64_encode($data);
        $html .= "<audio controls><source src=\"data:audio/$extension;base64,$encoded_audio\" type=\"audio/$extension\"></audio>";
      } else if (preg_match("(webm|mp4)", $extension) == 1) {
        $encoded_video = base64_encode($data);
        $html .= "<video controls><source src=\"data:video/$extension;base64,$encoded_video\" type=\"video/$extension\"></video>";
      }
    }
  }
  $html .= "  </div>";
  $html .= "</div>";

  $javascript = "  jQuery(\"#mailcwp_reply\").button({text: false,icons:{primary: \"ui-icon-arrowreturn-1-w\"}}).click(function() {composeMessage($msg_number, " . COMPOSE_REPLY . ")});";
  $javascript.= "  jQuery(\"#mailcwp_reply_all\").button({text: false,icons:{primary: \"ui-icon-arrowreturnthick-1-w\"}}).click(function() {composeMessage($msg_number, " . COMPOSE_REPLY_ALL . ")});";
  $javascript.= "  jQuery(\"#mailcwp_forward\").button({text: false,icons:{primary: \"ui-icon-arrowthick-1-e\"}}).click(function() {composeMessage($msg_number, " . COMPOSE_FORWARD . ")});";
  $javascript.= "  jQuery(\"#mailcwp_message_delete\").button({text: false, icons:{primary: \"ui-icon-trash\"}}).click(function() {deleteMessages('$msg_number'); jQuery(\"#$dialog_id\").dialog(\"close\")});";
  $javascript.= "  jQuery(\"#$dialog_id\").ready(function() {jQuery(\"#$dialog_id\").dialog({height: 560, width: 640, open: function(event, ui) {jQuery(\"#mailcwp-content-$dialog_id\").focus()}, close: function(){jQuery(\"#$dialog_id\").remove(); refresh(false);}});});";
  $javascript.= "  jQuery(\"#save_attachment\").button();";
  $javascript.= "  jQuery(\"#select_attachment\").button({text: false, icons: {primary: \"ui-icon-triangle-1-s\"}}).click(function() {jQuery(\"#attachment_list\").show().position({my: \"left top\", at: \"left bottom\", of: this}); jQuery(document).one(\"click\", function() {jQuery(\"#attachment_list\").hide();}); return false;});";
  $javascript.= "  jQuery(\"#attachment_set\").buttonset();";
  $javascript.= "  jQuery(\"#attachment_list\").hide().menu();";
  $javascript.= apply_filters("mailcwp_received_javascript", "");

//encoding of the message has already taken place
  if (false) {
    $utf8_html = @utf8_encode($html);
  }
  if ($utf8_html == null) {
    $utf8_html = $html;
  }

  $fixed_msg = @iconv("UTF-8", "UTF-8//IGNORE", $utf8_html);

  $result = array("result" => "OK", "html" => $fixed_msg, "javascript" => $javascript);
  echo json_encode($result);
  die();
}

function mailcwp_download_attachment_callback() {
  $mailcwp_session = mailcwp_get_session();
  if (!isset($mailcwp_session) || empty($mailcwp_session)) {
    return;
  }

  $account = $mailcwp_session["account"];
  $mbox_name = "$account[host]:$account[port]";
  $use_ssl = $account["use_ssl"];
  $validate_cert = $account["validate_cert"];
  $username = $account["username"];
  $password = $account["password"];
  $use_tls = $account["use_tls"];
  $folder = $mailcwp_session["folder"];

  $msg_number = (int)$_POST["msg_number"];
  $attachment_name = $_POST["attachment_name"];

  $use_ssl_flag = $use_ssl === "true" ? "/ssl" : "";
  $validate_cert_flag = $validate_cert === "true" ? "" : "/novalidate-cert";
  $use_tls_flag = $use_tls === "true" ? "/tls" : "";
  $mbox = mailcwp_imap_connect($account, 0, $folder);
  $message = new MailMessage($mbox, $msg_number);  
  $attachments = $message->getAttachments();

  $download_data = null;
  foreach ($attachments as $filename=>$data) {
    if ($filename == $attachment_name) {
      $download_data = $data;
    }
  }

  if ($download_data != null) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename='.basename($file));
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . sizeof($download_data));
    ob_clean();
    flush(); 
    echo $download_data;
  } else {
    echo "Download failed. Can't find attachment.";
  }
  die();
}

function mailcwp_compose_callback () {
  $message_id = $_POST["unique_id"];
  if (isset($_POST["subject"])) {
    $subject = mailcwp_get_imap_decoded_string(base64_decode($_POST["subject"]));
  }

  $mailcwp_session = mailcwp_get_session();
  if (!isset($mailcwp_session) || empty($mailcwp_session)) {
    return;
  }

//write_log("COMPOSE MESSAGE SESSION " . print_r($mailcwp_session, true));
//write_log("COMPOSE MESSAGE POST " . print_r($_POST, true));
  $account = $mailcwp_session["account"];

  $original = isset($_POST['original']) ? intval($_POST['original']) : -1;
  $mode = isset($_POST['mode']) ? intval($_POST['mode']) : -1;
  if ($mode == COMPOSE_EDIT_DRAFT) {
    $draft_id = $original;
  } else {
    $draft_id = "";
  }
  if ($original != -1) {
//write_log($_POST);

    $folder = $mailcwp_session["folder"];

    $mbox = mailcwp_imap_connect($account, 0, $folder);
    $mail_message = new MailMessage($mbox, $original);  
    $html_message = $mail_message->getHtmlMessage();
//print_r($html_message);
    $char_set = $mail_message->getCharSet();
    if (!empty($char_set) && !empty($html_message)) {
      $html_message = iconv($char_set, "UTF-8", $html_message);
    }
    $plain_message = $mail_message->getPlainMessage();
//print_r($plain_message);
//exit;
    $attachments = $mail_message->getAttachments();
    $headers = $mail_message->getHeaders();
    imap_close($mbox);

    //process inline images
    if (isset($html_message)) {
      $vStartSrc = -1;
      while (($vStartSrc = strpos($html_message, "src=\"cid:", $vStartSrc + 1)) !== FALSE) {
	$vEndSrc = strpos($html_message, "\"", $vStartSrc + 5);
	if ($vEndSrc !== FALSE) {
	  $vCidName = substr($html_message, $vStartSrc + 9, $vEndSrc - $vStartSrc - 9);
	  $vPartId = strpos($vCidName, "@");
	  if ($vPartId !== FALSE) {
	    $vCidName = substr($vCidName, 0, $vPartId);
	  }

  //write_log($vCidName);
	  if (isset($attachments[$vCidName])) {
	    $path_parts = pathinfo($vCidName);
	    if (isset($path_parts['extension'])) {
	      $extension = strtolower($path_parts['extension']);
	    } else {
	      $extension = "";
	    }
            $html = "";
	    if (preg_match("(png|gif|jpg)", $extension) == 1) {
	      $encoded_image = base64_encode($attachments[$vCidName]);
	      $html = "src=\"data:image/$extension;base64,$encoded_image\"";
	    }
	    $html_message = substr($html_message, 0, $vStartSrc) . $html . substr($html_message, $vEndSrc + 1);
	    unset($attachments[$vCidName]);
	  }
	}
      }
    }

    $from = "$account[name] <$account[email]>";
    $timezone = null;

    if (array_key_exists("timezone", $account)) {
      $tz_name = $account["timezone"];
      if (!empty($tz_name)) {
        $timezone = new DateTimeZone($tz_name);
      }
    }

    $original_datetime = new DateTime();
    $original_datetime->setTimestamp(mailcwp_get_imap_decoded_string($headers->udate));
    if (isset($timezone)) {
      $original_datetime->setTimeZone($timezone);
    }
    $original_date = $original_datetime->format("D j F Y");//date("D j F Y", mailcwp_get_imap_decoded_string($headers->udate));
    $original_time = $original_datetime->format("g:ia"); //date("g:ia", mailcwp_get_imap_decoded_string($headers->udate));
    $original_from = mailcwp_get_imap_decoded_string($headers->fromaddress);
    $original_to = isset($headers->toaddress)  ? mailcwp_get_imap_decoded_string($headers->toaddress) : "";
//write_log($mode);
    if ($mode == COMPOSE_REPLY || $mode == COMPOSE_REPLY_ALL) {
      $to = $original_from;
      if ($mode == COMPOSE_REPLY_ALL) {
//write_log($headers->to);
        foreach ($headers->to as $reply_to) {
          $reply_address = "$reply_to->mailbox@$reply_to->host";
          if ($reply_address != $account["email"]) {
            $to .= ", $reply_address";
          }
        }
        if (isset($headers->ccaddress)) {
          $cc = mailcwp_get_imap_decoded_string($headers->ccaddress);
        } else {
          $cc = "";
        }
      } else {
        $cc = "";
      }
    } else if ($mode == COMPOSE_EDIT_DRAFT) {
      $to = $original_to;
      if (isset($headers->ccaddress)) {
        $cc = mailcwp_get_imap_decoded_string($headers->ccaddress);
      } else {
        $cc = "";
      }
      if (isset($headers->bccaddress)) {
        $bcc = mailcwp_get_imap_decoded_string($headers->ccaddress);
      } else {
        $bcc = "";
      }
    } else {
      $to = "";
      $cc = "";
      $bcc = "";
    }
    if (property_exists($headers, "subject")) {
      $subject = mailcwp_get_imap_decoded_string($headers->subject);
    } else {
      $subject = "";
    }
    if ($html_message) {
      $html_message = apply_filters("mailcwp_get_html_message", $html_message, true);
      $message = $html_message;
      if ($mode != COMPOSE_EDIT_DRAFT) {
        $message = preg_replace("/(^|<br\/>)/i", "<br/>&gt; ", $message);
      }
      $message = "On $original_date, at $original_time, $original_from wrote:<br/>$message";
    } else {
      $plain_message = apply_filters("mailcwp_get_plain_message", $plain_message, true);
      $message = $plain_message;
      if ($mode != COMPOSE_EDIT_DRAFT) {
        //$message = preg_replace("/(^|\\n)/", "\n> ", $message);
        $message = preg_replace("/(^|\\n)/", "<br/>&gt; ", $message);
      }
      $message = "On $original_date, at $original_time, $original_from wrote:\n$message";
    }
  } else {
    $from = "$account[name] <$account[email]>";
    $to = "";
    $cc = "";
    $subject = "";
    $message = "";
  }

  $from = htmlentities($from);
  $to = htmlentities($to);
  $cc = htmlentities($cc);

  if (!empty($subject)) {
    $title = $subject;
  } else {
    $title = __("New Message", "mailcwp");
  }
  $html = "<div id=\"mailcwp_compose_dialog_$message_id\" title=\"$title\">";
  $html .= "  <form>";
  $html .= "    <fieldset>";
  $html .= "      <input type=\"hidden\" id=\"mailcwp_draft_id_$message_id\" value=\"$draft_id\">";
  $html .= "      <input type=\"hidden\" id=\"mailcwp_sent_$message_id\">";
  $html .= "      <label for=\"mailcwp_from_$message_id\">From</label>";
  $html .= "      <input type=\"text\" disabled=\"disabled\" id=\"mailcwp_from_$message_id\" name=\"mailcwp_from_$message_id\" value=\"$from\">";
  $html .= "      <label for=\"mailcwp_to_$message_id\">To</label>";
  $html .= "      <input id=\"mailcwp_to_$message_id\" name=\"mailcwp_to_$message_id\" value=\"$to\">";
  $html .= apply_filters("mailcwp_compose_address", "", "mailcwp_to_$message_id");
  $html .= "      <div id=\"mailcwp_extra_addresses_$message_id\"" . (empty($cc) ? " style=\"display: none\"" : "") . ">";
  $html .= "        <label for=\"mailcwp_cc_$message_id\">CC</label>";
  $html .= "        <input id=\"mailcwp_cc_$message_id\" name=\"mailcwp_cc_$message_id\" value=\"$cc\">";
  $html .= apply_filters("mailcwp_compose_address", "", "mailcwp_cc_$message_id");
  $html .= "        <label for=\"mailcwp_bcc_$message_id\">BCC</label>";
  $html .= "        <input id=\"mailcwp_bcc_$message_id\" name=\"mailcwp_bcc_$message_id\">";
  $html .= apply_filters("mailcwp_compose_address", "", "mailcwp_bcc_$message_id");
  $html .= "      </div>";
  $html .= "      <p><label for=\"mailcwp_subject\">Subject</label>";
  $subject_prefix = "";
  if (($mode == COMPOSE_REPLY || $mode == COMPOSE_REPLY_ALL) && $original != -1) {
    if (empty($subject) || stripos($subject, "Re:") === FALSE) {
      $subject_prefix = "Re: ";
    }
  } else if ($mode == COMPOSE_FORWARD && $original != -1) {
    if (empty($subject) || stripos($subject, "Fw:") === FALSE) {
      $subject_prefix = "Fw: ";
    }
  }

  $html .= "      <input type=\"text\" id=\"mailcwp_subject_$message_id\" name=\"mailcwp_subject_$message_id\" value=\"" . $subject_prefix . $subject . "\"></p>";
  $compose_toolbar_html = "  <div id=\"mailcwp_compose_toolbar_$message_id\">" .
  "    <button id=\"mailcwp_attach_$message_id\">" . __("Attachments", "mailcwp") . "</button>" .
  "    <button id=\"mailcwp_extra_addresses_toggle_$message_id\"><sup>CC</sup>/<sub>BCC</sub></button>";
  //"    <button id=\"mailcwp_toggle_format_$message_id\">" . __("Text/HTML", "mailcwp") . "</button>";
  if (isset($attachments) && is_array($attachments) && count($attachments) > 0) {
    $compose_toolbar_html .= "    <div id=\"forward_attachment_set_$message_id\" style=\"display: inline\">" .
    "      <button id=\"forward_attachment_$message_id\" onclick=\"jQuery('#forward-all-$message_id').submit();\">" . __("Forward Attachments", "mailcwp") . "</button>" .
    "      <button id=\"remove_attachment_$message_id\">" . __("Remove an attachment", "mailcwp") . "</button>";
    $compose_toolbar_html .= "    </div>" .
    "    <ul id=\"forward_attachment_list_$message_id\">";
    foreach ($attachments as $filename=>$data) {
      $compose_toolbar_html .= "      <li><a href=\"javascript:void(0)\" onclick=\"jQuery('#download-$filename-$message_id').submit();\">$filename</a></li>";
    }
    $compose_toolbar_html .= "    </ul>";
    $compose_toolbar_html .= "<script type=\"text/javascript\">";
    $compose_toolbar_html .= "  jQuery(\"#forward_attachment_$message_id\").button();";
    $compose_toolbar_html .= "  jQuery(\"#remove_attachment_$message_id\").button({text: false, icons: {primary: \"ui-icon-triangle-1-s\"}}).click(function() {jQuery(\"#forward_attachment_list_$message_id\").show().position({my: \"left top\", at: \"left bottom\", of: this}); jQuery(document).one(\"click\", function() {jQuery(\"#forward_attachment_list_$message_id\").hide();}); return false;});";
    $compose_toolbar_html .= "  jQuery(\"#forward_attachment_set_$message_id\").buttonset();";
    $compose_toolbar_html .= "  jQuery(\"#forward_attachment_list_$message_id\").hide().menu();";
    $compose_toolbar_html .= "</script>";
  }
  $html .= apply_filters("mailcwp_compose_toolbar", $compose_toolbar_html, $message_id);
  $html .= "  </div>";

  $html .= "      <textarea id=\"mailcwp_message_$message_id\" name=\"mailcwp_message_$message_id\" cols=\"80\" rows=\"40\">";
  if ($mode != COMPOSE_EDIT_DRAFT) {
    $html .= apply_filters("mailcwp_compose_before_message", "", $account["id"]);
  }
  $html .= "\n\n";
  $html .= $message;
  if ($mode != COMPOSE_EDIT_DRAFT) {
    $html .= apply_filters("mailcwp_compose_after_message", "", $account["id"]);
  }
  $html .= "</textarea>";
  $html .= "    </fieldset>";
  $html .= "  </form>";
  /*$html .= "<a name=\"mailcwp_compose_uploader_$message_id\"/><form id=\"mailcwp_compose_uploader_$message_id\" method=\"post\" action=\"";
  $html .= plugins_url("mailcwp-upload.php", __FILE__);
  $html .= "\">";
  $html .= "	<div id=\"uploader_$message_id\">";
  $html .= "		<p>Your browser doesn't have Flash, Silverlight or HTML5 support.</p>";
  $html .= "	</div>";
  $html .= "</form>";*/

  $html .= "<h4>" . __("Attachments", "mailcwp") . "</h4>";
  $html .= "<a name=\"mailcwp_compose_uploader_$message_id\"></a>";
  $html .= "<p>";
  $html .= "<div id=\"filelist_$message_id\">MailCWP is unable to attach files. Please contact the system administrator.</div>";
  $html .= "</p>"; 
  $html .= "<div id=\"uploader_$message_id\">";
  $html .= "    <button id=\"pickfiles_$message_id\">Select files</button>";
  $html .= "    <button id=\"removefiles_$message_id\" disabled>Remove files</button>";
  //$html .= "    <button id=\"uploadfiles_$message_id\">Upload files</button>";
  $html .= "</div>";
 
  $html .= "</div>";

  $current_user_id = get_current_user_id();
  $user_contacts = get_user_meta($current_user_id, "mailcwp_contacts", true);
//write_log("CONTACTS " . print_r($user_contacts, true));
  $javascript = "  jQuery(document).ready(function() {var vContacts = [";
  if (is_array($user_contacts) && !empty($user_contacts)) {
    foreach ($user_contacts as $user_contact) {
      $javascript .= "\"" . addslashes($user_contact) . "\",";
    }
  }
  $javascript .= "  ];";
  $javascript .= " jQuery(\"#mailcwp_to_$message_id, #mailcwp_cc_$message_id, #mailcwp_bcc_$message_id\").autocomplete({";
  $javascript .= "   source: vContacts";
  $javascript .= " });});"; 
  $javascript .= apply_filters("mailcwp_compose_javascript", "", $message_id);

  $upload_dir = wp_upload_dir();
  $mailcwp_upload_dir = "$upload_dir[basedir]/mailcwp/uploads";
  $upload_url = plugins_url("/mailcwp-upload.php", __FILE__);
  $utf8_html = @utf8_encode($html);
  if ($utf8_html == null) {
    $utf8_html = $html;
  }
  $result = array("result" => "OK", "upload_dir" => $mailcwp_upload_dir, "upload_url" => $upload_url, "html" => $utf8_html, "javascript" => $javascript);
//write_log($result);
  echo json_encode($result);
  die();
}

function create_message(&$headers, &$message, &$attachments, $for_draft = false, $via_smtp = false) {
  $to = str_replace(";", ",", $_POST["to"]);
  $cc = str_replace(";", ",", $_POST["cc"]);
  $bcc = str_replace(";", ",", $_POST["bcc"]);
  $subject = $_POST["subject"];
  $message = $_POST["message"];

  $mailcwp_session = mailcwp_get_session();
  if (!isset($mailcwp_session) || empty($mailcwp_session)) {
    return;
  }

  $account = $mailcwp_session["account"];
  $from = "$account[name] <$account[email]>";
  $mbox_name = "$account[host]:$account[port]";
  $use_ssl = $account["use_ssl"];
  $validate_cert = $account["validate_cert"];
  $username = $account["username"];
  $password = $account["password"];
  $use_tls = $account["use_tls"];
  $folder = $mailcwp_session["folder"];

  $unique_id = $_POST["unique_id"];
  $original = isset($_POST["original"]) ? $_POST["original"] : -1;
  $use_ssl_flag = $use_ssl === "true" ? "/ssl" : "";
  $validate_cert_flag = $validate_cert === "true" ? "" : "/novalidate-cert";
  $use_tls_flag = $use_tls === "true" ? "/tls" : "";
  if ($original > 0) {
    $mbox = mailcwp_imap_connect($account, 0, $folder);
    $mail_message = new MailMessage($mbox, $original);  
    $forward_attachments = $mail_message->getAttachments();
  } else {
    $forward_attachments = array();
  }

  $timezone = null;
  if (array_key_exists("timezone", $account)) {
    $tz_name = $account["timezone"];
    if (!empty($tz_name)) {
      $timezone = new DateTimeZone($tz_name);
    }
  }

  $now = new DateTime();
  if (isset($timezone)) {
    $now->setTimeZone($timezone);
  }
  
  $headers = "From: $from\r\n" .
             "Date: " . $now->format("D, d-M-Y H:i:s O") . "\r\n" . 
             "Reply-To: $from\r\n".
             "X-Mailer: MailCWP " . MAILCWP_VERSION . "\r\n";
  if ($for_draft) {
    $headers .= "To: $to\r\n" .
                "Subject: $subject\r\n";
  }
  if (!empty($cc)) {
    $headers .= "Cc: $cc\r\n";
  }
  if (!empty($bcc)) {
    $headers .= "Bcc: $bcc\r\n";
  }

  //This test for the composer is a workaround
  //This logic must be refactored to correctly handle plain text/html
  //composing and sending
  $plugin_base_path = plugin_dir_path(__FILE__) . "../";
  $composer_installed = file_exists($plugin_base_path . "mailcwp-composer");
  if ($composer_installed) {
    if (strpos($message, "<p>") === FALSE) {
      $message = nl2br($message); //, htmlspecialchars($message, ENT_COMPAT, "UTF-8", false));
    }
  }

  $plain_text_message = preg_replace("/<br[^>]*[\/]{0,1}>/i", "\n", $message);
  $plain_text_message = preg_replace("/<\/li>/i", "\n", $plain_text_message);
  $plain_text_message = preg_replace("/<p[^>]*[\/]{0,1}>/i", "\n\n", $plain_text_message);
  $plain_text_message = preg_replace("/<\/[ou]l>/i", "\n", $plain_text_message);
  $plain_text_message = html_entity_decode($plain_text_message);
  $plain_text_message = strip_tags($plain_text_message);

  $upload_dir = wp_upload_dir();
  $mailcwp_upload_dir = "$upload_dir[basedir]/mailcwp/uploads"; 
  $uploaded_files = glob("$mailcwp_upload_dir/$unique_id-*");
  if ($via_smtp) {
      $attachments = array();
      if (is_array($uploaded_files)) {
	foreach ($uploaded_files as $uploaded_file) {
          $attachments[] = $uploaded_file;
        }
      }
  } else {
    if (is_array($uploaded_files) && count($uploaded_files) > 0 || !empty($forward_attachments)) {
      $part2["type"] = TYPETEXT;
      $part2["subtype"] = "html";
      $part2["contents.data"] = $message;

      $boundary = "------=".md5(uniqid(rand()));
      $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
      $message = "\r\n\r\n--$boundary\r\nContent-Type: text/html; charset=\"ISO-8859-1\"\r\nContent-Transfer-Encoding: 8bit \r\n\r\n\r\n" . stripslashes($message);
      //process attachments
      //find files
      //if files found, read each one and generate attachment string
      if (is_array($uploaded_files)) {
	foreach ($uploaded_files as $uploaded_file) {
	  $basename = substr(basename($uploaded_file), strlen($unique_id) + 1);
	  $message .= "\r\n\r\n";
	  $message .= "--$boundary\r\n";
	  $message .= "Content-Transfer-Encoding: base64\r\n";
	  $message .= "Content-Disposition: attachment; filename=\"$basename\"\r\n"; 
	  $message .= "\r\n" . chunk_split(base64_encode(file_get_contents($uploaded_file))) . "\r\n";
	  unlink($uploaded_file);
	}
      }
      if (!empty($forward_attachments)) {
	foreach ($forward_attachments as $forward_filename => $forward_data) {
	  $message .= "\r\n\r\n";
	  $message .= "--$boundary\r\n";
	  $message .= "Content-Transfer-Encoding: base64\r\n";
	  $message .= "Content-Disposition: attachment; filename=\"$forward_filename\"\r\n"; 
	  $message .= "\r\n" . chunk_split(base64_encode($forward_data)) . "\r\n";
	}
      }
      $message .= "\r\n\r\n\r\n--$boundary--\r\n\r\n";
    } else {
      $boundary = "------=".md5(uniqid(rand()));
      $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
      $message = "\r\n\r\n--$boundary\r\nContent-Type: text/plain;\r\n\tcharset=\"ISO-8859-1\"\r\nContent-Transfer-Encoding: 8bit \r\n\r\n\r\n" . stripslashes($plain_text_message) . "\r\n\r\n--$boundary\r\nContent-Type: text/html;\r\n\tcharset=\"ISO-8859-1\"\r\nContent-Transfer-Encoding: 8bit \r\n\r\n\r\n" . stripslashes($message);;
      $message .= "\r\n\r\n\r\n--$boundary--\r\n\r\n";
    }
  }
  return true;
}

function mailcwp_save_draft_callback () {
  create_message($headers, $message, $attachments, true);

  $mailcwp_session = mailcwp_get_session();
  if (!isset($mailcwp_session) || empty($mailcwp_session)) {
    return;
  }

  $account = $mailcwp_session["account"];

  $mbox_name = "$account[host]:$account[port]";
  $username = $account["username"];
  $password = $account["password"];
  $use_ssl = $account["use_ssl"];
  $use_ssl_flag = $use_ssl === "true" ? "/ssl" : "";

  $to = $_POST["to"];
  $cc = $_POST["cc"];
  $bcc = $_POST["bcc"];
  $subject = $_POST["subject"];

  $draft_id = $_POST["draft_id"];
  $draft_folder = "";
  $result = array();
  if (is_array($account)) {
    //keep copy in sent folder
    $draft_folder = null;
    if (array_key_exists("draft_folder", $account)) {
      $draft_folder = $account["draft_folder"];
    }
    $mbox = mailcwp_imap_connect($account, OP_HALFOPEN, "");
    if (empty($draft_folder)) {
      $draft_folder = mailcwp_find_folder($mbox, $account, "Draft");
    }
    imap_close($mbox);

    if (!empty($draft_folder)) {
      $mbox = mailcwp_imap_connect($account, 0, $draft_folder);
//write_log($draft_id);
      if (!empty($draft_id)) {
        imap_delete($mbox, $draft_id);
	imap_expunge($mbox);
      }
      if (!imap_append($mbox, "{" . $mbox_name . $use_ssl_flag . "}" . $draft_folder, $headers . "\r\n" . $message . "\r\n", "\\Draft")) {
	$result["result"] = "Failed";
	$result["message"] = "Message could not be copied to draft folder ($sent_folder)";
	$result["imap_errors"] = imap_errors();
      } else {
        $check = imap_check($mbox);
        $found = false;
        for ($i = $check->Nmsgs; $i > 0 && !$found; $i--) {
          $draft_headers = imap_headerinfo($mbox, $i);
          $found = 
            ((property_exists($draft_headers, "subject") &&  $draft_headers->subject == $subject) || $subject == "") && 
            ((property_exists($draft_headers, "to") &&  $draft_headers->to == $to) || $to == "") && 
            ((property_exists($draft_headers, "cc") &&  $draft_headers->cc == $cc) || $cc == "") && 
            ((property_exists($draft_headers, "bcc") &&  $draft_headers->bcc == $bcc) || $bcc == "");
        }
        if (!$found) {
	  //$result["result"] = "Failed";
	  //$result["message"] = "Draft saved but cannot be found.";
          $draft_headers = imap_headerinfo($mbox, $check->Nmsgs);
        }
        $draft_id = $draft_headers->Msgno;
        $result["result"] = "OK";
        $result["draft_id"] = trim($draft_id);
      }
      imap_close($mbox);
    }
  }
  echo json_encode($result);
  die();
}

function mailcwp_delete_draft_callback() {
  $mailcwp_session = mailcwp_get_session();
  if (!isset($mailcwp_session) || empty($mailcwp_session)) {
    return;
  }

  $account = $mailcwp_session["account"];
  $mbox_name = "$account[host]:$account[port]";
  $username = $account["username"];
  $password = $account["password"];
  $use_ssl = $account["use_ssl"];
  $use_ssl_flag = $use_ssl === "true" ? "/ssl" : "";

  $draft_id = $_POST["draft_id"];
  $draft_folder = "";
  if (is_array($account)) {
    //keep copy in sent folder
    $draft_folder = isset($account["draft_folder"]) ? $account["draft_folder"] : null;
    $mbox = mailcwp_imap_connect($account, OP_HALFOPEN, "");
    if (empty($draft_folder)) {
      $draft_folder = mailcwp_find_folder($mbox, $account, "Draft");
    }
    imap_close($mbox);

    if (!empty($draft_folder)) {
      $mbox = mailcwp_imap_connect($account, 0, $draft_folder);
      if (!empty($draft_id)) {
        if (imap_delete($mbox, $draft_id)) {
          imap_expunge($mbox);
          $result["result"] = "OK";
        } else {
          $result["result"] = "Failed";
          $result["message"] = "Unable to delete draft";
          $result["imap_errors"] = imap_errors();
        }
      } else {
        $result["result"] = "Failed";
        $result["message"] = "No draft ID provided to delete.";
      }
      imap_close($mbox);
    }
  }
  echo json_encode($result);
  die();
}

function mailcwp_send_callback () {
  //echo print_r($_POST, true);
  $result = array();

  $to = str_replace(";", ",", $_POST["to"]);
  $cc = str_replace(";", ",", $_POST["cc"]);
  $bcc = str_replace(";", ",", $_POST["bcc"]);
  $subject = $_POST["subject"];
  $draft_id = $_POST["draft_id"];

  if (empty($to) && empty($cc) && empty($bcc)) {
    $result["result"] = "Failed";
    $result["message"] = "Please enter an address to send the mail to.";
  } else if (empty($subject)) {
    $result["result"] = "Failed";
    $result["message"] = "Please enter a subject before sending the mail.";
  } else {
    $mailcwp_session = mailcwp_get_session();
    $account = $mailcwp_session["account"];
    if (array_key_exists("smtp_host", $account)) {
      $smtp_host = $account["smtp_host"];
    } else {
      $smtp_host = '';
    }
    create_message($headers, $message, $attachments, false, !empty($smtp_host));
    if (empty($message)) {
      $result["result"] = "Failed";
      $result["message"] = "Please enter a message before sending the mail.";
    } else {
//write_log($message);
//write_log($headers);
      $to = stripslashes($to);
      $subject = stripslashes($subject);

      //check for smtp settings
      //$mailcwp_session = mailcwp_get_session();
      $account = $mailcwp_session["account"];
      if (array_key_exists("smtp_host", $account)) {
	$smtp_host = $account["smtp_host"];
	$smtp_port = $account["smtp_port"];
	$smtp_auth = $account["smtp_auth"];
	$smtp_username = $account["smtp_username"];
	$smtp_password = $account["smtp_password"];
      }

      //if smtp host is set use PHPMailer to sent mail via SMTP
      if (!empty($smtp_host)) {
        require_once("lib/class.phpmailer.php");
        require_once("lib/class.smtp.php");

        $options = get_option("mailcwp_settings", array());

	$mailer             = new PHPMailer();
        if (isset($options["smtp_connect_timeout"])) {
          $mailer->Timeout = intval($options["smtp_connect_timeout"]);
        } else {
          $mailer->Timeout = 10;
        }
	$mailer->IsSMTP();
	$mailer->SMTPAuth   = $smtp_auth;
	$mailer->Host       = $smtp_host;
        if (!empty($smtp_port)) {
  	  $mailer->Port       = $smtp_port;
        }
        if ($smtp_auth) {
  	  $mailer->Username   = $smtp_username;
	  $mailer->Password   = $smtp_password;
        }
	$mailer->SetFrom($account['email'], $account['name']);
	$mailer->AddReplyTo($account['email'], $account['name']);
	$mailer->Subject    = $subject;
	$mailer->AltBody    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test
	$mailer->MsgHTML(stripslashes($message));
        
        $to_items = explode(",", $to);
        foreach($to_items as $to_item) {
	  if (($start_address = strpos($to_item, '<')) !== FALSE) {
	    $end_address = strpos($to_item, '>');
	    $name = substr($to_item, 0, $start_address - 1);
	    $to_item = substr($to_item, $start_address + 1, $end_address - $start_address - 1);
	    $mailer->AddAddress($to_item, $name);
	  } else {
	    $mailer->AddAddress($to_item);
	  }
        }

        $cc_items = explode(",", $cc);
        foreach($cc_items as $cc_item) {
	  if (($start_address = strpos($cc_item, '<')) !== FALSE) {
	    $end_address = strpos($cc_item, '>');
	    $name = substr($cc_item, 0, $start_address - 1);
	    $cc_item = substr($cc_item, $start_address + 1, $end_address - $start_address - 1);
	    $mailer->AddCC($cc_item, $name);
	  } else {
	    $mailer->AddCC($cc_item);
	  }
        }

        $bcc_items = explode(",", $bcc);
        foreach($bcc_items as $bcc_item) {
	  if (($start_address = strpos($bcc_item, '<')) !== FALSE) {
	    $end_address = strpos($bcc_item, '>');
	    $name = substr($bcc_item, 0, $start_address - 1);
	    $bcc_item = substr($bcc_item, $start_address + 1, $end_address - $start_address - 1);
	    $mailer->AddBCC($bcc_item, $name);
	  } else {
	    $mailer->AddBCC($bcc_item);
	  }
        }

        foreach ($attachments as $attachment) {
          $mailer->AddAttachment($attachment);
        }
	$mail_sent = $mailer->Send();
      } else {
        $mail_sent = mail($to, $subject, $message, $headers);
      }
      if ($mail_sent) {
        $headers .= "To: $to\r\n" .
                    "Subject: $subject\r\n";
        $mailcwp_session = mailcwp_get_session();
        $account = $mailcwp_session["account"];

	//$account = $mailcwp_session["account"];
	$from = "$account[name] <$account[email]>";
	$mbox_name = "$account[host]:$account[port]";
	$use_ssl = $account["use_ssl"];
	$validate_cert = $account["validate_cert"];
	$username = $account["username"];
	$password = $account["password"];
	$use_tls = $account["use_tls"];
	$folder = $mailcwp_session["folder"];

	$unique_id = $_POST["unique_id"];
	$original = isset($_POST["original"]) ? $_POST["original"] : -1;
	$use_ssl_flag = $use_ssl === "true" ? "/ssl" : "";
	$validate_cert_flag = $validate_cert === "true" ? "" : "/novalidate-cert";
	$use_tls_flag = $use_tls === "true" ? "/tls" : "";

	$sent_folder = "";
	if (is_array($account)) {
	  //keep copy in sent folder
          $sent_folder = null;
          if (array_key_exists("sent_folder", $account)) {
  	    $sent_folder = $account["sent_folder"];
          }
          $mbox = mailcwp_imap_connect($account, OP_HALFOPEN, "");
	  if (empty($sent_folder)) {
	    $sent_folder = mailcwp_find_folder($mbox, $account, "Sent");
	  }
	  imap_close($mbox);

	  if (!empty($sent_folder)) {
	    $mbox = mailcwp_imap_connect($account, 0, $sent_folder);
    //write_log(print_r(imap_check($mbox)));
    //write_log("APPENDING TO FOLDER [$sent_folder] with HEADERS [$headers] and MESSAGE [$message]");
	    if (!imap_append($mbox, "{" . $mbox_name . $use_ssl_flag . "}" . $sent_folder, $headers . "\r\n" . $message . "\r\n", "\\Seen")) {
	      $result["result"] = "OK";
	      $result["message"] = "Message could not be copied to sent folder ($sent_folder)";
	      $result["imap_errors"] = imap_errors();
	    }
	    imap_close($mbox);
	  }
	  if ($original != -1) {
	    $mbox = mailcwp_imap_connect($account, 0, $folder);
    //write_log(print_r(imap_check($mbox)));
    //write_log("MARKING MESSAGE [$original] in [$folder] ANSWERED");
	    if (!imap_setflag_full($mbox, $original, "\\Answered")) {
	      $result["result"] = "OK";
	      $result["message"] = "Unable to flag message as answered.";
	      $result["imap_errors"] = imap_errors();
	    }
	    imap_close($mbox);
	  }
	}
	if (empty($result)) {
	  $result["result"] = "OK";
	}

	$current_user_id = get_current_user_id();
	if ($current_user_id != 0) {
    //delete_user_meta($current_user_id, "mailcwp_contacts");
	  $options = get_option("mailcwp_settings", array());
	  $max_contacts = isset($options["max_contacts"]) ? $options["max_contacts"] : 100;
	  $user_contacts = get_user_meta($current_user_id, "mailcwp_contacts", true);
	  if ($user_contacts == null) {
	   $user_contacts = array();
	  } else if (count($user_contacts) > $max_contacts) {
            $count = count($user_contacts);
            $unset_index = 0;
	    while ($count > $max_contacts) {
	      unset($user_contacts[$unset_index++]);
              $count -= 1;
	    }
	  }

          if (!empty($to)) {
            $to_items = explode(",", $to);
            foreach($to_items as $to_item) {
    	      if (!in_array($to_item, $user_contacts)) {
	        $user_contacts[] = $to_item;
	      }
            }
          }
          if (!empty($cc)) {
            $cc_items = explode(",", $cc);
            foreach($cc_items as $cc_item) {
    	      if (!in_array($cc_item, $user_contacts)) {
	        $user_contacts[] = $cc_item;
	      }
            }
          }
          if (!empty($bcc)) {
            $bcc_items = explode(",", $bcc);
            foreach($bcc_items as $bcc_item) {
    	      if (!in_array($bcc_item, $user_contacts)) {
	        $user_contacts[] = $bcc_item;
	      }
            }
          }
	  update_user_meta($current_user_id, "mailcwp_contacts", $user_contacts);
	}
      } else {
	$result["result"] = "Failed";
	$result["message"] = "Message could not be sent.";
	$result["imap_errors"] = imap_errors();
      }
    }
  }

  echo json_encode($result);
  die();
}

function mailcwp_mark_read_callback () {
  $mailcwp_session = mailcwp_get_session();
  if (!isset($mailcwp_session) || empty($mailcwp_session)) {
    return;
  }

  $account = $mailcwp_session["account"];
  //$from = $account["email"];
  $folder = $mailcwp_session["folder"];

  $messages = $_POST["messages"];

  $mbox = mailcwp_imap_connect($account, 0, $folder);
  imap_setflag_full($mbox, $messages, "\\Seen");
  //mailcwp_get_unseen_messages($account);
  //write_log(print_r(imap_errors(), true));
  echo json_encode(array ("result" => "OK"));
  die();
}

function mailcwp_mark_unread_callback () {
  $mailcwp_session = mailcwp_get_session();
  if (!isset($mailcwp_session) || empty($mailcwp_session)) {
    return;
  }

  $account = $mailcwp_session["account"];
  //$from = $account["email"];
  $folder = $mailcwp_session["folder"];

  $messages = $_POST["messages"];

  $mbox = mailcwp_imap_connect($account, 0, $folder);
  imap_clearflag_full($mbox, $messages, "\\Seen");
  //mailcwp_get_unseen_messages($account);
  //write_log(print_r(imap_errors(), true));
  echo json_encode(array ("result" => "OK"));
  die();
}

function mailcwp_delete_callback () {
  $result = array();

  $mailcwp_session = mailcwp_get_session();
  if (!isset($mailcwp_session) || empty($mailcwp_session)) {
    return;
  }

  $account = $mailcwp_session["account"];
  $use_ssl = $account["use_ssl"];
  //$from = $account["email"];
  $folder = $mailcwp_session["folder"];
  $use_tls = $account["use_tls"];

  $messages = $_POST["messages"];
//write_log($messages);
//write_log("DELETE MAIL " . print_r($account, true));
  $use_ssl_flag = $use_ssl === "true" ? "/ssl" : "";
  $use_tls_flag = $use_tls === "true" ? "/tls" : "";
  $mbox = mailcwp_imap_connect($account, 0, $folder);
//write_log("SEND ACCOUNT " . print_r($account));
  $sent_folder = "";
  if (is_array($account)) {
    $trash_folder = $account["trash_folder"];
    if (empty($trash_folder)) {
      $trash_folder = mailcwp_find_folder($mbox, $account, "Trash");
      if (empty($trash_folder)) {
        $trash_folder = mailcwp_find_folder($mbox, $account, "Deleted");
      }
    }
    if (!empty($trash_folder) && $trash_folder != $folder) {
//write_log("DELETE MOVE MESSAGES " . print_r($messages, true));
      imap_mail_move($mbox, $messages, $trash_folder);
      imap_expunge($mbox);
      $result["result"] = "OK";
      $result["message"] = "Moved to $trash_folder";
      $result["imap_errors"] = imap_errors();
    } else {
      imap_setflag_full($mbox, $messages, "\\Deleted");
      $result["result"] = "OK";
      if (!empty($trash_folder) && $folder == $trash_folder) {
	imap_expunge($mbox);
	$result["message"] = "Permanently Deleted";
      } else {
	$result["message"] = "Marked as Deleted";
      }
      $result["imap_errors"] = imap_errors();
    }
  } else {
    $result["result"] = "Failed";
    $result["message"] = "Account not found.";
  }
  //write_log("DELETE MESSAGES ($messages) " . print_r(imap_errors(), true));
  echo json_encode($result);
  die();
}

function mailcwp_set_page_size_callback() {
  $result = array();

  if (isset($_POST["page_size"])) {
    $mailcwp_session = mailcwp_get_session();
    $account = $mailcwp_session["account"];
    if (isset($account)) {
      $current_user = wp_get_current_user();
      $account["page_size"] = $_POST["page_size"];
      $mailcwp_session["account"] = $account;

      $user_mail_accounts = get_user_meta($current_user->ID, "mailcwp_accounts", true);
      $user_mail_accounts[$account["id"]] = $account;

      update_user_meta($current_user->ID, "mailcwp_accounts", $user_mail_accounts);
      update_user_meta($current_user->ID, "mailcwp_session", $mailcwp_session);

      $result["result"] = "OK";
      $result["message"] = "Page size set";
    } else {
      $result["result"] = "Failed";
      $result["message"] = "Account not found";
    }
  } else {
    $result["result"] = "Failed";
    $result["message"] = "No page size provided";
  }
  echo json_encode($result);
  die();
}

/*function mailcwp_folder_settings_callback() {
write_log("FOLDER SETTINGS");
  $account_to_edit = null;
  if (isset($_POST["account_id"])) {
    $account_id = $_POST["account_id"];
    if (!isset($_POST["user_id"]) || empty($_POST["user_id"])) {
      $current_user = wp_get_current_user();
      $user_id = $current_user->ID;
    } else {
      $user_id = $_POST["user_id"];
    }
    $accounts = get_user_meta($user_id, "mailcwp_accounts", true);
    $account_to_edit = isset($accounts[$account_id]) ? $accounts[$account_id] : null;
  } else {
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    if (isset($_POST["account_name"]) && !empty($_POST["account_name"])) {
      $account_name = $_POST["account_name"];
      $accounts = get_user_meta($user_id, "mailcwp_accounts", true);

      foreach ($accounts as $account) {
        if ($account["name"] == $account_name) {
          $acccount_to_edit = $account;
        }
      }
    }
  }
  $full_form = isset($_POST["full_form"]) ? $_POST["full_form"] : true;
write_log("ECHOING EDIT DIALOG");
  echo mailcwp_account_edit_dialog($user_id, $account_to_edit, $full_form);
  die();
}*/

function mailcwp_change_theme_callback() {
//write_log($_POST);
  $current_user = wp_get_current_user();
  $user_id = $current_user->ID;
  $options = get_user_meta($current_user->ID, "mailcwp_options", true);
  if (is_array($options)) {
    $options = array();
  }
  $options["theme"] = $_POST["theme"];
  $options["theme_name"] = $_POST["theme_name"];
//write_log($options);
  update_user_meta($current_user->ID, "mailcwp_options", $options);
  die();
}

function mailcwp_compose_close_callback() {
  echo apply_filters("mailcwp_compose_close_javascript", "", $_POST["message_id"]);
  die();
}

function mailcwp_handler ($atts, $content, $tag) {
  $smarty = new Smarty;
  $smarty->setTemplateDir(plugin_dir_path(__FILE__) . 'templates');
  $smarty->setCompileDir(plugin_dir_path(__FILE__) . 'templates_c');
  $smarty->setCacheDir(plugin_dir_path(__FILE__) . '/cache');

  if (!is_user_logged_in()) {
    //wp_die(__("Please login to view this page.", "mailcwp"));
    //wp_redirect(wp_login_url("$_SERVER[PHP_SELF]"));
    echo "<p>Please login to view this page.</p><p>You will be redirected in a moment. If you are not redirected click <a href=\"#\" onclick=\"window.location = '" . wp_login_url() . "?redirect_to=' + window.location.href;\">here</a>.<script type=\"text/javascript\">window.location = '" . wp_login_url() . "?redirect_to=' + window.location.href;</script>";
    exit;
  }

  //add_filter( 'show_admin_bar', '__return_false' );

  $options = get_option("mailcwp_settings", array());

  $hide_admin_bar = isset($options["hide_admin_bar"]) ? $options["hide_admin_bar"] : true;
  $hide_page_title = isset($options["hide_page_title"]) ? $options["hide_page_title"] : true;
  if (isset($options) && !empty($options)) {
    imap_timeout(isset($options["imap_open_timeout"]) ? $options["imap_open_timeout"] : 15, IMAP_OPENTIMEOUT);
    imap_timeout(isset($options["imap_read_timeout"]) ? $options["imap_read_timeout"] : 15, IMAP_READTIMEOUT);
    imap_timeout(isset($options["imap_write_timeout"]) ? $options["imap_write_timeout"] : 15, IMAP_WRITETIMEOUT);
    imap_timeout(isset($options["imap_close_timeout"]) ? $options["imap_close_timeout"] : 15, IMAP_CLOSETIMEOUT);
    //write_log("TIMEZONE SET " . date_default_timezone_set('Australia/Melbourne'));
  }

  $current_user = wp_get_current_user();
  $accounts = get_user_meta($current_user->ID, "mailcwp_accounts", true);
  $default_account_id = get_user_meta($current_user->ID, "mailcwp_account_default", true);

//write_log("CURRENT USER " . print_r($current_user, true));
//write_log("Accounts " . print_r($accounts, true));
  $smarty->assign('labels', 
    array(
      "compose" => __("Compose New Message", "mailcwp"),
      "refresh" => __("Refresh Folders", "mailcwp"),
      "options" => __("Options", "mailcwp"),
      "themes" => __("Theme", "mailcwp"),
      "mark_read" => __("Mark Read", "mailcwp"),
      "mark_unread" => __("Mark Unread", "mailcwp"),
      "delete" => __("Delete", "mailcwp"),
      "search" => __("Find Messages", "mailcwp")));
  $smarty->assign('user_change_theme', isset($options) && isset($options["user_change_theme"]) ? $options["user_change_theme"] : true); 
  $smarty->assign('set1_extensions', apply_filters("mailcwp_toolbar_set1", ""));
  $smarty->assign('set2_extensions', apply_filters("mailcwp_toolbar_set2", ""));
  $smarty->assign('set_search_extensions', apply_filters("mailcwp_toolbar_set_search", ""));
  $smarty->assign('progress_bar_src', plugins_url("/img/ajax_loading.gif", __FILE__));
  
  echo apply_filters("mailcwp_toolbar", $smarty->fetch('toolbar.tpl'));
  //$smarty->clear_all_assign();
  delete_user_meta($current_user->ID, 'mailcwp_session');
  $mailcwp_session = null;
  /*$smarty->assign('accounts', $accounts);
  $passwords = array();
  $folders = array();
  if (is_array($accounts)) {
    foreach ($accounts as $account) {
      if (isset($account["id"])) {
        $password = mailcwp_get_account_password($account);
        $passwords[$account["id"]] = $password;
      }
    }
  }
  $smarty->assign('passwords', $passwords);*/
  
?>
  <div id="mailcwp_container" class="ui-widget-content">
    <div id="mailcwp_accounts" class="ui-widget-content">
      <?php
      if (is_array($accounts)) {
        for ($mode = 0; $mode <= 1; $mode++) {
	  foreach ($accounts as $account) {
	    if (isset($account["id"])) {
              if (($mode == 0 && isset($default_account_id) && $account["id"] == $default_account_id) ||
                  ($mode == 1 && isset($default_account_id) && $account["id"] != $default_account_id) ||
                  !isset($default_account_id)) {
	    ?>
	      <h3 id="<?php echo $account["id"] ?>"><?php echo $account["name"] ?></h3>
	      <div>
		<?php
		  $password = mailcwp_get_account_password($account);
		  //present password page
		  if (empty($password)) {
		    echo mailcwp_login_dialog($account["id"]);
		    echo "</div>";
		  } else {
		    $mbox_name = $account["host"] . ":" . $account["port"];
		    $use_ssl_flag = $account["use_ssl"] ? "/ssl" : "";
		    $use_tls_flag = $account["use_tls"] ? "/tls" : "";
      //write_log("ACCOUNT $account[name]: $account[use_ssl]");
		    $mbox = mailcwp_imap_connect($account, OP_HALFOPEN, "");
		    if (isset($mbox)) {
		      $folders = imap_listmailbox($mbox, "{" . $mbox_name . $use_ssl_flag . "}", "*");
		      if ($folders === false) {
			echo __("No folders found.", "mailcwp");
		      } else {
			$folder_tree = array();
			foreach ($folders as $folder) {
			  $folder_name = substr($folder, strlen($mbox_name) + strlen($use_ssl_flag) + 2);
			  if ($mailcwp_session == null) {
			    $mailcwp_session = array();
			    $mailcwp_session["account"] = $account;
			    $mailcwp_session["folder"] = $folder_name;
			    update_user_meta($current_user->ID, "mailcwp_session", $mailcwp_session);
			  }
			  add_folder_to_tree($folder_tree, $folder_name);
			}
			?>
			<?php
			  echo folder_menu($account["id"], $folder_tree, "", true);
			?>
			<?php
		      }

		      //$headers = imap_headers($mbox);
		      imap_close($mbox);
		    } else {
			echo __("Unable to open mailbox. Please check the username and password.", "mailcwp");
		    }
		    /*$folder_toolbar_html = "  <div id=\"mailcwp_folder_toolbar_$account[name]\" class=\"mailcwp_folder_toolbar ui-widget-header ui-corner-all\">" .
		   (isset($options["user_manage_accounts"]) && $options["user_manage_accounts"] == true ? 
		    "    <button id=\"mailcwp_folder_settings_$account[id]\" class=\"mailcwp_folder_settings\">" . __("Account Settings", "mailcwp") . "</button>" .
		    "    <button id=\"mailcwp_close_account_$account[id]\" data=\"" . str_replace(" ", "_", $account["name"]) . "\" class=\"mailcwp_close_account\">" . __("Remove Account", "mailcwp") . "</button>" : "") .
		    "  </div>";
		    echo $folder_toolbar_html;*/
		  ?>
		</div>
	      <?php
	    }
	    }
          }
	  }
        }
        /*if (isset($options["user_manage_accounts"]) && $options["user_manage_accounts"] == true) {
        ?>
        <h3 id="mailcwp_add_account">Add Account</h3>
        <?php
        }*/
      }
      ?>
    </div>
    <div id="mailcwp_headers" class="ui-widget-content ui-corner-bottom">
       <?php _e(is_array($accounts) && count($accounts) > 0 ? "No messages found." : "No accounts found. Please contact the sysadmin administrator for assistance.", "mailcwp") ?>
    </div>
  </div>
  <div id="mailcwp_prologue">
    <span><a href="http://cadreworks.com/mailcwp-plugin">MailCWP version <?php echo MAILCWP_VERSION ?> by CadreWorks Pty Ltd, <?php echo date("Y") ?></a></span>
  </div>
  <span id="dummy" style="height:0"></span>
  <script type="text/javascript">
    <?php if ($hide_admin_bar) { ?>
      jQuery(document).ready(function() {
        jQuery("#wpadminbar").toggle();
        jQuery(document.body).offset({top: 0});
      });
    <?php } ?>
    <?php if ($hide_page_title) { ?>
      jQuery(document).ready(function() {
        jQuery("h1").toggle();
      });
    <?php } ?>
    <?php if (!isset($options["user_manage_accounts"]) || $options["user_manage_accounts"] === true) { ?>
    <?php if (!is_array($accounts) || count($accounts) == 0) { ?>
        //jQuery(document).ready(editAccount(null, null, null, false));
        jQuery(document).ready(function(){jQuery("#mailcwp_options").click()});
    <?php   } ?>
    <?php } ?>
    <?php if (isset($options["auto_refresh"]) && $options["auto_refresh"] == true && isset($options["refresh_interval"])) { ?>
      mRefreshInterval = <?php echo $options["refresh_interval"] ?>;
    <?php } else { ?>
      mRefreshInterval = 0;
    <?php }
    $mailcwp_options = get_user_meta($current_user->ID, "mailcwp_options", true);
//write_log("MAILCWP OPTIONS: " . print_r($mailcwp_options, true));
    if (is_array($mailcwp_options) && isset($mailcwp_options["theme"])) {
    ?>
      mThemeURL = "<?php echo $mailcwp_options["theme"] ?>";
      mThemeName = "<?php echo $mailcwp_options["theme_name"] ?>";
    <?php
    }
    ?>
  </script>
<?php
  //mailcwp_get_unseen_messages();
}

function folder_menu($account_id, $folder_tree, $prefix = "", $root = false) {
  $current_user = wp_get_current_user();
  $account = mailcwp_get_account($current_user->ID, $account_id);
  $default_folder = isset($account["default_folder"]) ? $account["default_folder"] : null;
  if (!isset($efault_folder) || $default_folder == '') {
    $default_folder = 'INBOX';
  }

  $result = "";
  $class = $root ? "mailcwp_folders" : "";
  if (!empty($folder_tree)) {
    $result = "<ul class=\"$class\">";
    /*if (isset($folder_tree["INBOX"])) {
      $key = 'INBOX';*/
    if (isset($folder_tree[$default_folder])) {
      $key = $default_folder;
      $result .= "<li><a href=\"javascript:void(0)\" onclick=\"getHeaders(false, 1, '$prefix$key', '$account_id')\">$key</a>";
      $result .= folder_menu($account_id, $folder_tree[$key], "$prefix$key.");
      $result .= "</li>";
    }
    $keys = array_keys($folder_tree);
    foreach ($keys as $key) {
      //if ($key != 'INBOX') {
      if ($key != $default_folder) {
	$result .= "<li><a href=\"javascript:void(0)\" onclick=\"getHeaders(false, 1, '$prefix$key', '$account_id')\">$key</a>";
	$result .= folder_menu($account_id, $folder_tree[$key], "$prefix$key.");
	$result .= "</li>";
      }
    }
    $result .= "</ul>";
  }
  return $result;
}

function add_folder_to_tree(&$folder_tree, $folder) {
  $parts = explode(".", $folder);
  $part1 = array_shift($parts);
  if (!isset($folder_tree[$part1])) {
    $folder_tree[$part1] = array();
  }
  if (!empty($parts)) {
    add_folder_to_tree($folder_tree[$part1], implode(".", $parts));
  }
}

/*function mailcwp_get_account_by_mailbox($mailbox, $username) {
  $result = null;

  $current_user = wp_get_current_user();
  $accounts = get_user_meta($current_user->ID, "mailcwp_accounts", true);
  if (is_array($accounts)) {
    $parts = explode(":", $mailbox);
    foreach ($accounts as $account) {
      if ($account["host"] == $parts[0] && $account["port"] == $parts[1] && $account["username"] == $username) {
        $result = $account;
      }
    }
  }

  return $result;
}*/

function mailcwp_get_imap_decoded_string($string) {
  $string_decode_array = imap_mime_header_decode($string);
  if (is_array($string_decode_array) && count($string_decode_array) > 0) {
    $decoded_string = "";
    foreach ($string_decode_array as $string_decode_item) {
      $decoded_string .= $string_decode_item->text;
    }
  } else {
    $decoded_string = $string;
  }
  return $decoded_string;
}

function mailcwp_find_folder($mbox, $account, $name) {
  $found_folder = null;

  $mbox_name = "$account[host]:$account[port]";
  $use_ssl_flag = $account["use_ssl"] ? "/ssl" : "";
//write_log($mbox_name . " - " . $use_ssl_flag);
  $folders = imap_listmailbox($mbox, "{" . $mbox_name . $use_ssl_flag . "}", "*");
  foreach($folders as $folder) {
//write_log($folder);
    if (stripos($folder, $name)) {
      $found_folder = substr($folder, strlen($mbox_name) + strlen($use_ssl_flag) + 2);
    }
  }

  return $found_folder;
}

add_action( 'admin_bar_menu', 'toolbar_link_to_mypage', 999 );

function toolbar_link_to_mypage( $wp_admin_bar ) {
  $node = $wp_admin_bar->get_node("user-actions");
  if ($node) {
    $url = "/mail";
    $pages = get_pages();
    echo "<pre>";
    for ($i = 0; $i < count($pages) && $url == "/mail"; $i++) {
      $page = $pages[$i];
      if (has_shortcode($page->post_content, "mailcwp")) {
        $url = $page->guid;
      }
    }
    echo "</pre>";
    $args = array(
      'id' => 'mailcwp',
      'title' => 'Mail',
      'href' => $url,
      'parent' => "user-actions"
    );
    $wp_admin_bar->add_node($args);
  }
}
