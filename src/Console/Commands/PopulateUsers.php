<?php namespace Mmic\SentinelLdap\Console\Commands;


use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use Mmic\SentinelLdap\Utility\LdapUtility;

class PopulateUsers extends Command {

protected $usernameFile;
protected $ldapUtility;
protected $config;
protected $numSuccesses = 0;
protected $numFailures = 0;
protected $numSkips = 0;

/**
 * The console command name.
 *
 * @var string
 */
protected $name = 'users:populate';

/**
 * The console command description.
 *
 * @var string
 */
protected $description = 'Retrieves a list of usernames from a text file, queries LDAP for their details, and creates Sentinel accounts accordingly';

/**
 * Create a new command instance.
 *
 * @return void
 */
public function __construct(LdapUtility $ldapUtility)
{
	parent::__construct();
	
	$this->usernameFile = public_path() . DIRECTORY_SEPARATOR . 'users.txt';
	
	$this->ldapUtility = $ldapUtility;
	
	$this->config = $this->ldapUtility->getConfig();
}

/**
 * Execute the console command.
 *
 * @return mixed
 */
public function fire()
{
	$success = $this->createUsers();
	
	if ($success === true) {
		$this->info($this->numSuccesses . ' users were created (or updated) successfully');
		
		if ($this->numSkips > 0) {
			$this->comment($this->numSkips . ' users were skipped (usually because LDAP did not contain valid matches for them)');
		}
		
		if ($this->numFailures > 0) {
			$this->error($this->numFailures . ' users failed to be created (due to some unexpected failure condition');
		}
	}
}

public function parseUsernameList($file)
{
	$trimmed = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	
	return $trimmed;
}

public function createUsers()
{
	$this->info('Retrieving list of usernames from "' . $this->getUsernameFile() . '")...');
	
	$usernames = $this->parseUsernameList($this->usernameFile);
	
	if (!empty($usernames)) {
		$this->info(count($usernames) . ' usernames were retrieved');
		
		$this->info('Connecting to LDAP server ' . $this->config['host'] . ' to validate usernames...');
		
		$ldap = ldap_connect($this->config['host']);
		
		if ($ldap === false) {
			$this->error('Could not create an LDAP link identifier (ensure that the initialization parameters are valid)');
			
			return false;
		}
		
		ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
		
		define('LDAP_OPT_DIAGNOSTIC_MESSAGE', '0x0032');
		
		$bind = ldap_bind($ldap, $this->config['search_user_dn'], $this->config['search_password']);
		
		if ($bind) {
			$this->info('Connection to LDAP server established successfully');
			
			$this->info('Looking-up usernames...');
			
			foreach ($usernames as $username) {
				$this->info('Looking-up "' . $username . '"...');
				
				$filter="(sAMAccountName=$username)";
				
				$result = ldap_search($ldap, $this->config['search_base'],$filter);
				
				if ($result === false) {
					$this->error('Call to ldap_search() failed');
					
					return false;
				}
				
				ldap_sort($ldap, $result, 'sn');
				
				$info = ldap_get_entries($ldap, $result);
				
				$entry = $this->ldapUtility->cleanUpEntry($info);
				
				//Each $entry is keyed by its DN (Distinguished Name), which isn't
				//very helpful. A simple reset() will get us to the data (there
				//is only one item in the array).
				
				$e = reset($entry);
				
				//Ensure that values for surname, given name (first name), and
				//SAM account name are present (all are required in this context).
				
				if (!empty($e['sn']) && !empty($e['givenname']) && !empty($e['samaccountname'])) {
					//Create the account (if it doesn't already exist).
					
					$this->info('Found user "' . $username . '" to have real name "' . $e['sn'] . ', ' . $e['givenname'] . '"');
					
					$this->info('Creating (or updating existing) Sentinel account for user...');
					
					try {
						$this->ldapUtility->createSentinelUser($username . '@medicalmutual.com', $e['givenname'], $e['sn']);
						
						$this->numSuccesses++;
					}
					catch (\Exception $e) {
						$this->error('Sentinel account could not be created for user "' . $username . '"');
						
						$this->numFailures++;
					}
				}
				else {
					$this->comment('Could not find a usable record for this LDAP account, skipping');
					
					$this->numSkips++;
				}
			}
			
			ldap_unbind($ldap);
		}
		else {
			if (ldap_get_option($ldap, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extended_error)) {
				$this->error("Error binding to LDAP: $extended_error");
			} else {
				$this->error('Error binding to LDAP: No additional information is available');
			}
			
			return false;
		}
	}
	else {
		$this->error('The list of usernames could not be parsed; ensure that the file exists, is readable, and contains one username per line');
		
		return false;
	}
	
	return true;
}

public function getUsernameFile()
{
	return $this->usernameFile;
}

}
