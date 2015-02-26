<div id="mailcwp_container" class="ui-widget-content">
  <div id="mailcwp_accounts" class="ui-widget-content">
      <?php
      delete_user_meta($current_user->ID, 'mailcwp_session');
      $mailcwp_session = null;
      if (is_array($accounts)) {
	foreach ($accounts as $account) {
          if (isset($account["id"])) {
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
		  $folders = imap_listmailbox($mbox, "{" . $mbox_name . $use_ssl_flag . "}", "*");
		  if ($folders == false) {
		    echo "Call failed";
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
		  /*$folder_toolbar_html = "  <div id=\"mailcwp_folder_toolbar_$account[name]\" class=\"mailcwp_folder_toolbar ui-widget-header ui-corner-all\">" .
		 (isset($options["user_manage_accounts"]) && $options["user_manage_accounts"] == true ? 
		  "    <button id=\"mailcwp_folder_settings_$account[id]\" class=\"mailcwp_folder_settings\">" . __("Account Settings") . "</button>" .
		  "    <button id=\"mailcwp_close_account_$account[id]\" data=\"" . str_replace(" ", "_", $account["name"]) . "\" class=\"mailcwp_close_account\">" . __("Remove Account") . "</button>" : "") .
		  "  </div>";
		  echo $folder_toolbar_html;*/
		?>
	      </div>
	    <?php
          }
          }
	}
      }
      ?>
  </div>
  <div id="mailcwp_headers" class="ui-widget-content ui-corner-bottom">
       <?php _e(is_array($accounts) && count($accounts) > 0 ? "No messages found." : "No accounts found.") ?>
  </div>
</div>
<div id="mailcwp_prologue">
  <span><a href="http://cadreworks.com/mailcwp-plugin">MailCWP version 1.0 by CadreWorks Pty Ltd, 2014</a></span>
</div>
