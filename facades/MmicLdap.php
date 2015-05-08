<?php namespace Mmic\Sentinelldap\Facades;


use Illuminate\Support\Facades\Facade;

class MmicLdap extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor()
	{
		return 'MmicLdap';
	}

}
