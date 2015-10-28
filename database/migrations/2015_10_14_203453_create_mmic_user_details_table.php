<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateMmicUserDetailsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('mmic_user_details', function(Blueprint $table)
		{
			$table->integer('sentinelId')->unsigned()->unique('uidx_sentinelId');
			$table->string('guid')->nullable()->unique('uifx_guid');
			$table->string('samAccountName')->nullable();
			$table->timestamps();
		});
	}

}
