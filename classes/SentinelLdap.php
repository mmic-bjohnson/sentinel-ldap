<?php namespace Mmic\SentinelLdap\Classes;

use Cartalyst\Sentinel\Sentinel;
use Cartalyst\Sentinel\Activation;

use App;
use Alert;
use Log;

use MmicLdap;

class SentinelLdap extends Sentinel
{

/**
 * {@inheritDoc}
 */
public function authenticate($credentials, $remember = false, $login = true)
{
	$response = $this->fireEvent('sentinel.authenticating', $credentials, true);
	
	if ($response === false) {
		return false;
	}
	
	if ($credentials instanceof UserInterface) {
		$user = $credentials;
	}
	else {
		//Check if this username even exists (this is required to be able to
		//utilize checkpoints and throttling).
		
		$user = $this->users->findByCredentials([
			'login' => $credentials['username'],
		]);
		
		//The default implementation only checks that the supplied password
		//matches the hash obtained from the Sentinel-related DB tables.
		//
		//Given our need to support Active-Directory-flavored LDAP, we need to
		//submit the credentials to AD, over the network, and check the response.
		
		try {
			$valid = MmicLdap::authenticate($credentials);
		}
		catch (LdapException $e) {
			$valid = false;
		}
		
		if ($valid !== true) {
			//We don't want to trigger a checkpoint failure if the LDAP server is
			//unreachable (we only want to trigger a failure when the credentials
			//are invalid).
			
			if ($e->getCode() !== -1) {
				$this->cycleCheckpoints('fail', $user, false);
			}
			
			throw $e;
		}
		else {
			//The credentials are valid.
			
			$userId = $this->ldapUtility->createOrUpdateSentinelUser($credentials['username']);
			
			$user = $this->users->findByCredentials(['id' => $userId]);
			
			try {
				if (!$this->login($user, true)) {
					return false;
				}
			}
			catch (LdapException $e) {
				throw $e;
			}
			
			$this->fireEvent('sentinel.authenticated', $user);
			
			return $this->user = $user;
		}
	}
}

}
