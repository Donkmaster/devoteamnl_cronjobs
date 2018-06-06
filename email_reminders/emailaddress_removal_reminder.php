<?php
// Script to send reminders about removing email addresses.
//
// Set it in crontab with something like the following, to schedule every Tuesday, at 10:30:
// #Minute, hour, day_of_month, month, day_of_week, command
// # Send reminder emails about cancelling email, every sunday at 22:30 am.
// 30 22 * * sun (/usr/local/bin/php /home/devoteam/cronjobs/email_reminders/emailaddress_removal_reminder.php)

// Main body.
$WARNING_DAYS_START = 30; // This many days ago left
$WARNING_DAYS_END = 60; // Up to this many days ago left (so, report on people that left between 30 and 60 days ago).

// Parse and set constants
$ini = parse_ini_file('passwords.hidden'); // to use: global $ini;

function getLeavers($warning_days_start, $warning_days_end) {
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
		 "      ,comp.cb_leave_date leave_date" .
		 "      ,comp.cb_payroll payroll" .
		 "      ,comp.cb_joindate joindate" .
		 "      ,comp.cb_practice unit" .
		 "      ,comp.cb_practice2 subunit" .
		 "      ,comp.cb_recruiter recruiter" .
		 "      ,comp.cb_referred_by referer" .
		 " from dtintra_users users" .
		 "     ,dtintra_comprofiler comp" .
		 " where users.id = comp.id" .
		 "  and users.email like '%@devoteam.com'" .
		 "  and comp.cb_leave_date < date_sub(CURDATE(), INTERVAL $warning_days_start DAY)" .
                 "  and comp.cb_leave_date > date_sub(CURDATE(), INTERVAL $warning_days_end DAY)" .
                 "  and comp.cb_leave_date != '0000-00-00'" .
                 " order by email;";
	  // echo "Sql is: " . $sql;
	  $result = mysqli_query($con, $sql);
	  if (mysqli_num_rows($result) > 0) {
	     // Load results into array so that we can release connection sooner
	     while($row = mysqli_fetch_array($result)){
	       $rows[] = $row;
	       //echo "Found a leaving person: " . $row['fullname'] . "\n";
	     }
	     // Cleanup here to release connection as we have results in array
	     mysqli_free_result($result);
	     mysqli_close($con);
	     // For each person found, add to list
             $counter = 0;
	     foreach($rows as $row) {
                $counter++;
		if ($counter == 1) {
			$personlist .= "<tr style='background-color: #DDD;'><th>#</th>" .
                            "<th align='left'>email</th>" .
                            "<th align='left'>Name</th>" .
                            "<th>Leave Date</th>" .
                            "<th>Payroll</th>" .
                            "<th>Join Date</th>" .
                            "<th align='left'>Unit</th>" .
                            "<th align='left'>Subunit</th>" .
                            "<th>Recruiter</th>" .
                            "<th>Referred By</th>" .
                            "</tr>";
		}
		$email = $row['email'];
		$fullname = $row['fullname'];
		$fullname = str_replace('  ', ' ', $fullname);
		$leavedate = $row['leave_date'];
		$joindate = $row['joindate'];
		$payroll = $row['payroll'];
		$unit = $row['unit'];
		$subunit = $row['subunit'];
		$recruiter = $row['recruiter'];
		$referred_by = $row['referer'];
		//echo "Adding person " . $fullname . " to list";
                $backgroundcolor = "#FFF";
                if ($counter % 2 == 0) {
                    $backgroundcolor = "#DDD";
                }
		$personlist .= "<tr style='background-color: ".$backgroundcolor.";'>" .
                    "<td align='left'>$counter</td>" .
                    "<td align='left'>$email</td>" .
                    "<td align='left'>$fullname</td>" .
                    "<td align='right'>$leavedate</td>" .
                    "<td align='center'>".$payroll."</td>" .
                    "<td align='right'>$joindate</td>" .
                    "<td align='left'>$unit</td>" .
                    "<td align='left'>$subunit</td>" .
                    "<td align='left'>".$recruiter."</td>" .
                    "<td align='left'>".$referred_by."</td>" .
                    "</tr>";
	     }
	  } else {
	    // No users found
	    $personlist .= "<tr><td colspan='10' align='left'>No people found that left between ".$warning_days_start." and " .$warning_days_end." days ago.</td></tr>";
	    // Cleanup
	    mysqli_free_result($result);
	    mysqli_close($con);
	  }
	}
	return $personlist;
} // Function getProbationEnd(days)

// Message
$message = '
<html>
<head>
  <title>Email account removal Reminders for people that left between ' . $WARNING_DAYS_START . ' and ' . $WARNING_DAYS_END . ' days ago.</title>
</head>
<body>
  <p>Here are the accounts of people that left Devoteam between ' . $WARNING_DAYS_START . ' and ' . $WARNING_DAYS_END . ' days ago:</p>
  <table>' . 
  getLeavers($WARNING_DAYS_START, $WARNING_DAYS_END) .
  '</table>
  <p style="color: #ffffff;">Forcing correct display of diacritics: éèü</p>
</body>
</html>
';

// To send HTML mail, the Content-type header must be set
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-type: text/html; charset=utf-8';

// Additional headers
$headers[] = 'From: PTT Email account removal Reminder Service <pttreminders@example.com>';
//$headers[] = 'Cc: roeland.lengers@gmail.com';
//$headers[] = 'Bcc: roeland.lengers@devoteam.com';
$headers[] = 'Content-Type: text/html; charset="UTF-8"';
// Multiple recipients
$to = 'roeland.lengers@devoteam.com' .
      ', silvia.smal@devoteam.com' .
      ', nenad.stefanovic@devoteam.com' .
      ', vladimir.francuz@devoteam.com' .
      ', imka.rolie@devoteam.com';
//$to = 'roeland.lengers@devoteam.com';
// Subject
$subject = 'Email account removal reminder for people that left between ' . $WARNING_DAYS_START . ' and ' . $WARNING_DAYS_END . ' days ago';

// Mail it
mail($to, $subject, $message, implode("\r\n", $headers));
//echo "Message is: " . $message;
?>
