var mDisableErrorNotice = false;

window.onbeforeunload = function() {
  mDisableErrorNotice = true;
};

function processAjaxResponse(aResponse) {
  //console.log(aResponse);
  try {
    vData = jQuery.parseJSON(aResponse);
  } catch (e) {
    display_notice("Unable to parse Ajax response. " + e.message);
    return false;
  }

  if (vData.hasOwnProperty("result") && vData.result == "OK") {
    if (vData.hasOwnProperty("message")) {
      display_notice(vData.message);
    }
    if (vData.hasOwnProperty("html")) {
      //console.log(vData.html);
      jQuery(document.body).append(vData.html);
    }
    if (vData.hasOwnProperty("javascript")) {
      try {
        //console.log(vData.javascript);
	eval(vData.javascript);
        return true;
      } catch (e) {
	display_notice("Error " + e.message);
      }
    } else {
      return true;
    }
  } else {
    if (vData.hasOwnProperty("message")) {
      display_notice(vData.message);
    } else {
      console.log(vData);
      display_notice("An unexpected error has occurred.");
    }
    return false;
  }
}  

function display_notice(aNotice){
  if (!mDisableErrorNotice) {
    vNoticeId = (Date.now() + "_" + (Math.random() * 1000)).replace(".", "_");
    vDialogId = "mailcwp_notice_" + vNoticeId;
    vHtml = "<div id=\"" + vDialogId + "\" title=\"Notice\">";
    vHtml += aNotice;
    vHtml += "</div>";
    jQuery(document.body).append(vHtml);
    jQuery("#" + vDialogId).dialog({
      close: function() {
	jQuery("#" + vDialogId).remove();
      }
    });
  }
}

function updateTips( t ) {
  var tips = jQuery( ".validateTips" );
  tips
    .text( t )
    .addClass( "ui-state-highlight" );
  setTimeout(function() {
    tips.removeClass( "ui-state-highlight", 1500 );
  }, 500 );
}

function checkLength( o, n, min, max ) {
  if ( o.val().length > max || o.val().length < min ) {
    o.addClass( "ui-state-error" );
    updateTips( "Length of " + n + " must be between " +
      min + " and " + max + "." );
    return false;
  } else {
    return true;
  }
}

function checkRegexp( o, regexp, n ) {
  if ( !( regexp.test( o.val() ) ) ) {
    o.addClass( "ui-state-error" );
    updateTips( n );
    return false;
  } else {
    return true;
  }
}

function checkMatch( o1, o2, n ) {
  if ( o1.val() != o2.val() ) {
    o1.addClass( "ui-state-error" );
    o2.addClass( "ui-state-error" );
    updateTips( n );
    return false;
  } else {
    return true;
  }
}

function validateForm(aForm) {
  var form_data = getFieldsFromForm(aForm),
      allFields = jQuery([]).add( jQuery("mailcwp_name") ).add( jQuery("mailcwp_host") ).add( jQuery("mailcwp_email") ), //.add( password ).add( confirm_password ),
      tips = jQuery( ".validateTips" );

  var bValid = true;
  allFields.removeClass( "ui-state-error" );

  bValid = bValid && checkLength( jQuery("#mailcwp_name"), "name", 3, 80 );
  bValid = bValid && checkLength( jQuery("#mailcwp_username"), "username", 1, 255 );
  bValid = bValid && checkLength( jQuery("#mailcwp_host"), "host", 3, 80 );
  bValid = bValid && checkLength( jQuery("#mailcwp_email"), "email", 6, 80 );
  //bValid = bValid && checkLength( password, "password", 5, 16 );

  bValid = bValid && checkRegexp( jQuery("#mailcwp_name"), /^[a-z]([0-9a-z_ ])+$/i, "Username may consist of a-z, 0-9, spaces, underscores, begin with a letter." );
  bValid = bValid && checkRegexp( jQuery("#mailcwp_email"), /^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i, "Please enter a valid email address, eg. someone@somewhere.com" );
  //bValid = bValid && checkRegexp( password, /^([0-9a-zA-Z])+$/, "Password field only allow : a-z 0-9" );
		       
  bValid = bValid && checkMatch( jQuery("#mailcwp_password"), jQuery("#mailcwp_confirm_password"), "Passwords do not match" );
  return bValid;
}

function getFieldsFromForm(aForm) {
  fields = {};
  jQuery(aForm + " input," + aForm + " select," + aForm + " textarea").each(function (index) {
    if (jQuery(this).attr("type") == "checkbox") {
      fields[jQuery(this).attr("name")] = jQuery(this).is(":checked");
    } else {
      fields[jQuery(this).attr("name")] = jQuery(this).val();
    }
  });
  //console.log(fields);
  return fields;
}

/*function editAccount(aAccountName, aAccountId, aUserId, aFullForm) {
  ajaxurl = typeof ajax_object != 'undefined' ? ajax_object.ajax_url : ajaxurl;
  jQuery.ajax ({
    type: "post", 
    url: ajaxurl, 
    data: { 
      action: "mailcwp_folder_settings",
      account_name: aAccountName,
      account_id: aAccountId,
      user_id: aUserId,
      full_form: aFullForm,
    }, 
    error: function(aObject, aTextStatus, aErrorThrown) { 
      alert("Unable to edit folder settings: " + aTextStatus + "/" + aErrorThrown); 
    }, 
    success: function (aHtml) { 
      jQuery(document.body).append(aHtml);
      form_data = getFieldsFromForm("#dialog-form");
      allFields = jQuery([]).add( jQuery("#mailcwp_name") ).add( jQuery("mailcwp_host") ).add( jQuery("mailcwp_email") ), //.add( password ).add( confirm_password ),
      tips = jQuery( ".validateTips" );

      jQuery("#dialog-form").dialog({
	height: 350,
	width: 350,
	modal: true,
	buttons: {
	  "Test": function() {
	    bValid = validateForm("#dialog-form");
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
	    bValid = validateForm("#dialog-form");
	    if (bValid) {
              form_data = getFieldsFromForm("#dialog-form");
              form_data.action = "mailcwp_edit_account";
	      jQuery.ajax ({
		type: "post", 
		url: ajaxurl, 
		data: form_data,
		error: function(aObject, aTextStatus, aErrorThrown) { 
		  alert("Unable to save changes: " + aTextStatus + "/" + aErrorThrown); 
		}, 
		success: function (aHtml) { 
		  alert(aHtml);
		  document.location.reload();
		}
	      });
	      jQuery( this ).dialog( "close" );
	      jQuery("#dialog-form").remove();
	    }
	  },
	  Cancel: function() {
	    jQuery( this ).dialog( "close" );
	    jQuery("#dialog-form").remove();
	  }
	},
	close: function() {
	  //allFields.val( "" ).removeClass( "ui-state-error" );
	}
      });
    }
  });
}*/

function confirm(aMessage, aYesCallback, aNoCallback, aCancelCallback) {
  vHtml = "<div id=\"mailcwp-confirm\" title=\"Confirm\">";
  vHtml += "<p><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin: 0 7px 20px 0\"></span>";
  vHtml += aMessage;
  vHtml += "</p>";
  vHtml += "</div>";

  vArguments = arguments;

  jQuery(document.body).append(vHtml);
  jQuery("#mailcwp-confirm").dialog({
    resizable: false,
    modal: true,
    close: function() {
      jQuery("#mailcwp-confirm").remove();
    },
    buttons: {
      "No": function() {
        if (typeof aNoCallback !== "undefined" && aNoCallback != null) {
          if (vArguments.length == 4) {
            aNoCallback();
          } else if (vArguments.length == 5) {
            aNoCallback(vArguments[4]);
          } else if (vArguments.length == 6) {
            aNoCallback(vArguments[4], vArguments[5]);
          }
        }
        jQuery("#mailcwp-confirm").dialog("close");
      },
      "Yes": function() {
        if (vArguments.length == 4) {
          aYesCallback();
        } else if (vArguments.length == 5) {
          aYesCallback(vArguments[4]);
        } else if (vArguments.length == 6) {
          aYesCallback(vArguments[4], vArguments[5]);
        }
        jQuery("#mailcwp-confirm").dialog("close");
      }
    }
  });
  if (typeof aCancelCallback !== "undefined" && aCancelCallback != null) {
    vButtons = jQuery("#mailcwp-confirm").dialog("option", "buttons");
    jQuery.extend(vButtons, {"Cancel": function() {
      if (vArguments.length == 4) {
        aCancelCallback();
      } else if (vArguments.length == 5) {
        aCancelCallback(vArguments[4]);
      } else if (vArguments.length == 6) {
        aCancelCallback(vArguments[4], vArguments[5]);
      }
      jQuery("#mailcwp-confirm").dialog("close");
    }});
    jQuery("#mailcwp-confirm").dialog("option", "buttons", vButtons);
  }
}

function htmlEscape(str) {
    return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br/>');
}

function checkDialogSize(aId) {
  var vId = "#" + aId;
  var vWindowWidth = jQuery(window).width();
  var vWidth = jQuery(vId).parent().width();
  if (vWidth > vWindowWidth) {
    jQuery(vId).parent().css("max-width", (vWindowWidth - 10) + "px");
    jQuery(vId).parent().css("left", "5px");
  }
}

function playSound(soundfile) {
  jQuery("#dummy").html("<embed src=\""+soundfile+"\" hidden=\"true\" autostart=\"true\" loop=\"false\" style=\"height:0\"/>");
}
