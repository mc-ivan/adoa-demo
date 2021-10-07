<?php
namespace ProcessMaker\Adoa\classes;

use ProcessMaker\Package\Adoa\Models\AdoaUsers;
use ProcessMaker\Package\Adoa\Models;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Client;
use Exception;
use DB;

class AdoaInformation
{
    public function api($url, $token)
    {
        try {
            $pmHeaders = $this->getApiHeaders($token);

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $pmHeaders);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            $resp = curl_exec($curl);
            curl_close($curl);
            $jsonResponse = json_decode($resp);

            if (is_null($jsonResponse)) {
                $jsonResponse = '';
            }

            return $jsonResponse;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getApiHeaders($token)
    {
        try {
            return [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getDependentEmployeeInformation($userPosition, $getUserId = false)
    {
        try {
            $pmTokenADOA = '3-5738379ecfaa4e9fb2eda707779732c7';
            $employeeData = [];
            $url = 'https://hrsieapi.azdoa.gov/api/hrorg/directedRptByPosAdv.json?pos=' . $userPosition;
            $employeesByManager = $this->api($url, $pmTokenADOA);
            if (!empty($employeesByManager->rows[0][0])) {
                $tempUserInformationJson = $employeesByManager->rows[0][0];
                $tempUserInformation = json_decode($tempUserInformationJson);

                foreach ($tempUserInformation as $valueEmployee) {
                    $employeeData[] = [
                        'FIRST_NAME' => $valueEmployee->name,
                        'LAST_NAME' => $valueEmployee->lastname,
                        'EIN' => $valueEmployee->ein,
                        'POSITION' => $valueEmployee->position,
                        'TITLE' => $valueEmployee->title,
                        'WORK_EMAIL' => $valueEmployee->email,
                    ];
                }
            }

            // Get user id
            if ($getUserId == true) {
                $adoaUsers = new AdoaUsers();
                //$adoaProcessRequest = new AdoaProcessRequest();

                foreach ($employeeData as $key => $employeeDataValue) {
                    ////---- User Data
                    if (!empty($employeeDataValue['EIN'])) {
                        $tempUserInfo = $adoaUsers->getUserInformationByEin($employeeDataValue['EIN']);
                        if (!empty($tempUserInfo['id'])) {
                            $employeeData[$key]['USER_ID'] = $tempUserInfo['id'];
                            //$tempRequestInfo = $adoaProcessRequest->getUserInformationByEin($employeeDataValue['EIN']);
                        }
                    }
                }
            }

            return $employeeData;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: getDependentEmployeeInformation ' . $error->getMessage();
        }
    }

    public function getManagerInformation($userEin)
    {
        try {
            $pmTokenADOA = '3-5738379ecfaa4e9fb2eda707779732c7';
            $employeeData = [];
            $url = 'https://hrsieapi.azdoa.gov/api/hrorg/managerInfo.json?ein=' . $userEin;
            $managerResponse = $this->api($url, $pmTokenADOA);

            $managerInfo = [];
            if (!empty($managerResponse->columns)) {
                foreach ($managerResponse->columns as $key => $value) {
                    if (!empty($managerResponse->rows[0][$key])) {
                        $managerInfo[$value] = $managerResponse->rows[0][$key];
                    }
                }
            }

            return $managerInfo;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: getManagerInformation ' . $error->getMessage();
        }
    }


    public function getCycleInformation($userAgency)
    {
        try {
            // Set Agency Information
            $currentYear = date("Y");
            $tempBeginTime = strtotime('01/01/'.$currentYear);
            $beginTime = date('Y-m-d', $tempBeginTime);
            $beginTimeLabel = date('m/d/Y', $tempBeginTime);
            $tempEndTime = strtotime('12/31/'.$currentYear);
            $endTime = date('Y-m-d', $tempEndTime);
            $endTimeLabel = date('m/d/Y', $tempEndTime);
            $emailReminderFlag = "N";
            $agency = [];

            // Load get information
            $pmTokenADOA = '3-5738379ecfaa4e9fb2eda707779732c7';
            $url = 'https://hrsieapi.azdoa.gov/api/hrorg/AzPerformAgencyCFG.json?agency='. $userAgency;
            $agencyInformation = $this->api($url, $pmTokenADOA);
            if (!empty($agencyInformation->rows)) {
                foreach ($agencyInformation->rows as $keyEmployee => $employee) {
                    foreach ($agencyInformation->columns as $key => $value) {
                        $agency[$keyEmployee][$value] = $employee[$key];
                    }
                }
            }

            if (!empty($agency[0])) {
                $currentYear = date("Y");
                $currentMonth = date("m");
                $cycleBegin = $agency[0]['CYCLE_BEGIN'];
                $cycleEnd = $agency[0]['CYCLE_END'];
                $emailReminderFlag = $agency[0]['EmailReminderFlag'];

                $tempCycleBegin = explode('/', $cycleBegin);
                $monthBegin = $tempCycleBegin[0];

                $tempCycleEnd = explode('/', $cycleEnd);
                $monthEnd = $tempCycleEnd[0];

                $monthBegin = (int)$monthBegin;
                $monthEnd = (int)$monthEnd;
                $currentMonth = (int)$currentMonth;
                $currentYear = (int)$currentYear;

                ////---- Begin Date
                if ($monthBegin == $currentMonth) {
                    $beginDate = $cycleBegin . '/' . $currentYear;
                } else {
                    if (($monthBegin > 1 && $monthBegin < $currentMonth)) {
                        $beginDate = $cycleBegin . '/' . $currentYear;
                    } else {
                        $beginDate = $cycleBegin . '/' . ($currentYear-1);
                    }
                }

                ////---- End Date
                if ($monthEnd == $currentMonth) {
                    $endDate = $cycleEnd . '/' . $currentYear;
                } else {
                    if (($monthEnd > $currentMonth && $monthEnd <= 12)) {
                        $endDate = $cycleEnd . '/' . $currentYear;
                    } else {
                        $endDate = $cycleEnd . '/' . ($currentYear+1);
                    }
                }

                $tempBeginTime = strtotime($beginDate);
                $beginTime = date('Y-m-d', $tempBeginTime);
                $beginTimeLabel = date('m/d/Y', $tempBeginTime);
                $tempEndTime = strtotime($endDate);
                $endTime = date('Y-m-d', $tempEndTime);
                $endTimeLabel = date('m/d/Y', $tempEndTime);
            }

            return [
                'cycle_begin' => $beginTime,
                'cycle_begin_label' => $beginTimeLabel,
                'cycle_end' => $endTime,
                'cycle_end_label' => $endTimeLabel
            ];
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: getCycleInformation ' . $error->getMessage();
        }
    }
}
