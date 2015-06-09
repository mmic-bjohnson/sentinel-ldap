<?php namespace Mmic\SentinelLdap\Models;


use Illuminate\Database\Eloquent\Model;

class UserDetails extends Model {

protected $table = 'user_details';
protected $guarded = ['*'];
protected $primaryKey = 'sentinelId';
public $incrementing = false;

}
