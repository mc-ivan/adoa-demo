<?php
namespace ProcessMaker\Package\Adoa;

use Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ProcessMaker\Models\Group;
use ProcessMaker\Package\Packages\Events\PackageEvent;
use ProcessMaker\Package\Adoa\Http\Middleware\AddToMenus;
use ProcessMaker\Package\Adoa\Http\Middleware\Redirect;
use ProcessMaker\Package\Adoa\Listeners\PackageListener;
use ProcessMaker\Package\PackageComments\Http\Middleware\AddToMenus as CommentsAddToMenus;
use ProcessMaker\Package\SavedSearch\Http\Middleware\AddToMenus as SavedSearchAddToMenus;
use GlobalScripts;

class PackageServiceProvider extends ServiceProvider
{

    // Assign the default namespace for our controllers
    protected $namespace = '\ProcessMaker\Package\Adoa\Http\Controllers';

    /**
     * If your plugin will provide any services, you can register them here.
     * See: https://laravel.com/docs/5.6/providers#the-register-method
     */
    public function register()
    {
        // Nothing is registered at this time
    }

    /**
     * After all service provider's register methods have been called, your boot method
     * will be called. You can perform any initialization code that is dependent on
     * other service providers at this time.  We've included some example behavior
     * to get you started.
     *
     * See: https://laravel.com/docs/5.6/providers#the-boot-method
     */
    public function boot()
    {
        GlobalScripts::addScript('/vendor/processmaker/packages/adoa/js/checkRequestsCoachingNotes.js');
        
        $this->setGroupIds();

        if ($this->app->runningInConsole()) {
            require(__DIR__ . '/../routes/console.php');
//            $this->commands([
//                Console\Commands\Install::class,
//                Console\Commands\Uninstall::class,
//            ]);
        } else {
            // Assigning to the web middleware will ensure all other middleware assigned to 'web'
            // will execute. If you wish to extend the user interface, you'll use the web middleware
            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(__DIR__ . '/../routes/web.php');


            Route::middleware('api')
                ->namespace($this->namespace)
                ->prefix('api/1.0')
                ->group(__DIR__ . '/../routes/api.php');
            
            if (class_exists(SavedSearchAddToMenus::class)) {
                Route::pushMiddlewareToGroup('web', SavedSearchAddToMenus::class);
            }
            
            if (class_exists(CommentsAddToMenus::class)) {
                Route::pushMiddlewareToGroup('web', CommentsAddToMenus::class);
            }

            Route::pushMiddlewareToGroup('web', AddToMenus::class);
                
            Route::pushMiddlewareToGroup('web', Redirect::class);
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views/', 'adoa');

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/processmaker/packages/adoa'),
        ], 'adoa');

        $this->app['events']->listen(PackageEvent::class, PackageListener::class);

        $this->publishes([
            __DIR__.'/../classes' => public_path('vendor/processmaker/packages/adoa'),
        ], 'adoa');

        $this->app->bind(
            \ProcessMaker\Http\Controllers\Api\ProcessController::class,
            \ProcessMaker\Package\Adoa\Http\Controllers\Api\ProcessController::class
        );
    }
    
    private function setGroupIds()
    {
        if (! $id = Cache::get('adoa.admin_group_id')) {
            $id = optional(Group::where('name', 'LIKE', '%Administrators%')->first())->id;         
            Cache::put('adoa.admin_group_id', $id);
        }        
        config(['adoa.admin_group_id' => $id]);
        
        if (! $id = Cache::get('adoa.employee_group_id')) {
            $id = optional(Group::where('name', 'LIKE', '%Employees%')->first())->id;         
            Cache::put('adoa.employee_group_id', $id);
        }        
        config(['adoa.employee_group_id' => $id]);
        
        if (! $id = Cache::get('adoa.manager_group_id')) {
            $id = optional(Group::where('name', 'LIKE', '%Managers%')->first())->id;         
            Cache::put('adoa.manager_group_id', $id);
        }        
        config(['adoa.manager_group_id' => $id]);

        if (! $id = Cache::get('adoa.agency_admin_group_id')) {
            $id = optional(Group::where('name', 'LIKE', '%Agency Admin%')->first())->id;         
            Cache::put('adoa.agency_admin_group_id', $id);
        }        
        config(['adoa.agency_admin_group_id' => $id]);
    }
}
