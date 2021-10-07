<?php
Route::group(['middleware' => ['auth:api', 'bindings']], function() {
    Route::get('admin/adoa/fetch', 'AdoaController@fetch')->name('package.skeleton.fetch');
    Route::apiResource('admin/adoa', 'AdoaController');
    Route::get('adoa/employee-list/{id}', 'AdoaUsersController@getUsersIdFullname');
    Route::get('adoa/user/{id}', 'AdoaUsersController@getUser');
    Route::get('adoa/user-title/{title}', 'AdoaUsersController@getUserByEin');
    Route::get('adoa/user-information/{ein}', 'AdoaUserInformationController@getUserManager');
    Route::resource('adoa/user-information', 'AdoaUserInformationController');
    Route::resource('adoa/type-appraisal', 'AdoaTypeAppraisalDetailController');
    Route::post('adoa/employee-appraisal', 'AdoaEmployeeAppraisalController@store');
    Route::get('adoa/employee-appraisal', 'AdoaEmployeeAppraisalController@getEmployeeAppraisalByUserId');
	Route::get('adoa/get-request-by-user/{process_id}/{user_id}', 'AdoaController@getRequestByProcessAndUser');
	Route::get('adoa/user-information-data/{user_id}', 'AdoaUserInformationController@getUserInformationByUserId');
    Route::get('getenvs', 'AdoaController@getEnvs')->name('getenvs');
    Route::get('adoa/get-task/{request}', 'AdoaController@getTask');
	Route::get('adoa/get-task-by-user/{request}/{user_id}', 'AdoaController@getTaskByUser');
    Route::get('adoa/group-admin-agency/{user_id}/{groupId}', 'AdoaController@getGroupAdminAgency');
    Route::get('adoa/group-admin/{user_id}', 'AdoaController@getGroupAdmin');
    Route::get('adoa/user-ein/{ein}', 'AdoaController@getUserInformation');
    Route::get('adoa/get-information/{type}/{user_id}', 'AdoaUserInformationController@getInformation');
    Route::get('adoa/get-information-by-manager/{type}/{user_id}', 'AdoaUserInformationController@getInformationByManager');
    Route::get('adoa/get-open-task/{user_id}/{request_id}', 'AdoaController@getOpenTask');
    Route::get('adoa/get-agency-enabled/{agency}', 'AdoaController@getAgencyEnabled');
    ////---- RWA
    Route::get('adoa/rwa-user-report', 'AdoaRwaReportController@getRwaByEmployeByEin');

    Route::get('adoa/users/prod', 'AdoaMigrateUsersController@migratedUsersProd');
    Route::get('adoa/users/dev', 'AdoaMigrateUsersController@migratedUsersDev');
    Route::get('adoa/users/admin', 'AdoaMigrateUsersController@migrateAdministrators');
    Route::get('adoa/get-users-agency', 'AdoaController@getUsersByAgency');
    Route::get('adoa/get-task-agency/{request_id}', 'AdoaController@getTaskAgency');
});
