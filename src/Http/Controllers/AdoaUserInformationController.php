<?php

namespace ProcessMaker\Package\Adoa\Http\Controllers;

use ProcessMaker\Adoa\classes\AdoaInformation;
use ProcessMaker\Http\Controllers\Controller;
use ProcessMaker\Http\Resources\ApiCollection;
use ProcessMaker\Package\Adoa\Models\AdoaUserInformation;
use ProcessMaker\Package\Adoa\Models\AdoaUsers;
use RBAC;
use Illuminate\Http\Request;
use URL;
use \DateTime;
use \DateTimeZone;
use \DB;
use Exception;

class AdoaUserInformationController extends Controller
{
    public function index()
    {
//         return view('testpackage::index');
    }

    public function store(Request $request){

        $userInformation = new AdoauserInformation();
        $userInformation->fill($request->json()->all());
        $userInformation->saveOrFail();
        return $userInformation;
    }

    public function getUserManager($ein) {
        return AdoaUserInformation::where('ein', $ein)
            ->first();
    }

    public function getUserInformationByUserId(int $user_id)
    {
        try {
            $adoaUsers = new AdoauserInformation();
            $query = $adoaUsers->getAllUserInformationByUserId($user_id);
            return $query;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: getUserInformationByUserId ' . $error->getMessage();
        }
    }

    public function getInformationByManager($type, int $user_id)
    {
        try {
            require_once dirname(__DIR__, 3) . '/classes/AdoaInformation.php';

            // Get user logged information
            $adoaUsers = new AdoaUsers();
            $response = $adoaUsers->getUserIdById($user_id);

            $userFirstName = (!empty($response['firstname'])) ? $response['firstname'] : '';
            $userLastName = (!empty($response['lastname'])) ? $response['lastname'] : '';
            $userTimezone = (!empty($response['timezone'])) ? $response['timezone'] : '' ;
            if (!empty($response['meta'])) {
                $userEin = $response['meta']['ein'];
                $userPosition = $response['meta']['position'];
                $userPositionTitle = $response['meta']['title'];
                $userEmail = $response['meta']['email'];
                $userSuperPosition = $response['meta']['super_position'];
                $userAgency = $response['meta']['agency'];
                $userAgencyName = $response['meta']['agency_name'];
                $userDepartment = $response['meta']['department'];
                $userProcessLevel = $response['meta']['process_level'];
                $userManager = $response['meta']['manager'];
            }

            // Review if the user is manager or not
            $managerOrEmployee = '';
            if ($type == "coaching-note-manager") {
                $managerOrEmployee = 'MANAGER';
            } else {
                $managerOrEmployee = 'EMPLOYEE';
            }

            $adoaInformation = new AdoaInformation();

            // Get manager information
            $supervisorFirstName = '';
            $supervisorLastName = '';
            $supervisorEIN = '';
            $supervisorEmail = '';

            if ($type == "self-appraisal" || $type == "coaching-note-employee") {
                if (!empty($userEin)) {
                    $managerInfo = $adoaInformation->getManagerInformation($userEin);
                    $supervisorFirstName = (!empty($managerInfo['FIRST_NAME'])) ? $managerInfo['FIRST_NAME'] : '';
                    $supervisorLastName = (!empty($managerInfo['LAST_NAME'])) ? $managerInfo['LAST_NAME'] : '';
                    $supervisorEIN = (!empty($managerInfo['EMPLOYEE'])) ? $managerInfo['EMPLOYEE'] : '';
                    $supervisorEmail = (!empty($managerInfo['WORK_EMAIL'])) ? $managerInfo['WORK_EMAIL'] : '';
                }
            }

            // Get dependent employees
            $responseUsers = [];
            if ($type == "informal-appraisal" || $type == "formal-appraisal" || $type == "coaching-note-manager") {
                $flagUserId = false;
                if ($type == "formal-appraisal") {
                    $flagUserId = true;
                }
                $responseUsers = $adoaInformation->getDependentEmployeeInformation($userPosition, $flagUserId);
            }



            // Cycle Information
            $responceCycle = [];
            if ($type == "informal-appraisal" || $type == "formal-appraisal" || $type == "self-appraisal") {
                $responceCycle = $adoaInformation->getCycleInformation($userAgency);
            }

            // Server information
            $hostUrl = $_SERVER['APP_URL'];
            $hostUrl = $hostUrl . '/';
            $apiHost = $hostUrl . 'api/1.0/';

            ////---- Get current date
            if (empty($userTimezone)) {
                $userTimezone = 'America/Phoenix';
            }

            $timestamp = time();
            $dt = new DateTime("now", new DateTimeZone($userTimezone));
            $dt->setTimestamp($timestamp);
            $currentDate = $dt->format('m/d/Y, h:i:s A');
            $currentDateDataBase = $dt->format('Y-m-d H:i:s');


            foreach ($responseUsers as $key => $value) {
                //var_dump($value);
                $responseUsers[$key]['SUPERVISOR_FIRST_NAME'] = $userFirstName;
                $responseUsers[$key]['SUPERVISOR_LAST_NAME'] = $userLastName;
                $responseUsers[$key]['SUPERVISOR_EIN'] = $userEin;
                $responseUsers[$key]['SUPERVISOR_EMAIL'] = $userEmail;

                $responseUsers[$key]['CYCLE_BEGIN'] = (!empty($responceCycle['cycle_begin'])) ? $responceCycle['cycle_begin'] : '';
                $responseUsers[$key]['CYCLE_END'] = (!empty($responceCycle['cycle_end'])) ? $responceCycle['cycle_end'] : '';
            }

            /*
            $userFirstName = (!empty($response['firstname'])) ? $response['firstname'] : '';
            $userLastName = (!empty($response['lastname'])) ? $response['lastname'] : '';
            $userTimezone = (!empty($response['timezone'])) ? $response['timezone'] : '' ;
            if (!empty($response['meta'])) {
                $userEin = $response['meta']['ein'];
                $userPosition = $response['meta']['position'];
                $userPositionTitle = $response['meta']['title'];
                $userEmail = $response['meta']['email'];
                $userSuperPosition = $response['meta']['super_position'];
                $userAgency = $response['meta']['agency'];
                $userAgencyName = $response['meta']['agency_name'];
                $userDepartment = $response['meta']['department'];
                $userProcessLevel = $response['meta']['process_level'];
                $userManager = $response['meta']['manager'];
            }
            */

            $response = [
                'EMPLOYEES_INFORMATION' => $responseUsers,
                /*'USER_ID' => $user_id,
                'EMPLOYEE_FIRST_NAME' => $userFirstName,
                'EMPLOYEE_LAST_NAME' => $userLastName,
                'EMPLOYEE_EIN' => $userEin,
                'EMPLOYEE_EMAIL' => $userEmail,
                'EMPLOYEE_POSITION' => $userPosition,
                'EMPLOYEE_POSITION_TITLE' => $userPositionTitle,
                'EMPLOYEE_SUPER_POSITION' => $userSuperPosition,
                'EMPLOYEE_AGENCY' => $userAgency,
                'EMPLOYEE_AGENCY_NAME' => $userAgencyName,
                'EMPLOYEE_DEPARTMENT' => $userDepartment,
                'EMPLOYEE_PROCESS_LEVEL' => $userProcessLevel,
                'SUPERVISOR_FIRST_NAME' => $supervisorFirstName,
                'SUPERVISOR_LAST_NAME' => $supervisorLastName,
                'SUPERVISOR_EIN' => $supervisorEIN,
                'SUPERVISOR_EMAIL' => $supervisorEmail,
                'API_HOST' => $apiHost,
                'CURRENT_DATETIME' => $currentDate,
                'CURRENT_DATETIME_DATABASE' => $currentDateDataBase,
                'HOST_URL' => $hostUrl,
                'CYCLE_BEGIN' => (!empty($responceCycle['cycle_begin'])) ? $responceCycle['cycle_begin'] : '',
                'CYCLE_BEGIN_LABEL' => (!empty($responceCycle['cycle_begin_label'])) ? $responceCycle['cycle_begin_label'] : '',
                'CYCLE_END' => (!empty($responceCycle['cycle_end'])) ? $responceCycle['cycle_end'] : '',
                'CYCLE_END_LABEL' => (!empty($responceCycle['cycle_end_label'])) ? $responceCycle['cycle_end_label'] : '',
                'CON_COACHING_NOTE_TYPE' => $managerOrEmployee,*/
            ];
            return $response;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: getUserInformationByUserId ' . $error->getMessage();
        }
    }

    public function getInformation($type, int $user_id)
    {
        try {
            require_once dirname(__DIR__, 3) . '/classes/AdoaInformation.php';

            // Get user logged information
            $adoaUsers = new AdoaUsers();
            $response = $adoaUsers->getUserIdById($user_id);

            $userFirstName = (!empty($response['firstname'])) ? $response['firstname'] : '';
            $userLastName = (!empty($response['lastname'])) ? $response['lastname'] : '';
            $userTimezone = (!empty($response['timezone'])) ? $response['timezone'] : '' ;
            if (!empty($response['meta'])) {
                $userEin = $response['meta']['ein'];
                $userPosition = $response['meta']['position'];
                $userPositionTitle = $response['meta']['title'];
                $userEmail = $response['meta']['email'];
                $userSuperPosition = $response['meta']['super_position'];
                $userAgency = $response['meta']['agency'];
                $userAgencyName = $response['meta']['agency_name'];
                $userDepartment = $response['meta']['department'];
                $userProcessLevel = $response['meta']['process_level'];
                $userManager = $response['meta']['manager'];
            }

            // Review if the user is manager or not
            $managerOrEmployee = '';
            if ($type == "coaching-note-manager") {
                $managerOrEmployee = 'MANAGER';
            } else {
                $managerOrEmployee = 'EMPLOYEE';
            }

            $adoaInformation = new AdoaInformation();

            // Get manager information
            $supervisorFirstName = '';
            $supervisorLastName = '';
            $supervisorEIN = '';
            $supervisorEmail = '';

            if ($type == "self-appraisal" || $type == "coaching-note-employee") {
                if (!empty($userEin)) {
                    $managerInfo = $adoaInformation->getManagerInformation($userEin);
                    $supervisorFirstName = (!empty($managerInfo['FIRST_NAME'])) ? $managerInfo['FIRST_NAME'] : '';
                    $supervisorLastName = (!empty($managerInfo['FIRST_NAME'])) ? $managerInfo['LAST_NAME'] : '';
                    $supervisorEIN = (!empty($managerInfo['FIRST_NAME'])) ? $managerInfo['EMPLOYEE'] : '';
                    $supervisorEmail = (!empty($managerInfo['FIRST_NAME'])) ? $managerInfo['WORK_EMAIL'] : '';
                }
            }

            // Get dependent employees
            $responseUsers = [];
            if ($type == "informal-appraisal" || $type == "formal-appraisal" || $type == "coaching-note-manager") {
                $flagUserId = false;
                if ($type == "formal-appraisal") {
                    $flagUserId = true;
                }
                $responseUsers = $adoaInformation->getDependentEmployeeInformation($userPosition, $flagUserId);
            }
            $responseUsers = json_encode($responseUsers);

            // Cycle Information
            $responceCycle = [];
            if ($type == "informal-appraisal" || $type == "formal-appraisal" || $type == "self-appraisal") {
                $responceCycle = $adoaInformation->getCycleInformation($userAgency);
            }

            // Server information
            $hostUrl = $_SERVER['APP_URL'];
            $hostUrl = $hostUrl . '/';
            $apiHost = $hostUrl . 'api/1.0/';

            ////---- Get current date
            if (empty($userTimezone)) {
                $userTimezone = 'America/Phoenix';
            }

            $timestamp = time();
            $dt = new DateTime("now", new DateTimeZone($userTimezone));
            $dt->setTimestamp($timestamp);
            $currentDate = $dt->format('m/d/Y, h:i:s A');
            $currentDateDataBase = $dt->format('Y-m-d H:i:s');

            $response = [
                'EMPLOYEES_INFORMATION' => $responseUsers,
                'USER_ID' => $user_id,
                'EMPLOYEE_FIRST_NAME' => $userFirstName,
                'EMPLOYEE_LAST_NAME' => $userLastName,
                'EMPLOYEE_EIN' => $userEin,
                'EMPLOYEE_EMAIL' => $userEmail,
                'EMPLOYEE_POSITION' => $userPosition,
                'EMPLOYEE_POSITION_TITLE' => $userPositionTitle,
                'EMPLOYEE_SUPER_POSITION' => $userSuperPosition,
                'EMPLOYEE_AGENCY' => $userAgency,
                'EMPLOYEE_AGENCY_NAME' => $userAgencyName,
                'EMPLOYEE_DEPARTMENT' => $userDepartment,
                'EMPLOYEE_PROCESS_LEVEL' => $userProcessLevel,
                'SUPERVISOR_FIRST_NAME' => $supervisorFirstName,
                'SUPERVISOR_LAST_NAME' => $supervisorLastName,
                'SUPERVISOR_EIN' => $supervisorEIN,
                'SUPERVISOR_EMAIL' => $supervisorEmail,
                'API_HOST' => $apiHost,
                'CURRENT_DATETIME' => $currentDate,
                'CURRENT_DATETIME_DATABASE' => $currentDateDataBase,
                'HOST_URL' => $hostUrl,
                'CYCLE_BEGIN' => (!empty($responceCycle['cycle_begin'])) ? $responceCycle['cycle_begin'] : '',
                'CYCLE_BEGIN_LABEL' => (!empty($responceCycle['cycle_begin_label'])) ? $responceCycle['cycle_begin_label'] : '',
                'CYCLE_END' => (!empty($responceCycle['cycle_end'])) ? $responceCycle['cycle_end'] : '',
                'CYCLE_END_LABEL' => (!empty($responceCycle['cycle_end_label'])) ? $responceCycle['cycle_end_label'] : '',
                'CON_COACHING_NOTE_TYPE' => $managerOrEmployee,
            ];
            return $response;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: getUserInformationByUserId ' . $error->getMessage();
        }
    }
}
