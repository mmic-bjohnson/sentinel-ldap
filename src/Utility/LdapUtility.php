<?php namespace Mmic\SentinelLdap\Utility;


use \App;

use \Sentinel;
use \Activation;

class LdapUtility
{

protected $config;
protected $ldapCert = '/usr/share/ca-certificates/medicalmutual-MMDC1-CA.crt';

function __construct()
{
	$this->config = config('roshangautam.sentinel-ldap');
	
	$this->applyLdapTlsCert();
	
	$this->usernameFile = public_path() . DIRECTORY_SEPARATOR . 'users.txt';
}

public function getLdapTlsCert()
{
	return $this->ldapCert;
}

public function applyLdapTlsCert()
{
	putenv('LDAPTLS_CACERT=' . $this->getLdapTlsCert());
}

/**
 * A recursive function that cleans-up ldap_get_entries() output so that it
 * matches the input that ldap_add() expects.
 * @param array $entry The result of a call to ldap_get_entries().
 * @return array The cleaned-up entry (or entries).
 * @link http://php.net/manual/en/function.ldap-get-entries.php#89508
 */
public function cleanUpEntry($entry) {
	$retEntry = array();
	
	for ($i = 0; $i < $entry['count']; $i ++) {
		if (is_array($entry[$i])) {
			$subtree = $entry[$i];
			
			//This condition should be superfluous so just take the recursive call
			//adapted to your situation in order to increase perf.
			
			if (!empty($subtree['dn']) and !isset($retEntry[$subtree['dn']])) {
				$retEntry[$subtree['dn']] = $this->cleanUpEntry($subtree);
			} else {
				$retEntry[] = $this->cleanUpEntry($subtree);
			}
		} else {
			$attribute = $entry[$i];
			
			if ($entry[$attribute]['count'] == 1) {
				$retEntry[$attribute] = $entry[$attribute][0];
			} else {
				for ($j = 0; $j < $entry[$attribute]['count']; $j++) {
					$retEntry[$attribute][] = $entry[$attribute][$j];
				}
			}
		}
	}
	
	return $retEntry;
}

public function createSentinelUser($email, $nameFirst, $nameLast)
{
	$user = Sentinel::findByCredentials(['email' => $email]);
	
	if (empty($user)) {
		//If the user does not yet have a Sentinel account, create one.
		
		$newUser = Sentinel::create(
			[
				'email' => $email,
				'first_name' => $nameFirst,
				'last_name' => $nameLast,
			]
		);
		
		$user = Sentinel::findById($newUser->id);
		
		//Activate the user automatically.
		
		$activation = Activation::create($user);
		
		$wasActivated = Activation::complete($user, $activation->code);
		
		//Assign the user to a conservative role (additional roles and/or
		//permissions can be granted later, as needed).
		
		$role = Sentinel::findRoleBySlug('staff');
		
		$role->users()->attach($user);
	}
	else {
		//If the user already has an account, update it with the latest LDAP
		//information.
		
		$credentials = [
			'first_name' => $nameFirst,
			'last_name' => $nameLast,
		];
		
		$user = Sentinel::update($user, $credentials);
	}
}

public function getConfig()
{
	return $this->config;
}

}
