<?php
require_once "../../../wp-load.php";

if (!is_user_logged_in()) {
  echo "Download failed. User not logged in.";
}
  require_once("classes/MailMessage.class.php");
//print_r($_POST);
  $download_data = explode("::", base64_decode($_POST["download_data"]));
  $download_dir = $_POST["download_dir"];
//print_r($download_data);
  $mbox_name = $download_data[0];
  $use_ssl = $download_data[1];
  $validate_cert = $download_data[2];
  $username = $download_data[3];
  $password = $download_data[4];
  $use_tls = $download_data[5];
  $folder = $download_data[6];
  $msg_number = (int)$download_data[7];
  $attachment_name = $download_data[8];

  imap_timeout(15, IMAP_OPENTIMEOUT);
  imap_timeout(15, IMAP_READTIMEOUT);
  imap_timeout(15, IMAP_WRITETIMEOUT);
  imap_timeout(15, IMAP_CLOSETIMEOUT);

  $use_ssl_flag = $use_ssl == "true" ? "/ssl" : "";
  $validate_cert_flag = $validate_cert == "true" ? "" : "/novalidate-cert";
  $use_tls_flag = $use_tls == "true" ? "/tls" : "";
  $mbox = imap_open("{" . $mbox_name . $use_ssl_flag . $validate_cert_flag . $use_tls_flag . "}$folder", $username, $password);
//print_r(imap_errors());
  $message = new MailMessage($mbox, $msg_number);  
//print_r($message);
  $attachments = $message->getAttachments();
//print_r($attachments);
  $download_data = null;
  if ($attachment_name == '*') {
    if (class_exists("ZipArchive")) {
      $attachment_name = "$download_dir/attachments-$msg_number.zip";
      $zip = new ZipArchive;
      $zip->open($attachment_name, ZipArchive::CREATE); 
      foreach ($attachments as $filename=>$data) {
        $zip->addFromString($filename, $data);
      }
      $zip->close();
      $download_data = file_get_contents($attachment_name);
      unlink($attachment_name);
    } else {
      //attempt shell out to zip
    }
  } else {
    foreach ($attachments as $filename=>$data) {
      if ($filename == $attachment_name) {
	$download_data = $data;
      }
    }
  }

  if ($download_data != null) {
    header('Content-Description: Download Email Attachment');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename='.basename($attachment_name));
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . sizeof($download_data));
    @ob_clean();
    flush(); 
    print $download_data;
    flush(); 
  } else {
    echo "Download failed. Can't find attachment.";
  }
?>
