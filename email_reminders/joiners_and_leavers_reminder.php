<?php
// Script to send reminders about joiners and leavers in the last week
//
// Set it in crontab with something like the following, to schedule every Tuesday, at 10:30:
// #Minute, hour, day_of_month, month, day_of_week, command
// # Send joiners and leavers reminder emails, every sunday at 22:30 am.
// 30 22 * * sun (/usr/local/bin/php /home/devoteam/cronjobs/email_reminders/joiners_and_leavers_reminder.php)

// Main body.
$WARNING_DAYS = 7;
// Parse and set constants
$ini = parse_ini_file('passwords.hidden'); // to use: global $ini;

function getJoiners($warning_days) {
	$personlist = "";
        global $ini;
        // Check DB connection
        $con=mysqli_connect($ini['DB_HOSTNAME'],$ini['DB_USER'],$ini['DB_PASSWORD'],$ini['DB_DATABASE']);
	if (mysqli_connect_errno()) {
	  echo "Failed to connect to MySQL: " . mysqli_connect_error();
	} else {
	  // Make sure we get diacritics in as well
	  mysqli_query($con, 'SET NAMES utf8');
	  $sql = "select users.id id " .
		 "      ,users.email email" .
		 "      ,users.name fullname" .
		 "      ,comp.cb_contract_signed_date signdate" .
		 "      ,comp.cb_payroll payroll" .
		 "      ,comp.cb_joindate joindate" .
		 "      ,comp.cb_practice unit" .
		 " from dtintra_users users" .
		 "     ,dtintra_comprofiler comp" .
		 " where users.id = comp.id" .
		 "  and users.email like '%@devoteam.com'" .
		 "  and comp.cb_contract_signed_date > DATE_SUB(CURDATE(),INTERVAL " . ($warning_days) . " DAY)" .
                 " order by signdate;";
	  //echo "Sql is: " . $sql;
	  $result = mysqli_query($con, $sql);
	  if (mysqli_num_rows($result) > 0) {
	     // Load results into array so that we can release connection sooner
	     while($row = mysqli_fetch_array($result)){
	       $rows[] = $row;
	       //echo "Found a birthday person: " . $row['fullname'] . "\n";
	     }
	     // Cleanup here to release connection as we have results in array
	     mysqli_free_result($result);
	     mysqli_close($con);
	     // For each person found, add to list
             $counter = 0;
	     foreach($rows as $row) {
                $counter++;
		if ($counter == 1) {
			$personlist .= "<tr><th>#</th><th align='left'>Name</th><th align='left'>Unit</th><th>Signing Date</th><th>Payroll</th><th>Joining Date</th></tr>";
		}
		$email = $row['email'];
		$fullname = $row['fullname'];
		$fullname = str_replace('  ', ' ', $fullname);
		$signdate = $row['signdate'];
		$joindate = $row['joindate'];
		$payroll = $row['payroll'];
		$unit = $row['unit'];
		//echo "Adding person " . $fullname . " to list";
		$personlist .= "<tr><td align='left'>$counter</td><td align='left'>$fullname</td><td align='left'>$unit</td><td align='right'>$signdate</td><td align='center'>".$payroll."</td><td align='right'>".$joindate."</td></tr>";
	     }
	  } else {
	    // No users found
	    $personlist .= "<tr><td colspan='6' align='left'>No signers found in last ".$warning_days." days.</td></tr>";
	    // Cleanup
	    mysqli_free_result($result);
	    mysqli_close($con);
	  }
	}
	return $personlist;
} // Function getJoiners(days)

function getLeavers($warning_days) {
	$personlist = "";
        global $ini;
        // Check DB connection
        $con=mysqli_connect($ini['DB_HOSTNAME'],$ini['DB_USER'],$ini['DB_PASSWORD'],$ini['DB_DATABASE']);
	if (mysqli_connect_errno()) {
	  echo "Failed to connect to MySQL: " . mysqli_connect_error();
	} else {
	  // Make sure we get diacritics in as well
	  mysqli_query($con, 'SET NAMES utf8');
	  $sql = "select users.id id " .
		 "      ,users.email email" .
		 "      ,users.name fullname" .
		 "      ,comp.cb_resign_date resigndate" .
		 "      ,comp.cb_payroll payroll" .
		 "      ,comp.cb_leave_date leavedate" .
		 "      ,comp.cb_practice unit" .
		 " from dtintra_users users" .
		 "     ,dtintra_comprofiler comp" .
		 " where users.id = comp.id" .
		 "  and users.email like '%@devoteam.com'" .
		 "  and comp.cb_resign_date > DATE_SUB(CURDATE(),INTERVAL " . ($warning_days) . " DAY)" .
                 " order by resigndate;";
	  //echo "Sql is: " . $sql;
	  $result = mysqli_query($con, $sql);
	  if (mysqli_num_rows($result) > 0) {
	     // Load results into array so that we can release connection sooner
	     while($row = mysqli_fetch_array($result)){
	       $rows[] = $row;
	       //echo "Found a birthday person: " . $row['fullname'] . "\n";
	     }
	     // Cleanup here to release connection as we have results in array
	     mysqli_free_result($result);
	     mysqli_close($con);
	     // For each person found, add to list
             $counter = 0;
	     foreach($rows as $row) {
                $counter++;
		if ($counter == 1) {
			$personlist .= "<tr><th align='left'>#</th><th align='left'>Name</th><th align='left'>Unit</th><th align='left'>Resigning Date</th><th align='left'>Payroll</th><th align='left'>Leaving Date</th></tr>";
		}
		$email = $row['email'];
		$fullname = $row['fullname'];
		$fullname = str_replace('  ', ' ', $fullname);
		$resigndate = $row['resigndate'];
		$leavedate = $row['leavedate'];
		$payroll = $row['payroll'];
		$unit = $row['unit'];
		//echo "Adding person " . $fullname . " to list";
		$personlist .= "<tr><td align='left'>$counter</td><td align='left'>$fullname</td><td align='left'>$unit</td><td align='right'>$resigndate</td><td align='center'>".$payroll."</td><td align='right'>$leavedate</td></tr>";
	     }
	  } else {
	    // No users found
	    $personlist .= "<tr><td colspan='6' align='left'>No resigners found in last ".$warning_days." days.</td></tr>";
	    // Cleanup
	    mysqli_free_result($result);
	    mysqli_close($con);
	  }
	}
	return $personlist;
} // Function getLeavers(days)

// Message
$message = '
<html>
<head>
  <title>(Re)Signing Reminders for coming ' . $WARNING_DAYS . ' days</title>
</head>
<body>
  <p>Here are the people that <strong>signed up</strong> in the last ' . $WARNING_DAYS . ' days:</p>
  <table>' . 
  getJoiners($WARNING_DAYS) .
  '</table>
  <p>Here are the people that <strong>resigned</strong> in the last ' . $WARNING_DAYS . ' days:</p>
  <table>' . 
  getLeavers($WARNING_DAYS) .
  '</table>
</body>
</html>
';

// To send HTML mail, the Content-type header must be set
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-type: text/html; charset=utf-8';

// Additional headers
$headers[] = 'From: PTT (Re)Signing Reminder Service <pttreminders@example.com>';
//$headers[] = 'Cc: roeland.lengers@gmail.com';
//$headers[] = 'Bcc: roeland.lengers@devoteam.com';
// Multiple recipients
$to = 'roeland.lengers@devoteam.com' .
      ', kitty.egelman@devoteam.com' .
      ', lara.meijer@devoteam.com' .
      ', arjan.van.grol@devoteam.com' .
      ', bryan.van.harten@devoteam.com' .
      ', silvia.smal@devoteam.com' .
      ', stans.schumacher@devoteam.com' .
      ', imka.rolie@devoteam.com' .
      ', marielle.callaars@devoteam.com' .
      ', bert.schaap@devoteam.com' .
      ', christian.flaig@devoteam.com' .
      ', nenad.stefanovic@devoteam.com' .
      ', martin.horstink@devoteam.com' .
      ', stevan.ognjenovic@devoteam.com' .
      ', andy.pritchard@devoteam.com' .
      ', steven.paddock@devoteam.com' ;
//$to = 'roeland.lengers@devoteam.com'; // note the comma
// Subject
$subject = 'Signing and Resigning Reminders for last ' . $WARNING_DAYS . ' days';

// Mail it
mail($to, $subject, $message, implode("\r\n", $headers));
//echo "Message is: " . $message;
?>
