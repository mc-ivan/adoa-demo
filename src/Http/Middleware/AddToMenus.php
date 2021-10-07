<?php
namespace ProcessMaker\Package\Adoa\Http\Middleware;

use Auth;
use Closure;
use Lavary\Menu\Builder;
use Lavary\Menu\Facade as Menu;

class AddToMenus
{
    private $inAdminGroup = false;

    private $inAgencyGroup = false;

    public function handle($request, Closure $next)
    {
        if (Auth::check()) {
            $this->setGroupStatus();

            $requestMenu = Menu::get('sidebar_request');
            $taskMenu = Menu::get('sidebar_task');

            if (! $this->inAdminGroup && ! $this->inAgencyGroup) {
                $this->clearMenu($requestMenu);
                $this->clearMenu($taskMenu);
            }

            $this->addToMenu($requestMenu);
            $this->addToMenu($taskMenu);
        }

        return $next($request);
    }

    private function setGroupStatus()
    {
        $groups = Auth::user()->groups->pluck('id');
        $this->inAdminGroup = $groups->contains(config('adoa.admin_group_id'));
        $this->inAgencyGroup = $groups->contains(config('adoa.agency_admin_group_id'));
    }

    private function clearMenu(Builder $menu)
    {
        $menu->filter(function($item) {
            if (! $item->hasParent()) {
                return true;
            }
        });
    }

    private function addToMenu(Builder $menu)
    {
        $submenu = $menu->first();

        $submenu->add(__('My Requests'), [
            'route' => ['package.adoa.listRequests'],
            'icon' => 'fa-tasks',
        ]);

        $submenu->add(__('Shared With Me'), [
            'route' => ['package.adoa.sharedWithMe'],
            'icon' => 'fa-share-square',
        ]);

        if ($this->inAgencyGroup) {
            $submenu->add(__('Agency Requests'), [
                'route' => ['package.adoa.agencyRequests', 'groupId' => config('adoa.agency_admin_group_id')],
                'icon' => 'fa-laptop-house',
            ]);
        }
    }
}
