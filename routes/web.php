<?php
use ProcessMaker\Adoa\classes\MigrateUsersProd;

Route::group(['middleware' => ['auth']], function () {
    // Route::get('admin/adoa', 'AdoaController@index')->name('package.skeleton.index');
    // Route::get('adoa', 'AdoaController@index')->name('package.skeleton.tab.index');
    Route::get('adoa/report', 'AdoaEmployeeAppraisalController@index')->name('package.adoa.tab.report');
    Route::get('adoa/employee-appraisal/print', 'AdoaEmployeeAppraisalController@generateReportPdf');
    Route::get('adoa/dashboard/requests', 'AdoaController@getListRequests')->name('package.adoa.listRequests');
    Route::get('adoa/dashboard/todo', 'AdoaController@getListToDo')->name('package.adoa.listToDo');
    Route::get('adoa/dashboard/shared-with-me', 'AdoaController@getListShared')->name('package.adoa.sharedWithMe');
    Route::get('adoa/print/{request}/{media}', 'AdoaController@printFile');
    Route::get('adoa/view/{request}/{media}', 'AdoaController@viewFile');
    Route::get('adoa/view-pdf/{request}', 'AdoaController@getFile')->name('package.adoa.getPdfFile');
    Route::get('adoa/dashboard/requests-agency/{groupId}', 'AdoaController@getListRequestsAgency')->name('package.adoa.agencyRequests');
    Route::get('adoa/new-dashboard', 'AdoaController@index');
    ////--- RWA
    // https://pm4-3315.processmaker.net/adoa/rwa-report
    Route::get('adoa/rwa-report', 'AdoaRwaReportController@index')->name('package.adoa.tab.rwa-report');

});