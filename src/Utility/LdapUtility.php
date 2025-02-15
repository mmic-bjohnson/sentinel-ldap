<?php namespace Mmic\SentinelLdap\Utility;


use App;

class LdapUtility
{

protected $config;
protected $ldapCert = '/usr/share/ca-certificates/medicalmutual-MMDC1-CA.crt';
protected $ldapConnection;

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

public function getConfig()
{
	return $this->config;
}

/**
 * Converts the binary GUID that Active Directory LDAP implementations return
 * into a decoded string, e.g., 3f79048f-42cd-4c77-8426-835cd9f8a3ad.
 * @param string $binary_guid
 * @return string The decoded GUID string.
 * @link http://php.net/manual/en/function.ldap-get-values-len.php#111899
 */
public function guidToString($binary_guid)
{
	$hex_guid = unpack("H*hex", $binary_guid);
	$hex = $hex_guid["hex"];
	
	$hex1 = substr($hex, -26, 2) . substr($hex, -28, 2) . substr($hex, -30, 2) . substr($hex, -32, 2);
	$hex2 = substr($hex, -22, 2) . substr($hex, -24, 2);
	$hex3 = substr($hex, -18, 2) . substr($hex, -20, 2);
	$hex4 = substr($hex, -16, 4);
	$hex5 = substr($hex, -12, 12);
	
	$guid_str = $hex1 . "-" . $hex2 . "-" . $hex3 . "-" . $hex4 . "-" . $hex5;
	
	return $guid_str;
}

public function ldapConnectionIsValid()
{
	if (is_resource($this->ldapConnection) && get_resource_type($this->ldapConnection) === 'ldap link') {
		return true;
	}
	else {
		return false;
	}
}

public function connectToLdap()
{
	if ($this->ldapConnectionIsValid()) {
		return true;
	}
	
	$ldap = ldap_connect($this->config['host']);
	
	if ($ldap === false) {
		$this->error('Could not create an LDAP link identifier (ensure that the initialization parameters are valid)');
		
		return false;
	}
	
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	
	try {
		$bind = ldap_bind($ldap, $this->config['search_user_dn'], $this->config['search_password']);
		
		$this->ldapConnection = $ldap;
		
		return true;
	}
	catch (ErrorException $e) {
		$ldapErrorString = ldap_error($conn);
		$ldapErrorNumber = ldap_errno($conn);
		
		$extendedError = NULL;
		
		ldap_get_option($conn, $config['LDAP_OPT_DIAGNOSTIC_MESSAGE'], $extendedError);
		
		$this->disconnect($conn);
		
		throw new LdapException(App::make('log'), $ldapErrorString, $ldapErrorNumber, $e, $extendedError);
	}
	
	return false;
}

public function lookupUserDetails($username)
{
	$this->connectToLdap();
	
	$ldap = &$this->ldapConnection;
	
	$filter="(sAMAccountName=$username)";
	
	$result = ldap_search($ldap, $this->config['search_base'], $filter);
	
	#if ($result === false) {
	#	$this->error('Call to ldap_search() failed');
	#
	#	return false;
	#}
	
	$info = ldap_get_entries($ldap, $result);
	
	$entry = $this->cleanUpEntry($info);
	
	return $entry;
}

public function unbind()
{
	if ($this->ldapConnectionIsValid()) {
		return ldap_unbind($this->ldapConnection);
	}
	else {
		return true;
	}
}

}
