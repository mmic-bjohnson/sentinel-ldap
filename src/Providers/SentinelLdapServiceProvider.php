<?php namespace Mmic\Sentinelldap\Providers;


use Illuminate\Support\ServiceProvider;

use App\Providers;

use App;

use Cartalyst\Sentinel\Sentinel;
use Cartalyst\Sentinel\Laravel\SentinelServiceProvider;

use Mmic\Sentinelldap\Classes\SentinelLdap;

class SentinelLdapServiceProvider extends SentinelServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register()
	{
		$this->mergeConfigFrom(
			base_path() . '/vendor/roshangautam/sentinel-ldap/src/config/config.php', 'roshangautam.sentinel-ldap'
		);
		
		//Bind the facade for the SentinelLdapManager class.
		
		App::bind('MmicLdap', function()
		{
			return new \Mmic\Sentinelldap\Classes\SentinelLdapManager(App::make('sentinel'));
		});
		
		$this->registerSentinel();
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
