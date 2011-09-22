<?php
    header("Content-Type: text/calendar");
    header("Content-Disposition: attachment; filename=Schedule.ics");
?>
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Apple Inc.//iCal 4.0.3//EN
CALSCALE:GREGORIAN

<?php foreach($_GET as $key=>$entry):

if($key === 'tsr_action')
	continue;

$entry = str_replace('&amp;', '&', $entry);
$parts = explode('|', $entry);

switch ($parts[3]) {
    case 'Mo':
        $parts[3] = 'Monday'; break;
    case 'Tu':
        $parts[3] = 'Tuesday'; break;
    case 'We':
        $parts[3] = 'Wednesday'; break;
    case 'Th':
        $parts[3] = 'Thursday'; break;
    case 'Fr':
        $parts[3] = 'Friday'; break;
    default:
    	continue;
}

$dp = explode("/", trim($parts[8]));
$start = strtotime($parts[3] . ' ' . $parts[4], strtotime($dp[2] . $dp[0] . $dp[1]));

$dp = explode("/", trim($parts[8]));
$end = strtotime($parts[3] . ' ' . $parts[5], strtotime($dp[2] . $dp[0] . $dp[1]));
?>

BEGIN:VEVENT
UID:<?php echo str_replace(' ', '', $parts[0] . $parts[2] . $parts[7] . 'START-' . $start . 'END-' . $end); ?>@uvasisremix
DTSTAMP:<?php echo date('Ymd') . 'T' . date('His') . "\n"; ?>
DTSTART:<?php echo date('Ymd', $start) . 'T' . date('His', $start) . "\n"; ?>
DTEND:<?php echo date('Ymd', $end) . 'T' . date('His', $end) . "\n"; ?>
RRULE:FREQ=WEEKLY;UNTIL=<?php 
	$dp = explode("/", $parts[9]);
	/*
	 * Note that the end date supplied in the POST parameter is advanced by 1 day using the 'tomorrow' keyword.
	 * This ensures that classes still show up on the end day; without it, classes would end a day earlier.
	 */
	echo date('Ymd', strtotime('tomorrow', strtotime($dp[2] . $dp[0] . $dp[1]))) . "\n"; ?>
SUMMARY:<?php echo $parts[0] . "\n"; ?>
LOCATION:<?php echo $parts[6] . "\n"; ?>
DESCRIPTION:<?php echo $parts[1]; ?>\n<?php echo $parts[2]; ?>\nProfessor: <?php echo $parts[7] . "\n"; ?>
END:VEVENT

<?php endforeach; ?>

END:VCALENDAR