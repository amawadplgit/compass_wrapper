<?php

include('../src/AMACompassWrapperBase.php');

use CompassWrapper\AMACompassWrapperBase;

$ws = new AMACompassWrapperBase(
    "imisapp\webservices",
    "+977nagasaki",
    "https://compass.ama.com.au/CompassWCF.CompassService.svc?wsdl",
    "https://compass.ama.com.au/CompassWCF.CompassService.svc/basic",
    "https://stagingmember.ama.com.au/AsiCommon/Services/Membership/MembershipWebService.asmx?wsdl",
    TRUE);



$response = "Hello, did you forget anything?";

//$response = $ws->getMemberProfileByUsername("lizhou");

//$response = $ws->getMemberProfileById("105008");
//$response = $ws->authenticate("lzhou@y7mail.com", "AmaQld2@18");
//$response = $ws->getMemberProfileById("239937");

//Other iMIS account
//$response = $ws->getMemberProfileById("asdf");
//$response = $ws->getMemberProfileByUsername("lizhou86test@gmail.com");

//$response = $ws->getLookup('ADDRESSPURPOSE');
//$response = $ws->getLookup('CATEGORY');
//$response = $ws->getLookup('PREFIX');
//$response = $ws->getLookup('MEMBER_TYPE');
//$response = $ws->getLookup('STATUS_CODE');
//$response = $ws->getLookup('PRACTICING');
//$response = $ws->getLookup('CASH_ACCOUNT');

//$response = $ws->getUsernameFromEmailAddress('bossco.tran@gmail.com', true);
//$response = $ws->getUsernameFromEmailAddress('kesentsengk@yahoo.com');
//$response = $ws->getUsernameFromEmailAddress('nazarmd@hotmail.com');
//$response = $ws->getMemberProfileByUsername("nazarmd@hotmail.com");
$response = $ws->getUsernameFromEmailAddress('lzhou@ama.com.au');

//$response = $ws->getUsernameFromEmailAddress('lzhou@ama.com.au');
//$response = $ws->getUsernameFromEmailAddress('lzhou@y7mail.com');

//$testProfile = $ws->getMemberProfileById('239937');

//$response = $ws->getUDFieldsSchema(TRUE);

//$response = $ws->client->GetUsernameFromEmail(array('Email'=>'lzhou@ama.com.au'));
//$response = $ws->getMemberProfileById('264941');
//$response = $ws->getMemberProfileById('250280');

//$response = $ws->getMemberProfileById("129194");
//$response = $ws->getMemberProfileById("154364");
//$response = $ws->getMemberProfileById("150909");
//$response = $ws->getMemberProfileById("239937");
//$response = $ws->authenticate("jwan245@student.monash.edu", "Welcome2AMA", TRUE, FALSE, "AMSA");
//$response = $ws->getUDFieldsSchema(TRUE);
//$response = $ws->getMemberProfileByUsername("votaz@mailinator.net");
//$response = $ws->generatePassword();
//$ws->logoutUser('234374','425225AB98F7F76A24D2C83F7C965444DB469471EDF4E99D124330661E7690E312FEFC8B7086038A926AD0C5FEC40D830E58CE96A37434AD27912BA6789BEF0FC8A6DF41E0926804226F4575D0D85F7344093477EF8E8657773F21CDB33797DC32CCD5EE7A460B915EE17F8AE59D4AE2B719F4BC82493150A0EB8EF41EE5B4759861CC0DBE1020AA9A6B63185810BB49F0AB155922B0E47F0DB1764A6FB158D0BEF5350C0CD05D667A0132287F5B6F9EFDF0385DF1D32E33C1D1D56FED67A7BED33CED0551D8A84FCE3147F6EBDC9191CDF4992CE6AF9F1891E00AA0CDB8DD23');
//$response = $ws->validateUserWithToken('234374','425225AB98F7F76A24D2C83F7C965444DB469471EDF4E99D124330661E7690E312FEFC8B7086038A926AD0C5FEC40D830E58CE96A37434AD27912BA6789BEF0FC8A6DF41E0926804226F4575D0D85F7344093477EF8E8657773F21CDB33797DC32CCD5EE7A460B915EE17F8AE59D4AE2B719F4BC82493150A0EB8EF41EE5B4759861CC0DBE1020AA9A6B63185810BB49F0AB155922B0E47F0DB1764A6FB158D0BEF5350C0CD05D667A0132287F5B6F9EFDF0385DF1D32E33C1D1D56FED67A7BED33CED0551D8A84FCE3147F6EBDC9191CDF4992CE6AF9F1891E00AA0CDB8DD23');

$ws->dr($response);

