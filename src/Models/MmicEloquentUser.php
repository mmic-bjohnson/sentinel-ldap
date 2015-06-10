<?php namespace Mmic\SentinelLdap\Models;


use Cartalyst\Sentinel\Users\EloquentUser;
use Cartalyst\Sentinel\Users\UserInterface;

class MmicEloquentUser extends EloquentUser {

protected $loginNames = ['samAccountName'];

public function userDetails() {
	return $this->hasOne('Mmic\SentinelLdap\Models\UserDetails', 'sentinelId', 'id');
}

}
