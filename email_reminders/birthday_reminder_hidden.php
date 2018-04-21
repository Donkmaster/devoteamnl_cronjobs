<?php
// Script to send reminders about upcoming birthdays, even for people that don't want these to be sent out.
//
// Set it in crontab with something like the following, to schedule every day, at 7:30:
// #Minute, hour, day_of_month, month, day_of_week, command
// # Send birthday reminder emails, every tuesday at 10:30 am.
// 30 7 * * * (/usr/local/bin/php /home/devoteam/cronjobs/email_reminders/birth_reminder_hidden.php)

// Globals
$WARNING_DAYS = 28;
$MAXINLIST = 50;
$TIMETOSLEEP = 10; // Seconds

// Parse and set constants
$ini = parse_ini_file('passwords.hidden'); // to use: global $ini;

function getBirthdayPeople() {
  $personlist = "";
        global $ini;
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
         "  and comp.cb_birthremsend != 'yes'" .
         "  and comp.cb_isactive = 'Yes'" .
         "  and right(comp.cb_birthday,5) = right(curdate(),5)" .
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

     // For each person found, add to list
     foreach($rows as $row) {
        $fullname = $row['fullname'];
        $fullname = str_replace('  ', ' ', $fullname);
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

function sendBirthdayMail($birthdayPeople) {
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
           <p style="font-size: 9px;">Stay up-to-date on everything happening at DevoteamNL/CH/UK/SRB. <a href="https://hive.devoteam.com/a/devoteam/intranet/ls/community/devoteam-nl/posts">Visit our Hive community.</a></p>
           <a href="https://hive.devoteam.com/a/devoteam/intranet/ls/community/devoteam-nl/posts"><img src="https://lh3.googleusercontent.com/BJf1XUJyVGIllIj6V5XATDdhzvQhT2OIfsMMg6kjfbezdZe0Ss28np8lo_ncsnJedzJbIUq30O9aJ_2rdw" width="100"></a>
	   <br/>
           <p style="font-size: 8px;">Do not want to receive these emails?<br/>
           Change your preference on "Edit User Profile" page of <a href="https://team.devoteam.nl/">team.devoteam.nl</a><br/>
           Don&#39;t want these mails sent about you?<br/>
           Change that as well on the "Edit User Profile" page of <a href="https://team.devoteam.nl/">team.devoteam.nl</a>
           </p>
	</body>
	</html>';
	$subject = 'Birthdays at Devoteam for today';

        $message = $messagePre . $birthdayPeople . $messagePost;
	//echo "Message to mail is: " . $message;
	// To send HTML mail, the Content-type header must be set
	$headers[] = 'MIME-Version: 1.0';
	$headers[] = 'Content-type: text/html; charset=utf-8';
	$headers[] = 'From: PTT Hidden Birthday Reminder Service <pttreminders@example.com>';

        $to = "roeland.lengers@devoteam.com" .
              ", silvia.smal@devoteam.com";
	mail($to, $subject, $message, implode("\r\n", $headers));
}

// Main Procedure
$birthdayPeople = getBirthdayPeople();
// No birthdays today: exit
if ($birthdayPeople == "") {
   return;
}
sendBirthdayMail($birthdayPeople);
   
?>
