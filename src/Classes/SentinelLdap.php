<?php namespace Mmic\SentinelLdap\Classes;

use Cartalyst\Sentinel\Sentinel;
use Cartalyst\Sentinel\Activation;

use Cartalyst\Sentinel\Activations\ActivationRepositoryInterface;
use Cartalyst\Sentinel\Persistences\PersistenceRepositoryInterface;
use Cartalyst\Sentinel\Roles\RoleRepositoryInterface;
use Cartalyst\Sentinel\Users\UserInterface;
use Cartalyst\Sentinel\Users\UserRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;

use App;
use Alert;
use Log;

use MmicLdap;

use Mmic\SentinelLdap\Classes\SentinelLdapManager;

class SentinelLdap extends Sentinel
{

public function __construct(
	PersistenceRepositoryInterface $persistences,
	UserRepositoryInterface $users,
	RoleRepositoryInterface $roles,
	ActivationRepositoryInterface $activations,
	Dispatcher $dispatcher,
	SentinelLdapManager $sentinelLdapManager
)
{
	parent::__construct(
		$persistences,
		$users,
		$roles,
		$activations,
		$dispatcher
	);
	
	$this->sentinelLdapManager = $sentinelLdapManager;
}

/**
 * {@inheritDoc}
 */
public function authenticate($credentials, $remember = false, $login = true)
{
	//TODO What happens when $credentials doesn't contain all of the required
	//information? We get undefined index errors, that's what!
	//This needs to be fixed. -CBJ 2016.03.22.
	
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
			
			if ($this->sentinelLdapManager->shouldSkipAuthRequirement()) {
				$credentials = Sentinel::findByCredentials(['samAccountName' => $credentials['username']]);
				
				//The username isn't valid (it doesn't exist in the database).
				
				if ($credentials === null) {
					return false;
				}
				
				$credentials = Sentinel::findById($credentials->id);
				
				if ($credentials === null) {
					return false;
				}
				
				$userId = $credentials->id;
			}
			else {
				$userId = $this->sentinelLdapManager->createOrUpdateSentinelUser($credentials['username']);
			}
			
			if ($userId !== false) {
				$user = $this->users->findById($userId);
				
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
			else {
				//This scenario can arise for a number of reasons, such as when
				//a required field (e.g., email) in LDAP has not been populated.
				
				return false;
			}
		}
	}
}

}
