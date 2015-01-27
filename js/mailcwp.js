var mPage = 1, mRefreshLock = false, mRefreshInterval = 0, mThemeURL = null, mThemeName = null, mRefreshQueue = [], mLastRefresh = new Date(), mDraftTimers = [], mUploaders = [];

jQuery(document).ready(function() {
  jQuery("#mailcwp_accounts").accordion({
    heightStyle: "content",
    activate: function(event, ui) {
      //alert(ui.newHeader[0].id);
      //console.log('getHeaders');
      getHeaders(false, 1, "INBOX", ui.newHeader[0].id);
    }
  });
  jQuery(".mailcwp_folders").menu({
    position: {
      my: "left top",
      at: "left top+25",
    },
  });
  jQuery(document).tooltip();
  if(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
    jQuery(document).tooltip("disable");
  }
  jQuery(".mailcwp_folder_settings").button({
    text: false,
    icons: {
      primary: "ui-icon-gear"
    }
  }).click(function(e, ui) {
    account = jQuery(this).attr("id").substring(24);
    editAccount(null, account);
    e.preventDefault();
  });

  jQuery(".mailcwp_close_account").button({
    text: false,
    icons: {
      primary: "ui-icon-circle-close"
    }
  }).click(function(e, ui) {
    account = jQuery(this).attr("id").substring(22);
    account_name = jQuery(this).attr("data");
    removeAccount(account, account_name);
    e.preventDefault();
  });

  jQuery( "#mailcwp_compose" ).button({
    text: false,
    icons: {
      primary: "ui-icon-pencil"
    }
  }).click(function(e, ui) {
    composeMessage();
    e.preventDefault();
  });
  jQuery( "#mailcwp_refresh" ).button({
    text: false,
    icons: {
      primary: "ui-icon-refresh"
    }
  }).click(function(e, ui) {
    jQuery("#mailcwp_search_term").val("");
    refresh(true);
    e.preventDefault();
  });
  jQuery("#mailcwp_search_term").keyup(function (event) {
    if (event.keyCode == 13) {
      refresh(false);
    }
  });
  jQuery( "#mailcwp_search" ).button({
    text: false,
    icons: {
      primary: "ui-icon-search"
    }
  }).click(function(e, ui) {
    refresh(false);
    e.preventDefault();
  });
  jQuery( "#mailcwp_options" ).button({
    text: false,
    icons: {
      primary: "ui-icon-gear"
    }
  }).click(function(e, ui) {
    jQuery.ajax ({
      type: "POST", 
      url: ajax_object.ajax_url, 
      data: { 
        action: "mailcwp_options_dialog",
      }, 
      error: function(aObject, aTextStatus, aErrorThrown) { 
        jQuery("#progressbar").hide();
        display_notice("Unable to show options: " + aTextStatus + "/" + aErrorThrown); 
      }, 
      success: function (aData) { 
        processAjaxResponse(aData);
      }  
    });
    e.preventDefault();
  });
  jQuery( "#mailcwp_themes" ).button({
    text: false,
    icons: {
      primary: "ui-icon-image"
    }
  }).click(function(e, ui) {
    jQuery("#switcher_themeswitcher").animate({width: 'toggle'});
    /*if (jQuery("#themeswitcher").is(":visible")) {
      jQuery("#switcher_themeswitcher").click();
    }*/
    e.preventDefault();
  });
  jQuery( "#mailcwp_read" ).button({
    text: false,
    disabled: true,
    icons: {
      primary: "ui-icon-mail-open"
    }
  }).click(function(e, ui) {
    vMessages = getSelectedMessages();
    jQuery("#progressbar").show();
    jQuery.ajax ({
      type: "POST", 
      url: ajax_object.ajax_url, 
      data: { 
        action: "mailcwp_mark_read",
        messages: vMessages
      }, 
      error: function(aObject, aTextStatus, aErrorThrown) { 
        jQuery("#progressbar").hide();
        display_notice("Unable to fetch headers: " + aTextStatus + "/" + aErrorThrown); 
      }, 
      success: function (aHtml) { 
        jQuery("#progressbar").hide();
        jQuery(".ui-tooltip").hide();
        jQuery(document).trigger("mailcwp_message_selection_off");
        refresh(false);
      }
    });
    e.preventDefault();
  });
  jQuery( "#mailcwp_unread" ).button({
    text: false,
    disabled: true,
    icons: {
      primary: "ui-icon-mail-closed"
    }
  }).click(function(e, ui) {
    vMessages = getSelectedMessages();
    jQuery("#progressbar").show();
    jQuery.ajax ({
      type: "POST", 
      url: ajax_object.ajax_url, 
      data: { 
        action: "mailcwp_mark_unread",
        messages: vMessages
      }, 
      error: function(aObject, aTextStatus, aErrorThrown) { 
        jQuery("#progressbar").hide();
        display_notice("Unable to fetch headers: " + aTextStatus + "/" + aErrorThrown); 
      }, 
      success: function (aHtml) { 
        jQuery("#progressbar").hide();
        jQuery(".ui-tooltip").hide();
        jQuery(document).trigger("mailcwp_message_selection_off");
        refresh(false);
      }
    });
    e.preventDefault();
  });
  jQuery( "#mailcwp_delete" ).button({
    text: false,
    disabled: true,
    icons: {
      primary: "ui-icon-trash"
    }
  }).click(function(e, ui) {
    jQuery(document).tooltip("option", "hide");
    vMessages = getSelectedMessages();
    deleteMessages(vMessages);
    e.preventDefault();
  });

  jQuery("#mailcwp_add_account").button().click(function(e, ui) {
    editAccount(null, null, null, false)
    e.preventDefault();
  });
  if (jQuery("#themeswitcher").length > 0) {
    jQuery("#themeswitcher").jui_theme_switch({
      stylesheet_link_id: "jquery_ui_styles-css",
      datasource_url: '../wp-content/plugins/mailcwp/js/json/themes.json',
      default_theme: mThemeName == null ? ajax_object.default_theme : mThemeName,
      use_groups: "no",
      project_url: "/wp-content/plugins/mailcwp",

      onChangeTheme: function(event, theme) {
	jQuery("#switcher_themeswitcher").animate({width: 'toggle'});
	jQuery.ajax({
	  type: "POST",
	  url: ajax_object.ajax_url, 
	  data: { 
            action: "mailcwp_change_theme",
            theme: theme.theme_url,
            theme_name: theme.theme_name,
          },
          error: function(aObject, aTextStatus, aErrorThrown) {
            display_notice("Unable to change theme: " + aTextStatus + "/" + aErrorThrown);
          },
          success: function (aData) {
          }
        });
      },

      onDisplay: function() {
        jQuery("#switcher_themeswitcher").addClass("ui-widget ui-widget-content");
        if (mThemeURL != null) {
          jQuery("#switcher_themeswitcher").val(mThemeURL);
        } 
        jQuery("#switcher_themeswitcher").change();
      }
    });
  }
  jQuery(document).on("mailcwp_message_selection_on", function() {
    jQuery("#mailcwp_read").button("enable");
    jQuery("#mailcwp_unread").button("enable");
    jQuery("#mailcwp_delete").button("enable");
  });
  jQuery(document).on("mailcwp_message_selection_off", function() {
    jQuery("#mailcwp_read").button("disable");
    jQuery("#mailcwp_unread").button("disable");
    jQuery("#mailcwp_delete").button("disable");
  });
  jQuery(document).on("dialogopen", ".ui-dialog", function(e, ui) {
    checkDialogSize(e.target.id);
  });

//console.log('Refreshing');
  refresh(true);
  setInterval(setLastUpdate, 60 * 1000);
});

function getSelectedMessages() {
  vMessages = "";
  jQuery("[id^=action-]").each(function() {
    if (jQuery(this).prop("checked")) {
      vMessages += jQuery(this).attr("id").substring(7).trim() + ",";
    }
  });
  return vMessages;
}

function removeAccount(aAccountId, aAccountName) {
  html = "<div id=\"remove_account_dialog_" + 
    aAccountId +
    "\" title=\"Remove " + aAccountName + "\">" +
    "<p><span class=\"ui-icon ui-icon-alert\" style=\"float:left; margin:0 7px 20px 0;\"></span>Remove this account from MailCWP?</p>" +
    "</div>";
  js = "jQuery(function() {" + 
    "  jQuery(\"#remove_account_dialog_" + aAccountId + "\").dialog({" +
    "    resizable: false," +
    "    height:140," +
    "    modal: true," +
    "    buttons: {" +
    "      \"Remove Account\": function() {" +
    "         jQuery.ajax ({" +
    "           type: \"post\"," + 
    "           url: ajax_object.ajax_url," + 
    "           data: {" + 
    "             action: \"mailcwp_delete_account\"," +
    "             account_id: \"" + aAccountId + "\"," +
    "           }," +
    "           error: function(aObject, aTextStatus, aErrorThrown) { " +
    "             display_notice(\"Unable to remove the account: \" + aTextStatus + \"/\" + aErrorThrown); " +
    "           }," + 
    "           success: function (aHtml) {" + 
    "             jQuery(\"#remove_account_dialog_" + aAccountId + "\").dialog(\"close\");" +
    "             document.location.reload();" +
    "           }" +
    "        });" +
    "      }," +
    "      Cancel: function() {" +
    "        jQuery( this ).dialog(\"close\");" +
    "      }" +
    "    }" +
    "  });" +
    "});";
  jQuery(document.body).append(html);
  eval(js);
}


function composeMessage(aMsgNumber, aMode) {
  vUniqueId = Math.floor((Math.random()*1000000)+1);
  jQuery.ajax({
    type: "POST",
    url: ajax_object.ajax_url, 
    data: { 
      action: "mailcwp_compose_message",
      unique_id: vUniqueId,
      original: aMsgNumber ? aMsgNumber : -1,
      mode: aMode,
    },
    error: function(aObject, aTextStatus, aErrorThrown) { 
      display_notice("Unable to compose message: " + aTextStatus + "/" + aErrorThrown); 
    }, 
    success: function (aData) { 
//console.log(aData);
      if (processAjaxResponse(aData)) {
        vData = jQuery.parseJSON(aData);
//console.log(vData.upload_dir);
	//jQuery("#uploader_" + vUniqueId).plupload({
	//console.log(vData);
	mUploaders[vUniqueId] = new plupload.Uploader({
	  runtimes: 'html5,html4',
          browse_button: 'pickfiles_' + vUniqueId,
          container: 'uploader_' + vUniqueId,

	  multipart_params: {
	    message_id: vUniqueId,
            upload_dir: vData.upload_dir,
	  },
	  url: vData.upload_url,

	  //max_file_count: 20,
	  chunk_size: '1mb',
	  filters: { 
	    max_file_size: '2mb',
	    mime_types: [
              {title: "Attachments", extensions: "*"}
            ] 
	  },

	  //rename: true,
	  //dragdrop: true,

	  /*views: {
	    list: true,
	    thumbs: true,
	    active: 'thumbs'
	  },

	  buttons: {
	    start: false,
	  },*/

	  //flash_swf_url : 'http://rawgithub.com/moxiecode/moxie/master/bin/flash/Moxie.cdn.swf',
	  //silverlight_xap_url : 'http://rawgithub.com/moxiecode/moxie/master/bin/silverlight/Moxie.cdn.xap',

          init: {
            Init: function() {
//console.log("INIT");
              jQuery("#filelist_" + vUniqueId).html("No attachments"); 
            },
            /*PostInit: function() {
console.log("POSTINIT");
console.log(jQuery('#filelist_' + vUniqueId));
              jQuery("#filelist_" + vUniqueId).html("No attachments"); 
            },*/

            FilesAdded: function(up, files) {
              vHtml = "<table width=\"100%\"><thead><tr><th></th><th>Name</th><th>Size</th><th>Uploaded</th></tr></thead><tbody>";
              //jQuery('#uploader_' + vUniqueId).plupload("enable");
              plupload.each(files, function(file) {
//console.log(file);
                vHtml += '<tr id="attachment_' + vUniqueId + '_' + file.id + '"><td><input type="checkbox" id="remove_' + vUniqueId + '_' + file.id + '" data="' + file.id + '"/></td><td>' + file.name + '</td><td>' + plupload.formatSize(file.size) + '</td><td id="progress_' + vUniqueId + '_' + file.id + '">0%</td></tr>';
              });
              vHtml += "</tbody></table>";
              jQuery("#filelist_" + vUniqueId).html(vHtml); 
	       jQuery("input[id^=remove_" + vUniqueId + "_]").change(function(e, ui) {
	//console.log("doing this " + vUniqueId);
		 if (jQuery("input[id^=remove_" + vUniqueId + "_]:checked").length > 0) {
		   jQuery('#removefiles_' + vUniqueId).button("enable");
		 } else {
		   jQuery('#removefiles_' + vUniqueId).button("disable");
		 }
	       });
            },

            FilesRemoved: function(up, files) {
              files.forEach(function(aFile) {
                jQuery("#attachment_" + vUniqueId + "_" + aFile.id).remove();
              });
              if (mUploaders[vUniqueId].files.length == 0) {
                jQuery('#removefiles_' + vUniqueId).button("disable");
              }
            },

            UploadProgress: function(up, file) {
              jQuery("#progress_" + vUniqueId + "_" + file.id).html(file.percent + "%");
            },

            UploadComplete: function() {
	      //jQuery('#form')[0].submit();
              sendMessage(aMsgNumber, vUniqueId)
              mUploaders[vUniqueId].destroy();
              delete mUploaders[vUniqueId];
            },
 
            Error: function(up, err) {
              displayNotice("Error #" + err.code + ": " + err.message);
            }
          }
       });
//console.log(plUploader);
       mUploaders[vUniqueId].init();
       if (mUploaders[vUniqueId]) {
         jQuery("#filelist_" + vUniqueId).html("No attachments"); 
       }
//console.log(plUploader.features);
//console.log(plUploader.runtime);
       jQuery('#pickfiles_' + vUniqueId).button();
       jQuery('#removefiles_' + vUniqueId).button().click(function (e, ui) {
         jQuery("input[id^=remove_" + vUniqueId + "_]:checked").each(function (i, e) {
           vFileId = jQuery("#" + e.id).attr("data");
           vFileToRemove = mUploaders[vUniqueId].getFile(vFileId);
           mUploaders[vUniqueId].removeFile(vFileToRemove);
         });
         e.preventDefault();
       });
       /*jQuery('#uploadfiles_' + vUniqueId).button().click(function(e) {
         uploader.start();
         e.preventDefault();
       });*/

       /*jQuery('#form').submit(function(e) {
	 //if (jQuery('#uploader_' + vUniqueId).plupload('getFiles').length > 0) {
	 if (pluploader.files.length > 0) {
	   //jQuery('#uploader_' + vUniqueId).on('complete', function() {
	   //  jQuery('#form')[0].submit();
	   //});

	   //jQuery('#uploader_' + vUniqueId).plupload('start');
	   plUploader.start();
	 } else {
	   alert("You must have at least one file in the queue.");
	 }
	 //return false;
	 e.preventDefault();
       });*/
       jQuery("#mailcwp_compose_dialog_" + vUniqueId).dialog({
	 height: 560, 
	 width: 640, 
	 open: function () {
	   jQuery("#mailcwp_extra_addresses_toggle_" + vUniqueId).button().click(function (e, ui) {
	     jQuery("#mailcwp_extra_addresses_" + vUniqueId).slideToggle();
	     e.preventDefault();
	   });
	    jQuery("#mailcwp_attach_" + vUniqueId).button().click(function (e, ui) {
	      vAnchor = jQuery("a[name='mailcwp_compose_uploader_" + vUniqueId + "']");
	      //console.log(vAnchor.offset());
	      jQuery("#mailcwp_compose_dialog_" + vUniqueId).animate({scrollTop: vAnchor.offset().top}, 'slow');
	      e.preventDefault();
	    });
	    jQuery("#mailcwp_toggle_format_" + vUniqueId).button().click(function (e, ui) {
	      
	    });
            mDraftTimers[vUniqueId] = setInterval(function() {saveDraft(aMsgNumber, vUniqueId, false)}, 60000);
	    //jQuery("#mailcwp_message_" vUniqueId).focus();
	  },
	  beforeClose: function(e, ui) {
	    //tinyMCE.get("mailcwp_message_" + vUniqueId).destroy();
	    //jQuery("#mailcwp_message_" + vUniqueId).tinymce().destroy();
	    
            vSent = jQuery("#mailcwp_sent_" + vUniqueId).val();
            if (vSent != "SENT") {
              e.preventDefault();
              confirm("Save a draft copy of this message?", saveDraftAndClose, deleteDraftAndClose, function(){}, aMsgNumber, vUniqueId);
            }
	  },
          close: function(e, ui) {
            vSent = jQuery("#mailcwp_sent_" + vUniqueId).val();
            if (vSent == "SENT") {
              deleteDraft(vUniqueId);
            }
            //closeComposeDialog(vUniqueId);
          },
	  buttons: {
	    "Send": function() {
	      vNumFiles = mUploaders[vUniqueId].files.length; //jQuery("#uploader_" + vUniqueId).plupload("getFiles").length;
	      if (vNumFiles > 0) {
		vCounter = 0;
		//jQuery("#uploader_" + vUniqueId).plupload('start');
		mUploaders[vUniqueId].start();
		/*jQuery("#uploader_" + vUniqueId).on("uploaded", function() {
		  vCounter +=1; 
		  if (vCounter == vNumFiles) {
		    sendMessage(aMsgNumber, vUniqueId)
		  }
		});*/
	      } else {
		sendMessage(aMsgNumber, vUniqueId);
	      }
	    },
	    "Cancel": function() {
	      jQuery("#mailcwp_compose_dialog_" + vUniqueId).dialog("close");
	      //jQuery("#mailcwp_compose_dialog_" + vUniqueId).remove();
	    }
          }
        });
      }
    }
  });
}

function saveDraftAndClose(aMsgNumber, aUniqueId) {
//console.log("save and close");
  saveDraft(aMsgNumber, aUniqueId, true);
}

function deleteDraftAndClose(aMsgNumber, aUniqueId) {
//console.log("delete and close");
  deleteDraft(aUniqueId);
}

function closeComposeDialog(aUniqueId) {
  jQuery.ajax({
    type: "POST",
    url: ajax_object.ajax_url, 
    data: { 
      action: "mailcwp_compose_close",
      message_id: aUniqueId,
    },
    error: function(aObject, aTextStatus, aErrorThrown) { 
      jQuery("#mailcwp_compose_dialog_" + aUniqueId).remove();
    }, 
    success: function (aScript) { 
      clearInterval(mDraftTimers[aUniqueId]);
      eval(aScript);
      jQuery("#mailcwp_compose_dialog_" + aUniqueId).remove();
      refresh(false);
    }
  });
}

function login(aAccountId, aUsername, aPassword) {
//alert(aAccountId + '/' + aUsername + '/' + aPassword);
  jQuery.ajax({
    type: "POST",
    url: ajax_object.ajax_url, 
    data: { 
      action: "mailcwp_login",
      account_id: aAccountId,
      username: aUsername,
      password: aPassword,
    },
    error: function(aObject, aTextStatus, aErrorThrown) { 
      display_notice("Unable to login: " + aTextStatus + "/" + aErrorThrown); 
    }, 
    success: function (aData) { 
      //refresh(false);
      window.location.reload();
    }
  });
}

function saveDraft(aMsgNumber, aUniqueId, aClose) {
//console.log("saving draft");
  jQuery.ajax({
    type: "POST",
    url: ajax_object.ajax_url, 
    data: { 
      action: "mailcwp_save_draft",
      to: jQuery("#mailcwp_to_" + aUniqueId).val(),
      cc: jQuery("#mailcwp_cc_" + aUniqueId).val(),
      bcc: jQuery("#mailcwp_bcc_" + aUniqueId).val(),
      subject: jQuery("#mailcwp_subject_" + aUniqueId).val(),
      message: jQuery("#mailcwp_message_" + aUniqueId).val(),
      original: aMsgNumber ? aMsgNumber : -1,
      unique_id: aUniqueId,
      draft_id: jQuery("#mailcwp_draft_id_" + aUniqueId).val(),
    },
    error: function(aObject, aTextStatus, aErrorThrown) { 
      display_notice("Unable to compose message: " + aTextStatus + "/" + aErrorThrown); 
    }, 
    success: function (aData) { 
      try {
        vData = jQuery.parseJSON(aData);
      } catch (e) {
        display_notice("Unable to parse Ajax response. " + e.message);
        return false;
      }
      if (vData.hasOwnProperty("result") && vData.result == "OK") {
        if (vData.hasOwnProperty("draft_id")) {
          jQuery("#mailcwp_draft_id_" + aUniqueId).val(vData.draft_id);
          if (aClose) {
//console.log("CLOSING COMPOSE");
            jQuery("#mailcwp_sent_" + aUniqueId).val("SENT");
            closeComposeDialog(aUniqueId);
          }
        } else {
          processAjaxResponse(aData);
        }
      } else {
        processAjaxResponse(aData);
      }
    }
  });
}

function deleteDraft(aUniqueId) {
  vDraftId = jQuery("#mailcwp_draft_id_" + aUniqueId).val();
  if (vDraftId == "") {
    closeComposeDialog(aUniqueId);
  } else {
    jQuery.ajax({
      type: "POST",
      url: ajax_object.ajax_url, 
      data: { 
	action: "mailcwp_delete_draft",
	draft_id: vDraftId,
      },
      error: function(aObject, aTextStatus, aErrorThrown) { 
	display_notice("Unable to delete draft: " + aTextStatus + "/" + aErrorThrown); 
      }, 
      success: function (aData) { 
	processAjaxResponse(aData);
	closeComposeDialog(aUniqueId);
      }
    });
  }
}

function sendMessage(aMsgNumber, aUniqueId) {
//alert("SEND MESSAGE " + aMsgNumber + "/" + aUniqueId);
//alert(jQuery("#mailcwp_compose_dialog_" + aUniqueId + " #mailcwp_to").val());
//alert(jQuery("#mailcwp_compose_dialog_" + aUniqueId + " #mailcwp_subject").val());
//alert(jQuery("#mailcwp_compose_dialog_" + aUniqueId + " #mailcwp_message").val());
  jQuery.ajax({
    type: "POST",
    url: ajax_object.ajax_url, 
    data: { 
      action: "mailcwp_send_message",
      to: jQuery("#mailcwp_to_" + aUniqueId).val(),
      cc: jQuery("#mailcwp_cc_" + aUniqueId).val(),
      bcc: jQuery("#mailcwp_bcc_" + aUniqueId).val(),
      subject: jQuery("#mailcwp_subject_" + aUniqueId).val(),
      message: jQuery("#mailcwp_message_" + aUniqueId).val(),
      original: aMsgNumber ? aMsgNumber : -1,
      unique_id: aUniqueId,
      draft_id: jQuery("#mailcwp_draft_id_" + aUniqueId).val(),
    },
    error: function(aObject, aTextStatus, aErrorThrown) { 
      display_notice("Unable to compose message: " + aTextStatus + "/" + aErrorThrown); 
    }, 
    success: function (aData) { 
//console.log(aData);
      if (processAjaxResponse(aData)) {
        jQuery("#mailcwp_sent_" + aUniqueId).val("SENT");
        jQuery("#mailcwp_compose_dialog_" + aUniqueId).dialog("close");
      }
    }
  });
}

function deleteMessages(aMessages) {
//console.log(aMessages);
  jQuery("#progressbar").show();
  jQuery.ajax ({
    type: "POST", 
    url: ajax_object.ajax_url, 
    data: { 
      action: "mailcwp_delete",
      messages: aMessages
    }, 
    error: function(aObject, aTextStatus, aErrorThrown) { 
      jQuery("#progressbar").hide();
      display_notice("Unable to delete messages: " + aTextStatus + "/" + aErrorThrown); 
    }, 
    success: function (aData) { 
//console.log(aData);
      //alert(aHtml);
      jQuery("#progressbar").hide();
      jQuery(".ui-tooltip").hide();
      jQuery(document).trigger("mailcwp_message_selection_off");
      refresh(false);
    }
  });
}

function refresh(aAllAccounts) {
  getHeaders(aAllAccounts, mPage);
  jQuery(document).ready(function() {
    jQuery(".mailcwp_folders").menu("collapseAll", null, true);
    jQuery(".ui-button").button("refresh");
    jQuery(document).tooltip("option", "hide");
  });
}

function setLastUpdate() {
  vLastUpdate = new Date() - mLastRefresh;
  vLastUpdate = Math.ceil(vLastUpdate / 1000 / 60);
  if (vLastUpdate < 1) {
    vMessage = "Last updated less than 1 min ago";
  } else if (vLastUpdate == 1) {
    vMessage = "Last updated 1 min ago";
  } else {
    vMessage = "Last updated " + vLastUpdate + " mins ago";
  }
  jQuery("#mailcwp_last_update").html(vMessage);
  if (mRefreshInterval > 0 && vLastUpdate > mRefreshInterval) {
    refresh(true);
  }
}

function getHeaders(aAllAccounts, aPage, aFolder, aAccount) {
  if (mRefreshLock) {
    mRefreshQueue.push([aAllAccounts, aPage, aFolder, aAccount]);
  } else {
    mRefreshLock = true;
//console.log("getHeaders " + aPage + "/" + aFolder + "/" + aAccount);
  //jQuery("#mailcwp_headers").html(aMailbox + "/" + aUsername + "/" + aPassword + "/" + aFolderName);
    //jQuery("#mailcwp_headers").html("Loading&hellip;");
    jQuery("#progressbar").show();
    jQuery.ajax ({
      type: "POST", 
      url: ajax_object.ajax_url, 
      data: { 
        action: "mailcwp_get_headers",
        all: aAllAccounts,
        page: aPage,
        folder: aFolder,
        account: aAccount,
        search: jQuery("#mailcwp_search_term").val()
      }, 
      error: function(aObject, aTextStatus, aErrorThrown) { 
        jQuery("#progressbar").hide();
        mRefreshLock = false;
        display_notice("Unable to fetch headers: " + aTextStatus + "/" + aErrorThrown); 
      }, 
      success: function (aHtml) { 
        if (aHtml == "0") {
          window.location = window.location.protocol + "//" + window.location.hostname + "/wp-login.php?redirect_to=" + window.location.href;
        } else {
	  jQuery(document).trigger("mailcwp_message_selection_off");
	  mLastRefresh = new Date();
	  setLastUpdate();
	  jQuery("#progressbar").hide();
	  mPage = aPage;
	  jQuery("#mailcwp_headers").html(aHtml);
	  jQuery(".mailcwp_answered span.ui-icon-arrowreturnthick-1-w").show();
	  if (mRefreshQueue.length > 0) {
	    vArgs = mRefreshQueue.splice(0, 1);
	    getHeaders(vArgs[0], vArgs[1], vArgs[2], vArgs[3]);
	  }
	  mRefreshLock = false;
        }
      }
    });
  }
}

function getMessage(aFolderName, aMessageNumber, aSubject) {
  //jQuery("#mailcwp_headers").html(aMailbox + "/" + aUsername + "/" + aPassword + "/" + aFolderName);
    jQuery("#progressbar").show();
    jQuery.ajax ({
      type: "POST", 
      url: ajax_object.ajax_url, 
      data: { 
        action: "mailcwp_get_message",
        msg_number: aMessageNumber,
        subject: aSubject
      }, 
      error: function(aObject, aTextStatus, aErrorThrown) { 
        jQuery("#progressbar").hide();
        display_notice("Unable to fetch headers: " + aTextStatus + "/" + aErrorThrown); 
      }, 
      success: function (aData) { 
        //console.log(aData);
        jQuery("#progressbar").hide();
        processAjaxResponse(aData);
      }
    });
}

function selectAll() {
  jQuery("[id^=action-]").prop("checked", jQuery("#select-all").prop("checked"));
  if (jQuery("#select-all").prop("checked")) {
    jQuery(document).trigger("mailcwp_message_selection_on");
  } else {
    jQuery(".ui-tooltip").hide();
    jQuery(document).trigger("mailcwp_message_selection_off");
  }
}

function checkSelection(aId) {
  vSelectionCount = 0;
  jQuery("[id^=action-]").each(function() {
    if (jQuery(this).prop("checked")) {
      vSelectionCount += 1;
    }
  });
  if (vSelectionCount > 0) {
    jQuery(document).trigger("mailcwp_message_selection_on");
  } else {
    jQuery(".ui-tooltip").hide();
    jQuery(document).trigger("mailcwp_message_selection_off");
  }
}
