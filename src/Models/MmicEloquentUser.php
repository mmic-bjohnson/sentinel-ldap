<?php namespace Mmic\SentinelLdap\Models;


use Cartalyst\Attributes\EntityInterface;
use Platform\Users\Models\User;

class MmicEloquentUser extends User implements EntityInterface {

protected $loginNames = ['samAccountName'];

public function userDetails() {
	return $this->hasOne('Mmic\SentinelLdap\Models\UserDetails', 'sentinelId', 'id');
}

}
