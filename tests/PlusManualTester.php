<?php

include('../src/AMACompassWrapperPlus.php');

use CompassWrapper\AMACompassWrapperPlus;

$ws = new AMACompassWrapperPlus("imisapp\webservices",
    "+977nagasaki",
    "https://compass.ama.com.au/CompassWCF.CompassService.svc?wsdl",
    "https://compass.ama.com.au/CompassWCF.CompassService.svc/basic",
    "https://stagingmember.ama.com.au/AsiCommon/Services/Membership/MembershipWebService.asmx?wsdl",
    TRUE);

$response = "Hello, did you forget anything?";
//Prod iMIS account
//$response = $ws->getIQAQuery('$/ContactManagement/DefaultSystem/Queries/Advanced/Contact/WEB/WEB_List_of_Practice');
//$response = $ws->getIQAQueryWithParameters('$/Test/Test IQA', array('227560'));
//$response = $ws->getIQAQueryWithParameters(
//    '$/ContactManagement/DefaultSystem/Queries/Advanced/Contact/WEB/WEB_List_of_Practice',
//    array('','kedron'));
$response = $ws->lookupPractise('kedron');
//$response = $ws->getIQAQueryWithParameters(
//    '$/ContactManagement/DefaultSystem/Queries/Advanced/Contact/WEB/WEB_Invoice',
//    array('100017', '2016-01-01', '2017-01-01'));

//$response = $ws->getInvoice('129194', '2016-01-01', '2018-01-01');
//$response = $ws->getInvoice('100017', '2016-01-01', '2018-01-01');
//$response = $ws->getMemberProfileByUsername("lizhou");

//$response = $ws->getMemberProfileById("241467");

//Test iMIS account -
//$response = $ws->getMemberProfileById("239937");
//$response = $ws->getMemberProfileById("239937");

//Other iMIS account
//$response = $ws->getMemberProfileByUsername("Q248046");

//$response = $ws->getJoinFees();
//$response = $ws->isUsernameInUse('lizhou');

//$response = $ws->getUsernameFromEmailAddress('bossco.tran@gmail.com', true);
//$response = $ws->getUsernameFromEmailAddress('lzhou@y7mail.com');

$profile = array(
    'iMISID' => '239937',
    'Firstname' => 'Li',
    'Lastname' => 'Zhou',
    'Prefix' => 'Mr',
    //'CategoryCode' => '',
    'MemberTypeCode' => 'M-NSW',
    'ChapterCode' => 'NSW',
    'CompanyName' => 'AMA Federal Office',
    //'DateOfBirth' => '1986-01-17T00:00:00',
    'EmailAddress' => 'lzhou@y7mail.com',
    'MobilePhone' => "0468881517",
    'PaidThruDate' => '2018-12-31T00:00:00',
    'StatusCode' => 'A',
    'Address' => array(
        array(
            'Address1' => '6 Home Street',
            'Address2' => '',
            'Address3' => '',
            'City' => 'Hometown',
            'PostalCode' => '2018',
            'StateProvince' => 'NSW',
            'Phone' => '',
            'Purpose' => 'Home',
            'PreferredMail' => true,
        ),
        array(
            'Address1' => '8 Business Street',
            'Address2' => '',
            'Address3' => '',
            'City' => 'Worktown',
            'PostalCode' => '2011',
            'StateProvince' => 'ACT',
            'Phone' => '',
            'Purpose' => 'Business',
            'PreferredMail' => false,
        ),
    ),
    'YR_GRAD' => '2020',
    'ATSI' => 'NO',
    'UNI_STUDENT_NUMBER' => 'u4180446',
);
//$response = $ws->updateMemberProfile($profile);
$params = array(
    'memberTypeCode' => 'M-TAS',
    'categoryCode' => 'F1Y1',
    'ID' => NULL,
    'productCodes' => '',
    'asOf' => date('Y-m-d'),//'2018-07-01',
);
//$response = $ws->client->GetJoinFeeBreakdown($params);
//$response = $ws->getMemberProfileById('276242');

//$testProfile = $ws->getMemberProfileById('239937');
//$response = $ws->compareProfiles($profile, $testProfile['profile']);
//$response = $ws->getTestProfileArray('F1S1', 'S', 'TAS');
//$response = $ws->getTestPayment('MONTHLY', 'MC', FALSE);

//$response = $ws->newMemberJoin($ws->getTestProfileArray(), $ws->getTestPayment());
//$response = $ws->newMemberJoin($ws->getTestProfileArray(), $ws->getTestPayment('Annual', 'VISA', FALSE));
//$response = $ws->newMemberJoin($ws->getTestProfileArrayAMSA('AMSAD'), $ws->getTestPayment('Annual', 'VISA', FALSE));
//$response = $ws->newMemberJoin($ws->getTestProfileArray('F1Y1', 'M', 'TAS', '9006432'), $ws->getTestPayment('ANNUAL', 'VISA', FALSE));

//$response = $ws->newMemberJoin($ws->getTestProfileArray(), $ws->getTestPayment('MONTHLY', 'VISA', TRUE));
//$response = $ws->getEFTDetailsForDues("276253");
//$response = $ws->getMemberProfileById("276253");

$response = $ws->newMemberJoin($ws->getTestProfileArray('F1S1', 'S', 'ACT'));

//$response = $ws->getTestProfileArray('F1Y1', 'M', 'TAS', '9006432');
//$response = $ws->updateMemberProfile($ws->getTestProfileArray('F1Y1', 'M', 'TAS', '9006432'));
//$response = $ws->updateMemberProfile($ws->getTestProfileArray('F1Y1', 'M', 'TAS', '9006432', 'lizhou6666'));
//$response = $ws->prepMemberProfileObject($profile);
//$response = $ws->updateMemberProfile($profile);

//$response = $ws->client->GetUsernameFromEmail(array('Email'=>'lzhou@ama.com.au'));
//$response = $ws->getMemberProfileById('264941');
//$response = $ws->getMemberProfileById('250280');
//$id = '9006432';
//$newProfile = $ws->getTestProfileArray('F1Y1', 'M', 'TAS', $id);
//$oldProfile = $ws->getMemberProfileById($id);
//$oldResult = $ws->compareProfiles($newProfile, $oldProfile['profile']);
//
//$retVal = $ws->updateMemberProfile($newProfile);
//
//$updatedProfile = $ws->getMemberProfileById($id);
//$ws->dr($updatedProfile);
//$ws->dr($newProfile);
//$response = $ws->compareProfiles($newProfile, $updatedProfile['profile']);
//$response = $ws->getMemberProfileById("150909");
//$response = $ws->getEFTDetailsForDues("129194");
//$response = $ws->getMemberProfileById('239937');
//$response = $ws->resetPassword('AmaQld2@18', 239937);
//$response = $ws->getMemberRenewalDetails("129194");

//$response = $ws->resetPassword('Welcome2AMA', '255172');
//$response = $ws->updateMemberProfile($ws->getTestProfileArrayTypeOnly('F1S1', 'S', 'ACT', '100019'));

//$response = $ws->getMemberProfileById('186520');
//$response = $ws->getMemberProfileById('100019');
$ws->dr($response);

