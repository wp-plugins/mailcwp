<?php

class MailMessage {
  var $charset;
  var $plain_message;
  var $html_message;
  var $attachments;
  var $headers;

  public function MailMessage($mbox, $mid) {
    $this->getMessage($mbox, $mid);
  }

  public function getHeaders() {
    return $this->headers;
  }

  public function getCharSet() {
    return $this->charset;
  }

  public function getPlainMessage() {
    return $this->plain_message;
  }

  public function getHtmlMessage() {
    return $this->html_message;
  }

  public function getAttachments() {
    return $this->attachments;
  }

  private function getMessage($mbox, $mid) {
      // input $mbox = IMAP stream, $mid = message id
      // output all the following:
      
      $this->html_message = $this->plain_message = $this->charset = '';
      $this->attachments = array();

      // HEADER
      $h = imap_header($mbox,$mid);
      $this->headers = $h;
      // add code here to get date, from, to, cc, subject...

      // BODY
      $s = imap_fetchstructure($mbox,$mid);
//write_log($s);
      if (!isset($s->parts))  // simple
	  $this->getMessagePart($mbox,$mid,$s,0);  // pass 0 as part-number
      else {  // multipart: cycle through each part
	  foreach ($s->parts as $partno0=>$p)
	      $this->getMessagePart($mbox,$mid,$p,$partno0+1);
      }
  }

  private function getMessagePart($mbox, $mid, $p, $partno) {
      // $partno = '1', '2', '2.1', '2.1.3', etc for multipart, 0 if simple

      // DECODE DATA
      $data = ($partno)?
	  imap_fetchbody($mbox,$mid,$partno):  // multipart
	  imap_body($mbox,$mid);  // simple
      // Any part may be encoded, even plain text messages, so check everything.
      if ($p->encoding==4)
	  $data = quoted_printable_decode($data);
      elseif ($p->encoding==3)
	  $data = base64_decode($data);

      // PARAMETERS
      // get all parameters, like charset, filenames of attachments, etc.
      $params = array();
      if (isset($p->parameters))
	  foreach ($p->parameters as $x)
	      $params[strtolower($x->attribute)] = $x->value;
      if (isset($p->dparameters))
	  foreach ($p->dparameters as $x)
	      $params[strtolower($x->attribute)] = $x->value;

      // ATTACHMENT
      // Any part with a filename is an attachment,
      // so an attached text file (type 0) is not mistaken as the message.
      if (isset($params['filename']) || isset($params['name'])) {
	  // filename may be given as 'Filename' or 'Name' or both
	  $filename = isset($params['filename'])? $params['filename'] : $params['name'];
	  // filename may be encoded, so see imap_mime_header_decode()
	  $this->attachments[$filename] = $data;  // this is a problem if two files have same name
      }

      // TEXT
      if ($p->type==0 && $data) {
	  // Messages may be split in different parts because of inline attachments,
	  // so append parts together with blank row.
//write_log($partno);
//write_log($p);
          if (strpos($partno, "1") === 0 || strpos($partno, ".") === FALSE) {
   	      if (strtolower($p->subtype)=='plain')
                  $this->plain_message .= trim($data) ."\n\n";
	      else
	          $this->html_message .= $data ."<br><br>";
              if (isset($params['charset'])) {
  	          $this->charset = $params['charset'];  // assume all parts are same charset
              }
          } else {
   	      if (strtolower($p->subtype)=='plain') {
	          $filename = isset($params['filename'])? $params['filename'] : isset($params['name']) ? $params['name'] : "part_$partno.txt";
	          $this->attachments[$filename] = trim($data);  // this is a problem if two files have same name
	      } else {
	          $filename = isset($params['filename'])? $params['filename'] : isset($params['name']) ? $params['name'] : "part_$partno.html";
	          $this->attachments[$filename] = trim($data);  // this is a problem if two files have same name
              }
          }
      }

      // EMBEDDED MESSAGE
      // Many bounce notifications embed the original message as type 2,
      // but AOL uses type 1 (multipart), which is not handled here.
      // There are no PHP functions to parse embedded messages,
      // so this just appends the raw source to the main message.
      elseif ($p->type==2 && $data) {
	  $this->plain_message .= $data."\n\n";
      }

      // SUBPART RECURSION
      if (isset($p->parts)) {
	  foreach ($p->parts as $partno0=>$p2)
	      $this->getMessagePart($mbox,$mid,$p2,$partno.'.'.($partno0+1));  // 1.2, 1.2.1, etc.
      }
  }
}
?>
