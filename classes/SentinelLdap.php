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
			'login' => $credentials['email'],
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
			
			#$userInfo = MmicLdap::search($credentials['email']);
			
			#dd($userInfo);
			
			$user = $this->users->findByCredentials(['email' => $credentials['email']]);
			
			//If the user does not yet have a Sentinel account, create one.
			
			if (empty($user)) {
				$newUser = $this->create(
					[
						'email' => $credentials['email'],
						#'password' => $credentials['password'],
					]
				);
				
				$user = $this->users->findById($newUser->id);
				
				$activation = $this->activations->create($user);
				
				$wasActivated = $this->activations->complete($user, $activation->code);
				
				$role = $this->findRoleBySlug('staff');
				
				$role->users()->attach($user);
			}
			else {
				//If the user already has a Sentinel account, update the associated
				//password to match the value in Active Directory (otherwise,
				//Sentinel's built-in login mechanism will not work correctly).
				
				#$this->update($user, $credentials);
			}
			
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