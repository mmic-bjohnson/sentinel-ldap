<?php namespace Mmic\SentinelLdap\Models;


use Mmic\SentinelLdap\Models\UserDetailsBase;

class UserDetails extends UserDetailsBase {

public function user()
{
	return $this->belongsTo('Mmic\SentinelLdap\Models\MmicEloquentUser', 'sentinelId', 'id');
}

}
