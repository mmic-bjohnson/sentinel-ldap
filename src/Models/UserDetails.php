<?php namespace Mmic\SentinelLdap\Models;


use Illuminate\Database\Eloquent\Model;

class UserDetails extends Model {

protected $table = 'user_details';

protected $fillable = [
	'guid',
	'samAccountName',
];

protected $primaryKey = 'sentinelId';

public $incrementing = false;

public function user()
{
	return $this->belongsTo('Mmic\SentinelLdap\Models\MmicEloquentUser', 'sentinelId', 'id');
}

}
