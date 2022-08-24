<?php

namespace CompassWrapper;

use SoapClient;

/**
 * iMIS Compass wrapper class for AMA Base version
 * Provides ability for web clients to integrate with iMIS via Compass API
 * Base class only provides capability to authenticate with iMIS
 * @author Li Zhou lzhou@ama.com.au
 * @copyright Australian Medical Association
 */

define("COMPASSURL", "https://testcompass.ama.com.au/CompassWCF.CompassService.svc?wsdl");
define("COMPASSLOCATION", "https://testcompass.ama.com.au/CompassWCF.CompassService.svc/basic");
define("MEMBERSHIPSERVICE", "https://sgmember.ama.com.au/AsiCommon/Services/Membership/MembershipWebService.asmx?wsdl");

define("PASSWORDMINLENGTH", 7);
define("PASSWORDREGEX", "/^((?=.*[a-z])(?=.*\d)|(?=.*[A-Z])(?=.*\d)).{7,32}$/");
define("WEAKPASSWORDMESSAGE", "The password entered does not meet our password policy. Passwords must now be a combination of letters and at least 1 number at a minimum of 7 characters.");

define("SIXMONTHS", 181*24*3600);
define("THREEMONTHS", 181*24*3600);//90
define("ONEMONTH", 30*24*3600);
define("DEBUG", FALSE);

class AMACompassWrapperBase
{
    public $client = null;
    protected $compassUrl;
    protected $compassServiceLocation;
    protected $membershipWebServiceUrl;
    protected $username = null;
    protected $password = null;
    protected $amaMemberTypeCode = array('M-ACT', "M-NSW", "M-TAS", "M-QLD", "M-NT", "M-SA", "M-VIC", "M-WA");//, "STAFF"
    protected $amaStudentTypeCode = array("S-ACT", "S-NSW", "S-TAS", "S-QLD", "S-NT", "S-SA", "S-VIC", "S-WA");
    protected $amsaMemberTypeCode = array("AMSA");
    protected $amaQNonMemberTypeCode = array('D-QLD', 'C-QLD', 'Q-PMA');
    protected $amaMemberTypeCodeForJoin = array(
        'M-ACT' => 'ACT',
        "M-QLD" => 'QLD',
        "M-NT" => 'NT',
        "M-TAS" => 'TAS',
        "M-SA" => 'SA',
        "M-NSW" => 'NSW',
        "M-VIC" => 'VIC',
        "M-WA" => 'WA',
    );
    protected $amsaMemberTypeCodeForJoin = array(
        'AMSA' => 'AMSA',
    );
    protected $staffTypeCode = array("STAFF");
    protected $cashAccountCode = array(
        'ACT' => 'ACT CC',
        'NT'=>'NT CC',
        'QLD'=>'Queensland CC',
        'TAS'=>'TAS CC',
        'SA'=>'South Australia CC',
        'NSW'=>'New South Wales CC');
    protected $categoryTypeCode = array(
        'FPS1' => 'Full time specialist',
        'FPP1' => 'Full time general practitioner',
        'F1Y1' => 'First year after graduation (intern)',
        'F2Y1' => 'Second year after graduation',
        'F3Y1' => 'Third year after graduation',
        'F4Y1' => 'Fourth year after graduation',
        'F5Y1' => 'Fifth year after graduation',
        'F6Y1' => 'Sixth year or more after graduation',
        'FPH1' => 'Part-time no more than 2 half days per week',
        'FPT1' => 'Part-time 11-20 hours per week',
        'FPT2' => 'Part-time no more than 5 half days per week',
        'FSR1' => 'Salaried Medical Officer with PP Rights or Specialist Quals',
        'FSS1' => 'Salaried/Career Medical Officer',
    );
    protected $amsaCategoryCode = array(
        "AMSAA" => "AMSA Annual Membership",
        "AMSAD" => "AMSA Degree Membership",
    );
    protected $debug;

    public function __construct($username,
                                $password,
                                $compassUrl = COMPASSURL,
                                $compassServiceLocation = COMPASSLOCATION,
                                $membershipWebServiceUrl = MEMBERSHIPSERVICE,
                                $debug = DEBUG,
                                $certVerify = TRUE)
    {
        $this->compassUrl = $compassUrl;
        $this->membershipWebServiceUrl = $membershipWebServiceUrl;
        $this->compassServiceLocation = $compassServiceLocation;
        $this->username = $username;
        $this->password = $password;
        $this->debug = $debug;

        $options = array(
            'location' => $compassServiceLocation,
            'login' => $username,
            'password' => $password,
        );

        if ($debug) {
            $options['trace'] = 1;
        }
        if (!$certVerify) {
            //Option to turn off verify peer for certain sp that have issue with ssl certs
            $arrContextOptions=array(
                "ssl"=>array(
                    "verify_peer"=>false,
                    "verify_peer_name"=>false,
                    "CN_match" => 'ama.com.au'
                ),
            );
            $options['stream_context'] = stream_context_create($arrContextOptions);
        }

        try {
            $this->client = new SoapClient($compassUrl, $options);
        } catch(\Exception $e) {
            throw new \Exception('SoapClient can not be initialised: '.$e->getMessage());
        }
    }


    /**
     * Member authentication with iMIS
     * @param string $login the login username member provided, it maybe email address, see below
     * @param string $password password supplied by member
     * @param boolean $emailLookup default to TRUE will check if supplied $login is email address, if yes, will look up against iMIS db to try to fetch actual username
     * @param boolean $enableSso default to FALSE, if TRUE, will try to fetch a Login cookie from iMIS and set the cookie to enable iMIS SSO
     * @param string $loginMode Three modes available AMAM = 0, AMAA = 1, AMSA = 2
     *
     * @return array retVal with loginStatus, accessLevel and member profile if login is successful
     *
     * */
    public function authenticate($login, $password, $emailLookup = TRUE, $enableSso = FALSE, $loginMode = "AMAM")
    {
        $retVal = array(
            'profile' => array(),
            'loginStatus' => 'FAILED',
            'accessLevel' => '',
            'message' => '',
            'customerMessage' => '');

        $isPasswordStrong = $this->isPasswordStrong($password);
        if (!$isPasswordStrong['isValid']) {
            $retVal['customerMessage'] = $isPasswordStrong['message'];
            $retVal['message'] = "WEAK PASSWORD";
            $profileResponse = $this->getMemberProfileByLogin($login, $loginMode=="AMSA");
            if (isset($profileResponse['profile']['iMISID'])) {
                $retVal['profile'] = $profileResponse['profile'];
            }
            return $retVal;
        }

        if ($emailLookup && filter_var($login, FILTER_VALIDATE_EMAIL)) {
            try {
                //GetUsernameFromEmail is a generic method will cause problem for some AMSA member
                //as same person with both AMA and AMSA will have the same email address in system
                //$response = $this->client->GetUsernameFromEmail(array('Email'=>$login));
                //$login = $response->GetUsernameFromEmailResult;
                $loginFromEmail = $this->getUsernameFromEmailAddress($login, $loginMode=="AMSA");
                //What if member has a email address as login but use different email address in profile?
                //this may return a NULL for login here
                if (!empty($loginFromEmail)) {
                    $login = $loginFromEmail;
                }

            } catch (\Exception $e) {
                $retVal['message'] = $e->getMessage();
                //Likely no unique user can be found with the email address
                $retVal['customerMessage'] = 'We are having issue with your member account. Please contact member services team.';
                return $retVal;
            }
        }
        try {

            $params = array(
                'userName' => $login,
                'password' => $password,
            );
            $response = $this->client->Authenticate($params);
            //$this->dr($response);

            if ($response->AuthenticateResult->LoginSuccess == TRUE) {

                $profile= $this->getMemberProfileByUsername($login);
                $retVal['loginStatus'] = $this->filterMemberTypeCode($profile['accessLevel'], $loginMode);

                if ($retVal['loginStatus'] == 'SUCCESS') {
                    $retVal['accessLevel'] = $profile['accessLevel'];
                    $retVal['profile'] = $profile['profile'];
                    $retVal['message'] = $profile['message'];
                    $retVal['customerMessage'] = $profile['customerMessage'];
                } else {
                    $retVal['customerMessage'] = 'Your account is not allowed to access this service. Please contact membership services if any questions.';
                    $retVal['message'] = 'Member Type Code is not valid: '.$profile['profile']['iMISID'];
                }
            } else if ($response->AuthenticateResult->LockedOut) {
                $retVal['message'] = 'Account is disabled for '.$login;
                $retVal['customerMessage'] = 'Account is disabled. Please contact membership services';
            } else if ($response->AuthenticateResult->PasswordExpired) {
                $retVal['message'] = 'Password expired not valid for '.$login;
                $retVal['customerMessage'] = 'Your password is disabled. Please contact membership services';
            } else {
                $retVal['message'] = 'Authentication failed for username: '.$login.' Failure response from server: '.var_export((array)$response->AuthenticateResult, TRUE);
                $retVal['customerMessage'] = 'Sorry, your username and password does not match our record. Please check and try again.';
            }
        } catch (\Exception $e) {
            $retVal['message'] = 'Exception: '.$e->getMessage();
        }
        return $retVal;
    }

    /**
     * Get member profile by username
     * @param string $username
     * @return array $retVal member profile in simple array structure as processed by the processMemberProfile method
     * with extra details such as accessLevel
     */
    public function getMemberProfileByUsername($username)
    {
        $params = array('userName'=>$username);
        try {
            $response = $this->client->GetMemberProfileByUsername($params);
            return $this->processMemberProfile($response->GetMemberProfileByUsernameResult, $username);
        } catch (\Exception $e) {
            return array('message' => $e->getMessage());
        }
    }

    /**
     * Get member profile by username
     * @param string $id
     * @return array $retVal member profile in simple array structure as processed by the processMemberProfile method
     * with extra details such as accessLevel
     */
    public function getMemberProfileById($id)
    {
        $params = array('ID'=>$id);
        try {
            $response = $this->client->GetMemberProfile($params);
            //$this->dr($response);
            return $this->processMemberProfile($response->GetMemberProfileResult);
        } catch (\Exception $e) {
            return array('message' => $e->getMessage());
        }
    }

    /**
     * Look up member's username by Email address, able to specify if this member is a AMSA
     * @param string $email
     * @param boolean $isAMSA
     * @return string $username
     */
    public function getUsernameFromEmailAddress($email, $isAMSA = FALSE) {
        $filters = array('EmailAddress'=>$email, 'AMSA'=>'FALSE');
        if ($isAMSA) {
            $filters['AMSA'] = 'TRUE';
        }
        $response = $this->search('GetUniqueLoginByEmail', $filters);
        //$retVal[$response->KeyValuePairOfstringanyType->key] = $response->KeyValuePairOfstringanyType->value;
        return $response->KeyValuePairOfstringanyType->value;
    }

    public function getMemberProfileByLogin($login, $isAMSA = FALSE)
    {
        try {
            if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
                $loginFromEmail = $this->getUsernameFromEmailAddress($login, $isAMSA);
                if (!empty($loginFromEmail)) {
                    $login = $loginFromEmail;
                }
            }
            return $this->getMemberProfileByUsername($login);
        } catch (\Exception $e) {
            return array('message' => $e->getMessage());
        }
    }
    /**
     * Perform a search query
     * @param string $query
     * @param array $filters
     * @return
     */
    public function search($query, $filters)
    {
        $retVal = array();

//        $searchRequest = array();
//        $searchRequest[] = new SoapVar($query, XSD_STRING, null, null, 'definitionField');
//        $filtersArray = array();
//        foreach ($filters as $key=>$value) {
//            $filter = array();
//            $filter[] = new SoapVar($key,XSD_STRING,null,null, 'parameterNameField');
//            $filter[] = new SoapVar($value, XSD_STRING,null,null,'valueField');
//            $filterObj = new SoapVar($filter,SOAP_ENC_OBJECT,null,null,'filter');
//            $filtersArray[] = $filterObj;
//        }
//        $searchRequest[] = new SoapVar($filtersArray,SOAP_ENC_OBJECT,null,null,'filterField');
//        $params = new SoapVar($searchRequest, SOAP_ENC_OBJECT, null, null, 'searchRequest');

        $filtersArray = array('filter' => array());
        foreach ($filters as $key=>$value) {
            $filter = array();
            $filter['parameterNameField'] = $key;
            $filter['valueField'] = $value;
            $filtersArray['filter'][] = $filter;
        }
        $params = array(
            'searchRequest' => array(
                'definitionField' => $query,
                'filterField' => $filtersArray,
            )
        );
        try {
            $response = $this->client->Search($params);
            //The Search method returns a very generic data result which needs to be checked if different query is used
            if (isset($response->SearchResult->Results->GenericDataRow->Data)) {
                $retVal = $response->SearchResult->Results->GenericDataRow->Data;
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage().' ||| '.$this->client->__getLastRequest();

        }
        return $retVal;
    }

    /**
     * Retrieve general look up table contents
     * Potential table names:
     * CASH_ACCOUNT, CAMPAIGN, APPEAL, CHAPTER, MEMBER_TYPE, ACTIVITY_TYPE, FUND, DISTRIBUTION, ADDRESSPURPOSE
     * CRAFT_GROUP, CATEGORY, PREFIX
     * @param string $tableName
     * @return array $data look up table data in key value pair format
     */
    public function getLookup($tableName)
    {
        $retVal = array();
        try {
            $params = array('tableName' => $tableName);
            $response = $this->client->GetLookup($params);
            if (isset($response->GetLookupResult->KeyValuePairOfstringstring)) {
                $array = (array) $response->GetLookupResult->KeyValuePairOfstringstring;
                $retVal = $this->keyValuePairToArray($array);
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }
        return $retVal;
    }

    public function filterMemberTypeCode($accessLevel, $loginMode = 'AMAM')
    {
        $accessStatus = 'FAILED';
        switch ($loginMode) {
            case "AMAA":
                if (in_array($accessLevel, array('MEMBER', 'STUDENT', 'LOGINONLY', 'AMAQCOMMUNITY'))) {
                    $accessStatus = 'SUCCESS';
                }
                break;
            case "AMSA":
                if (in_array($accessLevel, array('AMSA'))) {
                    $accessStatus = 'SUCCESS';
                }
                break;
            default:
                if (in_array($accessLevel, array('MEMBER', 'LOGINONLY'))) {
                    $accessStatus = 'SUCCESS';
                }
                break;
        }
        return $accessStatus;
    }
    /**
     * This function takes a memberProfile object from Compass,
     * Turns it into a easy to ready Array
     * And checks StatusCode, PaidThruDate, MemberTypeCode to determine the access level for this account
     * Possible accessLevel:
     * NULL: No access to system, member should be denied login
     * LOGINONLY: Allow login but no member content access, access only to profile area to renew membership
     * MEMBER: Allow login and full access to member contents
     * STUDENT: Allow login and access to student member contents
     * AMSA: Allow login for AMSA website and access to AMSA content
     * @param object $profileResult
     * @param string $login
     * @return array
     */
    protected function processMemberProfile($profileResult, $login = NULL)
    {
        $retVal = array(
            'profile' => array(),
            'accessLevel' => '',
            'message' => '',
            'customerMessage' => ''
        );
        //$this->dr($profileResult);
        if (isset($profileResult) && is_object($profileResult)) {
            $profile = (array) $profileResult;
            if (!empty($login)) {
                $profile['Username'] = $login;
            } else {
                $profile['Username'] = $this->getUsernameFromiMISID($profile['iMISID']);
            }
            foreach($profile as $key=>$value) {
                if (is_object($value) && in_array($key, array('UDFields'))) {
                    $UDArray = $this->getKeyValuePairFromUDFields($value);
                    unset($profile[$key]);
                } else if (is_object($value) && in_array($key, array('AddressTab1','AddressTab2','AddressTab3'))) {
                    $profile[$key] = (array) $value;
                } else if (is_object($value) && in_array($key, array('Relationships'))) {
                    $profile['WorkRelationshipId'] = NULL;
                    if (isset($value->RelationshipCollection->Relationships->Relationship->TargetId)
                        && $value->RelationshipCollection->RelationshipCode == 'WORK') {
                        $profile['WorkRelationshipId'] = $value->RelationshipCollection->Relationships->Relationship->TargetId;
                    }
                } else if (is_object($value)) {
                    $profile[$key] = (array) $value;
                }
            }
            $profile = array_merge($profile, $UDArray);

            $eft = $this->getEFTDetailsForDues($profile['iMISID']);
            $profile['PaymentCycle'] = empty($eft)?"Annual":"Monthly";
            $retVal['profile'] = $profile;

            //Check member profile logic
            if ($profile['StatusCode'] != 'A') {
                //Status not active
                $retVal['message'] = 'Member status not active';
                return $retVal;
            } else if (in_array($profile['MemberTypeCode'], $this->amaMemberTypeCode)) {
                //For full AMA member, check PaidThru date
                $currentTime = time();
                $paidThruTime = strtotime($profile['PaidThruDate']);

                //$paidThruTime = mktime(0, 0, 0, 12, 31, 2017);
                //$currentTime = mktime(0, 0, 0, 3, 28, 2018);

                $timeDiff = $currentTime - $paidThruTime;
                //$this->dr($timeDiff);
                if ($profile['PaidThruDate'] == '0001-01-01T00:00:00') {
                    //This will allow member to immidiately login after join, before payment is settled.
                    //Credit card details provided should have been validated.
                    if ($profile['MemberHasPendingDuesPayments']) {
                        //TODO business decision needed if allow login
                        $retVal['accessLevel'] = 'MEMBER';
                        $retVal['message'] = 'Member has payment pending';
                    } else {
                        //TODO business decision here
                        $eft = $this->getEFTDetailsForDues($profile['iMISID']);
                        if ($eft) {
                            $retVal['accessLevel'] = 'MEMBER';
                            $retVal['message'] = 'Member has EFT setup';
                        } else {
                            $retVal['message'] = 'Invalid PaidThruDate';
                        }
                    }
                } else if ($timeDiff > SIXMONTHS) {
                    $retVal['message'] = 'Membership lapsed for more than 6 months';
                    $retVal['customerMessage'] = 'Your membership has lapsed, please Join as a new Member again';
                    return $retVal;
                } else if ($timeDiff > THREEMONTHS) {
                    //Membership lapsed between 3-6 month, will let member login, but access to service should be denied
                    $retVal['accessLevel'] = 'LOGINONLY';
                    $retVal['message'] = 'Membership lapsed for more than 3 months';
                    $retVal['customerMessage'] = 'Your membership has lapsed, access to service has been suspended. please <a href="/join-renew">renew your membership</a>';
                } else if ($timeDiff > ONEMONTH) {
                    $retVal['accessLevel'] = 'MEMBER';
                    $retVal['message'] = 'Membership lapsed for more than a month';
                    $retVal['customerMessage'] = 'Your membership has lapsed, please renew your membership now';
                } else {
                    //AMA member with valid paid thru date
                    $retVal['accessLevel'] = 'MEMBER';
                }
            } else if (in_array($profile['MemberTypeCode'], $this->staffTypeCode)) {
                $retVal['accessLevel'] = 'MEMBER';
            } else if (in_array($profile['MemberTypeCode'], $this->amaQNonMemberTypeCode)) {
                $retVal['accessLevel'] = 'AMAQCOMMUNITY';
            } else if (in_array($profile['MemberTypeCode'], $this->amaStudentTypeCode)) {
                //TODO business decision here if we want to check for student paid thru date
                // Not checking PaidThruDate for student due to legacy student record does not have paidThruDate,
                // however we may want to check this in the future
                $retVal['accessLevel'] = 'STUDENT';
            } else if (in_array($profile['MemberTypeCode'], $this->amsaMemberTypeCode)) {
                $retVal['accessLevel'] = 'AMSA';
            } else {
                $retVal['message'] = 'Unexpected member type code';
            }
        } else {
            //$this->dr($profileResult);
            $retVal['message'] = 'Unexpected response result';
        }
        return $retVal;
    }

    /**
     * Retrieve username for a iMIS ID
     * This is the standard method for getting username
     * @param string $iMISID
     * @return string $username
     */
    public function getUsernameFromiMISID($iMISID)
    {
        $response = $this->client->GetUsernameFromiMISID(array('iMISID' => $iMISID));
        return $response->GetUsernameFromiMISIDResult;
    }

    /**
     * Retrieve member's current EFT details
     * @param string $iMISID
     * @return array $eftDetails
     */
    public function getEFTDetailsForDues($iMISID)
    {
        $filters = array('ID'=>$iMISID);
        $response = $this->search('GetEFTDetailsForDues', $filters);
        //$this->dr($response);
        if (isset($response->KeyValuePairOfstringanyType)) {
            return $this->keyValuePairToArray((array)$response->KeyValuePairOfstringanyType);
        }
        return null;
    }

    /**
     * Process the key value pair data from Compass and put it into a simple array structure
     * @param object $UDFields from Compass
     * @return array a simple array that contains the key => value data
     */
    protected function getKeyValuePairFromUDFields($UDFields)
    {
        $array = $UDFields->UDField;
        $result = array();
        foreach ($array as $obj) {
            $result[$obj->Field] = $obj->Value;
        }
        return $result;
    }

    /**
     * Retrieve the available UD Fields schema
     * An option is available to also retrieve the validation table for every field and their available values
     * @param boolean $getValidationTable if we want the validation table details, default to FALSE as there are extra call to be made
     * @return array
     */
    public function getUDFieldsSchema($getValidationTable = FALSE)
    {
        $response = $this->client->GetDemographicSchema();
        //$this->dr($response);
        $schema = array();
        $tables = (array) $response->GetDemographicSchemaResult->Tables->KeyValueOfstringTableSchemarxDJnjdP;

        foreach($tables as $table) {
            //$tableKey = $table->Key;
            $tableName = $table->Value->Name;
            if (is_array($table->Value->Fields->KeyValueOfstringFieldSchemarxDJnjdP)) {
                foreach($table->Value->Fields->KeyValueOfstringFieldSchemarxDJnjdP as $field) {
                    $item = array(
                        'tableName' => $tableName,
                        'type' => $field->Value->DataType.' '.$field->Value->FieldLength,
                    );
                    if ($getValidationTable && !empty($field->Value->ValidationTable)) {
                        $validationTable = $this->getLookup($field->Value->ValidationTable);
                        $item['validation'] = $field->Value->ValidationTable;
                        $item['validationTable'] = $validationTable;
                    }

                    $schema[$field->Key] = $item;
                }
            } else {
                $field = $table->Value->Fields->KeyValueOfstringFieldSchemarxDJnjdP;
                $item = array(
                    'tableName' => $tableName,
                    'type' => $field->Value->DataType.' '.$field->Value->FieldLength,
                );
                if ($getValidationTable && !empty($field->Value->ValidationTable)) {
                    $validationTable = $this->getLookup($field->Value->ValidationTable);
                    $item['validation'] = $field->Value->ValidationTable;
                    $item['validationTable'] = $validationTable;
                }

                $schema[$field->Key] = $item;
            }

        }
        return $schema;
    }

    /**
     * Turning keyValuePair from Compass to Array
     * @param
     * @return
     */
    protected function keyValuePairToArray($keyValuePairs)
    {
        $retVal = array();
        if(isset($keyValuePairs[0])) {
            foreach($keyValuePairs as $item) {
                if (!empty($item->key) || !empty($item->value)) {
                    $retVal[$item->key] = $item->value;
                }
            }
        } else if (isset($keyValuePairs['key'])) {
            $retVal[$keyValuePairs['key']] = $keyValuePairs['value'];
        }
        return $retVal;
    }

    /**
     * Use iMIS membership web service to generate a random password that conforms to the current iMIS password complexity requirements
     */
    public function generatePassword()
    {
//        try {
//            $ms = $this->getMembershipWebServiceInstance();
//            $response = $ms->GeneratePassword();
//            $password = $response->GeneratePasswordResult;
//        } catch (\Exception $e) {
//            $password = null;
//        }
        $password = substr(str_shuffle(str_repeat($x='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTY1234567890', ceil(8/strlen($x)) )),1,6);
        $password .= rand(10,99);
        return $password;
    }

    /**
     * Validate a user using the Login cookie
     * Membership webservices are
     */
    public function validateUserWithToken($iMISID, $token)
    {
        $retVal = array(
            'status' => 'FAILED',
            'message' => ''
        );
        $username = null;
        try {
            $ms = $this->getMembershipWebServiceInstance();
            $ms->__setCookie('Login', $token);
            $response = $ms->GetUserName();
            if (isset($response->GetUserNameResult) && !empty($response->GetUserNameResult)) {
                $username = $response->GetUserNameResult;
                $usernameFromiMISID = $this->getUsernameFromiMISID($iMISID);
                if ($username == $usernameFromiMISID) {
                    $retVal['status'] = 'SUCCESS';
                } else {
                    $retVal['message'] = 'Username mismatch '.$username.' | '.$usernameFromiMISID;
                }
            } else {
                $retVal['message'] = 'Unable to validate token';
            }
            //TODO validate the token with membership web service
        } catch (\Exception $e) {
            $retVal['message'] = 'Exception: '.$e->getMessage();
        }
        return $retVal;
    }

    /**
     * Retrieve encrypted sso token from Compass
     */
    public function getSSOToken($iMISID)
    {
        $response = $this->client->GetToken(array('iMISID' => $iMISID));
        $token = array();
        if (isset($response->GetTokenResult)) {
            $token = (array) $response->GetTokenResult;
        }
        return $token;
    }

    /**
     * Validate sso token with Compass
     *
     */
    public function validateSSOToken($token)
    {
        $response = $this->client->ValidateToken(array('ssoToken' => $token));
        return $response->ValidateTokenResult;
    }

    public function isPasswordStrong($password, $minLength = PASSWORDMINLENGTH, $regex = PASSWORDREGEX, $weakPasswordMessage = WEAKPASSWORDMESSAGE)
    {
        $retVal = array('isValid' => TRUE, 'message' => '');
        if (strlen($password) < $minLength || !preg_match($regex, $password))
        {
            $retVal['isValid'] = FALSE;
            $retVal['message'] = $weakPasswordMessage;
        }
        return $retVal;
    }

    /**
     * Logout the user out of iMIS system.
     * Test reveals that this is actually not doing anything
     */
    public function logoutUser($iMISID, $token)
    {
        $ms = $this->getMembershipWebServiceInstance();
        $ms->__setCookie('Login', $token);
        $response = $ms->Logout();
        $this->dr($response);
        return $response;
    }

    /**
     * Initialise a Soap instance for the iMIS membership web service
     * This is to complement the Compass service, as it contains a method to fetch a SSO token for iMIS Rise
     */
    protected function getMembershipWebServiceInstance()
    {
        return new SoapClient($this->membershipWebServiceUrl);
    }

    /**
     * Dev methods below
     * Display response on screen for dev purpose
     */
    public function dr($obj) {
        if ($this->debug) {
            print(var_export($obj, true).PHP_EOL);
        }
    }

}

