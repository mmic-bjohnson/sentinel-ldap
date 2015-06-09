<?php namespace Mmic\SentinelLdap\Providers;


use Illuminate\Foundation\AliasLoader;

use Cartalyst\Sentinel\Laravel\SentinelServiceProvider;

use Mmic\SentinelLdap\Classes\SentinelLdap;

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
		return new \Mmic\SentinelLdap\Classes\SentinelLdapManager($this->app['sentinel']);
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
	
	
	
	//Register any console commands.
	
	$this->commands('Mmic\SentinelLdap\Console\Commands\PopulateUsers');
	
	
	
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
	$this->app['sentinel'] = $this->app->share(function ($app) {
		
		// This is the only line that I changed from the stock implementation.
		// I simply changed the class reference from "Sentinel" to "SentinelLdap".
		// -CBJ 2015.04.28.
		
		$sentinel = new SentinelLdap(
			$app['sentinel.persistence'],
			$app['sentinel.users'],
			$app['sentinel.roles'],
			$app['sentinel.activations'],
			$app['events']
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
