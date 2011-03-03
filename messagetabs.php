<?php
// Copyright (C) 2011  Paul Yasi (paul at citrusdb.org)
// read the README file for more information

/*----------------------------------------------------------------------------*/
// Check for authorized accesss
/*----------------------------------------------------------------------------*/
if(constant("INDEX_CITRUS") <> 1){
  echo "You must be logged in to run this.  Goodbye.";
  exit; 
}

if (!defined("INDEX_CITRUS")) {
  echo "You must be logged in to run this.  Goodbye.";
  exit;
}

// get the variables passed to it by the ajax call, the datetime when the page was loaded first
// if the notes have a newer datetime then show them in red to indicate they are newer
//if (!isset($base->input['datetime'])) { $base->input['datetime'] = ""; }
//$pagedatetime = $base->input['datetime'];

// get the ticketdatetime set by tickets.php that holds the last datetime it was viewed
// to compare that to the newest note's datetime

if (!isset($_COOKIE['ticketdatetime'])) { 
  $_COOKIE['ticketdatetime'] = ""; 
}

$pagedatetime = $_COOKIE['ticketdatetime'];

// make an empty array to hold the message count and initialize nummessages
$messagearray = array();
$nummessages = 0;
$num_rows = 0;
$created = 0;

echo "<div id=\"tabnav\">\n";

// query the customer_history for the number of 
// waiting messages sent to that user
$supportquery = "SELECT id, DATE_FORMAT(creation_date, '%Y%m%d%H%i%s') AS mydatetime ".
  "FROM customer_history WHERE notify = '$user' ".
  "AND status = \"not done\" AND date(creation_date) <= CURRENT_DATE ORDER BY id DESC";
$supportresult = $DB->Execute($supportquery) or die ("$l_queryfailed");

//while ($mysupportresult = $supportresult->FetchRow()) {
//  $num_rows++;
//  if ($num_rows == 1) { $created = $mysupportresult['mydatetime']; }
//}

$num_rows = $supportresult->RowCount();
if ($num_rows > 0) {
  $mysupportresult = $supportresult->fields;
  $created = $mysupportresult['mydatetime'];
}

//$nummessages = $nummessages + $num_rows;

// assign the count of messages to the user message associative array
//$messagearray[$user] = $num_rows;

if ($created > $pagedatetime) {
  $bgstyle = "style = \"background-color: #AFA;\"";
} else {
  $bgstyle = "";
}

if ($num_rows == 0) {
  echo "<a href=\"$url_prefix/index.php?load=tickets&type=base#$user\" $bgstyle>".
    "<b style=\"font-weight:normal;\">$user($num_rows)</b></a>\n";
} else {
  echo "<a href=\"$url_prefix/index.php?load=tickets&type=base#$user\" $bgstyle>$user($num_rows)</a>\n";    
}

// query the customer_history for messages sent to 
// groups the user belongs to
$query = "SELECT * FROM groups WHERE groupmember = '$user' ";
$supportresult = $DB->Execute($query) 
  or die ("$l_queryfailed");

while ($mygroupresult = $supportresult->FetchRow()) {
  if (!isset($mygroupresult['groupname']))  { 
    $mygroupresult['groupname'] = "";    
  }

  // initialize num_rows
  $num_rows = 0;
  $created = 0;
  
  // query each group
  $groupname = $mygroupresult['groupname'];
  $query = "SELECT id, DATE_FORMAT(creation_date, '%Y%m%d%H%i%s') AS mydatetime FROM customer_history WHERE notify = '$groupname' ".
    "AND status = \"not done\" AND date(creation_date) <= CURRENT_DATE ORDER BY id DESC";
  $gpresult = $DB->Execute($query) or die ("$l_queryfailed");

  $num_rows = $gpresult->RowCount();
  if ($num_rows > 0) {
    $mygpresult = $gpresult->fields;
    $created = $mygpresult['mydatetime'];
  }
  
  if ($created > $pagedatetime) {
    $bgstyle = "style = \"background-color: #AFA;\"";
  } else {
    $bgstyle = "";
  }

  if ($num_rows == 0) {
    echo "<a href=\"$url_prefix/index.php?load=tickets&type=base#$groupname\" $bgstyle><b style=\"font-weight:normal;\">$groupname($num_rows)</b></a>\n";
  } else {
    echo "<a href=\"$url_prefix/index.php?load=tickets&type=base#$groupname\" $bgstyle>$groupname($num_rows)</a>\n";    
  }

}


echo "</div>\n";
