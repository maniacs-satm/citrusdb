<?php
// Copyright (C) 2008  Paul Yasi (paul at citrusdb.org)
// read the README file for more information

// generic ticket creation function
function create_ticket($DB, $user, $notify, $account_number, $status,
		       $description, $linkname = NULL, $linkurl = NULL,
		       $reminderdate = NULL, $user_services_id = NULL)
{
  global $url_prefix;
  
  if ($reminderdate) {
    if ($user_services_id) {
    // add ticket to customer_history table
    $query = "INSERT into customer_history ".
      "(creation_date, created_by, notify, account_number,".
      "status, description, linkurl, linkname, user_services_id) ".
      "VALUES ('$reminderdate', '$user', '$notify', '$account_number',".
      "'$status', '$description', '$linkurl', '$linkname', '$user_services_id')";
    } else {
      $query = "INSERT into customer_history ".
      "(creation_date, created_by, notify, account_number,".
      "status, description, linkurl, linkname) ".
      "VALUES ('$reminderdate', '$user', '$notify', '$account_number',".
      "'$status', '$description', '$linkurl', '$linkname')";
    }
  } else {
    if ($user_services_id) {
    // add ticket to customer_history table
      $query = "INSERT into customer_history ".
      "(creation_date, created_by, notify, account_number,".
      "status, description, linkurl, linkname, user_services_id) ".
      "VALUES (CURRENT_TIMESTAMP, '$user', '$notify', '$account_number',".
      "'$status', '$description', '$linkurl', '$linkname', '$user_services_id')";
    } else {
      $query = "INSERT into customer_history ".
      "(creation_date, created_by, notify, account_number,".
      "status, description, linkurl, linkname) ".
      "VALUES (CURRENT_TIMESTAMP, '$user', '$notify', '$account_number',".
      "'$status', '$description', '$linkurl', '$linkname')";      
    }
  }

  $result = $DB->Execute($query) or die ("create_ticket query failed");
  $ticketnumber = $DB->Insert_ID();

  $url = "$url_prefix/index.php?load=support&type=module&editticket=on&id=$ticketnumber";
  $message = "$notify: $description $url";

  // if the notify is a group or a user, if a group, then get all the users and notify each individual
  $query = "SELECT * FROM groups WHERE groupname = '$notify'";
  $DB->SetFetchMode(ADODB_FETCH_ASSOC);
  $result = $DB->Execute($query) or die ("Group Query Failed");
  
  if ($result->RowCount() > 0) {
    // we are notifying a group of users
    while ($myresult = $result->FetchRow()) {
      $groupmember = $myresult['groupmember'];
      enotify($DB, $groupmember, $message, $ticketnumber, $user, $notify, $description);
    } // end while    
  } else {
    // we are notifying an individual user
    enotify($DB, $notify, $message, $ticketnumber, $user, $notify, $description);
  } // end if result

  return $ticketnumber;

} // end create_ticket function


function enotify($DB, $user, $message, $ticketnumber, $fromuser, $tousergroup, $description)
{
  /*--------------------------------------------------------------------------*/
  // send notifications to a the jabber ID or email address
  /*--------------------------------------------------------------------------*/

  global $lang;
  include("$lang");
  
  $query = "SELECT email,screenname,email_notify,screenname_notify FROM user WHERE username = '$user'";
  $DB->SetFetchMode(ADODB_FETCH_ASSOC);
  $result = $DB->Execute($query) or die ("select screename $l_queryfailed");
  $myresult = $result->fields;
  $email = $myresult['email'];
  $screenname = $myresult['screenname'];
  $email_notify = $myresult['email_notify'];
  $screenname_notify = $myresult['screenname_notify'];

  include 'config.inc.php'; // include this here for jabber server config vars

  // if they have specified a screenname then send them a jabber notification
  if (($xmpp_server) && ($screenname) && ($screenname_notify == 'y')) {
    include 'XMPPHP/XMPP.php';

    // edit this to use database jabber user defined in config file
    $conn = new XMPPHP_XMPP("$xmpp_server", 5222, "$xmpp_user", "$xmpp_password", 'xmpphp', "$xmpp_domain", $printlog=false, $loglevel=XMPPHP_Log::LEVEL_INFO);
    
    try {
      $conn->connect();
      $conn->processUntil('session_start');
      $conn->presence();
      $conn->message("$screenname", "$message");
      $conn->disconnect();
    } catch(XMPPHP_Exception $e) {
      //die($e->getMessage());
      $xmppmessage = $e->getMessage();
      echo "$xmppmessage";
    }
  }
  
  // if they have specified an email then send them an email notification
  if (($email) && ($email_notify == 'y')) {

    // HTML Email Headers
    $to = $email;
    // truncate the description to fit in the subject
    $description = substr($description, 0, 40);    
    $subject = "$l_ticketnumber$ticketnumber $l_to: $tousergroup $l_from: $fromuser $description";
    mail ($to, $subject, $message);
    
  }
}

// add a service message ticket for new, modified, or shutoff services
function service_message($service_notify_type, $account_number,
			 $master_service_id, $user_service_id,
			 $new_master_service_id, $new_user_service_id)
			
{
  global $DB, $user, $lang;
  include("$lang");
  
  /*- Service Notify Types -*/
  // added
  // change - uses both user_service_id and new_user_service_id
  //   the change function will need to create a new_user_service_id
  //   like it should have been doing
  //
  // onetime - for one time billing removals
  // undelete
  //
  // removed
  // canceled
  // turnoff
  /*-------------------------*/

  // get the name of the service
  $query = "SELECT * FROM master_services WHERE id = $master_service_id";
  $DB->SetFetchMode(ADODB_FETCH_ASSOC);
  $result = $DB->Execute($query) or die ("service_message $l_queryfailed");
  $myresult = $result->fields;	
  $servicename = $myresult['service_description'];
  $activate_notify = $myresult['activate_notify']; // added
  $modify_notify = $myresult['modify_notify'];     // change,undelete
  $shutoff_notify = $myresult['shutoff_notify'];   // turnoff, removed, canceled
  
  // set a different notify and description depending on service_notify_type

  // ADDED
  if ($service_notify_type == "added") {
    $description = "$l_added $servicename $user_service_id";
    if ($activate_notify <> '') {
      $status = "not done";
      $notify = $activate_notify;
    } else {
      $status = "automatic";
      $notify = "";
    }
  }

  // CHANGE
  if ($service_notify_type == "change") { 
    // get the name of the new service
    $query = "SELECT * FROM master_services WHERE id = $new_master_service_id";
    $DB->SetFetchMode(ADODB_FETCH_ASSOC);
    $result = $DB->Execute($query)
      or die ("service_message_modify $l_queryfailed");
    $myresult = $result->fields;	
    $new_servicename = $myresult['service_description'];
    // use the new services modify notify, maybe different from old one
    $modify_notify = $myresult['modify_notify'];

    $description = "$l_change $servicename $user_service_id -> $new_servicename $new_user_service_id";
    if ($modify_notify <> '') {
      $status = "not done";
      $notify = $modify_notify;
    } else {
      $status = "automatic";
      $notify = "";
    } 
  }

  // UNDELETE
  if ($service_notify_type == "undelete") {
    $description = "$l_undelete $servicename $user_service_id";    
    if ($modify_notify <> '') {
      $status = "not done";
      $notify = $modify_notify;
    } else {
      $status = "automatic";
      $notify = "";
    } 
  }

    // ONETIME
  if ($service_notify_type == "onetime") {
    $description = "$l_onetimebilled $servicename $user_service_id";    
    if ($shutoff_notify <> '') {
      $status = "not done";
      $notify = $shutoff_notify;
    } else {
      $status = "automatic";
      $notify = "";
    } 
  }

  // REMOVED
  if ($service_notify_type == "removed") {
    $description = "$l_removed $servicename $user_service_id";    

    if ($shutoff_notify <> '') {
      $status = "not done";
      $notify = $shutoff_notify;
    } else {
      $status = "automatic";
      $notify = "";
    }
    
  }  
  
  // CANCELED
  if ($service_notify_type == "canceled") {
    $description = "$l_canceled $servicename $user_service_id";    

    if ($shutoff_notify <> '') {
      $status = "not done";
      $notify = $shutoff_notify;
    } else {
      $status = "automatic";
      $notify = "";
    }

  }


  // TURNOFF
  if ($service_notify_type == "turnoff") {
    $description = "$l_turnoff $servicename $user_service_id";    

    if ($shutoff_notify <> '') {
      $status = "not done";
      $notify = $shutoff_notify;
    } else {
      $status = "automatic";
      $notify = "";
    }

  }
  
  // create the ticket with the service message
  create_ticket($DB, $user, $notify, $account_number, $status, $description, NULL, NULL, NULL, $user_service_id);
}

// Print Tabs Showing Number of New Support Messages
function message_tabs($DB, $user) {
  
  // make an empty array to hold the message count and initialize nummessages
  $messagearray = array();
  $nummessages = 0;
    
  // query the customer_history for the number of 
  // waiting messages sent to that user
  $supportquery = "SELECT * FROM customer_history WHERE notify = '$user' ".
    "AND status = \"not done\" AND date(creation_date) <= CURRENT_DATE";
  $supportresult = $DB->Execute($supportquery) or die ("$l_queryfailed");
  $num_rows = $supportresult->RowCount();
  
  $nummessages = $nummessages + $num_rows;
  
  // assign the count of messages to the user message associative array
  $messagearray[$user] = $num_rows;
  
  // query the customer_history for messages sent to 
  // groups the user belongs to
  $query = "SELECT * FROM groups WHERE groupmember = '$user' ";
  $supportresult = $DB->Execute($query) 
    or die ("$l_queryfailed");
  
  while ($mygroupresult = $supportresult->FetchRow()) {
    if (!isset($mygroupresult['groupname']))  { 
      $mygroupresult['groupname'] = ""; 
    }
    
    // query each group
    $groupname = $mygroupresult['groupname'];
    $query = "SELECT * FROM customer_history WHERE notify = '$groupname' ".
      "AND status = \"not done\" AND date(creation_date) <= CURRENT_DATE";
    $gpresult = $DB->Execute($query) or die ("$l_queryfailed");
    $num_rows = $gpresult->RowCount();
    
    $nummessages = $nummessages + $num_rows;
    
    // assign the count of messages to the user message associative array
    $messagearray[$groupname] = $num_rows;  
    
  }

echo "<div id=\"tabnav\">\n";
foreach ($messagearray as $recipient => $messagecount) {
  if ($messagecount == 0) {
    echo "<a href=\"index.php?load=tickets&type=base#$recipient\"><b style=\"font-weight:normal;\">$recipient($messagecount)</b></a>\n";
  } else {
    echo "<a href=\"index.php?load=tickets&type=base#$recipient\">$recipient($messagecount)</a>\n";    
  }
  echo "<input type=text name=\"$recipient\" value=\"$messagecount\">";
}
echo "</div>\n";

return $messagearray;

}