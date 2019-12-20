<?php
// Script to send reminders about upcoming birthdays
//
// Set it in crontab with something like the following, to schedule every day, at 7:30:
// #Minute, hour, day_of_month, month, day_of_week, command
// # Send birthday reminder emails, every tuesday at 10:30 am.
// 30 7 * * * (/usr/local/bin/php /home/devoteam/cronjobs/email_reminders/birth_reminder.php)

// Globals
$WARNING_DAYS = 28;
$MAXINLIST = 50;
$TIMETOSLEEP = 10; // Seconds

// Parse and set constants
$ini = parse_ini_file('passwords.hidden'); // to use: global $ini;

function getAllRecipients() {
        global $ini;
        // Check DB connection, with correct string
        $con=mysqli_connect($ini['DB_HOSTNAME'],$ini['DB_USER'],$ini['DB_PASSWORD'],$ini['DB_DATABASE']);
  // Check connection
  if (mysqli_connect_errno()) {
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
  }
  // Make sure we get diacritics in as well
  mysqli_query($con, 'SET NAMES utf8');

  // Retrieve all email-addresses we want to sent to. 
  $sql = "select users.id id " .
         "      ,users.email email" .
         "      ,users.name fullname" .
         " from dtintra_users users" .
         "     ,dtintra_comprofiler comp" .
         " where users.id = comp.id" .
         "  and users.email like '%@devoteam.com'" .
         "  and comp.cb_isactive = 'Yes'" .
         "  and comp.cb_birthremreceive = 'yes'" .
         "  order by fullname;";
  $result2 = mysqli_query($con, $sql);
  $recipients = array();
  while($row = mysqli_fetch_array($result2)) {
     $recipients[] = $row['email'];
  }
  // For testing: only put Roeland Lengers in list
  //$recipients = ['roeland.lengers@devoteam.com','natasa.stevic@devoteam.com','stevan.ognjenovic@devoteam.com','nikola.rakocevic@devoteam.com'];
  
  // Cleanup
  mysqli_free_result($result2);
  mysqli_close($con);
  return $recipients;
}

function getBirthdayPeople() {
  global $ini;
  $personlist = "";
  // Check DB connection
  $con=mysqli_connect($ini['DB_HOSTNAME'],$ini['DB_USER'],$ini['DB_PASSWORD'],$ini['DB_DATABASE']);
// Check connection
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
} else {
  // Make sure we get diacritics in as well
  mysqli_query($con, 'SET NAMES utf8');

  $sql = "select users.id id " .
         "      ,users.email email" .
         "      ,users.name fullname" .
         "      ,right(comp.cb_birthday,5) birthday" .
         "      ,comp.cb_joindate joindate" .
         "      ,comp.firstname firstname" .
         "      ,if(comp.cb_joindate > now(), 1, 0) futurejoiner" .
         "      ,comp.avatar avatar" .
         "      ,comp.cb_payroll payroll" .
         " from dtintra_users users" .
         "     ,dtintra_comprofiler comp" .
         " where users.id = comp.id" .
         "  and users.email like '%@devoteam.com'" .
         "  and comp.cb_isactive = 'Yes'" .
         "  and right(comp.cb_birthday,5) = right(curdate(),5)" .
         "  and comp.cb_birthremsend = 'yes'" .
         "  order by fullname;";
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

     // For each person found, add to list of people having their birthday
     foreach($rows as $row) {
        $fullname = $row['fullname'];
        $fullname = str_replace('  ', ' ', $fullname);
        // And of course we have the weird diacritics from Serbia, for example: Rakočević
        //$fullname = $fullname . " | " . utf8_encode($fullname) . " | " . htmlspecialchars($fullname, ENT_COMPAT | ENT_HTML401, 'ISO-8859-1') . " | " . utf8_encode(htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8'));
        $email = $row['email'];
        $birthday = $row['birthday'];
        $joindate = $row['joindate'];
        $futureJoiner = $row['futurejoiner'];
        $avatar = $row['avatar'];
        $payroll = $row['payroll'];
//echo "Adding person " . $fullname . " to list";
        $personlist .= "<tr>";
        $personlist .= "<td><img src='https://team.devoteam.nl/images/comprofiler/tn" . $avatar. "'></td>";
        $personlist .= "<td><h2><a href='mailto:" . $email . "'>" . $fullname . "</a></h2><br/>$payroll</td>";
        $personlist .= "</tr>\r\n";
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
} // Function getBirthdayPeople()

function sendBirthdayMail($recipients,$birthdayPeople) {
	// Set max bcc recipients in one mail
	global $MAXINLIST;
	global $TIMETOSLEEP;

	$messagePre = '
	<html>
	<head>
	  <title>Birthday Reminder</title>
	</head>
	<body>
	  <p>Hello Team!</p>
	  <br/>
	  <p>Congratulations are in order!
	  <br/>
	  <p>Today is the birthday of:</p>
	  <table>';  
	$messagePost = '</table>
	   <br/>
	   <p>Regards,</p>
	   <p>Devoteam NL Birthday Reminder Service</p>
	   <br/>
           <p style="font-size: 9px;">Stay up-to-date on everything happening at Devoteam NL. <a href="https://devoteam.facebook.com/groups/249413462314147">Visit our Workplace community.</a></p>
           <a href="https://devoteam.facebook.com/groups/249413462314147"><img src="https://team.devoteam.nl/images/facebook-workplace-logo.png" width="100"></a>
	   <br/>
           <p style="font-size: 8px;">Do not want to receive these emails?<br/>
           Change your preference on "Edit User Profile" page of <a href="https://team.devoteam.nl/">team.devoteam.nl</a><br/>
           Don&#39;t want these mails sent about you?<br/>
           Change that as well on the "Edit User Profile" page of <a href="https://team.devoteam.nl/">team.devoteam.nl</a>
           </p>
	</body>
	</html>';
	//$headers[] = 'Cc: roeland.lengers@devoteam.com,marielle.callaars@devoteam.com';
	//$headers[] = 'Bcc: roeland.lengers@devoteam.com';
	// Multiple recipients
	$to = ''; // No recipient. All in BCC
	//$to = 'roeland.lengers@devoteam.com'; // note the comma
	// Subject
	$subject = 'Birthdays at Devoteam for today';

	// Get recipients
	$numrecipients = 0;
	//echo "Found " . count($recipients) . " recipients\n";
	// Build a bcc-group of X recipients, then send it.
	$bcc = array();
	foreach ($recipients as $recipient) {
		$numrecipients++;
		$bcc[] = $recipient;
		if (count($bcc) == $MAXINLIST) {
			// Need to send now. Reached MaxInList.
			//echo "Sending mail to: " . count($bcc) . " recipients\n";
                        $message = $messagePre . $birthdayPeople . $messagePost;
                        sendSingleMail($to, $subject, $message, $bcc);
			$bcc = array();
			//echo "And sleep a bit to prevent flooding\n";
			sleep($TIMETOSLEEP);
		}
	}
	// we might have exited the loop without sending the last recipients
	if (count($bcc) > 0) {
		//echo "Send the mail to the last " . count($bcc) . " recipients\n";
		$message = $messagePre . $birthdayPeople . $messagePost;
		sendSingleMail($to, $subject, $message, $bcc);
		// And reset the bcc array
		$bcc = array();
	}
} // function sendBirthdayMail

function sendSingleMail($to, $subject, $message, $bcc) {
	//echo "Message to mail is: " . $message;
        //echo "Have to put " . count($bcc) . " in the BCC\n";
	// To send HTML mail, the Content-type header must be set
	$headers[] = 'MIME-Version: 1.0';
	$headers[] = 'Content-type: text/html; charset=utf-8';
	$headers[] = 'From: PTT Birthday Reminder Service <pttreminders@example.com>';
        $bccstring = "Bcc: ";
        $bccCount = 0;
        foreach ($bcc as $recipient) {
		if ($bccCount > 0) {
			$bccstring .= ", ";
		}
		$bccCount++;
		$bccstring .= $recipient;
		//$bccstring .= "roeland" . $bccCount . "@lengers.org";
	}
	//echo "bcc string now: " . $bccstring . "\n";
        $headers[] = $bccstring;
        //$message .= $bccstring;
	mail($to, $subject, $message, implode("\r\n", $headers));
	
}

// Main Procedure
$birthdayPeople = getBirthdayPeople();
// No birthdays today: exit
if ($birthdayPeople == "") {
   return;
}
sendBirthdayMail(getAllRecipients(),$birthdayPeople);
   
?>
