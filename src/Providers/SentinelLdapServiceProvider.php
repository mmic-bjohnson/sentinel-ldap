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
		
		$this->mergeConfigFrom(
			base_path() . '/extensions/roshangautam/sentinel-ldap/src/config/config.php', 'roshangautam.sentinel-ldap'
		);
		
		$this->registerSentinel();
		
		//Bind the facade for the SentinelLdapManager class.
		
		$this->app->bind('MmicLdap', function()
		{
			return new \Mmic\SentinelLdap\Classes\SentinelLdapManager($this->app['sentinel']);
		});
		
		AliasLoader::getInstance()->alias(
			'MmicLdap',
			'Mmic\SentinelLdap\Facades\MmicLdap'
		);
		
		$this->app['config']->set('roshangautam.sentinel-ldap.search_password', $_ENV['LDAP_SEARCH_PASSWORD']);
		
		$this->commands('Mmic\SentinelLdap\Console\Commands\PopulateUsers');
		
		$this->app->singleton('LdapUtility', function($app)
		{
			return new LdapUtility;
		});
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
