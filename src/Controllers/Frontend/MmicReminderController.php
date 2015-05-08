<?php namespace Mmic\Sentinelldap\Controllers\Frontend;


use Platform\Users\Controllers\Frontend\ReminderController;

class MmicReminderController extends ReminderController {

/**
* {@inheritDoc}
*/

public function index()
{
	return view('mmic/sentinelldap::auth/password_reminder', compact('connections'));
}

}
