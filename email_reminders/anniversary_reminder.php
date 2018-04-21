<?php
// Script to send reminders about upcoming anniversaries
//
// Set it in crontab with something like the following, to schedule every Tuesday, at 10:30:
// #Minute, hour, day_of_month, month, day_of_week, command
// # Send anniversary reminder emails, every tuesday at 10:30 am.
// 30 10 * * tue (/usr/local/bin/php /home/devoteam/cronjobs/email_reminders/anniversary_reminder.php)

// Main body.
$WARNING_DAYS = 45;

// Parse and set constants
$ini = parse_ini_file('passwords.hidden'); // to use: global $ini;

function getAnniversaries($lustrum, $warning_days) {
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
         " from dtintra_users users" .
         "     ,dtintra_comprofiler comp" .
         " where users.id = comp.id" .
         "  and users.email like '%@devoteam.com'" .
         "  and (comp.cb_leave_date > now() or comp.cb_leave_date = '0000-00-00' or comp.cb_leave_date is null)" .
         "  and comp.cb_joindate > DATE_SUB(CURDATE(),INTERVAL " . ($lustrum * 365) . " DAY)" .
         "  and comp.cb_joindate < DATE_SUB(CURDATE(),INTERVAL " . ($lustrum * 365 - $warning_days) . " DAY)" .
         "  order by joindate;";
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
        $joindate = $row['joindate'];
        $resigndate = $row['resigndate'];
        $leavedate = $row['leavedate'];
        $payroll = $row['payroll'];
        $unit = $row['unit'];
        $manager = $row['manager'];
        $resigntext = "";
        if ($resigndate != '') {
           $resigntext = "<strong>Resigned on ".$resigndate.", leaving on ".$leavedate."</strong>";
        }
//echo "Adding person " . $fullname . " to list";
        $personlist .= "<tr><td>$fullname</td><td>$joindate</td><td align='right'>".$lustrum." years</td><td>Payroll: ".$payroll."</td><td>Unit: ".$unit."</td><td>Manager: ".$manager."</td><td>".$resigntext."</td></tr>";
     }
  } else {
    // No users found
    $personlist .= "<tr><td colspan='8'>None found with ".$lustrum." years</td></tr>";
    // Cleanup
    mysqli_free_result($result);
    mysqli_close($con);
  }
}
return $personlist;
} // Function getAnniversaries(lustrum)

// Message
$message = '
<html>
<head>
  <title>Anniversary Reminders for coming ' . $WARNING_DAYS . ' days</title>
</head>
<body>
  <p>Here are the Anniversary colleagues for the coming ' . $WARNING_DAYS . ' days!</p>
  <table>
    <tr><td colspan="5"><br/><strong>5 year anniversaries:</strong></td></tr>' .
  getAnniversaries(5, $WARNING_DAYS) .
    '<tr><td colspan="5"><br/><strong>10 year anniversaries:</strong></td></tr>' .
  getAnniversaries(10, $WARNING_DAYS) .
    '<tr><td colspan="5"><br/><strong>15 year anniversaries:</strong></td></tr>' .
  getAnniversaries(15, $WARNING_DAYS) .
    '<tr><td colspan="5"><br/><strong>20 year anniversaries:</strong></td></tr>' .
  getAnniversaries(20, $WARNING_DAYS) .
    '<tr><td colspan="5"><br/><strong>25 year anniversaries:</strong></td></tr>' .
  getAnniversaries(25, $WARNING_DAYS) .
    '<tr><td colspan="5"><br/><strong>30 year anniversaries:</strong></td></tr>' .
  getAnniversaries(30, $WARNING_DAYS) .
  '</table>
</body>
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
$to = 'roeland.lengers@devoteam.com' .
      ', silvia.smal@devoteam.com' .
      ', melissa.posdijk@devoteam.com' .
      ', natasa.stevic@devoteam.com' .
      ', christian.flaig@devoteam.com' .
      ', nenad.stefanovic@devoteam.com' .
      ', stevan.ognjenovic@devoteam.com' .
      ', stans.schumacher@devoteam.com' .
      ', imka.rolie@devoteam.com' .
      ', bert.schaap@devoteam.com' .
      ', milica.obric@devoteam.com';
//$to = 'roeland.lengers@devoteam.com';

// Subject
$subject = 'Anniversary Reminders for coming ' . $WARNING_DAYS . ' days';

// Mail it
mail($to, $subject, $message, implode("\r\n", $headers));
//echo "Message is: " . $message;


?>
