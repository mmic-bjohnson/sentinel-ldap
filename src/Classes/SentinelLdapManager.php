<?php namespace Mmic\SentinelLdap\Classes;


use ErrorException;

use App;

use Sentinel;

use Roshangautam\Sentinel\Addons\Ldap\Manager;

use Mmic\SentinelLdap\Exceptions\LdapException;
use Mmic\SentinelLdap\Models\UserDetails;
use Mmic\SentinelLdap\Utility\LdapUtility;

class SentinelLdapManager extends Manager
{

function __construct(LdapUtility $ldapUtility)
{
	$this->config = config('roshangautam.sentinel-ldap');
	
	$this->ldapUtility = $ldapUtility;
}

/**
 * {@inheritDoc}
 */
public function authenticate($credentials, $remember = false)
{
	$config = config('roshangautam.sentinel-ldap');
	
	$extendedConfig = config('mmic.sentinel-ldap');
	
	if (!$this->configIsValid($config, $extendedConfig)) {
		throw new ErrorException('Required configuration parameters are missing or invalid');
		
		return false;
	}
	
	if ($conn = $this->connect($config['host'], $config['port'])) {
		
		//The default implementation checks the user's credentials and then logs
		//the user in automatically if the credentials are valid. Our use-case
		//requires only that the credentials are checked for validity; we handle
		//the actual login elsewhere (because we need to create a local account
		//for the user if one doesn't already exist).
		
		#$user = $this->sentinel->findByCredentials($credentials);
		
		ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
		
		try {
			$valid = ldap_bind($conn, $credentials['username'] . $extendedConfig['authentication_domain'], $credentials['password']);
			
			if ($valid) {
				#$this->login($user, $remember);
				$this->disconnect($conn);
				return true;
			}
		}
		catch (ErrorException $e) {
			$ldapErrorString = ldap_error($conn);
			$ldapErrorNumber = ldap_errno($conn);
			
			$extendedError = NULL;
			
			ldap_get_option($conn, $config['LDAP_OPT_DIAGNOSTIC_MESSAGE'], $extendedError);
			
			$this->disconnect($conn);
			
			throw new LdapException(App::make('log'), $ldapErrorString, $ldapErrorNumber, $e, $extendedError);
		}
		
	}
	else {
		throw new ErrorException('Could not connect to LDAP server at "' . $config['host'] . '" on port "' . $config['port'] . '" (no further information is available)');
	}
	
	return false;
}

/**
 * Perform basic checks to ensure that the configuration files have been published.
 * @param array $config The base configuration values from Roshan Gautam's library.
 * @param array $extendedConfig The extended configuration values from MMIC's library.
 * @return bool true if the configuration is usable, false if not.
 */
public function configIsValid($config, $extendedConfig)
{
	//Host is the only parameter that is truly required in all scenarios.
	//But we also want to alert the user if other directives have not been set
	//explicitly (even if only to empty strings or null values).
	
	if (!empty($config) && !empty($config['host']) && !empty($extendedConfig)) {
		return true;
	}
	
	return false;
}

public function createSentinelUser($ldapEntry)
{
	$newUser = Sentinel::create(
		[
			'email' => $ldapEntry['mail'],
			'first_name' => $ldapEntry['givenname'],
			'last_name' => $ldapEntry['sn'],
			//Custom values that Sentinel does not define.
			'guid' => $this->guidToString($ldapEntry['objectguid']),
			'samAccountName' => $ldapEntry['samaccountname'],
		]
	);
	
	$user = Sentinel::findById($newUser->id);
	
	//Activate the user automatically.
	
	$activation = Activation::create($user);
	
	$wasActivated = Activation::complete($user, $activation->code);
	
	//Assign the user to a conservative role (additional roles and/or
	//permissions can be granted later, as needed).
	
	$role = Sentinel::findRoleBySlug('staff');
	
	$role->users()->attach($user);
	
	return $newUser->id;
}

public function updateSentinelUser($credentials, $ldapEntry)
{
	$credentialsNew = [
		'email' => $ldapEntry['mail'],
		'first_name' => $ldapEntry['givenname'],
		'last_name' => $ldapEntry['sn'],
	];
	
	$user = Sentinel::update($credentials, $credentialsNew);
	
	//TODO The samAccountName value lives in a different DB table, so to update
	//it, we either need to create a relationship via the model, or we need to
	//update a separate model instance (in which case it would be nice if the
	//write operations were performed within a single transaction).
	//
	//-CBJ 2015.07.27.
	
	//Custom values that Sentinel does not define.
	#'samAccountName' => $ldapEntry['samaccountname'],
	
	return $user->id;
}

public function createOrUpdateSentinelUser($username)
{
	//Lookup the user's account in LDAP.
	
	$ldapEntry = $this->ldapUtility->lookupUserDetails($username);
	
	//Each $entry is keyed by its DN (Distinguished Name), which isn't
	//very helpful. A simple reset() will get us to the data (there
	//is only one item in the array).
	
	$ldapEntry = reset($ldapEntry);
	
	//Ensure that values for surname, given name (first name), SAM account name
	//Active Directory GUID, and email are present (all are required in this context).
	
	if (!empty($ldapEntry['sn']) && !empty($ldapEntry['givenname']) && !empty($ldapEntry['samaccountname']) && !empty($ldapEntry['objectguid']) && !empty($ldapEntry['mail'])) {
		//If the user does not yet have a Sentinel account, in which case this
		//call will return an empty value, create an account.
		
		$credentials = Sentinel::findByCredentials(['samAccountName' => $username]);
		
		//But, before we do that, we need to check to see if this GUID
		//already exists. If it does, it means that this individual's
		//username was changed in LDAP, in which case it must be updated here.
		//(Marriage is the most common scenario under which this would occur.)
		
		$userDetails = (new UserDetails)->where('guid', $this->ldapUtility->guidToString($ldapEntry['objectguid']))->first();
		
		if (empty($credentials) && empty($userDetails)) {
			
			//This user does not have a Sentinal account.
			
			return $this->createSentinelUser($ldapEntry);
		}
		else {
			//The user already has an account; update it with the latest LDAP
			//information.
			
			$credentials = Sentinel::findById($userDetails->sentinelId);
			
			return $this->updateSentinelUser($credentials, $ldapEntry);
		}
	}
	else {
		return false;
	}
}

}
