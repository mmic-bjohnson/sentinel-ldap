<?php namespace Mmic\SentinelLdap\Models;


use Illuminate\Database\Eloquent\Model;

use MmDb;

class UserDetailsBase extends Model {

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

}
