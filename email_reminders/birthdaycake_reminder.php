<?php
// Script to send reminders about upcoming birthdays
//
// Set it in crontab with something like the following, to schedule every Tuesday, at 10:30:
// #Minute, hour, day_of_month, month, day_of_week, command
// # Send birthday cake reminder emails, every tuesday at 10:30 am.
// 30 10 * * tue (/usr/local/bin/php /home/devoteam/cronjobs/email_reminders/birthdaycake_reminder.php)

// Main body.
$WARNING_DAYS = 28;
// Parse and set constants
$ini = parse_ini_file('passwords.hidden'); // to use: global $ini;

function getBirthdayPeople($daysahead) {
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

  # For explanation of date wizardy, check out: https://stackoverflow.com/questions/83531/sql-select-upcoming-birthdays
  $sql = "select users.id id " .
         "      ,users.email email" .
         "      ,users.name fullname" .
         "      ,right(comp.cb_birthday,5) birthday" .
         "      ,comp.cb_joindate joindate" .
         "      ,comp.firstname firstname" .
         "      ,if(comp.cb_joindate > now(), 1, 0) futurejoiner" .
         "      ,if(substring(comp.cb_birthday,6,1)='0',concat('Y',right(comp.cb_birthday,5)),concat('X',right(comp.cb_birthday,5))) ordering" .
         " from dtintra_users users" .
         "     ,dtintra_comprofiler comp" .
         " where users.id = comp.id" .
         "  and users.email like '%@devoteam.com'" .
         "  and (comp.cb_leave_date > now() or comp.cb_leave_date = '0000-00-00' or comp.cb_leave_date is null)" .
         "  and cb_payroll = 'NL'" .
         "  and 1 = (FLOOR(DATEDIFF(DATE_ADD(DATE(NOW()), INTERVAL " . ($daysahead) . " DAY), cb_birthday) / 365.25)) - (FLOOR(DATEDIFF(DATE(NOW()),cb_birthday) / 365.25))" .
         "  order by ordering;";
#echo "Sql is: " . $sql;
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
//echo "Adding person " . $fullname . " to list";
        $personlist .= "<tr><td>$fullname</td><td align='center'>$birthday</td>";
        if ($futureJoiner == "1") {
           $personlist .= "<td><strong>Will join on: $joindate</strong></td>";
        } else {
           //$personlist .= "<td>Joined on: $joindate</td>";
           $personlist .= "<td>&nbsp;</td>";
        }
        $personlist .= "</tr>\r\n";
     }
  } else {
    // No users found
    $personlist .= "<tr><td colspan='4'>No birthday boys found in the upcoming ".$daysahead." days</td></tr>";
    // Cleanup
    mysqli_free_result($result);
    mysqli_close($con);
  }
}
return $personlist;
} // Function getBirthdayPeople(daysahead)

// Message
$message = '
<html>
<head>
  <title>Birthday Cake Reminders for coming ' . $WARNING_DAYS . ' days</title>
</head>
<body>
  <p>Here are the colleagues with birthdays in the coming ' . $WARNING_DAYS . ' days!</p>
  <p>Note: only for NL-payroll!</p>
  <br/>
  <table>
   <tr><th align="left">Name</td><th>Birthday</th><th colspan="2">Extra info</th></tr>
  ' . 
  getBirthdayPeople($WARNING_DAYS) .
  '</table>
</body>
</html>
';
/*   <p>Process:</p>
  <ul>
    <li>Silvia will contact the birthdayboy/girl on when &amp; where they want their cake</li>
    <li>Silvia will also ask the birthdayboy/girl which accountmanager is in charge on that day/client</li>
    <li>Silvia will then inform that account manager about expected cake-delivery and where the cake will be picked up.</li>
    <li>Silvia will then order cakes for delivery in Amsterdam or The Hague.</li>
    <li>Silvia will then inform Melis Schaap about who is doing what and when.</li>
  </ul>
*/

// To send HTML mail, the Content-type header must be set
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-type: text/html; charset=utf-8';

// Additional headers
$headers[] = 'From: PTT Birthday Cake Reminder Service <pttreminders@example.com>';
$headers[] = 'Cc: marielle.callaars@devoteam.com';
$to = 'nl.ptt@devoteam.com,melis.schaap@devoteam.com'; // note the comma
//$to = 'roeland.lengers@devoteam.com'; // note the comma
// Subject
$subject = 'Upcoming Birthdays for the coming ' . $WARNING_DAYS . ' days';

// Mail it
//echo "Message is: " . $message;
mail($to, $subject, $message, implode("\r\n", $headers));
?>
