<?php

return [
	
	'disable_ldap_check' => env('DISABLE_LDAP_CHECK', false),
	'user_model' => 'Mmic\Intranet\Models\User\User',
	'authentication_domain' => '@medicalmutual.com',
	'default_sentinel_role_slugs' => ['staff', 'ist-user'],

];
