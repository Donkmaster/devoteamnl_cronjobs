<?php
// Script to send headcount numbers
//
// Set it in crontab with something like the following, to schedule every Tuesday, at 10:30:
// #Minute, hour, day_of_month, month, day_of_week, command
// # Send headcount reminder emails, every monday at 8:30 am.
// 30 8 * * mon (/usr/local/bin/php /home/devoteam/cronjobs/email_reminders/devoteamnl_headcount.php)

// Main body.
$WARNING_DAYS = 28;
// Parse and set constants
$ini = parse_ini_file('passwords.hidden'); // to use: global $ini;

function getRecordList() {
$recordlist = "";
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
//  $sql = "SELECT cb_payroll payroll, cb_company company, cb_employeetype employeetype, count(*) total FROM `dtintra_comprofiler` WHERE cb_isactive = 'Yes' and cb_payroll is not null group by 1,2,3 order by 1,2,3;";
  $sql = "SELECT cb_payroll payroll, cb_employeetype employeetype, count(*) total FROM `dtintra_comprofiler` WHERE cb_isactive = 'Yes' and cb_payroll is not null group by 1,2 order by 1,2;";
  //echo "Sql is: " . $sql;
  $result = mysqli_query($con, $sql);

  if (mysqli_num_rows($result) > 0) {
     // Load results into array so that we can release connection sooner
     while($row = mysqli_fetch_array($result)){
       $rows[] = $row;
       //echo "Found a record: " . $row['payroll'] . "\n";
     }

     // Cleanup here to release connection as we have results in array
     mysqli_free_result($result);
     mysqli_close($con);

     $grandtotal = 0;
     // For each record found, add to list
     foreach($rows as $row) {
        $payroll = $row['payroll'];
        //$company = $row['company'];
        $employeetype = $row['employeetype'];
        $total = $row['total'];
        $grandtotal = $grandtotal + $total;
        //$recordlist .= "<tr><td>$payroll</td><td>$company</td><td>$employeetype</td><td>$total</td></tr>\r\n";
        $recordlist .= "<tr><td>$payroll</td><td>$employeetype</td><td>$total</td></tr>\r\n";
     }
     //$recordlist .= "<tr><td>--------</td><td>----------</td><td>-------------</td><td>------</td></tr>\r\n";
     $recordlist .= "<tr><td>--------</td><td>-------------</td><td>------</td></tr>\r\n";
     //$recordlist .= "<tr><td></td><td></td><td></td><td>$grandtotal</td></tr>\r\n";
     $recordlist .= "<tr><td></td><td></td><td>$grandtotal</td></tr>\r\n";
  } else {
    // No records found
    $recordlist .= "<tr><td colspan='3'>No records found.</td></tr>";
    // Cleanup
    mysqli_free_result($result);
    mysqli_close($con);
  }
}
return $recordlist;
} // Function RecordList

// Message
$message = '
<html>
<head>
  <title>Headcount report for Devoteam NL</title>
</head>
<body>
  <p>Here is the headcount for DevoteamNL as it is currently:</p>
  <br/>
  <table> 
    <tr><th>Payroll</th><th>Employeetype</th><th>Count</th></tr>' .
  getRecordList() .
  '</table>
</body>
</html>
';

// To send HTML mail, the Content-type header must be set
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-type: text/html; charset=utf-8';

// Additional headers
$headers[] = 'From: PTT Headcount Reminder Service <pttreminders@example.com>';
//$headers[] = 'Cc: roeland.lengers@devoteam.com';
//$headers[] = 'Bcc: roeland.lengers@devoteam.com';
// Multiple recipients
//$to = 'noemi.soubeyran@devoteam.com,roeland.lengers@devoteam.com'; // note the comma
$to = 'stans.schumacher@devoteam.com' .
      ',imka.rolie@devoteam.com' .
      ',nenad.stefanovic@devoteam.com' .
      ',vladimir.francuz@devoteam.com' .
      ',roeland.lengers@devoteam.com';
//$to = 'roeland.lengers@devoteam.com'; // note the comma
// Subject
$subject = 'Headcount reminder for Devoteam NL';

// Mail it
//echo "Message is: " . $message;
mail($to, $subject, $message, implode("\r\n", $headers));
?>
