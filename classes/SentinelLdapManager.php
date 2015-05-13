<?php namespace Mmic\SentinelLdap\Classes;


use ErrorException;

use Roshangautam\Sentinel\Addons\Ldap\Manager;

use App;
use Log;

class SentinelLdapManager extends Manager
{

/**
 * {@inheritDoc}
 */
public function authenticate($credentials, $remember = false)
{
	$config = config('roshangautam.sentinel-ldap');
	
	if ($conn = $this->connect($config['host'], $config['port'])) {
		
		//The default implementation checks the user's credentials and then logs
		//the user in automatically if the credentials are valid. Our use-case
		//requires only that the credentials are checked for validity; we handle
		//the actual login elsewhere.
		
		#$user = $this->sentinel->findByCredentials($credentials);
		
		ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
		
		try {
			$valid = ldap_bind($conn, $credentials['email'], $credentials['password']);
			
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
	
	return false;
}

}
