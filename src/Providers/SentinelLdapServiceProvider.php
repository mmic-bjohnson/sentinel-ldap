<?php namespace Mmic\SentinelLdap\Providers;


use Illuminate\Foundation\AliasLoader;

use Cartalyst\Sentinel\Laravel\SentinelServiceProvider;

use Mmic\SentinelLdap\Classes\SentinelLdap;
use Mmic\SentinelLdap\Utility\LdapUtility;
use Mmic\SentinelLdap\Classes\SentinelLdapManager;

class SentinelLdapServiceProvider extends SentinelServiceProvider
{

/**
 * {@inheritDoc}
 */
public function boot()
{
	//Traditionally, all of this is done within the register() method, but
	//when extending classes (e.g., "class SentinalLdap extends Sentinel {"),
	//it is necessary to move this logic into the boot method. Absent this
	//measure, the overrides are effective even when this extension is
	//disabled or uninstalled (because the register() method is called
	//no matter what, whereas boot() is called only for active extensions).
	
	
	
	//Handle configuration requirements.
	
	$this->publishes([
		realpath(__DIR__.'/../config/config.php') => config_path('mmic.sentinel-ldap.php'),
	]);
	
	$this->mergeConfigFrom(
		base_path() . '/extensions/roshangautam/sentinel-ldap/src/config/config.php', 'roshangautam.sentinel-ldap'
	);
	
	//This is a sensitive, environment-specific credential.
	
	$this->app['config']->set('roshangautam.sentinel-ldap.search_password', $_ENV['LDAP_SEARCH_PASSWORD']);
	
	
	
	//Call our ever-so-slightly modified version of this function, in order
	//to be able to use our SentinelLdap class, which extends the Sentinel
	//class.
	
	$this->registerSentinel();
	
	
	
	//Bind the facade for our SentinelLdapManager class (which extends Roshan
	//Gautam's Sentinel LDAP class). We then access all of his (and our) methods
	//using the "MmicLdap" facade, instead of his "Ldap" facade.
	
	$this->app->bind('MmicLdap', function()
	{
		return new \Mmic\SentinelLdap\Classes\SentinelLdapManager(
			$this->app->make('Mmic\SentinelLdap\Utility\LdapUtility'),
			$this->app->make('Mmic\SentinelLdap\Models\UserDetailsBase'),
			$this->app->make('Platform\Users\Models\User')
		);
	});
	
	AliasLoader::getInstance()->alias(
		'MmicLdap',
		'Mmic\SentinelLdap\Facades\MmicLdap'
	);
	
	
	
	//Register a utility class that our LDAP-related classes may leverage.
	
	$this->app->singleton('LdapUtility', function($app)
	{
		return new LdapUtility;
	});
	
	
	
	//Replace Sentinel's included user model with our custom model, which is
	//required to be able to store user data across more than one DB table.
	//(Thanks for this, Suhayb Wardany, over at Cartalyst!)
	
	//Get the user model.
	
	$usersModel = get_class($this->app['Mmic\SentinelLdap\Models\MmicEloquentUser']);
	
	//Set our models within Sentinel.
	
	$this->app['sentinel.users']->setModel($usersModel);
	$this->app['sentinel.persistence']->setUsersModel($usersModel);
}

public function register()
{
	//XXX TODO
	
	//When the parent method is called, whether via parent::register() or
	//by not defining this empty override method, our custom classes are
	//loaded, even if the extension is disabled or uninstalled!
	//I'd love to know why!
	//
	//-CBJ 2015.05.15
	
	#parent::register();
}

/**
 * {@inheritDoc}
 */
protected function registerSentinel()
{
	//Suhayb Wardany at Cartalyst (@suwardany on GitHub) concoted this whole
	//block when I asked him how to replace the Sentinel user model with a
	//custom model (required for user info to span multiple tables). I don't
	//yet fully understand how this works, but, hey, it works. :)
	//
	//-CBJ 2015.06.10.
	
	$this->app['sentinel.users'] = $this->app->share(function ($app) {
		$config = $app['config']->get('cartalyst.sentinel');
		
		$users        = array_get($config, 'users.model');
		$roles        = array_get($config, 'roles.model');
		$persistences = array_get($config, 'persistences.model');
		$permissions  = array_get($config, 'permissions.class');
		
		if (class_exists($roles) && method_exists($roles, 'setUsersModel')) {
			forward_static_call_array([$roles, 'setUsersModel'], [$users]);
		}
		
		if (class_exists($persistences) && method_exists($persistences, 'setUsersModel')) {
			forward_static_call_array([$persistences, 'setUsersModel'], [$users]);
		}
		
		if (class_exists($users) && method_exists($users, 'setPermissionsClass')) {
			forward_static_call_array([$users, 'setPermissionsClass'], [$permissions]);
		}
		
		return new \Mmic\SentinelLdap\Repositories\MmicIlluminateUserRepository($app['sentinel.hasher'], $app['events'], $users);
	});
	
	$this->app['sentinel'] = $this->app->share(function ($app) {
		
		// This is the only line that I changed from the stock implementation.
		// I simply changed the class reference from "Sentinel" to "SentinelLdap".
		// -CBJ 2015.04.28.
		
		$sentinel = new SentinelLdap(
			$app['sentinel.persistence'],
			$app['sentinel.users'],
			$app['sentinel.roles'],
			$app['sentinel.activations'],
			$app['events'],
			$app->make('Mmic\SentinelLdap\Classes\SentinelLdapManager')
		);
		if (isset($app['sentinel.checkpoints'])) {
			foreach ($app['sentinel.checkpoints'] as $key => $checkpoint) {
				$sentinel->addCheckpoint($key, $checkpoint);
			}
		}
		$sentinel->setActivationRepository($app['sentinel.activations']);
		$sentinel->setReminderRepository($app['sentinel.reminders']);
		$sentinel->setRequestCredentials(function () use ($app) {
			$request = $app['request'];
			$login = $request->getUser();
			$password = $request->getPassword();
			if ($login === null && $password === null) {
				return;
			}
			return compact('login', 'password');
		});
		$sentinel->creatingBasicResponse(function () {
			$headers = ['WWW-Authenticate' => 'Basic'];
			return new Response('Invalid credentials.', 401, $headers);
		});
		return $sentinel;
	});
	$this->app->alias('sentinel', 'Cartalyst\Sentinel\Sentinel');
}

}
