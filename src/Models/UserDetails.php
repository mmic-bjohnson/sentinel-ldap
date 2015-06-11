<?php namespace Mmic\SentinelLdap\Models;


use Illuminate\Database\Eloquent\Model;

use MmDb;

class UserDetails extends Model {

protected $fillable = [
	'guid',
	'samAccountName',
];

protected $primaryKey = 'sentinelId';

public $incrementing = false;

public function __construct()
{
	$this->table = MmDb::getDbPrefix() . 'user_details';
}

public function user()
{
	return $this->belongsTo('Mmic\SentinelLdap\Models\MmicEloquentUser', 'sentinelId', 'id');
}

}
