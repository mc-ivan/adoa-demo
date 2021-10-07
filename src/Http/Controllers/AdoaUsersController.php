<?php
namespace ProcessMaker\Package\Adoa\Http\Controllers;

use Illuminate\Http\Request;
use ProcessMaker\Http\Controllers\Controller;
// use ProcessMaker\Package\Adoa\Models\AdoaUserInformation;

use ProcessMaker\Package\Adoa\Models\AdoaUsers;
use \Auth;
use \DB;
use \Exception;

class AdoaUsersController extends Controller
{
    public $employeesList = array();

    public function index()
    {
        // return view('adoa::index');
    }

    public function getUsersIdFullname(Request $request, int $id)
    {
        try {
            $searchTerm = request('searchTerm');
            $userLogged = auth()->user();
            $userCurrent = array(
                'text' => strtoupper($userLogged['firstname'] . ' ' . $userLogged['lastname']),
                'id' => $userLogged['id'],
            );

            $adoaUser = new AdoaUsers();

            $employeeList = array();

            if ($userLogged->is_administrator) {
                $query = AdoaUsers::select(DB::raw("CONCAT(firstname,' ',lastname) AS text"), 'id')
                    ->where('status', 'ACTIVE')
                    ->when($searchTerm, function ($query, $searchTerm) {
                        return $query->where(DB::raw('CONCAT_WS(" ", firstname, lastname)'), 'like', '%' . $searchTerm . '%');
                    })
                    ->limit(200)
                    ->orderBy('text', 'ASC')
                    ->get()
                    ->toArray();

                $employeeList = empty($query) ? [] : $query;
                array_unshift($employeeList, $userCurrent);

                return $employeeList;

            } else if ($adoaUser->isAdoaManager($id)) {

                $employees = $this->getEmployeesByManagerId($id);
                $employeeList = empty($employees) ? [] : $employees;

                $manager = AdoaUsers::select(DB::raw("CONCAT(firstname,' ',lastname) AS text"), 'id', 'users.*')
                    ->where('id', $id)
                    ->where('status', 'ACTIVE')
                    ->first()
                    ->toArray();

                array_unshift($employeeList, $manager);
                return $employeeList;

            } else {
                $query = AdoaUsers::select(DB::raw("CONCAT(firstname,' ',lastname) AS text"), 'id')
                    ->where('status', 'ACTIVE')
                    ->where('id', $id)
                    ->get()
                    ->toArray();

                return $query;
            }
        } catch (Exception $exception) {
            throw new Exception('Error on Function getUserIdFullname: ' . $exception->getMessage());
        }
    }

    public function getUser(Int $id)
    {
        $query = AdoaUsers::select('id', 'title', 'firstname', 'lastname', 'email', 'username', 'status', 'meta')
            ->findOrfail($id);
        return $query;
    }

    public function getUserByEin(String $ein)
    {
        $adoaUsers = new AdoaUsers();
        $query = $adoaUsers->getAllUserInformationByEin($ein);
        return $query;
    }

    public function getEmployeesByManagerId(int $managerId, $position = '', $agency = '')
    {
        try {
            $adoaUsers = new AdoaUsers();
            if (empty($position) && empty($agency)) {
                ////---- Get Manager data
                $manager = $adoaUsers->getManagerById($managerId);
                $position = $manager['meta']['position'];
                $agency = $manager['meta']['agency'];
            }
            ////---- Get all employees of Manager
            $employees = $adoaUsers->getAllEmployeesBySuperPositionAndAgency($position, $agency);
            foreach ($employees as $value) {
                if (!empty($this->employeesList[$value['id']])) {
                    continue;
                }
                if ($value['status'] == 'ACTIVE') {
                    $this->employeesList[$value['id']] = $value;
                }
                if ($value['meta']['manager'] == 'Y') {
                    $this->getEmployeesByManagerId($value['id'], $value['meta']['position'], $value['meta']['agency']);
                }
            }
            return $this->employeesList;
        } catch (Exception $exception) {
            throw new Exception('Error function getEmployeesByManagerId: ' . $exception->getMessage());
        }
    }
}
