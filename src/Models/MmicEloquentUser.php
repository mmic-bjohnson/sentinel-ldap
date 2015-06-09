<?php namespace Mmic\SentinelLdap\Models;


#use Illuminate\Database\Eloquent\Model;

use Cartalyst\Sentinel\Users\EloquentUser;

class MmicEloquentUser extends EloquentUser {

protected $loginNames = ['samAccountName'];

public function userDetails() {
	return $this->hasOne('Mmic\SentinelLdap\Models\UserDetails', 'sentinelId', 'id');
}

}
