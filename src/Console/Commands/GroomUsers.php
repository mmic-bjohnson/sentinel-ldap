<?php namespace Mmic\SentinelLdap\Console\Commands;


use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use Sentinel;

use Mmic\SentinelLdap\Utility\LdapUtility;
use Mmic\SentinelLdap\Classes\SentinelLdapManager;

class GroomUsers extends Command {

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
protected $name = 'users:groom';

/**
 * The console command description.
 *
 * @var string
 */
protected $description = 'Iterates over all Sentinel users and soft-deletes any user who does not exist in LDAP';

/**
 * Create a new command instance.
 *
 * @return void
 */
public function __construct(
	LdapUtility $ldapUtility,
	SentinelLdapManager $sentinelLdapManager
)
{
	parent::__construct();
	
	#$this->usernameFile = public_path() . DIRECTORY_SEPARATOR . 'users.txt';
	
	$this->ldapUtility = $ldapUtility;
	
	$this->config = $this->ldapUtility->getConfig();
	
	$this->sentinelLdapManager = $sentinelLdapManager;
}

/**
 * Execute the console command.
 *
 * @return mixed
 */
public function fire()
{
	$success = $this->groomUsers();
	
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

public function groomUsers()
{
	#dd(Sentinel::findById(23));
	
	#$userRepo = app()->make('Mmic\SentinelLdap\Models\UserDetails');
	
	$userRepo = app()->make('Platform\Users\Repositories\UserRepository');
	
	#dd($userRepo);
	$users = $userRepo->all();
	dd($users);
	$bar = $this->output->createProgressBar(count($users));
	
	foreach ($users as $user) {
		$userDetails = $this->sentinelLdapManager->fetchLdapEntry($user->samAccountName);
		
		if ($userDetails === false) {
			//Expunge the user from Platform.
			
			$userModel = Sentinel::findById($user->sentinelId);
			
			#dd($user->sentinelId);
			
			$userModel->delete();
		}
		
		#$this->comment($user->samAccountName);
		
		$bar->advance();
	}
	
	$bar->finish();
	
	$this->line(PHP_EOL);
}

}
