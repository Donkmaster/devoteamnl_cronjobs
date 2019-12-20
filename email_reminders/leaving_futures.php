<?php
// Script to send reminders about people that have passed their 5 months in Futures program and still have as manager "arjan.van.grol@devoteam.com"
//
// Set it in crontab with something like the following, to schedule every Tuesday, at 10:30:
// #Minute, hour, day_of_month, month, day_of_week, command
// # Send list of people out of Futures program, but still with wrong manager.
// 30 22 * * sun (/usr/local/bin/php /home/devoteam/cronjobs/email_reminders/leaving_futures.php)

// Main body.
$WARNING_DAYS = 155; // 5 months
// Parse and set constants
$ini = parse_ini_file('passwords.hidden'); // to use: global $ini;

function getFuturesEnd($warning_days) {
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
		 "      ,comp.cb_contract_enddate contractenddate" .
		 "      ,comp.cb_payroll payroll" .
		 "      ,comp.cb_joindate joindate" .
		 "      ,comp.cb_practice unit" .
		 "      ,comp.cb_practice2 subunit" .
		 "      ,comp.cb_manager manager" .
		 "      ,comp.avatar avatar" .
		 " from dtintra_users users" .
		 "     ,dtintra_comprofiler comp" .
		 " where users.id = comp.id" .
		 "  and users.email like '%@devoteam.com'" .
		 "  and users.email != 'samuel.veldhuizen@devoteam.com'" .
		 "  and comp.cb_joindate < DATE_ADD(CURDATE(),INTERVAL -" . ($warning_days) . " DAY)" .
		 "  and (comp.cb_leave_date > now() or comp.cb_leave_date is null)" .
		 "  and comp.cb_originally_futures = 'Yes'" .
		 "  and (comp.cb_manager = '" . $ini['FUTURES_MANAGER'] . "' or comp.cb_practice like 'Futures%') " .
                 " order by joindate;";
	  //echo "Sql is: " . $sql;
	  $result = mysqli_query($con, $sql);
	  if (mysqli_num_rows($result) > 0) {
	     // Load results into array so that we can release connection sooner
	     while($row = mysqli_fetch_array($result)){
	       $rows[] = $row;
	       //echo "Found a person: " . $row['fullname'] . "\n";
	     }
	     // Cleanup here to release connection as we have results in array
	     mysqli_free_result($result);
	     mysqli_close($con);
	     // For each person found, add to list
             $counter = 0;
	     foreach($rows as $row) {
                $counter++;
		if ($counter == 1) {
			$personlist .= "<tr style='background-color: #DDD;'><th align='left'>#</th><th align='left'>Name</th><th align='left'>Practice</th><th align='left'>Unit</th><th>Manager</th><th align='left'>Join Date</th><th align='left'>Payroll</th><th align='left'>AdminLink</th></tr>";
		}
		$userid = $row['id'];
		$email = $row['email'];
		$fullname = $row['fullname'];
		$fullname = str_replace('  ', ' ', $fullname);
		$contractenddate = $row['contractenddate'];
		$joindate = $row['joindate'];
		$payroll = $row['payroll'];
		$unit = $row['unit'];
		$subunit = $row['subunit'];
		$manager = $row['manager'];
		$avatar = $row['avatar'];
		//echo "Adding person " . $fullname . " to list";
                $backgroundcolor = "#FFF";
                if ($counter % 2 == 0) {
                    $backgroundcolor = "#DDD";
                }
                $adminlink = "<a href='https://team.devoteam.nl/administrator/index.php?option=com_comprofiler&view=edit&cid=".$userid."'><img width='40' src='https://team.devoteam.nl/images/comprofiler/tn" . $avatar. "'></a>";
		$personlist .= "<tr style='background-color: ".$backgroundcolor.";'><td align='left'>$counter</td><td align='left'><a href='mailto:$email'>$fullname</a></td><td align='left'>$unit</td><td align='left'>$subunit</td><td>$manager</td><td align='right'>$joindate</td><td align='center'>".$payroll."</td><td align='right'>$adminlink</td></tr>";
	     }
	  } else {
	    // No users found
	    $personlist = "";
	    // Cleanup
	    mysqli_free_result($result);
	    mysqli_close($con);
	  }
	}
	return $personlist;
} // Function getFuturesEnd(days)

// Message
$employeelist = getFuturesEnd($WARNING_DAYS);
if ($employeelist == '') {
  return;
}
$message = '
<html>
<head>
  <title>Warninglist: people passed ' . $WARNING_DAYS . ' days in futures with "manager" still at ' . $ini['FUTURES_MANAGER'] . '</title>
</head>
<body>
  <h2 style="color: #ff0000;">People in the Futures Program for more than ' . $WARNING_DAYS . ' days and still have ' . $ini['FUTURES_MANAGER'] . '  as manager or are not assigned to proper unit/subunit:</h2>
  <table>' . 
  $employeelist .
  '</table>
  <h2>Please make changes on team.devoteam.nl to their Unit, SubUnit and especially their Manager.</h2>
  <h2>Click on their images to be taken directly to the admin interface.</h2>
  <p style="color: #ffffff;">Forcing correct display of diacritics: éèü</p>
</body>
</html>
';

//echo $message;

// To send HTML mail, the Content-type header must be set
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-type: text/html; charset=utf-8';

// Additional headers
$headers[] = 'From: PTT Futures Program Past Reminder Service <pttreminders@example.com>';
//$headers[] = 'Cc: roeland.lengers@gmail.com';
//$headers[] = 'Bcc: roeland.lengers@devoteam.com';
$headers[] = 'Content-Type: text/html; charset="UTF-8"';
// Multiple recipients
$to = $ini['PTT_MANAGER'] .
      ", " . $ini['FUTURES_MANAGER'];
//$to = $ini['PTT_MANAGER'];
// Subject
$subject = 'Futures Program passed ' . $WARNING_DAYS . ' days reminder';

// Mail it
mail($to, $subject, $message, implode("\r\n", $headers));
//echo "Message is: " . $message;
?>
