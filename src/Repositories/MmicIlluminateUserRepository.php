<?php namespace Mmic\SentinelLdap\Repositories;


use Cartalyst\Sentinel\Users\IlluminateUserRepository;
use Cartalyst\Sentinel\Users\UserInterface;

use Mmic\SentinelLdap\Models\UserDetails;

class MmicIlluminateUserRepository extends IlluminateUserRepository
{

/**
 * {@inheritDoc}
 */
public function findByCredentials(array $credentials)
{
	$instance = $this->createModel();
	
	$loginNames = $instance->getLoginNames();
	
	$query = $instance->newQuery();
	
	list($logins, $password, $credentials) = $this->parseCredentials($credentials, $loginNames);
	
	//This JOIN is the only customization that I made to get Sentinel to
	//"play nice" with our "authentication with LDAP by username" approach,
	//in which all non-Sentinel-specific data is stored in a separate table.
	//
	//-CBJ 2015.06.10.
	
	$table = with(new UserDetails)->getTable();
	
	$query
		->join($table, 'users.id', '=', $table . '.sentinelId')
		->select('users.*', $table . '.guid', $table . '.samAccountName');
	
	if (is_array($logins)) {
		foreach ($logins as $key => $value) {
			$query->where($key, $value);
		}
	} else {
		$query->whereNested(function ($query) use ($loginNames, $logins) {
			foreach ($loginNames as $name) {
				$query->orWhere($name, $logins);
			}
		});
	}
	
	return $query->first();
}

/**
 * {@inheritDoc}
 */
public function create(array $credentials, Closure $callback = null)
{
	$user = $this->createModel();
	
	$this->fireEvent('sentinel.user.creating', compact('user', 'credentials'));
	
	$this->fill($user, $credentials);
	
	if ($callback) {
		$result = $callback($user);
		
		if ($result === false) {
			return false;
		}
	}
	
	$user->save();
	
	//This next fill-and-save operation is the only customization to this method;
	//it is necessary to save the data that pertains to the user detail model
	//separately (I couldn't find any way to save to both models atomically).
	//
	//-CBJ 2015.06.10.
	
	$userDetails = new UserDetails;
	
	$userDetails->fill($credentials);
	
	$userDetails = $user->userDetails()->save($userDetails);
	
	$this->fireEvent('sentinel.user.created', compact('user', 'credentials'));
	
	return $user;
}

/**
 * {@inheritDoc}
 */
public function update($user, array $credentials)
{
	if (! $user instanceof UserInterface) {
		$user = $this->findById($user);
	}
	
	$this->fireEvent('sentinel.user.updating', compact('user', 'credentials'));
	
	$this->fill($user, $credentials);
	
	$user->save();
	
	//This next fill-and-save operation is the only customization to this method;
	//it is necessary to save the data that pertains to the user detail model
	//separately (I couldn't find any way to save to both models atomically).
	//
	//-CBJ 2015.06.10.
	
	$userDetails = (new UserDetails)->find($user->id);
	
	$userDetails->fill($credentials);
	
	$userDetails->save();
	
	$this->fireEvent('sentinel.user.updated', compact('user', 'credentials'));
	
	return $user;
}

}
