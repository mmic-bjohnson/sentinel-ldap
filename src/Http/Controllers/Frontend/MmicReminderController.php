<?php namespace Mmic\SentinelLdap\Http\Controllers\Frontend;


use Platform\Users\Controllers\Frontend\ReminderController;

class MmicReminderController extends ReminderController {

/**
* {@inheritDoc}
*/

public function index()
{
	return view('mmic/sentinel-ldap::auth/password_reminder', compact('connections'));
}

}
