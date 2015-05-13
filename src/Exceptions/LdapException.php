<?php namespace Mmic\SentinelLdap\Classes;

use \Exception;
use Log;

class LdapException extends Exception
{

//These are standardized LDAP error constants.

const SERVER_UNAVAILABLE = 'The LDAP server could not be reached, thereby preventing login (the server may be down temporarily). The Web Projects team has been notified and will investigate the failure as soon as possible.';
const ERROR_LOGON_FAILURE = 'The credentials are invalid. Please try again.';
const ERROR_NO_SUCH_USER = 'The credentials are invalid. Please try again.';
const ERROR_INVALID_LOGON_HOURS = 'An account login time restriction is preventing successful login. Please contact your system administrator if you believe this to be in error.';
const ERROR_INVALID_WORKSTATION = 'You are not allowed to log onto this computer. Please contact your system administrator if you believe this to be in error.';
const ERROR_PASSWORD_EXPIRED = 'Your password has expired. Please change it by pressing Ctrl + Alt + Delete on your workstation, and then try again.';
const ERROR_ACCOUNT_DISABLED = 'You cannot logon because your account is disabled. Please contact your system administrator if you believe this to be in error.';
const ERROR_ACCOUNT_EXPIRED = 'You cannot logon because your account has expired. Please contact your system administrator if you believe this to be in error.';
const ERROR_PASSWORD_MUST_CHANGE = 'You cannot logon because you have never changed your password. Please change it by pressing Ctrl + Alt + Delete on your workstation, and then try again.';
const ERROR_ACCOUNT_LOCKED_OUT = 'You cannot logon because your account is locked. Please contact your system administrator if you believe this to be in error.';

//These are our own custom LDAP error constants.

const ERROR_UNKNOWN = 'An unknown error occurred. The Web Projects team has been notified and will investigate the failure as soon as possible.';

protected $messageDetail;

protected $bindErrorMappings = [
	'525' => 'ERROR_NO_SUCH_USER',
	'52e' => 'ERROR_LOGON_FAILURE',
	'530' => 'ERROR_INVALID_LOGON_HOURS',
	'531' => 'ERROR_INVALID_WORKSTATION',
	'532' => 'ERROR_PASSWORD_EXPIRED',
	'533' => 'ERROR_ACCOUNT_DISABLED',
	'701' => 'ERROR_ACCOUNT_EXPIRED',
	'773' => 'ERROR_PASSWORD_MUST_CHANGE',
	'775' => 'ERROR_ACCOUNT_LOCKED_OUT',
];

protected $bindErrorMessage;
protected $bindErrorCode;
protected $bindErrorCodeConstant;

/**
 * 
 * @param string $message
 * @param int $code
 * @param string $previousException
 * @param string $messageDetail
 * @link http://php.net/manual/en/function.ldap-errno.php#20665
 * @link http://www-01.ibm.com/support/docview.wss?uid=swg21290631
 */
public function __construct(\Illuminate\Log\Writer $log, $message, $code, $previousException = NULL, $messageDetail = NULL)
{
	parent::__construct($message, $code, $previousException);
	
	$this->setMessageDetail($messageDetail);
	
	$this->setErrorProperties();
	
	//Log an alert if the failure is sufficiently critical.
	
	$this->logErrorIfNecessary();
}

/**
 * @return array in which keys represent LDAP sub-error-codes and values
 * represent class constants to which the corresponding error strings are
 * assigned.
 */
protected function getBindErrorMappings()
{
	return $this->bindErrorMappings;
}

/**
 * Attempt to discern more detailed error code information than the default PHP
 * implementation provides.
 * @return void
 */
protected function parseBindErrorMessage()
{
	$mappings = $this->getBindErrorMappings();
	
	foreach ($mappings as $c => $m) {
		
		//We prefix the needle with a string that should always be present
		//to reduce the likelihood of false-positive matches occurring
		//elsewhere in the error string.
		
		if (stristr($this->getMessageDetail(), 'data ' . $c)) {
			$this->bindErrorCode = $c;
			$this->bindErrorCodeConstant = $mappings[$c];
			$this->bindErrorMessage = constant("self::$this->bindErrorCodeConstant");
		}
	}
}

public function getMessageDetail()
{
	return $this->messageDetail;
}

protected function setMessageDetail($messageDetail)
{
	$this->messageDetail = $messageDetail;
}

/**
 * Set object's key properties.
 * @return void
 */
protected function setErrorProperties()
{
	if ($this->getCode() === -1) {
		
		//Use a friendlier, more meaningful message for this particular error.
		
		$this->bindErrorCodeConstant = 'SERVER_UNAVAILABLE';
	}
	elseif ($this->getCode() === 49) {
		
		//Errors in this class are by far the most common.
		
		$this->parseBindErrorMessage();
	}
	else {
		//Errors in this class are so plentiful that it's impractical to handle
		//them all in this context.
		
		$this->bindErrorCodeConstant = 'ERROR_UNKNOWN';
	}
	
	if (!isset($this->bindErrorCodeConstant)) {
		$this->bindErrorCodeConstant = 'ERROR_UNKNOWN';
	}
	
	//Somewhat of an unusual syntax; this simply accesses self's constants
	//using dynamic input.
	
	$this->message = constant("self::$this->bindErrorCodeConstant");
}

public function logErrorIfNecessary()
{
	if ($this->getCode() === -1 || $this->getCode() !== 49) {
		
		//This condition is considered noteworthy and somebody should perhaps be
		//notified so that appropriate action may be taken.
		
		if (!empty($this->getMessageDetail())) {
			$this->message .= ' (' . $this->getMessageDetail() . ')';
		}
		
		Log::alert($this->getMessage() . '[errno ' . $this->getCode() . ']', ['context' => 'User login attempt']);
	}
}

public function getBindErrorCodeConstant()
{
	return $this->bindErrorCodeConstant;
}

}
