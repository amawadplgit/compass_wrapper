<?php
use PHPUnit\Framework\TestCase;

final class AmaCompassWrapperTest extends TestCase
{
    private $id = '239937';
    private $username = 'lzhou@y7mail.com';
    private $testPassword = 'AmaQld2@1823';//Different password may need to enter for each test, as password policy prohibit use of past
    private $newTestPassword = "AmaFed2018234";

    public function testConstructor()
    {
        $ws = new CompassWrapper\AMACompassWrapperPlus("imisapp\webservices", "+977nagasaki");
        //Before running this test, make sure the iMIS record has a valid expiry date
        $ws->resetPassword($this->testPassword, $this->id);

        return $ws;
    }

    /**
     * @depends testConstructor
     */
    public function testGetUsernameFromEmail($ws)
    {
        $retVal = $ws->getUsernameFromEmailAddress($this->username);
        $this->assertNotNull($retVal);
    }

    /**
     * @depends testConstructor
     */
    public function testAuthenticationWeakPassword($ws)
    {
        $retVal = $ws->authenticate($this->username, "asdf", true);
        $this->assertSame('FAILED', $retVal['loginStatus']);
        $this->assertSame('WEAK PASSWORD', $retVal['message']);
    }

    /**
     * @depends testConstructor
     */
    public function testAuthenticationFail($ws)
    {
        $retVal = $ws->authenticate($this->username, "AmaQld2@1", true);
        $this->assertSame('FAILED', $retVal['loginStatus']);
    }

    /**
     * @depends testConstructor
     */
    public function testAuthenticationOK($ws)
    {
        $retVal = $ws->authenticate($this->username, $this->testPassword, true);
        $this->assertSame('SUCCESS', $retVal['loginStatus']);
    }

    /**
     * @depends testConstructor
     */
    public function testSSOToken($ws)
    {
        $response = $ws->getSSOToken($this->id);
        $responseiMISID = $ws->validateSSOToken($response);
        $this->assertSame($this->id, $responseiMISID);
    }


    /**
     * @depends testConstructor
     */
    public function testGetMemberProfile($ws)
    {
        $retVal = $ws->getMemberProfileById($this->id);
        $this->assertSame('MEMBER', $retVal['accessLevel']);
        $this->assertSame($this->id, $retVal['profile']['iMISID']);
    }

    /**
     * @depends testConstructor
     */
    public function testUpdateMemberProfile($ws)
    {
        $newProfile = $ws->getTestProfileArray('F1Y1', 'M', 'TAS', $this->id);
        $oldProfile = $ws->getMemberProfileById($this->id);
        $oldResult = $ws->compareProfiles($newProfile, $oldProfile['profile']);
        $this->assertSame(FALSE, $oldResult['isSame']);
        $this->assertNotEmpty($oldResult['fieldDiff']);

        $retVal = $ws->updateMemberProfile($newProfile);

        $updatedProfile = $ws->getMemberProfileById($this->id);
        $result = $ws->compareProfiles($newProfile, $updatedProfile['profile']);
        $this->assertSame(TRUE, $result['isSame']);
        $this->assertEmpty($result['fieldDiff']);
    }

    /**
     * @depends testConstructor
     */
    public function testChangePassword($ws)
    {
        $username = $this->username;
        $oldPassword = $this->testPassword;
        $newPassword = $this->newTestPassword;

        //Verify that we can login using old password
        $retVal = $ws->authenticate($username, $oldPassword);
        $this->assertSame('SUCCESS', $retVal['loginStatus']);

        //Change our password to new
        $ws->changePassword($oldPassword, $newPassword, $this->id);
        //Verify that we can not login using old password now
        $retVal = $ws->authenticate($username, $oldPassword);
        $this->assertSame('FAILED', $retVal['loginStatus']);

        //Verify that we can login using new password
        $retVal = $ws->authenticate($username, $newPassword);
        $this->assertSame('SUCCESS', $retVal['loginStatus']);

        //Change password back
        $ws->changePassword($newPassword, $oldPassword, $this->id);

        $retVal = $ws->authenticate($username, $oldPassword);
        $this->assertSame('SUCCESS', $retVal['loginStatus']);
    }

    /**
     * @depends testConstructor
     */
    public function testResetPassword($ws)
    {
        $oldPassword = $this->testPassword;
        $newPassword = $this->newTestPassword;

        //Verify that we can login using old password
        $retVal = $ws->authenticate($this->username, $this->testPassword);
        $this->assertSame('SUCCESS', $retVal['loginStatus']);

        //Reset our password to new
        $ws->resetPassword($newPassword, $this->id);
        //Verify that we can not login using old password now
        $retVal = $ws->authenticate($this->username, $oldPassword);
        $this->assertSame('FAILED', $retVal['loginStatus']);

        //Verify that we can login using new password
        $retVal = $ws->authenticate($this->username, $newPassword);
        $this->assertSame('SUCCESS', $retVal['loginStatus']);

        //Change password back
        $ws->resetPassword($oldPassword, $this->id);

    }

    /**
     * @depends testConstructor
     */
    public function testChangeUsername($ws)
    {
        $retVal = $ws->changeUsername('lizhou', $this->testPassword, $this->id);
        $this->assertEmpty($retVal['status']);

        $retVal = $ws->getMemberProfileById($this->id);
        $oldUsername = $retVal['profile']['Username'];
        $newUsername = "newtestusername";
        $retVal = $ws->changeUsername($newUsername, $this->testPassword, $this->id);
        $this->assertSame('SUCCESS', $retVal['status']);

        $retVal = $ws->getMemberProfileById($this->id);
        $this->assertSame(strtoupper($newUsername), $retVal['profile']['Username']);

        $retVal = $ws->changeUsername($oldUsername, $this->testPassword, $this->id);
        $this->assertSame('SUCCESS', $retVal['status']);

        $retVal = $ws->getMemberProfileById($this->id);
        $this->assertSame(strtoupper($oldUsername), $retVal['profile']['Username']);
    }

    /**
     * @depends testConstructor
     */
    public function testCheckDuplicateUsername($ws)
    {
        $retVal = $ws->isUsernameInUse('LIZHOU');
        $this->assertSame(TRUE, $retVal);
        $retVal = $ws->isUsernameInUse('AVERYRANDOMNAME');
        $this->assertSame(FALSE, $retVal);
    }

    /**
     * @depends testConstructor
     */
    public function testJoinFeeFetch($ws)
    {
        $response = $ws->getJoinFees();
        $this->assertGreaterThan(10, $response['M-ACT']['FPS1']['TotalFeesIncTax']);
        $this->assertGreaterThan(10, $response['M-ACT']['F1Y1']['TotalFeesIncTax']);
        $this->assertGreaterThan(10, $response['M-QLD']['FPS1']['TotalFeesIncTax']);
        $this->assertGreaterThan(10, $response['M-TAS']['FPS1']['TotalFeesIncTax']);
        $this->assertGreaterThan(10, $response['M-NT']['FPS1']['TotalFeesIncTax']);
    }

    /**
     * @depends testConstructor
     */
    public function testPractiseListLookup($ws)
    {
        $response = $ws->lookupPractise('kedron');
        $this->assertSame('Kedron Park 7 Day Medical Centre', $response['data'][0]['COMPANY']);
        $this->assertSame('QLD', $response['data'][0]['STATE']);
        $this->assertSame('KEDRON', $response['data'][0]['CITY']);
        $this->assertSame('4031', $response['data'][0]['ZIP']);
        $this->assertSame('07 3857 6288', $response['data'][0]['PHONE']);
        $this->assertSame('150909', $response['data'][0]['ID']);
        $this->assertSame('QDI/Kedron Park X-Rays', $response['data'][1]['COMPANY']);

    }

    /**
     * @depends testConstructor
     */
    public function testGetInvoice($ws)
    {
        $id = '129194';
        //$response = $ws->getInvoice($id, '2016-07-01', '2017-06-30', true);
        //$this->assertSame('Dr Steven Jon Hambleton', $response['billing']['Name']);
        //$this->assertSame($id, $response['billing']['iMISID']);
        //$this->assertSame('', $response['message']);
        //$this->assertSame(11, sizeof($response['data']));
    }

    /**
     * @depends testConstructor
     */
    public function testNewJoinAnnual($ws)
    {
        $profile = $ws->getTestProfileArray();
        $response = $ws->newMemberJoin($profile, $ws->getTestPayment('Annual', 'VISA'));
        $this->assertSame('SUCCESS', $response['status']);
        $id = isset($response['profile']['iMISID'])?$response['profile']['iMISID']:NULL;
        $this->assertSame('A', $response['profile']['StatusCode']);
        $this->assertSame('M-TAS', $response['profile']['MemberTypeCode']);

        $this->assertSame('Annual', $response['profile']['PaymentCycle']);
        $profileResponse = $ws->getMemberProfileById($id);
        $this->assertSame('MEMBER', $profileResponse['accessLevel']);

        $username = $response['profile']['Username'];
        $retVal = $ws->authenticate($username, $profile['Account']['newPassword']);
        $this->assertSame('SUCCESS', $retVal['loginStatus']);
        $this->assertSame('MEMBER', $retVal['accessLevel']);

    }

    /**
     * @depends testConstructor
     */
    public function testNewJoinMonthly($ws)
    {
        $response = $ws->newMemberJoin($ws->getTestProfileArray(), $ws->getTestPayment('Monthly', 'VISA'));
        $id = isset($response['profile']['iMISID'])?$response['profile']['iMISID']:NULL;
        $this->assertSame('SUCCESS', $response['status']);
        $this->assertSame('A', $response['profile']['StatusCode']);
        $this->assertSame('Monthly', $response['profile']['PaymentCycle']);
        $profileResponse = $ws->getMemberProfileById($id);
        $this->assertSame('MEMBER', $profileResponse['accessLevel']);

        $eftResponse = $ws->getEFTDetailsForDues($id);

        $this->assertSame($id, $eftResponse['ID']);
        $this->assertSame('CC', $eftResponse['EFT']);
        $this->assertSame('************1111', $eftResponse['CC_Number']);
    }

    /**
     * @depends testConstructor
     */
    public function testNewStudentJoin($ws)
    {
        $profile = $ws->getTestProfileArray('F1S1', 'S', 'ACT');
        $response = $ws->newMemberJoin($profile);
        $id = isset($response['profile']['iMISID'])?$response['profile']['iMISID']:NULL;
        $this->assertSame('SUCCESS', $response['status']);
        $this->assertSame('A', $response['profile']['StatusCode']);
        $this->assertSame('S-ACT', $response['profile']['MemberTypeCode']);

        $profileResponse = $ws->getMemberProfileById($id);
        $this->assertSame('STUDENT', $profileResponse['accessLevel']);

        $username = $response['profile']['Username'];
        $retVal = $ws->authenticate($username, $profile['Account']['newPassword']);
        $this->assertSame('FAILED', $retVal['loginStatus']);

        $retVal = $ws->authenticate($username, $profile['Account']['newPassword'], TRUE, FALSE, 'AMAA');
        $this->assertSame('SUCCESS', $retVal['loginStatus']);
        $this->assertSame('STUDENT', $retVal['accessLevel']);
    }

    /**
     * @depends testConstructor
     */
    public function testNewStudentJoinQLD($ws)
    {
        $profile = $ws->getTestProfileArray('F1S1', 'S', 'QLD');
        $response = $ws->newMemberJoin($profile);
        $id = isset($response['profile']['iMISID'])?$response['profile']['iMISID']:NULL;
        $this->assertSame('SUCCESS', $response['status']);
        $this->assertSame('A', $response['profile']['StatusCode']);
        $this->assertSame('S-QLD', $response['profile']['MemberTypeCode']);

        $profileResponse = $ws->getMemberProfileById($id);
        $this->assertSame('STUDENT', $profileResponse['accessLevel']);

        $username = $response['profile']['Username'];
        $retVal = $ws->authenticate($username, $profile['Account']['newPassword']);
        $this->assertSame('FAILED', $retVal['loginStatus']);

        $retVal = $ws->authenticate($username, $profile['Account']['newPassword'], TRUE, FALSE, 'AMAA');
        $this->assertSame('SUCCESS', $retVal['loginStatus']);
        $this->assertSame('STUDENT', $retVal['accessLevel']);
    }

    /**
     * @depends testConstructor
     */
    public function testNewStudentJoinSA($ws)
    {
        $profile = $ws->getTestProfileArray('F1S1', 'S', 'SA');
        $response = $ws->newMemberJoin($profile);
        $id = isset($response['profile']['iMISID'])?$response['profile']['iMISID']:NULL;
        $this->assertSame('SUCCESS', $response['status']);
        $this->assertSame('A', $response['profile']['StatusCode']);
        $this->assertSame('S-SA', $response['profile']['MemberTypeCode']);

        $profileResponse = $ws->getMemberProfileById($id);
        $this->assertSame('STUDENT', $profileResponse['accessLevel']);

        $username = $response['profile']['Username'];
        $retVal = $ws->authenticate($username, $profile['Account']['newPassword']);
        $this->assertSame('FAILED', $retVal['loginStatus']);

        $retVal = $ws->authenticate($username, $profile['Account']['newPassword'], TRUE, FALSE, 'AMAA');
        $this->assertSame('SUCCESS', $retVal['loginStatus']);
        $this->assertSame('STUDENT', $retVal['accessLevel']);
    }

    /**
     * @depends testConstructor
     */
    public function testNewStudentJoinVIC($ws)
    {
        $profile = $ws->getTestProfileArray('F1S1', 'S', 'VIC');
        $response = $ws->newMemberJoin($profile);
        $id = isset($response['profile']['iMISID'])?$response['profile']['iMISID']:NULL;
        $this->assertSame('SUCCESS', $response['status']);
        $this->assertSame('A', $response['profile']['StatusCode']);
        $this->assertSame('S-VIC', $response['profile']['MemberTypeCode']);

        $profileResponse = $ws->getMemberProfileById($id);
        $this->assertSame('STUDENT', $profileResponse['accessLevel']);

        $username = $response['profile']['Username'];
        $retVal = $ws->authenticate($username, $profile['Account']['newPassword']);
        $this->assertSame('FAILED', $retVal['loginStatus']);

        $retVal = $ws->authenticate($username, $profile['Account']['newPassword'], TRUE, FALSE, 'AMAA');
        $this->assertSame('SUCCESS', $retVal['loginStatus']);
        $this->assertSame('STUDENT', $retVal['accessLevel']);
    }

    /**
     * @depends testConstructor
     */
    public function testNewStudentJoinWA($ws)
    {
        $profile = $ws->getTestProfileArray('F1S1', 'S', 'WA');
        $response = $ws->newMemberJoin($profile);
        $id = isset($response['profile']['iMISID'])?$response['profile']['iMISID']:NULL;
        $this->assertSame('SUCCESS', $response['status']);
        $this->assertSame('A', $response['profile']['StatusCode']);
        $this->assertSame('S-WA', $response['profile']['MemberTypeCode']);

        $profileResponse = $ws->getMemberProfileById($id);
        $this->assertSame('STUDENT', $profileResponse['accessLevel']);

        $username = $response['profile']['Username'];
        $retVal = $ws->authenticate($username, $profile['Account']['newPassword']);
        $this->assertSame('FAILED', $retVal['loginStatus']);

        $retVal = $ws->authenticate($username, $profile['Account']['newPassword'], TRUE, FALSE, 'AMAA');
        $this->assertSame('SUCCESS', $retVal['loginStatus']);
        $this->assertSame('STUDENT', $retVal['accessLevel']);
    }

    /**
     * @depends testConstructor
     */
    public function testNewStudentJoinNT($ws)
    {
        $profile = $ws->getTestProfileArray('F1S1', 'S', 'NT');
        $response = $ws->newMemberJoin($profile);
        $id = isset($response['profile']['iMISID'])?$response['profile']['iMISID']:NULL;
        $this->assertSame('SUCCESS', $response['status']);
        $this->assertSame('A', $response['profile']['StatusCode']);
        $this->assertSame('S-NT', $response['profile']['MemberTypeCode']);

        $profileResponse = $ws->getMemberProfileById($id);
        $this->assertSame('STUDENT', $profileResponse['accessLevel']);

        $username = $response['profile']['Username'];
        $retVal = $ws->authenticate($username, $profile['Account']['newPassword']);
        $this->assertSame('FAILED', $retVal['loginStatus']);

        $retVal = $ws->authenticate($username, $profile['Account']['newPassword'], TRUE, FALSE, 'AMAA');
        $this->assertSame('SUCCESS', $retVal['loginStatus']);
        $this->assertSame('STUDENT', $retVal['accessLevel']);
    }

    /**
     * @depends testConstructor
     */
    public function testRenew($ws)
    {
        $this->assertSame('','');
    }

    /**
     * @depends testConstructor
     */
    public function testNewActivity($ws)
    {
        $response = $ws->newActivity($this->id, 'STATE_TRF', 'Hello TEST');
        $this->assertSame('SUCCESS', $response['status']);
    }


}
