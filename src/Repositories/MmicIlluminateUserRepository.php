<?php namespace Mmic\SentinelLdap\Repositories;


use Cartalyst\Sentinel\Users\IlluminateUserRepository;

class MmicIlluminateUserRepository extends IlluminateUserRepository
{

/**
 * {@inheritDoc}
 */
public function findByCredentials(array $credentials)
{
	//Just so we know when this is finally being called...
	
	dd($credentials);
	
	$instance = $this->createModel();
	
	$loginNames = $instance->getLoginNames();
	
	$query = $instance->newQuery();
	
	list($logins, $password, $credentials) = $this->parseCredentials($credentials, $loginNames);
	
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

}
