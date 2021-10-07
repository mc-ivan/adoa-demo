<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

Artisan::command('adoa:install', function () {
    // if (!Schema::hasTable('sample_skeleton')) {
    //     Schema::create('sample_skeleton', function (Illuminate\Database\Schema\Blueprint $table) {
    //         $table->increments('id');
    //         $table->string('name');
    //         $table->enum('status', ['ENABLED', 'DISABLED'])->default('ENABLED');
    //         $table->timestamps();
    //     });
    // }

    if (!Schema::hasTable('adoa_employee_appraisal')) {
        Schema::create('adoa_employee_appraisal', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->string('user_ein')->nullable();
            $table->integer('evaluator_id')->nullable();
            $table->integer('supervisor_id')->nullable();
            $table->string('supervisor_ein')->nullable();
            $table->integer('type')->nullable()->index()->comment('1=Employee Coaching Note; 2=Manager Coaching Note; 3=Employee Self-Appraisal; 4=Informal Manager Appraisal for Employee; 5=Formal Manager Appraisal for Employee;');
            $table->mediumText('content')->nullable();
            $table->dateTime('date', 0)->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamps();

        });
    }

    if (!Schema::hasColumn('adoa_employee_appraisal','request_id')) {
        Schema::table('adoa_employee_appraisal', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->integer('request_id')->after('id')->nullable();
        });
    }


    if (!Schema::hasTable('adoa_type_appraisal_detail')) {
        Schema::create('adoa_type_appraisal_detail', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        DB::table('adoa_type_appraisal_detail')->insert(
            [
                ['id'=>1,'description' => 'Employee Coaching Note'],
                ['id'=>2,'description' => 'Manager Coaching Note'],
                ['id'=>3,'description' => 'Employee Self-Appraisal'],
                ['id'=>4,'description' => 'Informal Manager Appraisal for Employee'],
                ['id'=>5,'description' => 'Formal Manager Appraisal for Employee']
            ]
        );
    }

    if (!Schema::hasTable('adoa_user_information')) {
        Schema::create('adoa_user_information', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->integer('user_id');
            $table->string('position')->nullable();
            $table->string('manager')->nullable();
            $table->string('super_position')->nullable();
            $table->string('title')->nullable();
            $table->string('ein')->nullable();
            $table->string('agency')->nullable();
            $table->string('agency_name')->nullable();
            $table->string('process_level')->nullable();
            $table->string('department')->nullable();
            $table->timestamps();
        });
    }

    Artisan::call('vendor:publish', [
        '--tag' => 'adoa',
        '--force' => true
    ]);

    $this->info('Adoa has been installed');
})->describe('Installs the required js files and table in DB');


