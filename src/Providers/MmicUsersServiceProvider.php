<?php namespace Mmic\SentinelLdap\Providers;


use Platform\Users\Providers\UsersServiceProvider;

class MmicUsersServiceProvider extends UsersServiceProvider
{

public function register()
{
	parent::register();
	
	//Override the default authentication repository binding (we require
	//custom logic in ours).
	
	$this->app->bind('platform.users.auth', 'Mmic\Users\Repositories\MmicAuthRepository');
}

}
