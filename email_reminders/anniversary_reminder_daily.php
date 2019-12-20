<?php
// Script to send reminders about current anniversaries
//
// Set it in crontab with something like the following, to schedule every Tuesday, at 10:30:
// #Minute, hour, day_of_month, month, day_of_week, command
// # Send anniversary reminder emails, every tuesday at 10:30 am.
// 30 10 * * * (/usr/local/bin/php /home/devores/cronjobs/email_reminders/anniversary_reminder_daily.php)

// Main body.
$WARNING_DAYS = 1;

// Parse and set constants
$ini = parse_ini_file('passwords.hidden'); // to use: global $ini;

function getAnniversaries($lustrum, $warning_days) {
$CURDAY = date("Y-m-d");
//$CURDAY = "2019-01-01";
$CURYEAR = substr($CURDAY,0,4);
$CURYEARVAL = intval($CURYEAR);
$CURMONTHDAY = substr($CURDAY,5,9);
/*echo "Current date: " . $CURDAY . "\n";
echo "Current year: " . $CURYEAR . "\n";
echo "Current yearval: " . $CURYEARVAL . "\n";
echo "Current yearmonthday: " . $CURMONTHDAY . "\n";
*/
$personlist = "";
// Check DB connection
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
         "      ,comp.cb_joindate joindate" .
         "      ,comp.firstname firstname" .
         "      ,comp.cb_payroll payroll" .
         "      ,comp.cb_resign_date resigndate" .
         "      ,comp.cb_leave_date leavedate" .
         "      ,comp.cb_practice unit" .
         "      ,comp.cb_manager manager" .
         "      ,comp.avatar avatar" .
         " from dtintra_users users" .
         "     ,dtintra_comprofiler comp" .
         " where users.id = comp.id" .
         "  and users.email like '%@devoteam.com'" .
         "  and (comp.cb_leave_date > now() or comp.cb_leave_date = '0000-00-00' or comp.cb_leave_date is null)" .
         "  and (comp.cb_joindate = '" . ($CURYEARVAL - 5) . "-" . $CURMONTHDAY . "' " .
         "       or comp.cb_joindate = '" . ($CURYEARVAL - 1) . "-" . $CURMONTHDAY . "' " .
         "       or comp.cb_joindate = '" . ($CURYEARVAL - 10) . "-" . $CURMONTHDAY . "' " .
         "       or comp.cb_joindate = '" . ($CURYEARVAL - 15) . "-" . $CURMONTHDAY . "' " .
         "       or comp.cb_joindate = '" . ($CURYEARVAL - 20) . "-" . $CURMONTHDAY . "' " .
         "       or comp.cb_joindate = '" . ($CURYEARVAL - 25) . "-" . $CURMONTHDAY . "' " .
         "       or comp.cb_joindate = '" . ($CURYEARVAL - 30) . "-" . $CURMONTHDAY . "' " .
         "       or comp.cb_joindate = '" . ($CURYEARVAL - 35) . "-" . $CURMONTHDAY . "' " .
         "       or comp.cb_joindate = '" . ($CURYEARVAL - 40) . "-" . $CURMONTHDAY . "' " .
         "      )" .
         "  order by joindate desc;";
//echo "Sql is: " . $sql;
  $result = mysqli_query($con, $sql);

  $avatarlist = "";
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
        $userid = $row['id'];
        $fullname = $row['fullname'];
        $fullname = str_replace('  ', ' ', $fullname);
        $email = $row['email'];
        $joindate = $row['joindate'];
        $resigndate = $row['resigndate'];
        $leavedate = $row['leavedate'];
        $payroll = $row['payroll'];
        $avatar = $row['avatar'];
        $unit = $row['unit'];
        $manager = $row['manager'];
        $resigntext = "";
        if ($resigndate != '') {
           $resigntext = "<strong>Resigned on ".$resigndate.", leaving on ".$leavedate."</strong>";
        }
//echo "Adding person " . $fullname . " to list";
        $personlist .= "<tr>".
           "<td align='left'><a href='https://team.devoteam.nl/administrator/index.php?option=com_comprofiler&view=edit&cid=".$userid."'>$fullname</a></td>" .
           "<td>$joindate</td>" .
           "<td>Payroll: ".$payroll."</td>" .
           "<td>Unit: ".$unit."</td>" .
           "<td>Manager: ".$manager."</td>" .
           "<td>".$resigntext."</td></tr>";
        $avatarlist .= "<a href='mailto:".$email."'><img width='260' src='https://team.devoteam.nl/images/comprofiler/" . $avatar. "'></a><br/>";
     }
     $personlist = "<table>". $personlist . "</table>" . $avatarlist;
  } else {
    // No users found
    $personlist = "";
    // Cleanup
    mysqli_free_result($result);
    mysqli_close($con);
    return "";
  }
}
return $personlist;
} // Function getAnniversaries(lustrum)

// Message
$personlist = getAnniversaries(5, $WARNING_DAYS);
if ($personlist == '') {
  return;
}
$message = '
<html>
<head>
  <title>Anniversary Reminders for coming today</title>
</head>
<body>
  <p>Here are the Anniversary colleagues for today!</p>
  <p>Click on their photo to start an email to them</p>' .
    $personlist .
  '</body>
</html>
';

// To send HTML mail, the Content-type header must be set
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-type: text/html; charset=utf-8';

// Additional headers
$headers[] = 'From: PTT Anniversary Reminder Service <pttreminders@example.com>';
//$headers[] = 'Cc: roeland.lengers@gmail.com';
//$headers[] = 'Bcc: roeland.lengers@devoteam.com';
// Multiple recipients
$to = 'nl.ptt@devoteam.com' .
      ', nl.management_team@devoteam.com';
//$to = 'roeland.lengers@devoteam.com';

// Subject
$subject = 'Anniversary Reminders for today!';

// Mail it
mail($to, $subject, $message, implode("\r\n", $headers));
//echo "Message is: " . $message;
?>
