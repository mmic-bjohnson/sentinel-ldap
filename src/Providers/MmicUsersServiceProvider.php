<?php namespace Mmic\SentinelLdap\Providers;


use Platform\Users\Providers\UsersServiceProvider;

class MmicUsersServiceProvider extends UsersServiceProvider
{

public function boot()
{
	parent::boot();
	
	
	
	//Override the default authentication repository binding (we require custom
	//logic in ours).
	
	$this->app->bind('platform.users.auth', 'Mmic\SentinelLdap\Repositories\MmicAuthRepository');
	
	
	
	//Change the login column from the default (email) to username.
	
	$this->app['config']->set('platform-users.login_columns', ['username', 'password']);
}

public function register()
{
	
}

}
