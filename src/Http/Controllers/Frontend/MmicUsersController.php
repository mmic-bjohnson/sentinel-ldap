<?php namespace Mmic\SentinelLdap\Http\Controllers\Frontend;


use Platform\Users\Controllers\Frontend\UsersController;
use Platform\Users\Repositories\UserRepositoryInterface;

class MmicUsersController extends UsersController {

/**
* {@inheritDoc}
*/
public function __construct(UserRepositoryInterface $users)
{
	parent::__construct($users);
}

/**
* {@inheritDoc}
*/
public function login()
{
	$connections = $this->users->auth()->getSocialConnections();

	return view('mmic/sentinel-ldap::auth/login', compact('connections'));
}

}
