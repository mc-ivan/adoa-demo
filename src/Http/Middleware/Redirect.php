<?php
namespace ProcessMaker\Package\Adoa\Http\Middleware;

use Auth;
use Closure;
use ProcessMaker\Models\ProcessRequest;
use ProcessMaker\Models\ProcessRequestToken;

class Redirect
{
    private $inAdminGroup = false;

    private $inAgencyGroup = false;

    public function handle($request, Closure $next)
    {
        if (Auth::check()) {
            $this->setGroupStatus();

            if (! $this->inAdminGroup && ! $this->inAgencyGroup) {
                switch ($request->path()) {
                    case 'tasks':
                    case 'requests':
                        return redirect()->route('package.adoa.listToDo');
                }
                if ($request->route()->getName() == 'requests.show') {
                    if (isset($request->route()->parameters['request'])) {
                        $processRequest = $request->route()->parameters['request'];
                        $userId = Auth::user()->id;
                        if ($processRequest['user_id'] == $userId && isset($processRequest['data']['pdf'])) {
                            return redirect()->route('package.adoa.getPdfFile', ['request' => $processRequest->id]);
                        }
                        //if (!isset($processRequest['data']['pdf']) && $processRequest['status'] == 'COMPLETED') {
                            //return redirect()->route('package.adoa.listToDo');
                        //}
                        if ($task = $this->getTask($processRequest, $userId)) {
                            return redirect()->route('tasks.edit', ['task' => $task->id]);
                        }
                        if ($processRequest['user_id'] != $userId) {
                            return redirect()->route('package.adoa.listRequests');
                        }
                    }
                }
            }
        }

        return $next($request);
    }

    private function setGroupStatus()
    {
        $groups = Auth::user()->groups->pluck('id');
        $this->inAdminGroup = $groups->contains(config('adoa.admin_group_id'));
        $this->inAgencyGroup = $groups->contains(config('adoa.agency_admin_group_id'));
    }

    private function getTask(ProcessRequest $processRequest, $userId) {
        return ProcessRequestToken::where('process_request_id', $processRequest->id)
            ->where('element_type', 'task')
            ->where('status', 'ACTIVE')
            ->where('user_id', $userId)
            ->first();
    }
}
