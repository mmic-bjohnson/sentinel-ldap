<?php

return [
	
	'disable_ldap_check' => env('DISABLE_LDAP_CHECK', false),
	'ldap_search_password' => env('LDAP_SEARCH_PASSWORD'),
	'user_model' => 'Mmic\Intranet\Models\User\User',
	'authentication_domain' => '@medicalmutual.com',
	'default_sentinel_role_slugs' => ['staff', 'ist-user'],

];
