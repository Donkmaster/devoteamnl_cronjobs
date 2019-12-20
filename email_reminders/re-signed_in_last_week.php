<?php
// Script to send reminders about signers and resigners and payroll change that were registered in the last week
// Logic:
// Run query looking for change in signing or resigning versus temporary table
// Report the ones with differences
// Update temporary table for use next week
//
// Set it in crontab with something like the following, to schedule every Tuesday, at 10:30:
// #Minute, hour, day_of_month, month, day_of_week, command
// # Send signers and resigners and payroll change reminder emails, every sunday at 22:30 am.
// 30 22 * * sun (/usr/local/bin/php /home/devoteam/cronjobs/email_reminders/re-signed_in_last_week.php)

// Main body.
$WARNING_DAYS = 7;
// Parse and set constants
$ini = parse_ini_file('passwords.hidden'); // to use: global $ini;

function dropAndCreateTempTable() {
        global $ini;
        // Check DB connection
        $con=mysqli_connect($ini['DB_HOSTNAME'],$ini['DB_USER'],$ini['DB_PASSWORD'],$ini['DB_DATABASE']);
	if (mysqli_connect_errno()) {
	  echo "Failed to connect to MySQL: " . mysqli_connect_error();
          die('Could not connect: ' . mysql_error());
	} else {
	  // Make sure we get diacritics in as well
	  mysqli_query($con, 'SET NAMES utf8');
	  $sql = "drop table temp_users_last_week;";
	  //echo "Sql is: " . $sql;
	  if ( mysqli_query($con, $sql) ) {
            //echo "Table succesfully dropped\n";
          } else {
            echo "Could not delete table\n";
          }
	  // Cleanup
	  //mysqli_free_result($result); // no results to be freed
	  //mysqli_close($con);
	}
        // Create new table
	$sql = "create table temp_users_last_week as (" .
          " select users.id id" .
          "      ,users.email email" .
	  "      ,comp.cb_payroll" .
          "      ,comp.cb_joindate" .
   	  "      ,comp.cb_contract_signed_date" .
	  "      ,comp.cb_resign_date" .
	  "      ,comp.cb_leave_date" .
          "  from dtintra_users users" .
          "     , dtintra_comprofiler comp " .
          " where users.id = comp.id);";
	//echo "Sql is: " . $sql;
	if (mysqli_query($con, $sql)) {
          //echo "Create table worked!\n";
        } else {
          echo "Create table failure!\n";
        }
	//mysqli_free_result($result); // no results to be freed
	mysqli_close($con);
	return;
} // Function dropAndCreateTempTable()

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
		 "     ,temp_users_last_week temp" .
		 " where users.id = comp.id" .
                 "  and users.id = temp.id" .
		 "  and users.email like '%@devoteam.com'" .
                 "  and (temp.cb_contract_signed_date != comp.cb_contract_signed_date " .
                 "       or (temp.cb_contract_signed_date is not null " .
                 "           and comp.cb_contract_signed_date is null) " . 
                 "       or (comp.cb_contract_signed_date is not null " .
                 "           and temp.cb_contract_signed_date is null)) " .
                 " order by signdate;";
	  //echo "Sql is: " . $sql;
	  $result = mysqli_query($con, $sql);
	  if (mysqli_num_rows($result) > 0) {
	     // Load results into array so that we can release connection sooner
	     while($row = mysqli_fetch_array($result)){
	       $rows[] = $row;
	     }
	     // Cleanup here to release connection as we have results in array
	     mysqli_free_result($result);
	     mysqli_close($con);
	     // For each person found, add to list
             $counter = 0;
	     foreach($rows as $row) {
                $counter++;
		if ($counter == 1) {
			$personlist .= "<tr><th>#</th><th align='left'>Name</th><th align='left'>Unit</th><th>Signing Date</th><th>Signing WEEK</th><th>Payroll</th><th>Joining Date</th></tr>";
		}
		$email = $row['email'];
		$fullname = $row['fullname'];
		$fullname = str_replace('  ', ' ', $fullname);
		$signdate = $row['signdate'];
                $signdatedate = new DateTime($signdate);
                $week = $signdatedate->format("W");
		$joindate = $row['joindate'];
		$payroll = $row['payroll'];
		$unit = $row['unit'];
		//echo "Adding person " . $fullname . " to list";
		$personlist .= "<tr><td align='left'>$counter</td><td align='left'>$fullname</td><td align='left'>$unit</td><td align='right'>$signdate</td><td align='center'>$week</td><td align='center'>".$payroll."</td><td align='right'>".$joindate."</td></tr>";
	     }
	  } else {
	    // No users found
	    $personlist .= "<tr><td colspan='7' align='left'>No registration of signers found in last ".$warning_days." days.</td></tr>";
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
		 "     ,temp_users_last_week temp" .
		 " where users.id = comp.id" .
                 "  and users.id = temp.id" .
		 "  and users.email like '%@devoteam.com'" .
                 "  and (temp.cb_resign_date != comp.cb_resign_date " .
                 "       or (temp.cb_resign_date is not null " .
                 "           and comp.cb_resign_date is null) " . 
                 "       or (comp.cb_resign_date is not null " .
                 "           and temp.cb_resign_date is null)) " .
                 " order by resigndate;";
	  //echo "Sql is: " . $sql;
	  $result = mysqli_query($con, $sql);
	  if (mysqli_num_rows($result) > 0) {
	     // Load results into array so that we can release connection sooner
	     while($row = mysqli_fetch_array($result)){
	       $rows[] = $row;
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
	    $personlist .= "<tr><td colspan='6' align='left'>No registration of resigners found in last ".$warning_days." days.</td></tr>";
	    // Cleanup
	    mysqli_free_result($result);
	    mysqli_close($con);
	  }
	}
	return $personlist;
} // Function getLeavers(days)

function getNewPayroll($warning_days) {
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
		 "     ,temp_users_last_week temp" .
		 " where users.id = comp.id" .
                 "  and users.id = temp.id" .
		 "  and users.email like '%@devoteam.com'" .
                 "  and (temp.cb_payroll != comp.cb_payroll " .
                 "       or (temp.cb_payroll is not null " .
                 "           and comp.cb_payroll is null) " . 
                 "       or (comp.cb_payroll is not null " .
                 "           and temp.cb_payroll is null)) " .
                 " order by email;";
	  //echo "Sql is: " . $sql;
	  $result = mysqli_query($con, $sql);
	  if (mysqli_num_rows($result) > 0) {
	     // Load results into array so that we can release connection sooner
	     while($row = mysqli_fetch_array($result)){
	       $rows[] = $row;
	     }
	     // Cleanup here to release connection as we have results in array
	     mysqli_free_result($result);
	     mysqli_close($con);
	     // For each person found, add to list
             $counter = 0;
	     foreach($rows as $row) {
                $counter++;
		if ($counter == 1) {
			$personlist .= "<tr><th align='left'>#</th><th align='left'>Name</th><th align='left'>Unit</th><th align='left'>New Payroll</th></tr>";
		}
		$email = $row['email'];
		$fullname = $row['fullname'];
		$fullname = str_replace('  ', ' ', $fullname);
		$resigndate = $row['resigndate'];
		$leavedate = $row['leavedate'];
		$payroll = $row['payroll'];
		$unit = $row['unit'];
		//echo "Adding person " . $fullname . " to list";
		$personlist .= "<tr><td align='left'>$counter</td><td align='left'>$fullname</td><td align='left'>$unit</td><td align='center'>".$payroll."</td></tr>";
	     }
	  } else {
	    // No users found
	    $personlist .= "<tr><td colspan='4' align='left'>No change in payroll found in last ".$warning_days." days.</td></tr>";
	    // Cleanup
	    mysqli_free_result($result);
	    mysqli_close($con);
	  }
	}
	return $personlist;
} // Function getNewPayroll($warning_days)

// Message
$message = '
<html>
<head>
  <title>(Re)Signing Reminders for coming ' . $WARNING_DAYS . ' days</title>
</head>
<body>
  <p>Here are the people that were <i>registered</i> as <strong>signed up</strong> in the last ' . $WARNING_DAYS . ' days:</p>
  <table>' . 
  getJoiners($WARNING_DAYS) .
  '</table>
  <p>Here are the people that were <i>registered</i> as <strong>resigned</strong> in the last ' . $WARNING_DAYS . ' days:</p>
  <table>' . 
  getLeavers($WARNING_DAYS) .
  '</table>
  <p>Here are the people that were <i>registered</i> as <strong>changed payroll</strong> in the last ' . $WARNING_DAYS . ' days:</p>
  <table>' . 
  getNewPayroll($WARNING_DAYS) .
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
$to = ' nl.management_team@devoteam.com' .
      ', imka.rolie@devoteam.com' .
      ', marc.bovy@devoteam.com' .
      ', nenad.stefanovic@devoteam.com' .
      ', nl.delivery@devoteam.com' .
      ', vladimir.francuz@devoteam.com' .
      ', stevan.ognjenovic@devoteam.com' .
      ', nl.ptt@devoteam.com' ;
$to = 'roeland.lengers@devoteam.com, stans.schumacher@devoteam.com, marc.bovy@devoteam.com'; // Yes: only to Stans, Bovy and Roeland
//$to = 'roeland.lengers@devoteam.com';
// Subject
$subject = '(Re)Signing and Resigning Reminders for last ' . $WARNING_DAYS . ' days';

// Mail it
mail($to, $subject, $message, implode("\r\n", $headers));
//echo "Message is: " . $message;

// Drop the temp table and recreate for next weeks use
dropAndCreateTempTable();

?>
