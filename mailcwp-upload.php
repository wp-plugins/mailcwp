<?php

require_once "../../../wp-load.php";

if (!is_user_logged_in()) {
  die('{"ERROR": -1}');
}

$message_id = $_REQUEST["message_id"];
$upload_dir = $_REQUEST["upload_dir"];
if (empty($_FILES) || $_FILES["file"]["error"]) {
  die('{"OK": 0}');
}
 
$fileName = $_FILES["file"]["name"];
$ext = pathinfo($fileName, PATHINFO_EXTENSION);
if ($ext == 'php') {
  die('{"ERROR": -2}');
}
move_uploaded_file($_FILES["file"]["tmp_name"], "$upload_dir/$message_id-$fileName");
 
die('{"OK": 1}');
?>
