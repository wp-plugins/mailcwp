<?php
$message_id = $_REQUEST["message_id"];
$upload_dir = $_REQUEST["upload_dir"];
if (empty($_FILES) || $_FILES["file"]["error"]) {
  die('{"OK": 0}');
}
 
$fileName = $_FILES["file"]["name"];
move_uploaded_file($_FILES["file"]["tmp_name"], "$upload_dir/$message_id-$fileName");
 
die('{"OK": 1}');
?>
