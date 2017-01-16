<?php namespace Mmic\SentinelLdap\Models;


use Mmic\SentinelLdap\Models\UserDetailsBase;

class UserDetails extends UserDetailsBase {

public function user()
{
	return $this->belongsTo(config('mmic.sentinel-ldap.user_model', 'Cartalyst\Sentinel\Users\EloquentUser'), 'sentinelId', 'id');
}

}
