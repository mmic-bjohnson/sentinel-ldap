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
			$this->error($this->numFailures . ' users failed to be created (due to some unexpected failure condition)');
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
		
		#$this->info('Connecting to LDAP server ' . $this->config['host'] . ' to validate usernames...');
		
		#$bind = connectToLdap();
		
		#if ($bind) {
			#$this->info('Connection to LDAP server established successfully');
			
			$this->info('Looking-up usernames...');
			
			foreach ($usernames as $username) {
				$this->info('Looking-up "' . $username . '"...');
				
				$userId = $this->ldapUtility->createOrUpdateSentinelUser($username);
				
				var_dump($userId);
				
				if (empty($userId)) {
					$this->error('Sentinel account could not be created for user "' . $username . '"');
					
					$this->numFailures++;
				}
				else {
					$this->numSuccesses++;
				}
				
				break;
			}
			
			$this->ldapUtility->unbind();
		#}
		
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
