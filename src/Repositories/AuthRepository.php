<?php namespace Mmic\Users\Repositories;


use Platform\Users\Repositories\AuthRepository;

use Mmic\Sentinelldap\Classes\LdapException;

class MmicAuthRepository extends AuthRepository {

/**
 * {@inheritDoc}
 */
public function login(array $input)
{
	try
	{
		// Should the user be remembered?
		$remember = (bool) array_get($input, 'remember', false);
		
		// Get all the valid credentials columns
		$credentials = array_intersect_key($input, array_flip($this->getValidLoginColumns()));
		
		// Authenticate the user with the given credentials
		if ($user = $this->users->getSentinel()->authenticate($credentials, $remember))
		{
			// Fire the 'platform.user.logged_in'
			$this->fireEvent('platform.user.logged_in', $user);
			
			return [ null, $user ];
		}
		
		$errors = trans('platform/users::auth/message.user_not_found');
	}
	catch (Checkpoints\NotActivatedException $e)
	{
		$errors = trans('platform/users::auth/message.user_not_activated');
	}
	catch (Checkpoints\ThrottlingException $e)
	{
		$type = $e->getType();

		$delay = $e->getDelay();

		$errors = trans("platform/users::auth/message.throttling.{$type}", compact('delay'));
	}
	catch (LdapException $e)
	{
		$errors = $e->getMessage();
	}
	
	return [ $errors, null ];
}

}
