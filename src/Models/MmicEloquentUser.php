<?php namespace Mmic\SentinelLdap\Models;


use Illuminate\Database\Eloquent\SoftDeletes;

use Platform\Users\Models\User;

class MmicEloquentUser extends User {

use SoftDeletes;

protected $loginNames = ['samAccountName'];

public function userDetails() {
	return $this->hasOne('Mmic\SentinelLdap\Models\UserDetails', 'sentinelId', 'id');
}

}
