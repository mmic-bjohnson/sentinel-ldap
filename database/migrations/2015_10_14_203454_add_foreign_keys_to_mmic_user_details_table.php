<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToMmicUserDetailsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		//Get underlying Doctrine Schema Manager.
		
		$sm = Schema::getConnection()->getDoctrineSchemaManager();
		
		//Check to see if the foreign key constraint already exists; if it does,
		//bail-out.
		
		$foreignKeys = $sm->listTableForeignKeys('mmic_user_details');
		
		foreach ($foreignKeys as $foreignKey) {
			if ($foreignKey->getName() === 'fk_sentinelId') {
				return;
			}
		}
		
		Schema::table('mmic_user_details', function(Blueprint $table)
		{
			$table->foreign('sentinelId', 'fk_sentinelId')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('RESTRICT');
		});
	}

	public function down()
	{
		
	}
}
