<?php namespace Mmic\SentinelLdap\Models;


use Platform\Users\Models\User;

class MmicEloquentUser extends User {

protected $loginNames = ['samAccountName'];

public function userDetails() {
	return $this->hasOne('Mmic\SentinelLdap\Models\UserDetails', 'sentinelId', 'id');
}

}
