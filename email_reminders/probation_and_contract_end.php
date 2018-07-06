<?php
// Script to send reminders about probation period and contract ending in the coming period
//
// Set it in crontab with something like the following, to schedule every Tuesday, at 10:30:
// #Minute, hour, day_of_month, month, day_of_week, command
// # Send joiners and leavers reminder emails, every sunday at 22:30 am.
// 30 22 * * sun (/usr/local/bin/php /home/devoteam/cronjobs/email_reminders/probation_and_contract_end.php)

// Main body.
$WARNING_DAYS = 120; // 4 months should be enough
// Parse and set constants
$ini = parse_ini_file('passwords.hidden'); // to use: global $ini;

function getProbationEnd($warning_days) {
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
		 "      ,comp.cb_probationenddate probationenddate" .
		 "      ,comp.cb_payroll payroll" .
		 "      ,comp.cb_joindate joindate" .
		 "      ,comp.cb_practice unit" .
		 "      ,comp.cb_recruiter recruiter" .
		 "      ,comp.cb_referred_by referer" .
		 " from dtintra_users users" .
		 "     ,dtintra_comprofiler comp" .
		 " where users.id = comp.id" .
		 "  and users.email like '%@devoteam.com'" .
		 "  and comp.cb_probationenddate < DATE_ADD(CURDATE(),INTERVAL " . ($warning_days) . " DAY)" .
		 "  and comp.cb_probationenddate > now()" .
                 " order by probationenddate;";
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
			$personlist .= "<tr style='background-color: #DDD;'><th>#</th>" .
                            "<th align='left'>Name</th>" .
                            "<th align='left'>Unit</th>" .
                            "<th>Join Date</th><th>Payroll</th>" .
                            "<th>Probation End Date</th>" .
                            "<th>Recruiter</th>" .
                            "<th>Referred By</th>" .
                            "</tr>";
		}
		$email = $row['email'];
		$fullname = $row['fullname'];
		$fullname = str_replace('  ', ' ', $fullname);
		$probationenddate = $row['probationenddate'];
		$joindate = $row['joindate'];
		$payroll = $row['payroll'];
		$unit = $row['unit'];
		$recruiter = $row['recruiter'];
		$referred_by = $row['referer'];
		//echo "Adding person " . $fullname . " to list";
                $backgroundcolor = "#FFF";
                if ($counter % 2 == 0) {
                    $backgroundcolor = "#DDD";
                }
		$personlist .= "<tr style='background-color: ".$backgroundcolor.";'>" .
                    "<td align='left'>$counter</td>" .
                    "<td align='left'>$fullname</td>" .
                    "<td align='left'>$unit</td>" .
                    "<td align='right'>$joindate</td>" .
                    "<td align='center'>".$payroll."</td>" .
                    "<td align='right'>".$probationenddate."</td>" .
                    "<td align='left'>".$recruiter."</td>" .
                    "<td align='left'>".$referred_by."</td>" .
                    "</tr>";
	     }
	  } else {
	    // No users found
	    $personlist .= "<tr><td colspan='6' align='left'>No probation end dates found in coming ".$warning_days." days.</td></tr>";
	    // Cleanup
	    mysqli_free_result($result);
	    mysqli_close($con);
	  }
	}
	return $personlist;
} // Function getProbationEnd(days)

function getContractEnd($warning_days) {
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
		 " from dtintra_users users" .
		 "     ,dtintra_comprofiler comp" .
		 " where users.id = comp.id" .
		 "  and users.email like '%@devoteam.com'" .
		 "  and comp.cb_contract_enddate < DATE_ADD(CURDATE(),INTERVAL " . ($warning_days) . " DAY)" .
		 "  and comp.cb_contract_enddate > now()" .
                 " order by contractenddate;";
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
			$personlist .= "<tr><th align='left'>#</th><th align='left'>Name</th><th align='left'>Unit</th><th align='left'>Join Date</th><th align='left'>Payroll</th><th align='left'>Contract End Date</th></tr>";
		}
		$email = $row['email'];
		$fullname = $row['fullname'];
		$fullname = str_replace('  ', ' ', $fullname);
		$contractenddate = $row['contractenddate'];
		$joindate = $row['joindate'];
		$payroll = $row['payroll'];
		$unit = $row['unit'];
		//echo "Adding person " . $fullname . " to list";
		$personlist .= "<tr><td align='left'>$counter</td><td align='left'>$fullname</td><td align='left'>$unit</td><td align='right'>$joindate</td><td align='center'>".$payroll."</td><td align='right'>$contractenddate</td></tr>";
	     }
             $personlist .= "<tr><td colspan='6'><br/>Please take note of the 'non-competition' clause when renewing/extending someone's contract.</td></tr>";
	  } else {
	    // No users found
	    $personlist .= "<tr><td colspan='6' align='left'>No contract end dates found in coming ".$warning_days." days.</td></tr>";
	    // Cleanup
	    mysqli_free_result($result);
	    mysqli_close($con);
	  }
	}
	return $personlist;
} // Function getContractEnd(days)

// Message
$message = '
<html>
<head>
  <title>Probation Period and Contract Ending Reminders for coming ' . $WARNING_DAYS . ' days</title>
</head>
<body>
  <p>Here are those people whose <strong>probation period</strong> ends in the coming ' . $WARNING_DAYS . ' days:</p>
  <table>' . 
  getProbationEnd($WARNING_DAYS) .
  '</table>
  <p>Here are those people whose <strong>contract</strong> ends in the coming ' . $WARNING_DAYS . ' days:</p>
  <table>' . 
  getContractEnd($WARNING_DAYS) .
  '</table>
  <p style="color: #ffffff;">Forcing correct display of diacritics: éèü</p>
</body>
</html>
';

// To send HTML mail, the Content-type header must be set
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-type: text/html; charset=utf-8';

// Additional headers
$headers[] = 'From: PTT Probation Period and Contract Ending Reminder Service <pttreminders@example.com>';
//$headers[] = 'Cc: roeland.lengers@gmail.com';
//$headers[] = 'Bcc: roeland.lengers@devoteam.com';
$headers[] = 'Content-Type: text/html; charset="UTF-8"';
// Multiple recipients
$to = 'roeland.lengers@devoteam.com' .
      ', kitty.egelman@devoteam.com' .
      ', lara.meijer@devoteam.com' .
      ', arjan.van.grol@devoteam.com' .
      ', bryan.van.harten@devoteam.com' .
      ', silvia.smal@devoteam.com' .
      ', stans.schumacher@devoteam.com' .
      ', marielle.callaars@devoteam.com' .
      ', marc.bovy@devoteam.com' .
      ', marc.kikkert@devoteam.com' .
      ', hans.mollevanger@devoteam.com' .
      ', ratko.popovski@devoteam.com' .
      ', chris.hau@devoteam.com' .
      ', hamdija.haracic@devoteam.com' .
      ', roel.tijm@devoteam.com' .
      ', jan.faber@devoteam.com' .
      ', arnold.van.wijnbergen@devoteam.com' .
      ', marinus.snyman@devoteam.com' .
      ', imka.rolie@devoteam.com' .
      ', nenad.stefanovic@devoteam.com' .
      ', vladimir.francuz@devoteam.com' .
      ', bert.schaap@devoteam.com';
//$to = 'roeland.lengers@devoteam.com';
// Subject
$subject = 'Probation Period and Contract Ending Reminders for coming ' . $WARNING_DAYS . ' days';

// Mail it
mail($to, $subject, $message, implode("\r\n", $headers));
//echo "Message is: " . $message;
?>
