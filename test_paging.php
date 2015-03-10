<?php

$page = 197;
$num_page = 200;

  for ($i = 1; $i <= 3 && $i <= $num_page; $i++) {
    $paging_toolbar .= "<a class=\"mailcwp_page_number" . ($i == $page ? "_selected" : "") . "\" href=\"javascript:void(0)\" onclick=\"getHeaders(false, $i)\">$i</a>&nbsp;&nbsp;";
  }
  if ($page < 6) {
    for ($i = 4; $i <= 6 && $i <= $num_page; $i++) {
      $paging_toolbar .= "<a class=\"mailcwp_page_number" . ($i == $page ? "_selected" : "") . "\" href=\"javascript:void(0)\" onclick=\"getHeaders(false, $i)\">$i</a>&nbsp;&nbsp;";
    }
  } else {
    if ($page > 4 && $page < $num_page - 3) {
      $paging_toolbar .= "&hellip;&nbsp;&nbsp";
      for ($i = $page - 1; $i <= $page + 1; $i++) {
        $paging_toolbar .= "<a class=\"mailcwp_page_number" . ($i == $page ? "_selected" : "") . "\" href=\"javascript:void(0)\" onclick=\"getHeaders(false, $i)\">$i</a>&nbsp;&nbsp;
";
      }
    }
  }  
  if ($num_page < $i + 6) {
    for ($i = $i + 1; $i <= $num_page; $i++) {
      $paging_toolbar .= "<a class=\"mailcwp_page_number" . ($i == $page ? "_selected" : "") . "\" href=\"javascript:void(0)\" onclick=\"getHeaders(false, $i)\">$i</a>&nbsp;&nbsp;";
    }
  } else {
    if ($page > $num_page - 6) {
      for ($i = $num_page - 5; $i <= $num_page - 3; $i++) {
        $paging_toolbar .= "&hellip;&nbsp;&nbsp";
        $paging_toolbar .= "<a class=\"mailcwp_page_number" . ($i == $page ? "_selected" : "") . "\" href=\"javascript:void(0)\" onclick=\"getHeaders(false, $i)\">$i</a>&nbsp;&nbsp;
";
      }
    } else {
      $paging_toolbar .= "&hellip;&nbsp;&nbsp";
    }
    for ($i = $num_page - 2; $i <= $num_page; $i++) {
      $paging_toolbar .= "<a class=\"mailcwp_page_number" . ($i == $page ? "_selected" : "") . "\" href=\"javascript:void(0)\" onclick=\"getHeaders(false, $i)\">$i</a>&nbsp;&nbsp;";
    }
  }

echo $paging_toolbar;
?>
