jQuery(document).ready(function() {
    /*jQuery( "#dialog-form" ).dialog({
      dialogClass: 'wp-dialog', 
      autoOpen: false,
      height: 350,
      width: 350,
      modal: true,
      buttons: {
        "Test": function() {
          bValid = validateForm();
          if (bValid) {
            form_data = getFieldsFromForm("#dialog-form");
            form_data.action = "mailcwp_test_account";
            jQuery.ajax ({
              type: "post", 
              url: ajaxurl, 
              data: form_data,
              error: function(aObject, aTextStatus, aErrorThrown) { 
                alert("Unable to complete test: " + aTextStatus + "/" + aErrorThrown); 
              }, 
              success: function (aHtml) { 
                alert(aHtml);
              }
            });
          }
        },
        "Submit": function() {
          bValid = validateForm();
          if (bValid) {
            form_data = getFieldsFromForm("#dialog-form");
            form_data.action = "mailcwp_edit_account";
            jQuery.ajax ({
              type: "post", 
              url: ajaxurl, 
              data: form_data,
              error: function(aObject, aTextStatus, aErrorThrown) { 
                alert("Unable to clear cache: " + aTextStatus + "/" + aErrorThrown); 
              }, 
              success: function (aHtml) { 
                alert(aHtml);
                document.location.reload();
              }
            });
            jQuery( this ).dialog( "close" );
          }
        },
        Cancel: function() {
          jQuery( this ).dialog( "close" );
        }
      },
      close: function() {
        //allFields.val( "" ).removeClass( "ui-state-error" );
      }
    });*/
  jQuery( "#mailcwp-add-account" ).click(function() {
    editAccount(null, null, jQuery(this).attr("data"), false);
  });
  jQuery("#account-edit-save").button().click(function () {
    jQuery.ajax({
      type: "POST",
      url: ajaxurl,
      data: {
	action: "mailcwp_edit_account",
	mailcwp_user_id: jQuery("#mailcwp_user_id").val(),
	mailcwp_account_id: jQuery("#mailcwp_account_id").val(),
	mailcwp_name: jQuery("#mailcwp_name").val(),
	mailcwp_host: jQuery("#mailcwp_host").val(),
	mailcwp_port: jQuery("#mailcwp_port").val(),
	mailcwp_use_ssl: jQuery("#mailcwp_use_ssl").prop("checked"),
	mailcwp_validate_cert: jQuery("#mailcwp_validate_cert").prop("checked"),
	mailcwp_email: jQuery("#mailcwp_email").val(),
	mailcwp_username: jQuery("#mailcwp_username").val(),
	mailcwp_password: jQuery("#mailcwp_password").val(),
	mailcwp_use_tls: jQuery("#mailcwp_use_tls").prop("checked"),
      },
      success: function(aData) {
        vData = jQuery.parseJSON(aData);
        if (vData.hasOwnProperty("result") && vData.result == "OK") {
          jQuery('#account-edit-form').hide(); 
          jQuery('#account-profile-table').show();
          //display_notice("Account saved.");
          alert("Account saved.");
          window.location.reload();
        } else {
          processAjaxResponse(aData);
        }
      },
      error: function(aObject, aTextStatus, aErrorThrown) {
        //display_notice("Error: " + aTextStatus + "/" + aErrorThrown);
        alert("Error: " + aTextStatus + "/" + aErrorThrown);
      }
    });
  });
  jQuery("#account-edit-test").button().click(function () {
    jQuery.ajax({
      type: "POST",
      url: ajaxurl,
      data: {
        action: "mailcwp_test_account",
        mailcwp_name: jQuery("#mailcwp_name").val(),
        mailcwp_host: jQuery("#mailcwp_host").val(),
        mailcwp_port: jQuery("#mailcwp_port").val(),
        mailcwp_use_ssl: jQuery("#mailcwp_use_ssl").prop("checked"),
        mailcwp_validate_cert: jQuery("#mailcwp_validate_cert").prop("checked"),
        mailcwp_email: jQuery("#mailcwp_email").val(),
        mailcwp_username: jQuery("#mailcwp_username").val(),
        mailcwp_password: jQuery("#mailcwp_password").val(),
        mailcwp_use_tls: jQuery("#mailcwp_use_tls").prop("checked"),
      },
      success: function(aData) {
        try {
          vData = jQuery.parseJSON(aData);
          if (vData.hasOwnProperty("result") && vData.result == "OK") {
            //display_notice("Test Succeeded");
            alert("Test Succeeded");
          } else {
            processAjaxResponse(aData);
          }
        } catch (e) {
          //display_notice("Error " + e.message);
          alert("Error " + e.message);
        }
      },
      error: function(aObject, aTextStatus, aErrorThrown) {
        //display_notice("Error: " + aTextStatus + "/" + aErrorThrown);
        alert("Error: " + aTextStatus + "/" + aErrorThrown);
      },
    });
  });
});

function editAccount(aName, aAccountId, aUserId, aFullForm) {
  if (aAccountId == null) {
    displayAccount(null, aUserId);
  } else {
    jQuery.ajax({
      type: "POST",
      url: ajaxurl,
      data: {
	action: "mailcwp_get_account",
	account_id: aAccountId,
	user_id: aUserId,
      },
      success: function(aData) {
  //console.log(aData);
	vData = jQuery.parseJSON(aData);
	if (vData.hasOwnProperty("result") && vData.result == "OK") {
          displayAccount(vData.account, aUserId);
	  /*jQuery("#mailcwp_id").val(vData.account.id);
	  jQuery("#mailcwp_name").val(vData.account.name);
	  jQuery("#mailcwp_email").val(vData.account.email);
	  jQuery("#mailcwp_host").val(vData.account.host);
	  jQuery("#mailcwp_port").val(vData.account.port);
	  jQuery("#mailcwp_use_ssl").prop("checked", vData.account.use_ssl === "true");
	  jQuery("#mailcwp_validate_cert").prop("checked", vData.account.validate_cert === "true");
	  jQuery("#mailcwp_username").val(vData.account.username);
	  jQuery("#mailcwp_password").val(vData.account.password);
	  jQuery("#mailcwp_confirm_password").val(vData.account.password);
	  jQuery("#mailcwp_use_tls").prop("checked", vData.account.use_tls === "true");
	  jQuery("#account-profile-table").hide();
	  jQuery("#account-edit-form").show();*/
	} else {
	  processAjaxResponse(aData);
	}
      },
      error: function(aObject, aTextStatus, aErrorThrown) {
	//display_notice("Error: " + aTextStatus + "/" + aErrorThrown);
	alert("Error: " + aTextStatus + "/" + aErrorThrown);
	   
      }
    });
  }
}

function displayAccount($account, $user_id) {
  jQuery("#mailcwp_account_id").val($account == null ? "" : $account.id);
  jQuery("#mailcwp_user_id").val($user_id == null ? "" : $user_id);
  jQuery("#mailcwp_name").val($account == null ? "" : $account.name);
  jQuery("#mailcwp_email").val($account == null ? "" : $account.email);
  jQuery("#mailcwp_host").val($account == null ? "" : $account.host);
  jQuery("#mailcwp_port").val($account == null ? "" : $account.port);
  jQuery("#mailcwp_use_ssl").prop("checked", $account == null ? false : $account.use_ssl === "true");
  jQuery("#mailcwp_validate_cert").prop("checked", $account == null ? false : $account.validate_cert === "true");
  jQuery("#mailcwp_username").val($account == null ? "" : $account.username);
  jQuery("#mailcwp_password").val($account == null ? "" : $account.password);
  jQuery("#mailcwp_confirm_password").val($account == null ? "" : $account.password);
  jQuery("#mailcwp_use_tls").prop("checked", $account == null ? false : $account.use_tls === "true");
  jQuery("#account-profile-table").hide();
  jQuery("#account-edit-form").show();
}
