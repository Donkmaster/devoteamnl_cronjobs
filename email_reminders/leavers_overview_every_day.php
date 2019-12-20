<?php
// Script to send reminders about joiners and leavers in the last week
//
// Set it in crontab with something like the following, to schedule every Tuesday, at 10:30:
// #Minute, hour, day_of_month, month, day_of_week, command
// # Send starters reminder email, every third friday at 22:30 am.
// 15 10 15-21 * fri (/usr/local/bin/php /home/devoteam/cronjobs/email_reminders/starters_overview.php)


// Parse and set constants
$ini = parse_ini_file('passwords.hidden'); // to use: global $ini;

// Main body.
$WARNING_DAYS = 31;

function getLeavers($warning_days) {
	$personlist = "";
        $nowdate = date_create('now')->format('Y-m-d');
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
		 "      ,comp.cb_practice2 subunit" .
		 "      ,comp.cb_resignationonfile cb_resignationonfile" .
		 "      ,comp.cb_resignationconfirmed cb_resignationconfirmed" .
		 "      ,comp.cb_devoteamlaptop cb_devoteamlaptop" .
		 "      ,comp.cb_devoteamphone cb_devoteamphone" .
		 "      ,comp.cb_resignationconfirmationsent cb_resignationconfirmationsent" .
		 "      ,comp.cb_keep_mobile_number cb_keep_mobile_number" .
                 "      ,comp.cb_originally_futures futures" .
                 "      ,comp.cb_exitinterview_invited exitinterview_invited" .
		 " from dtintra_users users" .
		 "     ,dtintra_comprofiler comp" .
		 " where users.id = comp.id" .
		 "  and users.email like '%@devoteam.com'" .
		 "  and comp.cb_leave_date > DATE_ADD(CURDATE(),INTERVAL -" . ($warning_days) . " DAY)" .
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
		  $personlist .= "<tr style='background-color: #DDD;'><th align='left'>#</th>" .
		    "<th align='left'>Name</th>" .
		    "<th align='left'>Practice</th>" .
		    "<th align='left'>Unit</th>" .
		    "<th align='left'>Resigning Date</th>" .
		    "<th align='center'>Payroll</th>" .
                    "<th align='center'>Started as Futures?</th>".
		    "<th align='left'>Leaving Date</th>" .
		    "<th align='center'>Resignation<br/>on file?</th>" .
		    "<th align='center'>Resignation<br/>Confirmed?</th>" .
		    "<th align='center'>Devoteam<br/>Laptop?</th>" .
		    "<th align='center'>Devoteam<br/>Phone?</th>" .
		    "<th align='center'>Wants to keep<br/>mobile number?</th>" .
		    "<th align='center'>Resignation<br/>Confirmation Sent?</th>" .
		    "<th align='center'>Invited for<br/>Exit Interview?</th>" .
		    "</tr>";
		}
		$userid = $row['id'];
		$email = $row['email'];
		$fullname = $row['fullname'];
		$fullname = str_replace('  ', ' ', $fullname);
		$resigndate = $row['resigndate'];
		$leavedate = $row['leavedate'];
		$payroll = $row['payroll'];
		$unit = $row['unit'];
		$subunit = $row['subunit'];
                $futures = $row['futures'];
                $exitinterview = $row['exitinterview_invited'];
		$keep_mobile_number = $row['cb_keep_mobile_number'];
		//echo "Adding person " . $fullname . " to list";
                $backgroundcolor = "#FFF";
                $color = "#000";
                if ($counter % 2 == 0) {
                    $backgroundcolor = "#DDD";
                    $color = "#000";
                }
                if ($leavedate < $nowdate) {
                    $backgroundcolor = "#F8485E";
                    $color = "#FFF";
                }

		$personlist .= "<tr style='background-color: ".$backgroundcolor."; color: ".$color."'><td align='left'>$counter</td>" .
		  "<td align='left'><a href='https://team.devoteam.nl/administrator/index.php?option=com_comprofiler&view=edit&cid=".$userid."'>$fullname</a></td>" .
		  "<td align='left'>$unit</td>" .
		  "<td align='left'>$subunit</td>" .
		  "<td align='right'>$resigndate</td>" .
		  "<td align='center'>$payroll</td>" .
                  "<td align='center'>".$futures."</td>".
		  "<td align='right'>$leavedate</td>" .
		  "<td align='center'>".$row['cb_resignationonfile']."</td>" .
		  "<td align='center'>".$row['cb_resignationconfirmed']."</td>" .
		  "<td align='center'>".$row['cb_devoteamlaptop']."</td>" .
		  "<td align='center'>".$row['cb_devoteamphone']."</td>" .
		  "<td align='center'>".$row['cb_keep_mobile_number']."</td>" .
		  "<td align='center'>".$row['cb_resignationconfirmationsent']."</td>" .
		  "<td align='center'>".$row['exitinterview_invited']."</td>" .
		  "</tr>";
	     }
	  } else {
	    // No users found
	    $personlist .= "<tr><td colspan='15' align='left'>No resigners found in last ".$warning_days." days.</td></tr>";
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
  <title>Daily Colleagues Leaving Reminder</title>
</head>
<body>
  <p>Here are the colleagues that will leave in the future or have left in the last ' . $WARNING_DAYS . ' days:</p>
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
$headers[] = 'From: PTT Colleagues Leaving Reminder Service <pttreminders@example.com>';
//$headers[] = 'Cc: roeland.lengers@gmail.com';
//$headers[] = 'Bcc: roeland.lengers@devoteam.com';
// Multiple recipients
$to = "nl.ptt@devoteam.com" .
      ", wilfred.mollenvanger@devoteam.com" .
      ", imka.rolie@devoteam.com" .
      ", marielle.callaars@devoteam.com" .
      ", marc.bovy@devoteam.com" .
      ", carola.johanningmeijer-harmsen@devoteam.com";
//$to = 'roeland.lengers@devoteam.com';
// Subject
$subject = 'Daily Colleagues Leaving Reminder';

// Mail it
mail($to, $subject, $message, implode("\r\n", $headers));
//echo "Message is: " . $message;
?>
