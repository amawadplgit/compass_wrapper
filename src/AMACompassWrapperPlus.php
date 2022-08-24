<?php

namespace CompassWrapper;
/**
 * iMIS Compass wrapper class for AMA Plus version
 * Provides ability for web clients to integrate with iMIS via Compass API
 * Plus version provides full ability to update, process join/renewal requests and potentially more.
 * @author Li Zhou lzhou@ama.com.au
 * @copyright Australian Medical Association
 */
require_once('AMACompassWrapperBase.php');

class AMACompassWrapperPlus extends AMACompassWrapperBase
{

    /**
     * Update member profile
     * @param array $profile member profile in simple array as provided by web site client
     * @return array $retVal to indicate if update has been done successfully. status will be "SUCCESS" if successful
     * exception details will be included in message field if any
     */
    public function updateMemberProfile($profile)
    {
        $retVal = array(
            'profile' => array(),
            'status' => 'FAILED',
            'message' => '',
            'customerMessage' => ''
        );
        if (isset($profile['iMISID']) && !empty($profile['iMISID'])) {
            try {
                $profileObj = $this->prepMemberProfileObject($profile);
                //$this->dr($profileObj);
                $updateResponse = $this->client->UpdateMemberProfile(array('memberProfile' => $profileObj));

                //$this->compareProfiles($profile, $updateResponse->UpdateMemberProfileResult);

                $newProfile = $this->processMemberProfile($updateResponse->UpdateMemberProfileResult);

                $usernameChangeOK = TRUE;
                $passwordChangeOK = TRUE;
                $errorMessage = "";

                //Changing username
                if (!empty($profile['Account']['username'])
                    && strcasecmp($profile['Account']['username'], $newProfile['profile']['Username']) != 0) {
                    $response = $this->changeUsername($profile['Account']['username'], $profile['Account']['oldPassword'], $profile['iMISID']);
                    if ($response['status'] != 'SUCCESS') {
                        $usernameChangeOK = FALSE;
                        $errorMessage .= "Username change is not successful: ".$response['message'];
                    } else {
                        $newProfile['profile'] = $this->getMemberProfileById($profile['iMISID']);
                    }
                }

                //Changing password
                //Using resetPassword instead of changePassword to keep consistent with previous practise on ama.com.au
                if (!empty($profile['Account']['newPassword'])
                    && !empty($profile['Account']['oldPassword'])
                    && $profile['Account']['oldPassword'] != $profile['Account']['newPassword']
                ) {
                    $response = $this->changePassword($profile['Account']['oldPassword'], $profile['Account']['newPassword'], $profile['iMISID']);
                    //$response = $this->resetPassword($profile['Account']['newPassword'], $profile['iMISID']);
                    if ($response['status'] != 'SUCCESS') {
                        $passwordChangeOK = FALSE;
                        $errorMessage .= " Password change is not successful: ".$response['message'];
                    }
                }

                if (!empty($newProfile['profile']) && $usernameChangeOK && $passwordChangeOK) {
                    $retVal['profile'] = $newProfile['profile'];
                    $retVal['status'] = 'SUCCESS';
                    $retVal['message'] = 'Update successful';
                    $retVal['customerMessage'] = 'Account is updated successful';
                } else {
                    //$this->dr($newProfile);
                    //$this->dr($updateResponse);
                    $retVal['message'] = 'Error occurred: '.$errorMessage;
                    $retVal['customerMessage'] = 'Error occurred. Please try again later.';
                }
            } catch (\Exception $e) {
                $retVal['message'] = $e->getMessage().' '.$e->getTraceAsString();
                $retVal['customerMessage'] = 'Error occurred. Please try again later.';
            }
        } else {
            $retVal['message'] = 'Invalid iMIS ID';
        }
        return $retVal;
    }

    /**
     * Check if username is in use in the iMIS system
     * @param string $username username to check
     * @return boolean True if $username is in use, otherwise False
     */
    public function isUsernameInUse($username)
    {
        $response = $this->client->DupCheck(array('userName'=>$username));
        return $response->DupCheckResult;
    }

    /**
     * Change web username for a member
     * Username can only be changed for account when the following conditions are met
     * 1. account is NOT disabled nor locked out
     * 2. new username must be unique in iMIS, use isUsernameInUse to check
     * 3. password must be valid
     * @param string $newUsername new username to be changed to
     * @param string $currentPassword password for this account is required to change username
     * @param string $iMISID iMIS ID of the account
     *
     * @return array $retVal status=SUCCESS if successful, otherwise a exception details may be contained in message field
     * */
    public function changeUsername($newUsername, $currentPassword, $iMISID) {
        $retVal = array('status' => '','message' => '');
        try {
            $isInUse = $this->isUsernameInUse($newUsername);
            if (!$isInUse) {
                $params = array(
                    'newUsername' => $newUsername,
                    'currentPassword' => $currentPassword,
                    'iMISID' => $iMISID
                );

                $response = $this->client->ChangeUsername($params);
                if (empty((array) $response)) {
                    //$response is empty when it returns without error
                    $retVal['status'] = 'SUCCESS';
                } else {
                    $retVal['message'] = 'Unexpected response: '.var_export($response, true);
                }
            } else {
                $retVal['message'] = 'Username is already in use';
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }
        return $retVal;
    }

    /**
     * Change password for a member
     * Password can only be changed when the following conditions are met
     * 1. account is NOT disabled nor locked out
     * 2. currentPassword must be valid
     * @param string $currentPassword
     * @param string $newPassword
     * @param string $iMISID
     *
     * @return
     * */
    public function changePassword($currentPassword, $newPassword, $iMISID) {
        $retVal = array(
            'status' => 'FAILED',
            'message' => '',
            'customerMessage' => '',
        );
        try {
            $username = $this->getUsernameFromiMISID($iMISID);
            //$response = $this->client->Authenticate(array('userName'=>$username, 'password'=>$currentPassword));

            $params = array(
                'currentPassword' => $currentPassword,
                'newPassword' => $newPassword,
                'ID' => $username
            );
            $response = $this->client->ChangePassword($params);
            if (empty((array) $response)) {
                //$response is empty when it returns without error
                $retVal['status'] = 'SUCCESS';
            } else {
                $retVal['message'] = 'Unexpected response: '.var_export($response, true);
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
            $retVal['customerMessage'] = 'Password is not changed due to a system exception, please try again later';
        }
        return $retVal;
    }

    /**
     * Reset a web password for a iMIS ID
     * @param string $password
     * @param string $iMISID
     * @return
     */
    public function resetPassword($password, $iMISID) {
        $retVal = array('status' => 'FAILED', 'message' => '', 'customerMessage' => '');
        try {
            $response = $this->client->GetUsernameFromiMISID(array('iMISID' => $iMISID));
            $username = $response->GetUsernameFromiMISIDResult;
            $params = array(
                'password' => $password,
                'userID' => $username
            );
            $response = $this->client->ResetPassword($params);
            if (empty((array) $response)) {
                //$response is empty when it returns without error
                $retVal['status'] = 'SUCCESS';
            } else {
                $retVal['message'] = 'Unexpected response: '.var_export($response, true);
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
            if (strpos($retVal['message'], 'recently been used') !== FALSE ) {
                $retVal['customerMessage'] = 'This password was used as your password before, please choose a different one.';
            } else if (strpos($retVal['message'], 'locked out') !== FALSE ) {
                $retVal['customerMessage'] = 'Your account has been locked due to security reasons. Please contact AMA member services on 1300 133 655';
            }
        }
        return $retVal;
    }

    /**
     * Retrieve details of join fees for states for all categories
     *
     * @return
     */
    public function getJoinFees()
    {
        return $this->getJoinFeesForTypeCode($this->amaMemberTypeCodeForJoin, $this->categoryTypeCode);
    }

    public function getJoinFeesAMSA()
    {
        return $this->getJoinFeesForTypeCode($this->amsaMemberTypeCodeForJoin, $this->amsaCategoryCode);
    }

    public function getBillFromDate()
    {
        if (date('d') > 15) {
            $billFromDate = mktime(0,0,0, date('m')+1, 1, date('Y'));
        } else {
            $billFromDate = time();
        }
        //$billFromDate = mktime(0,0,0, 8, 1, 2018);
        return date('Y-m-d', $billFromDate);
    }

    public function getJoinFeesForTypeCode($memberTypeCode, $categoryTypeCode)
    {
        $retVal = array();
        try {
            foreach($memberTypeCode as $memberType=>$state) {
                $retVal[$memberType] = array();
                foreach($categoryTypeCode as $categoryKey => $categoryValue) {
                    $params = array(
                        'memberTypeCode' => $memberType,
                        'categoryCode' => $categoryKey,
                        'ID' => NULL,
                        'productCodes' => '',
                        'asOf' => $this->getBillFromDate(),//date('Y-m-d'),//'2018-07-01',
                    );
                    //$this->dr($params);
                    $response = $this->client->GetJoinFeeBreakdown($params);
                    //$this->dr($response);
                    $feeArray = (array) $response->GetJoinFeeBreakdownResult->Summary;
                    //$this->dr($feeArray);
                    $params = array(
                        'freq' => 'Monthly',
                        'totalDuesPayable' => $feeArray['MembershipFeeIncTax'],
                        'billedFrom' => $this->getBillFromDate(),//date('Y-m-d'),
                        'billedTo' => $feeArray['MembershipEnding'],//date('Y-m-d'),//'2018-07-01',
                    );
                    //$this->dr($params);
                    $response = $this->client->DetermineEFTScheduleForDuesForPeriod($params);
                    $monthlyFeeArray = (array) $response->DetermineEFTScheduleForDuesForPeriodResult;
                    //$this->dr($feeArray);
                    //$this->dr($monthlyFeeArray);
                    $annualFees = array_intersect_key($feeArray, array_flip(array('MembershipEnding','TotalFeesIncTax')));
                    $monthlyFees = array_intersect_key($monthlyFeeArray, array_flip(array('InstalmentAmount','FirstInstalmentDate')));
                    $retVal[$memberType][$categoryKey] = array_merge($annualFees, $monthlyFees, array("Description"=> $categoryValue, "State"=>$state));
                    //$response = $this->client->GetNewMemberDuesSummary($params);
                    //$feeArray = (array) $response->GetNewMemberDuesSummaryResult;
                }
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }
        return $retVal;
    }

    public function newUserRecord($profile)
    {
        $retVal = array(
            'profile' => array(),
            'status' => 'FAILED',
            'accessLevel' => '',
            'message' => '',
            'customerMessage' => '');
        try {

            if (!isset($profile['iMISID'])) {
                $memberProfile = $this->prepMemberProfileObject($profile);
                //$this->dr($memberProfile);
                $response = $this->client->NewMemberProfile(array('memberProfile' => $memberProfile));
                $memberProfile = $this->processMemberProfile($response->NewMemberProfileResult);
            }

            $retVal['profile'] = $memberProfile['profile'];
            //$retVal['accessLevel'] = $newProfile['accessLevel'];
            $retVal['status'] = 'SUCCESS';
            $retVal['message'] = '';
            $retVal['customerMessage'] = 'Your tmp account is created';

        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }

        return $retVal;
    }

    /**
     * Process a new member Join request
     * @param array $profile profile in simple array structure from web clients
     * @param array $payment if payment required, not required for student membership
     * @return
     */
    public function newMemberJoin($profile, $payment = NULL)
    {
        $retVal = array(
            'profile' => array(),
            'status' => 'FAILED',
            'accessLevel' => '',
            'message' => '',
            'customerMessage' => '');
        try {

            if (!isset($profile['iMISID'])) {
                $memberProfile = $this->prepMemberProfileObject($profile);
                //$this->dr($memberProfile);
                $response = $this->client->NewMemberProfile(array('memberProfile'=>$memberProfile));
                $memberProfile = $this->processMemberProfile($response->NewMemberProfileResult);
            } else {
                //For previous failed payment, they may come back with existing iMISID
                $memberProfile = $this->getMemberProfileById($profile['iMISID']);
            }

            $iMISID = $memberProfile['profile']['iMISID'];

            $duesLine = $memberProfile['profile']['OutstandingDuesPayableOnWeb'];
            if (empty($duesLine)) {
                // Call the following to ensure you know what product codes to bill and the amount to be billed.
                $params = array(
                    'memberTypeCode' => $profile['MemberTypeCode'],
                    'categoryCode' => $profile['CategoryCode'],
                    'ID' => $iMISID,
                    'productCodes' => '',
                    'asOf' => $this->getBillFromDate(),//date('Y-m-d'),
                );
                $response = $this->client->GetJoinFeeBreakdown($params);
                //$this->dr($response);
                $feeArray = isset($response->GetJoinFeeBreakdownResult->Details->NewMemberFeeDetails)?(array) $response->GetJoinFeeBreakdownResult->Details->NewMemberFeeDetails:array();
                $productCodes = "";
                //$this->dr($feeArray);
                if (isset($feeArray['ProductCode'])) {
                    $productCodes .= $feeArray['ProductCode'];
                } else {
                    foreach($feeArray as $fee) {
                        $productCodes .= $fee->ProductCode.",";
                        //$productResponse = $this->client->GetProductDetails(array('productCode'=>$fee->ProductCode));
                        //$this->dr($productResponse->GetProductDetailsResult);
                    }
                }
                $totalAmountFromSummary = $response->GetJoinFeeBreakdownResult->Summary->TotalFeesIncTax;

                //3) BillJoinFeesAsOf and pass in the MemberType and Category you want to bill
                // This will return the member profile and on it look at the
                // OutstandingDuesPayableOnWeb -> dues lines to pay off by setting each line's AmountToPay to be the Balance
                // and then passing this collection of lines into PayJoinDues.

                $params['productCodes'] = $productCodes;
                //$this->dr($params);
                $response = $this->client->BillJoinFeesAsOf($params);
                $duesLine = (array) $response->BillJoinFeesAsOfResult->OutstandingDuesPayableOnWeb;
            }

            $totalAmount = 0;
            if (isset($duesLine['DuesLineItem'])) {
                foreach($duesLine['DuesLineItem'] as $item) {
                    $totalAmount += $item->Balance;
                }
            }

            $paymentResult = array('ReturnCode' => '');
            $section = !empty($profile['ChapterCode'])?$profile['ChapterCode']:'AMSA';
            if ($totalAmount > 0) {
                $gateway = array(
                    'BatchCreatedBy' => 'WEB'.$section,
                    'CashAccountCode' => $payment['CardType'].'_'.$section,
                    'CardholderName' => $payment['CardholderName'],
                    'CreditCardNumber' => $payment['CreditCardNumber'],
                    'ExpiryMonth' => $payment['ExpiryMonth'],
                    'ExpiryYear' => $payment['ExpiryYear'],
                    'CCV' => $payment['CCV'],
                    'PaymentAmount' => $totalAmount,
                );

                if ($payment['billing_method'] == 'Annual') {
                    // Call PayJoinDues
                    // pass in the memberType and Category that the contact should be changed to upon successful payment.
                    // If payment is made in full then their Paid_THRU will be updated automatically.
                    $paymentResult = $this->payJoinDues($iMISID, $profile, $duesLine, $gateway, $totalAmount, $totalAmount);
                } else {
                    //  You can use
                    //  EFTInstalmentSchedule DetermineEFTScheduleForDues
                    //  To figure out how the payments will be deducted.
                    //  For tieToEFTMerchant, use one of thee as appropriate:
                    //  ACT CC | FEDCPD CC | New South Wales CC | NT CC | Queensland CC | South Australia CC | TAS CC

                    $response = $this->client->DetermineEFTScheduleForDues(array(
                        'iMISID' => $iMISID,
                        'freq' => 'Monthly',
                        'totalDuesPayable' => $totalAmount,
                    ));

                    $FirstDate = $response->DetermineEFTScheduleForDuesResult->FirstInstalmentDate;
                    $FinalDate = $response->DetermineEFTScheduleForDuesResult->FinalInstalmentDate;
                    $InstalmentAmount = $response->DetermineEFTScheduleForDuesResult->InstalmentAmount;
                    $PaymentAmountUpfront = $response->DetermineEFTScheduleForDuesResult->PaymentAmountUpfront;

                    //$response->DetermineEFTScheduleForDuesResult;

                    if ($PaymentAmountUpfront > 0) {
                        $gateway['PaymentAmount'] = $PaymentAmountUpfront;
                        $paymentResult = $this->payJoinDues($iMISID, $profile, $duesLine, $gateway, $PaymentAmountUpfront, $totalAmount);
                        //$this->dr($paymentResult);
                    } else {
                        //TODO validate credit card in those case payment is not required upfront
                        $paymentResult['ReturnCode'] = 'SUCCESS';
                    }

                    if ($paymentResult['ReturnCode'] == 'SUCCESS') {
                        $gateway['PaymentAmount'] = $InstalmentAmount;
                        $params = array(
                            'ID' => $iMISID,
                            'gateway' => $gateway,
                            'freq' => 'Monthly',
                            'firstInstalmentDate' => $FirstDate,
                            'finalInstalmentDate' => '9999-12-31',//$FinalDate,//
                            'tieToMerchantAccount' => $this->cashAccountCode[$profile['ChapterCode']],
                        );
                        $setEFTResponse = $this->client->SetupDuesEFTFromGateway($params);
                    }
                }
            } else if (in_array($profile['MemberTypeCode'], $this->amaStudentTypeCode)) {
                //Student type code does not require payment
                $paymentResult['ReturnCode'] = 'SUCCESS';
                //TODO Check with business what PaidThruDate to set for students
                $profile['PaidThruDate'] = date('Y').'-12-31T00:00:00';
            } else {
                $paymentResult['ErrorMessage'] = 'No dues for non student type';
                $paymentResult['InternalErrorMessage'] = 'iMISID:'.$iMISID.' '.$profile['MemberTypeCode'].' '.$profile['CategoryCode'];
                $paymentResult['CCGatewayErrorMessage'] = 'Not submitted to gateway';
                $paymentResult['CCGatewayRef'] = 'Not submitted to gateway';
            }

            $profile['iMISID'] = $iMISID;

            if ($paymentResult['ReturnCode'] == 'SUCCESS') {
                //5) Add Credentials , check username dup
//                $username = $profile['Account']['username'];
//                if ($this->isUsernameInUse($username)) {
//                    $username = $profile['iMISID'];
//                    $profile['Account']['username'] = $profile['iMISID'];
//                }
//                $response = $this->client->AddCredentials(array(
//                    'username' => $username,
//                    'password' => $profile['Account']['newPassword'],
//                    'ID' => $iMISID,
//                ));

                $actualUsername = $this->createCredentials($iMISID, $profile['Account']['username'], $profile['Account']['newPassword']);
                $profile['Account']['username'] = $actualUsername;

                $newProfile = $this->updateMemberProfile($profile);
                //TODO cross checking updateMemberProfile details here
                $retVal['profile'] = $newProfile['profile'];
                //$retVal['accessLevel'] = $newProfile['accessLevel'];
                $retVal['status'] = 'SUCCESS';
                $retVal['message'] = isset($paymentResult['CCGatewayRef'])?$paymentResult['CCGatewayRef']:'';
                $retVal['customerMessage'] = 'Your account is created with login: '.$actualUsername;
            } else {
                //TODO handle more error here
                $retVal['profile'] = $profile;
                $retVal['message'] = $paymentResult['ErrorMessage'].' | '.$paymentResult['InternalErrorMessage'].' | '.$paymentResult['CCGatewayErrorMessage'];
                if ($paymentResult['SuccessfulCCDeduction']) {
                    //Payment has been taken
                    $retVal['customerMessage'] = 'Error has occurred, please contact membership service asap';
                } else if (strpos($paymentResult['InternalErrorMessage'], 'pending payment for dues already exists')!==FALSE) {
                    $retVal['customerMessage'] = 'A payment is pending for your account';
                } else {
                    $retVal['customerMessage'] = 'Error has occurred, please try again';
                }
            }

        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }

        return $retVal;
    }

    public function createCredentials($iMISID, $username, $password)
    {
        if ($this->isUsernameInUse($username)) {
            $username = $iMISID;
        }
        $response = $this->client->AddCredentials(array(
            'username' => $username,
            'password' => $password,
            'ID' => $iMISID,
        ));
        return $username;
    }

    /**
     * Process a member renewal request
     * @param array $profile profile in simple array strucutre from web clients
     * @param array $payment payment details may not be required for monthly paying member just trying to update their profile details
     * @return
     */
    public function renewMembership($profile, $payment = null)
    {
        $retVal = array(
            'profile' => array(),
            'status' => 'FAILED',
            'accessLevel' => '',
            'message' => '',
            'customerMessage' => '');
        $logMessage = "Renewal ";
        try {
            $memberProfile = $this->getMemberProfileById($profile['iMISID']);
            $iMISID = $memberProfile['profile']['iMISID'];

            $duesLine = $memberProfile['profile']['OutstandingDuesPayableOnWeb'];
            if (!empty($duesLine)) {
                $totalAmount = 0;
                if (isset($duesLine['DuesLineItem'])) {
                    foreach($duesLine['DuesLineItem'] as $item) {
                        $totalAmount += $item->Balance;
                    }
                }

                $paymentResult = array('ReturnCode' => '');
                if ($totalAmount > 0 && !empty($payment)) {

                    $gateway = array(
                        'BatchCreatedBy' => 'WEB'.$profile['ChapterCode'],
                        'CashAccountCode' => $payment['CardType'].'_'.$profile['ChapterCode'],
                        'CardholderName' => $payment['CardholderName'],
                        'CreditCardNumber' => $payment['CreditCardNumber'],
                        'ExpiryMonth' => $payment['ExpiryMonth'],
                        'ExpiryYear' => $payment['ExpiryYear'],
                        'CCV' => $payment['CCV'],
                        'PaymentAmount' => $totalAmount,
                    );

                    if ($payment['billing_method'] == 'Annual') {
                        $logMessage .= 'Paying Annual ';
                        $paymentResult = $this->payDues($iMISID, $duesLine, $gateway, $totalAmount, $totalAmount);
                        //return $paymentResult;
                    } else {
                        $logMessage .= 'Paying Monthly EFT ';
                        //  You can use
                        //  EFTInstalmentSchedule DetermineEFTScheduleForDues
                        //  To figure out how the payments will be deducted.
                        //  For tieToEFTMerchant, use one of thee as appropriate:
                        //  ACT CC | FEDCPD CC | New South Wales CC | NT CC | Queensland CC | South Australia CC | TAS CC
                        $response = $this->client->DetermineEFTScheduleForDues(array(
                            'iMISID' => $iMISID,
                            'freq' => 'Monthly',
                            'totalDuesPayable' => $totalAmount,
                        ));

                        $FirstDate = $response->DetermineEFTScheduleForDuesResult->FirstInstalmentDate;
                        $FinalDate = $response->DetermineEFTScheduleForDuesResult->FinalInstalmentDate;//
                        $InstalmentAmount = $response->DetermineEFTScheduleForDuesResult->InstalmentAmount;
                        $PaymentAmountUpfront = $response->DetermineEFTScheduleForDuesResult->PaymentAmountUpfront;

                        if ($response->DetermineEFTScheduleForDuesResult->AlreadyRegisteredForDuesEFT) {
                            $this->dr("already on EFT");
                            //Compare details
                            $eftDetails = $this->getEFTDetailsForDues($iMISID);
                            $FirstDate = $eftDetails['NextInstalmentDate'];
                            $deductionAmount = $eftDetails['DeductionAmount'];

                            $params = array(
                                'ID' => $iMISID,
                                'EFTDetails' => array(
                                    'tieToMerchantAccount' => $this->cashAccountCode[$profile['ChapterCode']],
                                ),
                                //'gateway' => $gateway,
                                //'freq' => 'Monthly',
                                //'firstInstalmentDate' => $FirstDate,
                                //'finalInstalmentDate' => '9999-12-31',//$FinalDate,//
                                //'tieToMerchantAccount' => $this->cashAccountCode[$profile['ChapterCode']],
                            );

                            //$setEFTResponse = $this->client->SetupDuesEFT($params);
                        } else {
                            //No existing EFT
                            //TODO use separate method depends on whether people had EFT or not
                        }
                        $gateway['PaymentAmount'] = $InstalmentAmount;
                        $params = array(
                            'ID' => $iMISID,
                            'gateway' => $gateway,
                            'freq' => 'Monthly',
                            'firstInstalmentDate' => $FirstDate,
                            'finalInstalmentDate' => '9999-12-31',//$FinalDate,//
                            'tieToMerchantAccount' => $this->cashAccountCode[$profile['ChapterCode']],
                        );
                        //$this->dr($params);
                        $setEFTResponse = $this->client->SetupDuesEFTFromGateway($params);
                        //TODO check this if it may have any error
                        $paymentResult['ReturnCode'] = 'SUCCESS';
                    }
                } else {
                    $logMessage .= 'No payment ';
                    $paymentResult['ReturnCode'] = 'SUCCESS';
                }
            } else {
                //No dues line, it shouldn't really happen here for renew
                $paymentResult = array('ReturnCode' => 'SUCCESS');
                $logMessage .= 'No duesline ';
            }

            $logMessage .= $paymentResult['ReturnCode'];
            if ($paymentResult['ReturnCode'] == 'SUCCESS') {

                $newProfile = $this->updateMemberProfile($profile);
                $retVal['profile'] = $newProfile['profile'];
                $retVal['status'] = 'SUCCESS';
                $retVal['message'] = $logMessage.' '.(isset($paymentResult['CCGatewayRef'])?$paymentResult['CCGatewayRef']:'');
                $retVal['customerMessage'] = 'Thank you';
//                $activityResponse = $this->newActivity($iMISID, 'RENEW', $logMessage);
//                if ($activityResponse['status'] != 'SUCCESS') {
//                    $retVal['message'] .= ' '.$activityResponse['message'];
//                }
            } else {
                //TODO handle more error here
                $retVal['profile'] = $profile;
                $retVal['message'] = $paymentResult['ErrorMessage'].' | '.$paymentResult['InternalErrorMessage'].' | '.$paymentResult['CCGatewayErrorMessage'].' | '.$logMessage;
                if ($paymentResult['SuccessfulCCDeduction']) {
                    //Payment has been taken
                    $retVal['customerMessage'] = 'Error has occurred, please contact membership service asap';
                } else if (strpos($paymentResult['InternalErrorMessage'], 'pending payment for dues already exists')!==FALSE) {
                    $retVal['customerMessage'] = 'A payment is pending for your account ';
                } else {
                    $retVal['customerMessage'] = 'Error has occurred, please try again ';
                }
            }

        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }

        return $retVal;
    }

    /**
     * Retrieve member renewal details such as fees and payment methods
     * @param string $iMISID
     * @return array
     */
    public function getMemberRenewalDetails($iMISID)
    {
        $retVal = array(
            'profile' => array(),
            'status' => 'FAILED',
            'accessLevel' => '',
            'message' => '',
            'customerMessage' => '');
        try {
            $profile = $this->getMemberProfileById($iMISID);
            $dues = $profile['profile']['OutstandingDuesPayableOnWeb'];
            $renewalDetails = array();
            $renewalDetails['totalAmount'] = 0;
            if (isset($dues['DuesLineItem'])) {
                foreach ($dues['DuesLineItem'] as $item) {
                    $renewalDetails['totalAmount'] += $item->Balance;
                }
            }

            $response = $this->client->DetermineEFTScheduleForDues(array(
                'iMISID' => $iMISID,
                'freq' => 'Monthly',
                'totalDuesPayable' => $renewalDetails['totalAmount'],
            ));

            $renewalDetails['firstDate'] = $response->DetermineEFTScheduleForDuesResult->FirstInstalmentDate;
            $renewalDetails['finalDate'] = $response->DetermineEFTScheduleForDuesResult->FinalInstalmentDate;
            $renewalDetails['installmentAmount'] = $response->DetermineEFTScheduleForDuesResult->InstalmentAmount;
            $renewalDetails['paymentUpfront'] = $response->DetermineEFTScheduleForDuesResult->PaymentAmountUpfront;
            $renewalDetails['isAlreadyOnEFT'] = $response->DetermineEFTScheduleForDuesResult->AlreadyRegisteredForDuesEFT;

            unset($profile['profile']['OutstandingDuesPayableOnWeb']);

            $eftDetails = $this->getEFTDetailsForDues($iMISID);

            $retVal['status'] = 'SUCCESS';
            $retVal['profile'] = $profile['profile'];
            $retVal['accessLevel'] = $profile['accessLevel'];
            $retVal['renewalDetails'] = $renewalDetails;
            $retVal['eftDetails'] = $eftDetails;
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
            $retVal['customerMessage'] = 'Unable to retrieve renewal details, please try again later.';
        }
        return $retVal;
    }


    public function billMember($iMISID)
    {
        $retVal = array(
            'profile' => array(),
            'status' => 'FAILED',
            'accessLevel' => '',
            'message' => '',
            'customerMessage' => '');
        try {
            $memberProfile = $this->getMemberProfileById($iMISID);
            $duesLine = $memberProfile['profile']['OutstandingDuesPayableOnWeb'];
            if (empty($duesLine)) {
                // Call the following to ensure you know what product codes to bill and the amount to be billed.
                $params = array(
                    'memberTypeCode' => $memberProfile['profile']['MemberTypeCode'],
                    'categoryCode' => $memberProfile['profile']['CategoryCode'],
                    'ID' => $iMISID,
                    'productCodes' => '',
                    'asOf' => $this->getBillFromDate(),//date('Y-m-d'),
                );
                $response = $this->client->GetJoinFeeBreakdown($params);
                //$this->dr($response);
                $feeArray = isset($response->GetJoinFeeBreakdownResult->Details->NewMemberFeeDetails)?(array) $response->GetJoinFeeBreakdownResult->Details->NewMemberFeeDetails:array();
                $productCodes = "";
                //$this->dr($feeArray);
                if (isset($feeArray['ProductCode'])) {
                    $productCodes .= $feeArray['ProductCode'];
                } else {
                    foreach($feeArray as $fee) {
                        $productCodes .= $fee->ProductCode.",";
                        //$productResponse = $this->client->GetProductDetails(array('productCode'=>$fee->ProductCode));
                        //$this->dr($productResponse->GetProductDetailsResult);
                    }
                }
                $totalAmountFromSummary = $response->GetJoinFeeBreakdownResult->Summary->TotalFeesIncTax;

                //3) BillJoinFeesAsOf and pass in the MemberType and Category you want to bill
                // This will return the member profile and on it look at the
                // OutstandingDuesPayableOnWeb -> dues lines to pay off by setting each line's AmountToPay to be the Balance
                // and then passing this collection of lines into PayJoinDues.

                $params['productCodes'] = $productCodes;
                //$this->dr($params);
                $response = $this->client->BillJoinFeesAsOf($params);
                $duesLine = (array) $response->BillJoinFeesAsOfResult->OutstandingDuesPayableOnWeb;
            }

            $totalAmount = 0;
            if (isset($duesLine['DuesLineItem'])) {
                foreach($duesLine['DuesLineItem'] as $item) {
                    $totalAmount += $item->Balance;
                }
            }
            $retVal['profile'] = $memberProfile;
            if ($totalAmount > 0) {
                $retVal['status'] = 'SUCCESS';
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }

        return $retVal;
    }


    /**
     * Turns a profile array from website client into a MemberProfile object for Compass
     * @param array $profile
     * @return object $profile
     */
    private function prepMemberProfileObject($profile) {
        if (!empty($profile['iMISID'])) {
            //Existing member with iMIS ID, Fetch their member profile from iMIS first and only modify what we have
            $response = $this->client->GetMemberProfile(array('ID'=>$profile['iMISID']));
            $memberProfile = $response->GetMemberProfileResult;
            $newRecord = FALSE;
        } else {
            //Initialise a brand new profile
            $memberProfile = new \stdClass();
            $newRecord = TRUE;
        }
        $UDFields = $this->getUDFieldsSchema();
        //$this->dr($UDFields);
        $memberProfile->UDFields = array('UDField' => array());
        foreach ($profile as $key=>$value) {
            if ($key == "Address") {
                foreach ($value as $address) {
                    //AMA iMIS default address purpose
                    $tabs = array('Business'=>'AddressTab1','Other'=>'AddressTab2','Home'=>'AddressTab3');
                    $tab = $tabs[$address['Purpose']];
                    if (!isset($memberProfile->$tab->AddressNumber)) {
                        $memberProfile->$tab = new \stdClass();
                        $memberProfile->$tab->BadAddressCode = '';
                    }
                    if (isset($address['PreferredMail'])) {
                        $memberProfile->$tab->PreferredBill = $address['PreferredMail'];
                        $memberProfile->$tab->PreferredShip = $address['PreferredMail'];
                    }
                    if (isset($address['ID']) && !empty($address['ID'])) {
                        if (!isset($memberProfile->Relationships->RelationshipCollection->Relationships->Relationship->TargetId)) {
                            //New relationship
                            $memberProfile->Relationships = new \stdClass();
                            //$memberProfile->Relationships->RelationshipCollection = new \stdClass();
                            $memberProfile->Relationships->RelationshipCollection = array();
                            $relationships = new \stdClass();
                            $relationships->RelationshipCode = 'WORK';
                            $relationships->Relationships = array();
                            $relationship = new \stdClass();
                            $relationship->DeleteRelationship = FALSE;
                            $relationship->GroupCode = 'PRINCIPAL';
                            $relationship->Reciprocal_Type = 'PRACTICE';
                            $relationship->Relationship_Type = 'WORK';
                            $relationship->TargetId = $address['ID'];
                            $relationships->Relationships[] = $relationship;
                            $memberProfile->Relationships->RelationshipCollection[] = $relationships;
                        } else if (isset($memberProfile->Relationships->RelationshipCollection->Relationships->Relationship->TargetId)
                            && $address['ID'] != $memberProfile->Relationships->RelationshipCollection->Relationships->Relationship->TargetId) {
                            //Update a relationship
                            $this->dr('Updating Relationship '.$address['ID'] .' for '. $memberProfile->Relationships->RelationshipCollection->Relationships->Relationship->TargetId);
                            $memberProfile->Relationships->RelationshipCollection->Relationships->Relationship->TargetId = $address['ID'];
                        }
                    } else if (isset($address['ID'])
                        && $address['ID'] == 0
                        && isset($memberProfile->Relationships->RelationshipCollection->Relationships->Relationship->TargetId)) {
                        //Delete a relationship
                        $memberProfile->Relationships->RelationshipCollection->Relationships->Relationship->DeleteRelationship = TRUE;
                        $this->dr('DELETING RELATIONSHIP');
                    }
                    foreach ($address as $addressKey => $addressValue) {
                        $memberProfile->$tab->$addressKey = $addressValue;
                    }
                }
            } else if (array_key_exists($key, $UDFields)) {
                $UDField = $this->getUDField($key, $UDFields[$key]['tableName'], $value, strpos($UDFields[$key]['type'], 'Date') !== FALSE);
                $memberProfile->UDFields['UDField'][] = $UDField;
            } else if ($key == "Account") {
                continue;
            } else {// TODO check if we need to filter value
                $memberProfile->$key = $value;
            }
        }
        if ($newRecord) {
            $memberProfile->MemberTypeCode = "T-".$profile['ChapterCode'];//"WEB";//"T-".$profile['ChapterCode'];//
            $memberProfile->StatusCode = "A";//"D";//
        }
        return $memberProfile;
    }

    /**
     * Pay dues for Join request
     * @param string $iMISID
     * @param array $profile
     * @param array $duesLine
     * @param array $gateway
     * @param float $paymentAmount
     * @param float $totalAmount
     * @return 
     */
    private function payJoinDues($iMISID, $profile, $duesLine, $gateway, $paymentAmount, $totalAmount)
    {
        $gateway['PaymentAmount'] = $paymentAmount;
        $payRatio = $paymentAmount / $totalAmount;
        foreach($duesLine['DuesLineItem'] as $item) {
            $item->AmountToPay = $item->Balance * $payRatio;
        }
        $params = array(
            'memberTypeCode' => $profile['MemberTypeCode'],
            'categoryCode' => $profile['CategoryCode'],
            'ID' => $iMISID,
            'payableDues' => $duesLine,
            'gateway' => $gateway,
        );
        //$this->dr($params);
        $processorResponse = $this->client->PayJoinDues($params);
        //$this->dr($this->client->__getLastRequest());
        return (array) $processorResponse->PayJoinDuesResult;
    }

    /**
     * Pay dues for renewal request
     *
     */
    private function payDues($iMISID, $duesLine, $gateway, $paymentAmount, $totalAmount)
    {
        $gateway['PaymentAmount'] = $paymentAmount;
        $payRatio = $paymentAmount / $totalAmount;
        foreach($duesLine['DuesLineItem'] as $item) {
            $item->AmountToPay = $item->Balance * $payRatio;
        }
        $params = array(
            'ID' => $iMISID,
            'payableDues' => $duesLine,
            'gateway' => $gateway,
        );
        //$this->dr($params);
        $processorResponse = $this->client->PayDues($params);
        //$this->dr($this->client->__getLastRequest());
        return (array) $processorResponse->PayDuesResult;
    }

    /**
     * Create a UDField obj for Compass from the value provided
     *
     */
    private function getUDField($fieldName, $tableName, $value, $isDate = FALSE)
    {
        $UDField = new \stdClass();
        $UDField->Field = $fieldName;
        $UDField->Table = $tableName;
        if ($isDate) {
            $UDField->Value = new \SoapVar($value, XSD_DATETIME, "dateTime", "http://www.w3.org/2001/XMLSchema");
        } else {
            $UDField->Value = new \SoapVar($value, XSD_STRING, "string", "http://www.w3.org/2001/XMLSchema");
        }
        return $UDField;
    }

    /**
     * Create a soap var
     * @param string $value
     * @return \SoapVar
     */
    private function getSoapVar($value)
    {
        return new \SoapVar($value, XSD_STRING, "string", "http://www.w3.org/2001/XMLSchema");
    }

    /**
     * Call a iQA query without parameter and return result
     * @param string $queryPath
     * @return array
     */
    public function getIQAQuery($queryPath)
    {
        $params = array(
            'queryPath' => $queryPath,
        );

        try {
            $response = $this->client->IQAQuery($params);
        } catch (\Exception $e) {
            $response = $e->getMessage();
            $this->dr($this->client->__getLastRequest());
        }

        return $response;
    }

    /**
     * Call a iQA query and return result
     * @param string $queryPath
     * @param array $parameters
     * @return array
     */
    public function getIQAQueryWithParameters($queryPath, $parameters)
    {
        $retVal = array('message' => '', 'data' => array());
        $params = array(
            'iqaQueryRequest' => array(
                'QueryLocation' => $queryPath,
                'Parameters' => array(),
            ),
        );

        for ($i=0; $i<sizeof($parameters); $i++) {
            $params['iqaQueryRequest']['Parameters'][] = array(
                'Key' => $i,
                'Value' => $parameters[$i],
            );
        }
        //$this->dr($params);
        try {
            $response = $this->client->IQAQueryWithParameters($params);
            //$this->dr($response);
            $data = array();
            if (isset($response->IQAQueryWithParametersResult->Header->Columns->ResultHeaderColumn)) {
                $header = array();
                foreach($response->IQAQueryWithParametersResult->Header->Columns->ResultHeaderColumn as $id => $column) {
                    $item = array(
                        'Name' => $column->Name,
                        'DataType' => $column->DataType,
                        );
                    $header[$id] = $item;
                }

                //$this->dr($response->IQAQueryWithParametersResult->Rows->ResultRow);
                if (is_array($response->IQAQueryWithParametersResult->Rows->ResultRow)) {
                    foreach($response->IQAQueryWithParametersResult->Rows->ResultRow as $id => $row) {
                        $item = array();
                        foreach($row->Columns->ResultDataColumn as $rid => $rowData) {
                            $item[$header[$rid]['Name']] = $rowData->Value;
                        }
                        $data[$id] = $item;
                    }
                } else if (is_object($response->IQAQueryWithParametersResult->Rows->ResultRow)) {
                    $item = array();
                    foreach($response->IQAQueryWithParametersResult->Rows->ResultRow->Columns->ResultDataColumn as $rid => $rowData) {
                        $item[$header[$rid]['Name']] = $rowData->Value;
                    }
                    $data[] = $item;
                }

                $retVal['data'] = $data;
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage().' | ';
            $this->dr($this->client->__getLastRequest());
        }
        //$this->dr($this->client->__getLastRequest());
        return $retVal;
    }

    /**
     * Look up practise by its name
     * @param string $name
     * @return array
     */
    public function lookupPractise($name)
    {
        $response = $this->getIQAQueryWithParameters(
            '$/ContactManagement/DefaultSystem/Queries/Advanced/Contact/WEB/WEB_List_of_Practice',
            array('', $name));

        return $response;
    }

    public function findRecord($firstName, $lastName, $dob)
    {
        $response = $this->getIQAQueryWithParameters(
            '$/ContactManagement/DefaultSystem/Queries/Advanced/Contact/WEB/WEB_Find_Duplicate',
            array($firstName, $lastName, $dob)
        );

        return $response;
    }

    public function findRecordByContactKey($contactKey)
    {
        $retVal = array('message' => '', 'data'=>array());
        $response = $this->getIQAQueryWithParameters(
            '$/AMAREST/Member_by_contact_key',
            array($contactKey)
        );
        if (isset($response['data'][0]) && !empty($response['data'][0]) && sizeof($response['data']) == 1) {
            $retVal['data'] = $response['data'][0];
        } else {
            $retVal['message'] = 'No unique record found';
        }
        return $retVal;
    }

    /**
     * @param string $iMISID
     * @param string $start start date of invoice period
     * @param string $end end date of invoice period
     * @param boolean $showDetails whether all details should be shown, default to false as most irrelevant
     *
     * @return array
     * Retrieve payment details for member to generate invoice
     */
    public function getInvoice($iMISID, $start, $end, $showDetails = FALSE)
    {
        $retVal = array('message' => '', 'data'=>array());
        $response = $this->getIQAQueryWithParameters(
            '$/ContactManagement/DefaultSystem/Queries/Advanced/Contact/WEB/WEB_Invoice',
            array($iMISID, $start, $end)
        );
        //$this->dr($response);
        //Get billing items
        foreach ($response['data'] as $row) {
            if (!array_key_exists($row['TransactionDate'], $retVal['data'])) {
                $retVal['data'][$row['TransactionDate']] = $row;
                $retVal['data'][$row['TransactionDate']]['GST'] = 0;
            } else {
                $retVal['data'][$row['TransactionDate']]['Amount'] += $row['Amount'];
                //if (strpos($retVal['data'][$row['TransactionDate']]['Description'], $row['Description']) === FALSE) {
                    $retVal['data'][$row['TransactionDate']]['Description'] .= ', '.$row['Description'];
                    $retVal['data'][$row['TransactionDate']]['ProductCode'] .= ', '.$row['ProductCode'];
                //}
            }
            if (strpos($row['Description'], 'GST') !== FALSE) {
                $retVal['data'][$row['TransactionDate']]['GST'] += $row['Amount'];
            }

            if (!$showDetails) {
                unset($retVal['data'][$row['TransactionDate']]['TransactionDate']);
                unset($retVal['data'][$row['TransactionDate']]['PayMethod']);
                unset($retVal['data'][$row['TransactionDate']]['ResultRow']);
                unset($retVal['data'][$row['TransactionDate']]['Id']);
                unset($retVal['data'][$row['TransactionDate']]['ActivityType']);
            }
            //$productResponse = $this->client->GetProductDetails(array('productCode'=>$row['ProductCode']));
            //$row['Title'] = $productResponse->GetProductDetailsResult->Title;
        }
        ksort($retVal['data']);
        //Get billing address
        $profileResponse = $this->getMemberProfileById($iMISID);
        $tabs = array('AddressTab1', 'AddressTab2', 'AddressTab3');
        foreach ($tabs as $tab) {
            if (isset($profileResponse['profile'][$tab]['PreferredBill'])
                && $profileResponse['profile'][$tab]['PreferredBill'])
            {
                $retVal['billing'] = $profileResponse['profile'][$tab];
                $retVal['billing']['Name'] = $profileResponse['profile']['FullName'];
                $retVal['billing']['iMISID'] = $profileResponse['profile']['iMISID'];
                $retVal['billing']['MajorKey'] = $profileResponse['profile']['MajorKey'];
                $retVal['billing']['CategoryCode'] = $profileResponse['profile']['CategoryCode'];
                $retVal['billing']['CategoryDescription'] = $profileResponse['profile']['CategoryDescription'];
                break;
            }
        }
        //$this->dr($profileResponse['profile']);

        //$retVal['account'] = $this->getMemberProfileById($iMISID);
        return $retVal;
    }

    /**
     * Create a new activity for user
     *
     * @param string $iMISID user's imisid
     * @param string $activityType
     * @param string $note
     * @param string $categoryCode
     * @param string $actionCode
     * @return
     */
    public function newActivity($data)
    {
        $retVal = array('status' => 'FAILED', 'message' => '');

        $defaultParam = array(
            'ContactId' => NULL,//$iMISID,
            'ActivityType' => NULL,//$activityType,
            'Note' => NULL,//$note,
            'CategoryCode' => NULL,//$categoryCode,
            'ActionCodes' => NULL,//$actionCode,
            'FollowUp' => '',
            'Amount' => '0',
            'CampaignCode' => NULL,
            'Delete' => false,
            'Description' => NULL,
            'EffectiveDate' => NULL,
            'FinancialEntityCode' => NULL,
            'GracePeriod' => 0,
            'InstituteContactId' => NULL,
            'IsRecurringRequest' => false,
            'NextInstallDate' => NULL,
            'OtherCode' => NULL,
            'OtherContactId' => NULL,
            'ProductCode' => NULL,
            //'SequenceNum' => 11034402,
            'SolicitorContactId' => NULL,
            'SourceCode' => NULL,
            'SourceSystem' => NULL,
            'StatusCode' => NULL,
            'ThruDate' => NULL,
            'TicklerDate' => NULL,
            'TransactionDate' => NULL,
            'Units' => '0',
            'UserField1' => NULL,
            'UserField2' => NULL,
            'UserField3' => NULL,
            'UserField4' => NULL,
            'UserField5' => NULL,
            'UserField6' => NULL,
            'UserField7' => NULL,
        );

        $params = array_merge($defaultParam, $data);

        try {
            $params = array('activity' => $params);

            $response = $this->client->SaveActivity($params);
            if (isset($response->SaveActivityResult->ContactId)) {
                $retVal['status'] = 'SUCCESS';
                $retVal['sequenceNum'] = $response->SaveActivityResult->SequenceNum;
            } else {
                $retVal['message'] = 'Unknown error, response invalid';
            }
            //$this->dr($response);
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }
        return $retVal;
    }

    /**
     * This method compares two profiles to make sure changes have been made as expected
     * @param array $profile the new profile with updated details, source of comparison
     * @param array $processedProfile the processed profiled returned from Compass.
     *
     * @return array
     */
    public function compareProfiles($profile, $processedProfile)
    {
        $retVal = array('isSame' => FALSE, 'fieldDiff' => array());
        $fieldCount = 0;
        $isSameCount = 0;
        foreach($profile as $key=>$value) {

            if ($key == "Address") {
                foreach ($value as $address) {
                    //AMA iMIS default address purpose
                    $tabs = array('Business'=>'AddressTab1','Other'=>'AddressTab2','Home'=>'AddressTab3');
                    $tab = $tabs[$address['Purpose']];

                    foreach ($address as $addressKey => $addressValue) {
                        if ($addressKey=="ID") continue;
                        $fieldCount++;
                        if (strcasecmp($processedProfile[$tab][$addressKey], $addressValue) == 0) {
                            $isSameCount++;
                        } else {
                            $retVal['fieldDiff'][] = $tab."-".$addressKey;
                        }
                    }
                }
            } else if ($key == "Account") {
                continue;
            } else {
                $fieldCount++;
                if (strcasecmp($value, $processedProfile[$key]) == 0
                    || ($value == "TRUE" && $processedProfile[$key] == TRUE)
                    || ($value == "FALSE" && $processedProfile[$key] == FALSE)) {
                    $isSameCount++;
                } else {
                    $retVal['fieldDiff'][] = $key;
                }
            }
        }
        if ($isSameCount == $fieldCount) {
            $retVal['isSame'] = TRUE;
        }
        return $retVal;
    }

    /**
     * Test methods below
     *
     */
    public function getTestProfileArray($categoryCode = "F1Y1", $memberType = "M", $state = "QLD", $iMISID = null, $username = null, $oldPassword = null, $newPassword = null)
    {
        $randString = 'L'.substr(str_shuffle(str_repeat($x='abcdefghijklmnopqrstuvwxyz', ceil(8/strlen($x)) )),1,8);
        $newProfile = array(
            'Firstname' => $randString,
            'Lastname' => 'Amatest',
            'Prefix' => 'Mr',
            'CompanyName' => 'AMA Federal Office',
            'DateOfBirth' => '1986-01-17T00:00:00',
            'MobilePhone' => "046".rand(1000000,9999999),
            'StatusCode' => 'A',
            'Address' => array(
                array(
                    'Address1' => rand(1,200).' Home Street',
                    'Address2' => 'Home address 2'.rand(1,200),
                    'Address3' => 'HA 3'.rand(1,200),
                    'City' => 'Hometown',
                    'PostalCode' => rand(1000,9999),
                    'StateProvince' => 'NSW',
                    'Phone' => '666'.rand(10000,99999),
                    'Purpose' => 'Home',
                    'PreferredMail' => false,
                    //'PreferredShip' => true,
                ),
                array(
                    'Address1' => rand(1,200).'8 Business Street',
                    'Address2' => 'Bus 2'.rand(1,200),
                    'Address3' => 'BA 3'.rand(1,200),
                    'City' => 'Worktown',
                    'PostalCode' => rand(1000,9999),
                    'StateProvince' => 'ACT',
                    'Phone' => '888'.rand(10000,99999),
                    'Purpose' => 'Business',
                    'PreferredMail' => true,
                    'ID' => '150909'
                    //'EmailAddress' => '',
                    //'AddressStatus' => 'NOT_VALIDATED',
                    //'BSP' => '',
                    //'BadAddressDescription' => NULL,
                    //'Barcode' => '',
                    //'Country' => '',
                    //'DPID' => '',
                    //'Fax' => '',
                ),
            ),
            'CRAFT_GROUP' => 'DIT',
            'DISCIPLINE' => 'VENE',
            'FEDERAL_VOTING_GROUP' => 'OBGY',
            'FED_PRACTICE_GROUP' => 'GP,PSP',
            'PRAC_STATUS' => 'N',
            'EMP_MODE' => 'F',
            'TRAINING_NETWORK' => 'ORTH',
            'TRAINING_EXP_COMPL_DATE' => rand(2019,2028).'-01-01T00:00:00',
            'HOW_EMP' => 'PPY',
            'WHERE_EMP' => 'HA',
            'EMP_AS' => 'NZ',
            'FIELD_PRAC' => 'MACU',
            'VMO' => 'FALSE',
            'VMO_PRACTICES_NOTES' => 'TEST NOTE',
            'OTHER_MEMBERSHIP' => 'ANZCA',
            'INTEREST_GROUP' => 'DHS',
            'MED_SCHOOL' => 'BU',
            'YR_MED_SCHOOL' => rand(2000,2018),
            'YR_GRAD' => rand(2018, 2025),
            'ATSI' => 'No',
            'UNI_STUDENT_NUMBER' => 'u4'.rand(10000,99999),
            'STUDENT_PLACEMENT_TYPE' => 'ADF',
            'PRIVACY' => 'MJA,FEES',
            'AHPRA' => 'MED11111111111',
        );

        if (!empty($iMISID)) {
            //Existing account
            $newProfile['iMISID'] = $iMISID;//'iMISID' => '9006421',
        } else {
            //New account
            $newProfile['EmailAddress'] = $randString.'@test.com';
            $username = $newProfile['EmailAddress'];
        }
        $newProfile['CategoryCode'] = $categoryCode;
        $newProfile['MemberTypeCode'] = $memberType.'-'.$state;//'M-NSW',//'WEB'
        $newProfile['ChapterCode'] = $state;

        $newProfile['Account'] = array(
            'username'=> $username,
            'newPassword'=> $newPassword?$newPassword:'amacompass2018',
            'oldPassword'=> $oldPassword?$oldPassword:'amacompass2018',
        );

        return $newProfile;
    }

    public function getTestProfileArrayAMSA($categoryCode = "AMSAD", $iMISID = null, $username = null, $oldPassword = null, $newPassword = null)
    {
        $randString = 'L'.substr(str_shuffle(str_repeat($x='abcdefghijklmnopqrstuvwxyz', ceil(8/strlen($x)) )),1,8);
        $newProfile = array(
            'Firstname' => $randString,
            'Lastname' => 'Amatest',
            'Prefix' => 'Mr',
            'CompanyName' => 'AMA Federal Office',
            'DateOfBirth' => '1986-01-17T00:00:00',
            'MobilePhone' => "046".rand(1000000,9999999),
            'StatusCode' => 'A',
            'Address' => array(
                array(
                    'Address1' => rand(1,200).' Home Street',
                    'Address2' => 'Home address 2'.rand(1,200),
                    'Address3' => 'HA 3'.rand(1,200),
                    'City' => 'Hometown',
                    'PostalCode' => rand(1000,9999),
                    'StateProvince' => 'NSW',
                    'Phone' => '666'.rand(10000,99999),
                    'Purpose' => 'Home',
                    'PreferredMail' => false,
                    //'PreferredShip' => true,
                ),
            ),
            'MED_SCHOOL' => 'BU',
            'YR_MED_SCHOOL' => rand(2000,2018),
            'YR_GRAD' => rand(2018, 2025),
            'ATSI' => 'No',
            'UNI_STUDENT_NUMBER' => 'u4'.rand(10000,99999),
        );

        if (!empty($iMISID)) {
            //Existing account
            $newProfile['iMISID'] = $iMISID;//'iMISID' => '9006421',
        } else {
            //New account
            $newProfile['EmailAddress'] = $randString.'@test.com';
            $username = $newProfile['EmailAddress'];
        }
        $newProfile['CategoryCode'] = $categoryCode;
        $newProfile['MemberTypeCode'] = 'AMSA';
        $newProfile['ChapterCode'] = '';

        $newProfile['Account'] = array(
            'username'=> $username,
            'newPassword'=> $newPassword?$newPassword:'amacompass2018',
            'oldPassword'=> $oldPassword?$oldPassword:'amacompass2018',
        );

        return $newProfile;
    }

    public function getTestProfileArrayTypeOnly($categoryCode = "F1Y1", $memberType = "M", $state = "TAS", $iMISID = null, $username = null, $oldPassword = null, $newPassword = null)
    {
        $newProfile = array(
            'StatusCode' => 'A',
            'CompanyName' => 'AMA Federal Test22',
            //'CompanySort' => 'AMA FEDERAL TEST',
            'Address' => array(
                array(
                    'Purpose' => 'Business',
                    'ID' => '0'
                ),
            ),
        );

        if (!empty($iMISID)) {
            //Existing account
            $newProfile['iMISID'] = $iMISID;//'iMISID' => '9006421',
        }
        $newProfile['CategoryCode'] = $categoryCode;
        $newProfile['MemberTypeCode'] = $memberType.'-'.$state;//'M-NSW',//'WEB'
        $newProfile['ChapterCode'] = $state;

        return $newProfile;
    }

    public function getTestPayment($method = "Annual", $cardType = 'VISA', $validExpiry  = TRUE) {
        $number = array(
            'VISA' => '4111111111111111',
            'MC'   => '2221000000000009',
            'AMEX' => '378282246310005',
            'DINERS' => '30569309025904',
        );
        $payment = array(
            'billing_method' => $method,
            'CardType' => $cardType,
            'CardholderName' => 'Test Card',
            'CreditCardNumber' => $number[$cardType],
            'ExpiryMonth' => '01',
            'ExpiryYear' => $validExpiry?'2020':'2017',
            'CCV' => '123',
        );
        return $payment;
    }
}

