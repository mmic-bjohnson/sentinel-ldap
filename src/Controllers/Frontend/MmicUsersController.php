<?php namespace Mmic\SentinelLdap\Controllers\Frontend;


use Platform\Users\Controllers\Frontend\UsersController;

class MmicUsersController extends UsersController {

/**
* {@inheritDoc}
*/

public function login()
{
	$connections = $this->users->auth()->getSocialConnections();

	return view('mmic/sentinelldap::auth/login', compact('connections'));
}

}
