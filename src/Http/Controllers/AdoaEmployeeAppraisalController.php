<?php
namespace ProcessMaker\Package\Adoa\Http\Controllers;

use ProcessMaker\Http\Controllers\Controller;
use ProcessMaker\Http\Resources\ApiCollection;
use ProcessMaker\Package\Adoa\Models\AdoaUsers;
use ProcessMaker\Package\Adoa\Models\AdoaProcessRequest;
use ProcessMaker\Package\Adoa\Models\AdoaEmployeeAppraisal;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use RBAC;
use Illuminate\Http\Request;
use URL;
use DateTime;
use DB;


class AdoaEmployeeAppraisalController extends Controller
{
    public function index(){

        $userLogged = auth()->user();
        $adoaUser = AdoaUsers::select('id', 'firstname', 'lastname', 'meta')
        ->where('id', auth()->user()->id)->get()->toArray();
        $manager   = new AdoaUsers();
        $isManager = $manager->isAdoaManager(auth()->user()->id);

        return view('adoa::adoaEmployeeAppraisal', [
            'adoaUser' => empty($adoaUser[0]) ? [] : $adoaUser[0],
            'isManager' => $isManager,
            'isSysAdmin' => $userLogged->is_administrator
        ]);
    }

    public function store(Request $request){

        $oldCase = AdoaEmployeeAppraisal::where('id', $request->get('request_id'))
            ->where('type', $request->get('type'))->get()->toArray();

        $user       = AdoaUsers::select('id', 'users.*')->where('username', $request->get('user_ein'))->first()->toArray();
        $supervisor = AdoaUsers::where('username', $request->get('supervisor_ein'))->first()->toArray();
        $userData   = array_merge($request->all(), ['user_id' => $user['id'], 'supervisor_id' => $supervisor['id']]);

        if(empty($oldCase)) {
            $appraisal  = new AdoaEmployeeAppraisal();
            $appraisal->fill($userData);
            $appraisal->saveOrFail();

            return $appraisal;
        } else {
            $oldCase->update($userData);

            return $oldCase;
        }
    }

    public function show(String $id){
        return AdoaEmployeeAppraisal::findOrFail($id);
    }

    public function getEmployeeAppraisalByUserId(Request $request)
    {
        try {
            $today      = date('Y-m-d 00:00:00');
            $finalToday = date('Y-m-d 23:59:59');

            $initDate  = !empty($request->get('initDate')) ? date('Y-m-d 00:00:00', strtotime($request->get('initDate'))) : $today;
            $finalDate = !empty($request->get('endDate')) ? date('Y-m-d 23:59:59', strtotime($request->get('endDate'))) : $finalToday;
            $userId    = !empty($request->get('userId')) ? $request->get('userId') :  '';
            $type      = $request->has('type') ? explode(',', $request->get('type')) : [];

            $query = AdoaEmployeeAppraisal::select(
                DB::raw("CONCAT(users.firstname,' ',users.lastname) AS fullname, CONCAT(evaluator.firstname,' ',evaluator.lastname) AS evaluator_fullname"),
                'adoa_employee_appraisal.*',
                'media.id as file_id'
                )
                ->where('adoa_employee_appraisal.date', '>=', $initDate)
                ->where('adoa_employee_appraisal.date', '<=', $finalDate)
                ->when($userId != '', function ($query) use ($userId) {
                    $query->where('adoa_employee_appraisal.user_id', '=', $userId);
                    return $query;
                })
                ->when(!empty($type), function ($query) use ($type) {
                    $query->whereIn('adoa_employee_appraisal.type', $type);
                    return $query;
                })

                ->join('users', 'adoa_employee_appraisal.user_id', '=', 'users.id')
                ->join('users as evaluator', 'adoa_employee_appraisal.evaluator_id', '=', 'evaluator.id')
                ->join('media', 'adoa_employee_appraisal.request_id', '=', 'media.model_id')
                ->join('process_requests', 'adoa_employee_appraisal.request_id', '=', 'process_requests.id')
                ->whereRaw('media.id = json_extract(process_requests.data, "$.pdf")')
                ->where('process_requests.status', 'COMPLETED')
                ->orderBy('date', 'DESC')
                ->distinct('adoa_employee_appraisal.request_id')
                ->get()
                ->toArray();
            return $query;
        } catch (Exception $exception) {
            return [$exception->getMessage()];
            throw new Exception('Error function getEmployeeAppraisalByUserId: ' . $exception->getMessage());
        }
    }

    public function getEmployeeAppraisalByUser(Array $data)
    {
        try {
            $today       = date('Y-m-d 00:00:00');
            $finalToday  = date('Y-m-d 23:59:59');

            $initDate    = !empty($data['initDate']) ? date('Y-m-d 00:00:00', strtotime($data['initDate'])) : $today;
            $finalDate   = !empty($data['endDate']) ? date('Y-m-d 23:59:59', strtotime($data['endDate'])) : $finalToday;
            $userId      = !empty($data['userId']) ? $data['userId'] :  '';
            $type        = $data['type'];

            $query = AdoaEmployeeAppraisal::select(
                DB::raw("CONCAT(evaluator.firstname,' ',evaluator.lastname) AS evaluator_fullname"),
                'evaluator.*' , 'adoa_employee_appraisal.*', 'media.id as file_id')
                ->where('adoa_employee_appraisal.date', '>=', $initDate)
                ->where('adoa_employee_appraisal.date', '<=', $finalDate)
                ->whereIn('adoa_employee_appraisal.type', $type)
                ->where('adoa_employee_appraisal.user_id', '=', $userId)
                ->join('users as evaluator', 'adoa_employee_appraisal.evaluator_id', '=', 'evaluator.id')
                ->join('media', 'adoa_employee_appraisal.request_id', '=', 'media.model_id')
                ->join('process_requests', 'adoa_employee_appraisal.request_id', '=', 'process_requests.id')
                ->whereRaw('media.id = json_extract(process_requests.data, "$.pdf")')
                ->where('process_requests.status', 'COMPLETED')
                ->orderBy('date', 'DESC')
                ->get()
                ->toArray();
            return $query;

        } catch (Exception $exeption) {
            throw new Exception('Error Processing Request');
        }
    }

    public function uniqueMultidimensionalArray(Array $array, String $key) {
        try {
            $arrayUnique = array();
            $keyArray    = array();
            $index       = 0;

            foreach($array as $value) {
                if (!in_array($value[$key], $keyArray)) {
                    $keyArray[$index]     = $value[$key];
                    $arrayUnique[$index]  = $value;
                }
                $index++;
            }
            return $arrayUnique;
        } catch (Exception $exception) {
            return $array;
            throw new Exception('Error uniqueMultidimensionalArray: ' . $exception->getMessage());
        }
    }

    public function generateReportPdf(Request $request)
    {
        try {
            $type        = !empty($request->get('type'))  ? explode(',', $request->get('type')) : [];
            $userId      = !empty($request->get('userId')) ? $request->get('userId') : '1';
            $currentUser = auth()->user()->toArray();
            $currentDate = date('Y-m-d H:i:s');
            $today       = date('Y-m-d 00:00:00');
            $finalToday  = date('Y-m-d 23:59:59');
            $initDate    = !empty($request->get('initDate')) ? $request->get('initDate') : $today;
            $endDate     = !empty($request->get('endDate')) ? $request->get('endDate') : $finalToday;

            $user = AdoaUsers::find($userId)->toArray();

            $dataReport = array(
                'currentUser' => $currentUser,
                'currentDate' => $currentDate,
                'userId'      => $userId,
                'initDate'    => $initDate,
                'endDate'     => $endDate,
                'type'        => $type
            );

            $appraisalList = $this->getEmployeeAppraisalByUser($dataReport);
            $html = '';

            foreach ($appraisalList as $appraisal) {

                switch ($appraisal['type']) {
                    case '1' :
                        $html .= $this->getContentEmployeeCoachingNotes($appraisal, $user, $currentUser);
                        break;
                    case '2' :
                        $html .= $this->getContentManagementCoachingNotes($appraisal, $user, $currentUser);
                        break;
                    case '3' :
                        $html .= $this->getContentEmployeeSelfAppraisal($appraisal, $user, $currentUser);
                        break;
                    case '4' :
                        $html .= $this->getContentInformalAppraisal($appraisal, $user, $currentUser);
                        break;
                    case '5' :
                        $html .= $this->getContentFormalAppraisal($appraisal, $user, $currentUser);
                        break;
                    default:
                        break;
                }
            }
            if(empty($html)){
                throw new Exception('No Data to export to PDF.');
            }

            $fileName    = $user['firstname']. ' ' . $user['lastname'] . ' '. date('m-d-Y his') . '.pdf';

            $dompdf = new Dompdf();
            $dompdf->set_option('isHtml5ParserEnabled' , true);
            $dompdf->set_option('isRemoteEnabled' , true);
            $dompdf->loadHtml($html);
            $dompdf->set_paper('Letter', 'portrait');
            $dompdf->render();

            return $dompdf->stream($fileName);

        } catch (\Throwable $exception) {
            new Exception("Error Processing Request", 1);
            ($exception->getMessage());
            return redirect()->back()->withInput()->withErrors(['error' => $exception->getMessage()]);
        }
    }

    public function getContentEmployeeCoachingNotes(Array $appraisal, Array $user, Array $currentUser)
    {
        try {
            $supervisor = AdoaUsers::find($appraisal['supervisor_id'])->toArray();
            $content    = !empty($appraisal['content']) ? json_decode($appraisal['content'], true) : [];

            $adoaProcessRequest = new AdoaProcessRequest();
            $requestData = $adoaProcessRequest->getDataByRequest($appraisal['request_id']);
            $requestData = $requestData[0]['data'];
            $requestData = json_decode($requestData);
            $backgroundColor = $requestData->CON_COACHING_COLOR;
            $coachingRole = $requestData->CON_COACHING_ROLE;

            $whiteSpace  = '';
            $whiteNumber = '0.00' ;

            $html = '';
            $html .= '<div style="background-color:';
            $html .= $backgroundColor;
            $html .= '; padding: 12px;">';
            $html .= '<div style="padding-left: 50px;" align="left"><img draggable="false" src="https://doa.az.gov/sites/default/files/ADOA-White-300px.png" width="200px"/></div>';
            $html .= '</div>';
            $html .= '<h4 class="text-center" style="font-family: Helvetica; text-align: center; color: ';
            $html .= $backgroundColor;
            $html .= ';"> <strong>COACHING NOTES</strong></h4>';

            $html .= '<table style="font-family:Helvetica; font-size: 12px; width: 100%; border-collapse: collapse;">';
            $html .= '<tr style="color: #fff; background-color: ';
            $html .=  $backgroundColor;
            $html .= ';">';
            $html .= '<td height="30px">';
            $html .= '<strong>';
            $html .= '1:1 Coaching Notes';
            $html .= '</strong>';
            $html .= '</td>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<table style="font-family:Helvetica; font-size: 12px; width: 100%; border-collapse: collapse;">';
            $html .= '<tr>';
            $html .= '<td width="40%">';
            $html .= '<span><span style="font-weight: bold;">Employee Name: </span>';
            $html .=  empty($user['firstname']) ? $whiteSpace : $user['firstname'] . ' ';
            $html .=  empty($user['lastname']) ? $whiteSpace : $user['lastname'];
            $html .= '</span>';
            $html .= '</td>';
            $html .= '<td width="40%">';
            $html .= '<span><span style="font-weight: bold;">Supervisor Name: </span>';
            $html .=  empty($supervisor['firstname']) ? $whiteSpace : $supervisor['firstname'] . ' ';
            $html .=  empty($supervisor['lastname']) ? $whiteSpace : $supervisor['lastname'];
            $html .= '</span>';
            $html .= '</td>';
            $html .= '<td width="20%">';
            $html .= '<span>Date:</span>';
            $html .=  empty($appraisal['date']) ? $whiteSpace : date("m/d/Y", strtotime($appraisal['date']));
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<table style="font-family:Helvetica; font-size: 12px; width: 100%; border: 1px solid #000; border-collapse: collapse;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: ';
            $html .= $backgroundColor;
            $html .= '; color:#fff; text-align: center;" width="25%" height="30px">';
            $html .= '<span>';
            $html .= 'DIRECTIONS';
            $html .= '</span>';
            $html .= '</td>';
            $html .= '<td width="80%" height="30px">';
            $html .= '<span>';
            $html .= 'Discuss and Document results, behaviors and impact on the business as they relate to the Discussion Points, in the left column which aligns with the front-line employee`s role in the organization.';
            $html .= '</span>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<table style="font-family:Helvetica; width: 100%;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: #dfdfdf; font-size: 12px; text-align: center; font-weight: bold;" width="25%" height="40px">';
            $html .= '<span>';
            $html .= 'Discussion Points';
            $html .= '</span>';
            $html .= '<div style="font-size: 12px; width: 0;margin:auto; height: 40px; border-style: solid; border-width: 20px 40px 0 40px; border-color: #000000 transparent transparent transparent;"></div>';
            $html .= '</td>';
            $html .= '<td style="background-color: #dfdfdf; text-align: center; font-weight: bold;" width="75%" height="40px">';
            $html .= '<h5 style="font-weight: bold;">';
            $html .= 'Role: ';
            $html .= $coachingRole;
            $html .= '</h5>';
            $html .= '<h5 style="font-weight: bold;">';
            $html .= 'Organization Level: Front-Line';
            $html .= '</h5>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<table style="font-family:Helvetica; font-size: 12px; font-weight: bold;text-align: center; width: 100%;">';
            $html .= '<tr>';
            $html .= '<td width="25%"></td>';
            $html .= '<td width="37%">Discussion</td>';
            $html .= '<td width="37%">Commitments/Actions/Tasks</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<table style="font-family:Helvetica; width: 100%;">';
            $html .= '<tr>';
            $html .= '<td width="25%" height="200px" style="border: 1px solid #000; font-size: 10px; text-align: left; padding-left: 5px;">';
            $html .= '<p style="font-size: 10px; font-weight: bold;">';
            $html .= 'Celebrate Successes';
            $html .= '</p>';
            $html .= '<p style="font-size: 10px; font-weight: bold;">';
            $html .= 'Review Prior Commitments';
            $html .= '</p>';
            $html .= '<span>';
            $html .= 'and actions items';
            $html .= '</span>';
            $html .= '<span>';
            $html .= '<ul>';
            $html .= '<li>';
            $html .= 'Progress on goals and projects';
            $html .= '</li>';
            $html .= '</ul>';
            $html .= '</span>';
            $html .= '<p style="font-size: 10px; font-weight: bold;">';
            $html .= 'Problem Solving';
            $html .= '</p>';
            $html .= '<span>';
            $html .= '<ul>';
            $html .= '<li>';
            $html .= 'Discuss issues/obstacles confronting the employee';
            $html .= '</li>';
            $html .= '<li>';
            $html .= 'Use basic problem solving techniques, as appropriate';
            $html .= '</li>';
            $html .= '</ul>';
            $html .= '</span>';
            $html .= '<p style="font-size: 10px; font-weight: bold;">';
            $html .= 'Individual Development';
            $html .= '</p>';
            $html .= '<span>';
            $html .= '<ul>';
            $html .= '<li>';
            $html .= 'Discuss/Address development needs for current and next-level roles';
            $html .= '</li>';
            $html .= '<li>';
            $html .= 'Retention Discussion';
            $html .= '</li>';
            $html .= '</ul>';
            $html .= '</span>';
            $html .= '<p style="font-size: 10px; font-weight: bold;">';
            $html .= 'Help Needed';
            $html .= '</p>';
            $html .= '<span>';
            $html .= '<ul>';
            $html .= '<li>';
            $html .= 'Identify and discuss help needed by the employee';
            $html .= '</li>';
            $html .= '</ul>';
            $html .= '</span>';
            $html .= '<p style="font-size: 10px; font-weight: bold;">';
            $html .= 'Open Discussion';
            $html .= '</p>';
            $html .= '<span>';
            $html .= '<ul>';
            $html .= '<li>';
            $html .= 'Identify and discuss any other topics the employee raises.';
            $html .= '</li>';
            $html .= '</ul>';
            $html .= '</span>';
            $html .= '<p style="font-size: 10px; font-weight: bold;">';
            $html .= 'Next Steps';
            $html .= '</p>';
            $html .= '<span>';
            $html .= '<ul>';
            $html .= '<li>';
            $html .= 'Briefly summarize commitments and actions items to be completed by next meeting';
            $html .= '</li>';
            $html .= '</ul>';
            $html .= '</span>';
            $html .= '</td>';
            $html .= '<td width="37%" height="200px" style="font-size: 12px; background-color: #dfdfdf; vertical-align: top;">';
            $html .= '<span>';
            $html .=  empty($content['discussion']) ? $whiteSpace : $content['discussion'];
            $html .= '</span>';
            $html .= '</td>';
            $html .= '<td width="37%" height="200px" style="font-size: 12px; background-color: #dfdfdf; vertical-align: top;">';
            $html .= '<span>';
            $html .=  empty($content['commitments']) ? $whiteSpace : $content['commitments'];
            $html .= '</span>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<table style="page-break-after:always; font-family:Helvetica; font-size: 12px; width: 100%;">';
            $html .= '<tr>';
            $html .= '<td rowspan="3" width="25%">';
            $html .= '<p style="font-weight: bold;">';
            $html .= '(Note: Not all topics Need to be discussed in every coaching event)';
            $html .= '</p>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td colspan="4" style="text-align: center;">';
            $html .= 'Build problem solving skills by coaching through the PDCA cycle.';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr >';
            $html .= '<td width="18%" style="font-size: 12px; color: #fff; text-align: center; background-color: ';
            $html .= $backgroundColor;
            $html .= '; font-weight: bold; border-right: 1px solid #fff;">Plan the Work</td>';
            $html .= '<td width="18%" style="font-size: 12px; color: #fff; text-align: center; background-color: ';
            $html .= $backgroundColor;
            $html .= '; font-weight: bold; border-right: 1px solid #fff;">Do the Work</td>';
            $html .= '<td width="18%" style="font-size: 12px; color: #fff; text-align: center; background-color: ';
            $html .= $backgroundColor;
            $html .= '; font-weight: bold; border-right: 1px solid #fff;">Check for Gaps</td>';
            $html .= '<td width="18%" style="font-size: 12px; color: #fff; text-align: center; background-color: ';
            $html .= $backgroundColor;
            $html .= '; font-weight: bold; border-right: 1px solid #fff;">Act to Close Gaps</td>';
            $html .= '</tr>';
            $html .= '</table>';
            $html .= '</br>';

            return $html;
        } catch (Exception $exception) {
            throw new Exception($exception);
        }
    }

    public function getContentManagementCoachingNotes(Array $appraisal, Array $user, Array $currentUser)
    {
        try {
            $supervisor = AdoaUsers::find($appraisal['supervisor_id'])->toArray();
            $content    = !empty($appraisal['content']) ? json_decode($appraisal['content'], true) : [];
            $adoaProcessRequest = new AdoaProcessRequest();
            $requestData = $adoaProcessRequest->getDataByRequest($appraisal['request_id']);
            $requestData = $requestData[0]['data'];
            $requestData = json_decode($requestData);
            $backgroundColor = $requestData->CON_COACHING_COLOR;
            $coachingRole = $requestData->CON_COACHING_ROLE;

            $whiteSpace  = '';
            $whiteNumber = '0.00' ;

            $html = '';
            $html .= '<div style="background-color:';
            $html .= $backgroundColor;
            $html .= '; padding: 12px;">';
            $html .= '<div style="padding-left: 50px;" align="left"><img draggable="false" src="https://doa.az.gov/sites/default/files/ADOA-White-300px.png" width="200px"/></div>';
            $html .= '</div>';
            $html .= '<h4 class="text-center" style="font-family: Helvetica; text-align: center; color: ';
            $html .= $backgroundColor;
            $html .= ';"> <strong>COACHING NOTES</strong></h4>';

            $html .= '<table style="font-family:Helvetica; font-size: 12px; width: 100%; border-collapse: collapse;">';
            $html .= '<tr style="color: #fff; background-color: ';
            $html .=  $backgroundColor;
            $html .= ';">';
            $html .= '<td height="30px">';
            $html .= '<strong>';
            $html .= '1:1 Coaching Notes';
            $html .= '</strong>';
            $html .= '</td>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<table style="font-family:Helvetica; font-size: 12px; width: 100%; border-collapse: collapse;">';
            $html .= '<tr>';
            $html .= '<td width="40%">';
            $html .= '<span><span style="font-weight: bold;">Employee Name: </span>';
            $html .=  empty($user['firstname']) ? $whiteSpace : $user['firstname'] . ' ';
            $html .=  empty($user['lastname']) ? $whiteSpace : $user['lastname'];
            $html .= '</span>';
            $html .= '</td>';
            $html .= '<td width="40%">';
            $html .= '<span><span style="font-weight: bold;">Supervisor Name: </span>';
            $html .=  empty($supervisor['firstname']) ? $whiteSpace : $supervisor['firstname'] . ' ';
            $html .=  empty($supervisor['lastname']) ? $whiteSpace : $supervisor['lastname'];
            $html .= '</span>';
            $html .= '</td>';
            $html .= '<td width="20%">';
            $html .= '<span>Date:</span>';
            $html .=  empty($appraisal['date']) ? $whiteSpace : date("m/d/Y", strtotime($appraisal['date']));
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<table style="font-family:Helvetica; font-size: 12px; width: 100%; border: 1px solid #000; border-collapse: collapse;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: ';
            $html .= $backgroundColor;
            $html .= '; color:#fff; text-align: center;" width="25%" height="30px">';
            $html .= '<span>';
            $html .= 'DIRECTIONS';
            $html .= '</span>';
            $html .= '</td>';
            $html .= '<td width="80%" height="30px">';
            $html .= '<span>';
            $html .= 'Discuss and Document results, behaviors and impact on the business as they relate to the Discussion Points, in the left column which aligns with the front-line employee`s role in the organization.';
            $html .= '</span>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<table style="font-family:Helvetica; width: 100%;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: #dfdfdf; font-size: 12px; text-align: center; font-weight: bold;" width="25%" height="40px">';
            $html .= '<span>';
            $html .= 'Discussion Points';
            $html .= '</span>';
            $html .= '<div style="font-size: 12px; width: 0;margin:auto; height: 40px; border-style: solid; border-width: 20px 40px 0 40px; border-color: #000000 transparent transparent transparent;"></div>';
            $html .= '</td>';
            $html .= '<td style="background-color: #dfdfdf; text-align: center; font-weight: bold;" width="75%" height="40px">';
            $html .= '<h5 style="font-weight: bold;">';
            $html .= 'Role: ';
            $html .= $coachingRole;
            $html .= '</h5>';
            $html .= '<h5 style="font-weight: bold;">';
            $html .= 'Organization Level: Front-Line';
            $html .= '</h5>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<table style="font-family:Helvetica; font-size: 12px; font-weight: bold;text-align: center; width: 100%;">';
            $html .= '<tr>';
            $html .= '<td width="25%"></td>';
            $html .= '<td width="37%">Discussion</td>';
            $html .= '<td width="37%">Commitments/Actions/Tasks</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<table style="font-family:Helvetica; width: 100%;">';
            $html .= '<tr>';
            $html .= '<td width="25%" height="200px" style="border: 1px solid #000; font-size: 10px; text-align: left; padding-left: 5px;">';
            $html .= '<p style="font-size: 10px; font-weight: bold;">';
            $html .= 'Celebrate Successes';
            $html .= '</p>';
            $html .= '<p style="font-size: 10px; font-weight: bold;">';
            $html .= 'Review Prior Commitments';
            $html .= '</p>';
            $html .= '<span>';
            $html .= 'and actions items';
            $html .= '</span>';
            $html .= '<span>';
            $html .= '<ul>';
            $html .= '<li>';
            $html .= 'Progress on goals and projects';
            $html .= '</li>';
            $html .= '</ul>';
            $html .= '</span>';
            $html .= '<p style="font-size: 10px; font-weight: bold;">';
            $html .= 'Problem Solving';
            $html .= '</p>';
            $html .= '<span>';
            $html .= '<ul>';
            $html .= '<li>';
            $html .= 'Discuss issues/obstacles confronting the employee';
            $html .= '</li>';
            $html .= '<li>';
            $html .= 'Use basic problem solving techniques, as appropriate';
            $html .= '</li>';
            $html .= '</ul>';
            $html .= '</span>';
            $html .= '<p style="font-size: 10px; font-weight: bold;">';
            $html .= 'Individual Development';
            $html .= '</p>';
            $html .= '<span>';
            $html .= '<ul>';
            $html .= '<li>';
            $html .= 'Discuss/Address development needs for current and next-level roles';
            $html .= '</li>';
            $html .= '<li>';
            $html .= 'Retention Discussion';
            $html .= '</li>';
            $html .= '</ul>';
            $html .= '</span>';
            $html .= '<p style="font-size: 10px; font-weight: bold;">';
            $html .= 'Help Needed';
            $html .= '</p>';
            $html .= '<span>';
            $html .= '<ul>';
            $html .= '<li>';
            $html .= 'Identify and discuss help needed by the employee';
            $html .= '</li>';
            $html .= '</ul>';
            $html .= '</span>';
            $html .= '<p style="font-size: 10px; font-weight: bold;">';
            $html .= 'Open Discussion';
            $html .= '</p>';
            $html .= '<span>';
            $html .= '<ul>';
            $html .= '<li>';
            $html .= 'Identify and discuss any other topics the employee raises.';
            $html .= '</li>';
            $html .= '</ul>';
            $html .= '</span>';
            $html .= '<p style="font-size: 10px; font-weight: bold;">';
            $html .= 'Next Steps';
            $html .= '</p>';
            $html .= '<span>';
            $html .= '<ul>';
            $html .= '<li>';
            $html .= 'Briefly summarize commitments and actions items to be completed by next meeting';
            $html .= '</li>';
            $html .= '</ul>';
            $html .= '</span>';
            $html .= '</td>';
            $html .= '<td width="37%" height="200px" style="font-size: 12px; background-color: #dfdfdf; vertical-align: top;">';
            $html .= '<span>';
            $html .=  empty($content['discussion']) ? $whiteSpace : $content['discussion'];
            $html .= '</span>';
            $html .= '</td>';
            $html .= '<td width="37%" height="200px" style="font-size: 12px; background-color: #dfdfdf; vertical-align: top;">';
            $html .= '<span>';
            $html .=  empty($content['commitments']) ? $whiteSpace : $content['commitments'];
            $html .= '</span>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<table style="page-break-after:always; font-family:Helvetica; font-size: 12px; width: 100%;">';
            $html .= '<tr>';
            $html .= '<td rowspan="3" width="25%">';
            $html .= '<p style="font-weight: bold;">';
            $html .= '(Note: Not all topics Need to be discussed in every coaching event)';
            $html .= '</p>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td colspan="4" style="text-align: center;">';
            $html .= 'Build problem solving skills by coaching through the PDCA cycle.';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr >';
            $html .= '<td width="18%" style="font-size: 12px; color: #fff; text-align: center; background-color: ';
            $html .= $backgroundColor;
            $html .= '; font-weight: bold; border-right: 1px solid #fff;">Plan the Work</td>';
            $html .= '<td width="18%" style="font-size: 12px; color: #fff; text-align: center; background-color: ';
            $html .= $backgroundColor;
            $html .= '; font-weight: bold; border-right: 1px solid #fff;">Do the Work</td>';
            $html .= '<td width="18%" style="font-size: 12px; color: #fff; text-align: center; background-color: ';
            $html .= $backgroundColor;
            $html .= '; font-weight: bold; border-right: 1px solid #fff;">Check for Gaps</td>';
            $html .= '<td width="18%" style="font-size: 12px; color: #fff; text-align: center; background-color: ';
            $html .= $backgroundColor;
            $html .= '; font-weight: bold; border-right: 1px solid #fff;">Act to Close Gaps</td>';
            $html .= '</tr>';
            $html .= '</table>';
            $html .= '</br>';

            return $html;
        } catch (Exception $exception) {
            throw new Exception($exception);
        }
    }

    public function getContentEmployeeSelfAppraisal(Array $appraisal, Array $user, Array $currentUser)
    {
        try {
            $supervisor      = AdoaUsers::find($appraisal['supervisor_id'])->toArray();

            $content     = !empty($appraisal['content']) ? json_decode($appraisal['content'], true) : [];
            $whiteSpace  = '';
            $whiteNumber = '0.00' ;

            ////----Employee Self-Appraisal Pag. 1
            ////---- Section 1
            $html = '';
            $html .= '<div style="background-color: #bd241f; padding: 12px;">';
            $html .= '<div style="padding-left: 50px;" align="left"><img draggable="false" src="https://doa.az.gov/sites/default/files/ADOA-White-300px.png" width="200px" /></div>';
            $html .= '</div>';
            $html .= '<h4 style="color: #bd241f; font-family: Helvetica; text-align: center"><strong>SELF APPRAISAL</strong></h4>';

            $html .= '<table width="100%" style="border-collapse: collapse; font-family: Helvetica; font-size: 12px; text-align: left">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th scope="col" width="20%" height="30px" style="background: #e1e5e7; color: #505b67"><strong>First Name</strong></th>';
            $html .= '<th scope="col" width="20%" height="30px" style="background: #e1e5e7; color: #505b67">Employee Last Name</th>';
            $html .= '<th colspan="2" scope="col" width="20%" height="30px" style="background: #e1e5e7; color: #505b67">EIN</th>';
            $html .= '<th colspan="4" scope="col" width="40%" height="30px" style="background: #e1e5e7; color: #505b67">Performance Period</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td>';
            $html .=  empty($user['firstname']) ? $whiteSpace : $user['firstname'];
            $html .= '</td>';
            $html .= '<td>';
            $html .=  empty($user['lastname']) ? $whiteSpace : $user['lastname'];
            $html .= '</td>';
            $html .= '<td colspan="2">';
            $html .=  empty($user['username']) ? $whiteSpace : $user['username'];
            $html .= '</td>';
            $html .= '<td>From</td>';
            $html .= '<td>';
            $html .=  empty($content['period_to']) ? $whiteSpace : $content['period_to'];
            $html .= '</td>';
            $html .= '<td>To</td>';
            $html .= '<td>';
            $html .=  empty($content['period_from']) ? $whiteSpace : $content['period_from'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';

            $html .= '<table width="100%" class="table" style="border-collapse: collapse; font-family: Helvetica; font-size: 12px; text-align: left">';
            $html .= '<thead class="thead-light">';
            $html .= '<tr>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Supervisor First Name</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Supervisor Last Name</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Agency</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Division</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Team</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Employee Job Title</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td>';
            $html .=  empty($supervisor['firstname']) ? $whiteSpace : $supervisor['firstname'];
            $html .= '</td>';
            $html .= '<td>';
            $html .=  empty($supervisor['lastname']) ? $whiteSpace : $supervisor['lastname'];
            $html .= '</td>';
            $html .= '<td></td>';
            $html .= '<td>&nbsp;</td>';
            $html .= '<td>&nbsp;</td>';
            $html .= '<td>';
            $html .=  empty($user['meta']['title']) ? $whiteSpace : $user['meta']['title'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" style="border-collapse:collapse; font-family: Helvetica">';
            $html .= '<thead class="thead-light" style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<th colspan="2" scope="col" align="Left" height="30px" style="background: #e1e5e7; color: #505b67">SECTION 1: RESULTS</th>';
            $html .= '<th colspan="2" scope="col" align="Right" height="30px" style="background: #e1e5e7; color: #505b67">WHAT results were accomplished?</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style="font-size: 12px;">';
            $html .= '<tr style="background-color: #a21c28; color: #fff;">';
            $html .= '<td height="40px">Consider</td>';
            $html .= '<td height="40px">';
            $html .= '<ul>';
            $html .= '<li>Job expectations and metrics</li>';
            $html .= '<li>Impact, contribution, value added</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td height="40px">';
            $html .= '<ul>';
            $html .= '<li>Key accomplishments</li>';
            $html .= '<li>Outcomes</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td height="40px">';
            $html .= '<ul>';
            $html .= '<li>Actions taken</li>';
            $html .= '<li>Goals</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td colspan="4" height="40px">Summarize the employee&rsquo;s results and describe how they contributed to or detracted from individual, team and/or organization success:</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="height: 320px;" colspan="4">';
            $html .=  empty($content['section1_results_comment']) ? $whiteSpace : $content['section1_results_comment'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';

            $html .= '<table class="table" style="border-collapse: collapse; font-family: Helvetica; font-size: 12px;">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th style="color: #fff; background-color: #6b1b09; text-align: center;" colspan="5" height="30px">RESULT - RATING GUIDELINES</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td style="text-align: center;" height="30px"><strong>1</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>2</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>3</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>4</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>5</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Fails to Meet</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Inconsistently Meets</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Meets</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Often Exceeds</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Almost Always Exceeds</strong></td>';
            $html .= '</tr>';
            $html .= '<tr style="padding:10px;">';
            $html .= '<td>Results do not meet expectations. Immediate and sustained improvement is required.</td>';
            $html .= '<td>Results inconsistently meet expectations. Immediate and sustained improvement is required.</td>';
            $html .= '<td>Results meet expectations and contribute to the overall success of the team, division and/or agency.</td>';
            $html .= '<td>Results often exceed expectations, adding strong value/contribution to the success of team, division and/or agency goals.</td>';
            $html .= '<td>Achieves exceptional, high impact results, adding unique value and substantial contribution to the team, division and/or agency.</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" style="font-family: Helvetica">';
            $html .= '<thead style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: #000; color: #fff; text-align: center;" width="25%" height="60px"><strong>RESULTS RATING:</strong></td>';
            $html .= '<td style="text-align: center;" width="25%" height="60px">Please utilize the Results rating guidelines above to guide you in determining the rating.</td>';
            $html .= '<td style="text-align: center;" width="25%" height="60px">Performance Period Rating:</td>';
            $html .= '<td width="25%" height="60px">';
            $html .=  empty($content['section1_results_rating']) ? $whiteSpace : $content['section1_results_rating'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '</table>';
            $html .= '</br>';

            ////----section 2
            $html .= '<table class="table" width="100%" style="border-collapse: collapse; font-family: Helvetica">';
            $html .= '<thead class="thead-light" style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<th scope="col" align="left" height="30px" style="background: #e1e5e7; color: #505b67"><strong>SECTION 2: BEHAVIORS</strong></th>';
            $html .= '<th scope="col" align="center" height="30px" style="background: #e1e5e7; color: #505b67"><strong>HOW were results accomplished?</strong></th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: #a21c28; color: #fff;" colspan="2" align="center" height="30px">Read/Review the Minimum Behavioral Expectations outlined below before providing comments.</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table style="border-collapse: collapse; font-family: Helvetica; font-size: 11px;">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th style="color: #fff; background-color: #999999; text-align: center;" colspan="10">MINIMUM BEHAVIORAL EXPECTATIONS</th>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<th style="color: #fff; background-color: #999999; text-align: center;" colspan="10">State employees are expected to demonstrate all of the Core Beliefs and Core Values listed below.</th>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" colspan="10">Core Belief</td>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Understand Customer Needs</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Identify Problems</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Improve Processes</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Measure Results</strong></td>';
            $html .= '<td style="border: 1px solid #000000; color: #ffffff; background-color: #a21c28; text-align: center;" colspan="2"><strong>Leadership Expectations (for people managers, only)</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Listens to and confirms understanding with customers to identify needs</li>';
            $html .= '<li>Delivers solutions that meet the customers&rsquo; needs</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Observes, asks questions, gathers data to thoroughly understand the situation</li>';
            $html .= '<li>Identifies root causes and waste</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Actively participates in improvement activities</li>';
            $html .= '<li>Suggests ideas and acts to eliminate waste, solve problems and improve processes</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Measures work activities, analyzes data and makes changes to improve results</li>';
            $html .= '<li>Complies with quality, service, productivity and time standards</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="2" rowspan="4">';
            $html .= '<ul>';
            $html .= '<li>Models the Arizona Management System in word and action*</li>';
            $html .= '<li>Sets challenging expectations/goals and measures results</li>';
            $html .= '<li>Raises the level of team performance and capability</li>';
            $html .= '<li>Recognizes and addresses performance issues</li>';
            $html .= '<li>Motivates, recognizes and rewards individuals and the team</li>';
            $html .= '<li>*In agencies that have implemented AMS</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" colspan="8"><strong>Core Values</strong></td>';
            $html .= '</tr>';
            $html .= '<tr style="padding: 0px;">';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Do the Right Thing</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="3"><strong>Commit to Excellence</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="3"><strong>Care for One Another</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Communicates with honesty and transparency</li>';
            $html .= '<li>Takes responsibility for actions</li>';
            $html .= '<li>Adheres to State and agency Standards of Conduct and is a thoughtful steward of State resources</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="3">';
            $html .= '<ul>';
            $html .= '<li>Achieves standards of quality, service and timeliness</li>';
            $html .= '<li>Seeks to improve self</li>';
            $html .= '<li>Adapts to and is open to change</li>';
            $html .= '<li>Follows-through on commitments</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="3">';
            $html .= '<ul>';
            $html .= '<li>Communicates with honesty and transparency</li>';
            $html .= '<li>Takes responsibility for actions</li>';
            $html .= '<li>Adheres to State and agency Standards of Conduct and is a thoughtful steward of State resources</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" colspan="10">&nbsp;</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="text-align: justify;" colspan="10">Provide examples of how the employee demonstrated (or failed to demonstrate) the Core Beliefs and Values this rating period, and the impact of the employee s behaviors, especially on the results documented in Section 1. (NOTE: Managers need not comment on every Core Belief and/or Value.)</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="height: 200px;" colspan="10">';
            $html .=  empty($content['section2_behaviors_comment']) ? $whiteSpace : $content['section2_behaviors_comment'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" style="border-collapse: collapse; font-family: Helvetica; font-size: 12px;">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th style="color: #fff; background-color: #6b1b09; text-align: center;" colspan="5" height="30px">BEHAVIORS - RATING GUIDELINES</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td style="text-align: center;" height="30px"><strong>1</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>2</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>3</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>4</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>5</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Fails to Meet</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Inconsistently Meets</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Meets</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Often Exceeds</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Almost Always Exceeds</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td>Behaviors do not align with the Minimum Behavioral Expectations. Immediate and sustained improvement is required.</td>';
            $html .= '<td>Behaviors inconsistently align with the Minimum Behavioral Expectations. Immediate and sustained improvement is required.</td>';
            $html .= '<td>Overall, behaviors align with the Minimum Behavioral Expectations.</td>';
            $html .= '<td>Actions demonstrate strong alignment beyond the Minimum Behavioral Expectations leading to improved personal, team and organization results.</td>';
            $html .= '<td>Actions serve as role model alignment to the Minimum Behavioral Expectations. Influences others to change, improve and strengthen their behavior leading to improved personal, team and organizational results.</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" style="font-family: Helvetica">';
            $html .= '<thead style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: #000; color: #fff; text-align: center;" width="25%" height="60px"><b>BEHAVIORS RATING:</b></td>';
            $html .= '<td style="text-align: center;" width="25%" height="60px">Please utilize the Behaviors rating chart above to guide you in determining the rating.</td>';
            $html .= '<td style="text-align: center;" width="25%" height="60px">Performance Period Rating:</td>';
            $html .= '<td width="25%" style="text-align: center;" height="60px"><b>';
            $html .=  empty($content['section2_behaviors_rating']) ? $whiteSpace : $content['section2_behaviors_rating'];
            $html .= '</b></td>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '</table>';
            $html .= '</br>';

            ////----section 3
            $html .= '<table class="table" style="font-family: Helvetica">';
            $html .= '<thead style="font-size: 12px;">';
            $html .= '<tr class="thead-light">';
            $html .= '<th colspan="4" scope="col" align="left" style="background: #e1e5e7; color: #505b67" height="30px"><strong>SECTION 3: FINAL PERFORMANCE PERIOD RATING</strong></th>';
            $html .= '</tr>';
            $html .= '<tr style="background-color: #a21c28; color: #fff;">';
            $html .= '<th colspan="4" height="30px">&nbsp;</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: #000; color: #fff; text-align: center; width: 25%;" height="60px"><strong>FINAL PERFORMANCE PERIOD RATING:</strong></td>';
            $html .= '<td style="text-align: center; width: 25%;" height="60px">The Final Performance Period Rating is calculated by averaging the Results and Behaviors ratings:</td>';
            $html .= '<td style="text-align: center; width: 25%;" height="60px">&nbsp;</td>';
            $html .= '<td style="text-align: center; width: 25%;" height="60px"><b>Rating: ';
            $html .=  empty($content['section3_final_performance_rating']) ? $whiteSpace : $content['section3_final_performance_rating'];
            $html .= '</b></td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            ////----section 4
            $html .= '<table class="table" width="100%" style="page-break-after:always; font-family: Helvetica;">';
            $html .= '<thead style="font-size: 12px;">';
            $html .= '<tr class="thead-light">';
            $html .= '<th scope="col" align="left" style="background: #e1e5e7; color: #505b67" height="30px"><strong>SECTION 4: LOOKING FORWARD......</strong></th>';
            $html .= '</tr>';
            $html .= '<tr style="background-color: #a21c28; color: #fff;">';
            $html .= '<th height="30px">Recommended or required actions to achieve stronger results and/or behaviors</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="height: 150px;">';
            $html .=  empty($content['section4_looking_forward']) ? $whiteSpace : $content['section4_looking_forward'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';

            return $html;
        } catch (Exception $exception) {
            throw new Exception($exception);
        }
    }

    public function getContentInformalAppraisal(Array $appraisal, Array $user, Array $currentUser)
    {
        try {
            $supervisor      = AdoaUsers::find($appraisal['supervisor_id'])->toArray();

            $content     = !empty($appraisal['content']) ? json_decode($appraisal['content'], true) : [];
            $whiteSpace  = '';
            $whiteNumber = '0.00' ;

            ////----Informal Appraisal Pag. 1
            ////---- Section 1
            $html = '';
            $html .= '<div style="background-color: #bd241f; padding: 12px;">';
            $html .= '<div style="padding-left: 50px;" align="left"><img draggable="false" src="https://doa.az.gov/sites/default/files/ADOA-White-300px.png" width="200px" /></div>';
            $html .= '</div>';
            $html .= '<h4 style="color: #bd241f; font-family: Helvetica; text-align: center"><strong>INFORMAL APPRAISAL</strong></h4>';

            $html .= '<table width="100%" style="border-collapse: collapse; font-family: Helvetica; font-size: 12px; text-align: left">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th scope="col" width="20%" height="30px" style="background: #e1e5e7; color: #505b67"><strong>First Name</strong></th>';
            $html .= '<th scope="col" width="20%" height="30px" style="background: #e1e5e7; color: #505b67">Employee Last Name</th>';
            $html .= '<th colspan="2" scope="col" width="20%" height="30px" style="background: #e1e5e7; color: #505b67">EIN</th>';
            $html .= '<th colspan="4" scope="col" width="40%" height="30px" style="background: #e1e5e7; color: #505b67">Performance Period</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td>';
            $html .=  empty($user['firstname']) ? $whiteSpace : $user['firstname'];
            $html .= '</td>';
            $html .= '<td>';
            $html .=  empty($user['lastname']) ? $whiteSpace : $user['lastname'];
            $html .= '</td>';
            $html .= '<td colspan="2">';
            $html .=  empty($user['username']) ? $whiteSpace : $user['username'];
            $html .= '</td>';
            $html .= '<td>From</td>';
            $html .= '<td>';
            $html .=  empty($content['period_to']) ? $whiteSpace : $content['period_to'];
            $html .= '</td>';
            $html .= '<td>To</td>';
            $html .= '<td>';
            $html .=  empty($content['period_from']) ? $whiteSpace : $content['period_from'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';

            $html .= '<table width="100%" class="table" style="border-collapse: collapse; font-family: Helvetica; font-size: 12px; text-align: left">';
            $html .= '<thead class="thead-light">';
            $html .= '<tr>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Supervisor First Name</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Supervisor Last Name</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Agency</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Division</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Team</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Employee Job Title</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td>';
            $html .=  empty($supervisor['firstname']) ? $whiteSpace : $supervisor['firstname'];
            $html .= '</td>';
            $html .= '<td>';
            $html .=  empty($supervisor['lastname']) ? $whiteSpace : $supervisor['lastname'];
            $html .= '</td>';
            $html .= '<td></td>';
            $html .= '<td>&nbsp;</td>';
            $html .= '<td>&nbsp;</td>';
            $html .= '<td>';
            $html .=  empty($user['meta']['title']) ? $whiteSpace : $user['meta']['title'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" style="border-collapse:collapse; font-family: Helvetica">';
            $html .= '<thead class="thead-light" style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<th colspan="2" scope="col" align="Left" height="30px" style="background: #e1e5e7; color: #505b67">SECTION 1: RESULTS</th>';
            $html .= '<th colspan="2" scope="col" align="Right" height="30px" style="background: #e1e5e7; color: #505b67">WHAT results were accomplished?</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style="font-size: 12px;">';
            $html .= '<tr style="background-color: #a21c28; color: #fff;">';
            $html .= '<td height="40px">Consider</td>';
            $html .= '<td height="40px">';
            $html .= '<ul>';
            $html .= '<li>Job expectations and metrics</li>';
            $html .= '<li>Impact, contribution, value added</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td height="40px">';
            $html .= '<ul>';
            $html .= '<li>Key accomplishments</li>';
            $html .= '<li>Outcomes</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td height="40px">';
            $html .= '<ul>';
            $html .= '<li>Actions taken</li>';
            $html .= '<li>Goals</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td colspan="4" height="40px">Summarize the employee&rsquo;s results and describe how they contributed to or detracted from individual, team and/or organization success:</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="height: 320px;" colspan="4">';
            $html .=  empty($content['section1_results_comment']) ? $whiteSpace : $content['section1_results_comment'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';

            $html .= '<table class="table" style="border-collapse: collapse; font-family: Helvetica; font-size: 12px;">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th style="color: #fff; background-color: #6b1b09; text-align: center;" colspan="5" height="30px">RESULT - RATING GUIDELINES</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td style="text-align: center;" height="30px"><strong>1</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>2</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>3</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>4</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>5</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Fails to Meet</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Inconsistently Meets</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Meets</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Often Exceeds</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Almost Always Exceeds</strong></td>';
            $html .= '</tr>';
            $html .= '<tr style="padding:10px;">';
            $html .= '<td>Results do not meet expectations. Immediate and sustained improvement is required.</td>';
            $html .= '<td>Results inconsistently meet expectations. Immediate and sustained improvement is required.</td>';
            $html .= '<td>Results meet expectations and contribute to the overall success of the team, division and/or agency.</td>';
            $html .= '<td>Results often exceed expectations, adding strong value/contribution to the success of team, division and/or agency goals.</td>';
            $html .= '<td>Achieves exceptional, high impact results, adding unique value and substantial contribution to the team, division and/or agency.</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" style="font-family: Helvetica">';
            $html .= '<thead style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: #000; color: #fff; text-align: center;" width="25%" height="60px"><strong>RESULTS RATING:</strong></td>';
            $html .= '<td style="text-align: center;" width="25%" height="60px">Please utilize the Results rating guidelines above to guide you in determining the rating.</td>';
            $html .= '<td style="text-align: center;" width="25%" height="60px">Performance Period Rating:</td>';
            $html .= '<td width="25%" height="60px">';
            $html .=  empty($content['section1_results_rating']) ? $whiteSpace : $content['section1_results_rating'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '</table>';
            $html .= '</br>';

            ////----section 2
            $html .= '<table class="table" width="100%" style="border-collapse: collapse; font-family: Helvetica">';
            $html .= '<thead class="thead-light" style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<th scope="col" align="left" height="30px" style="background: #e1e5e7; color: #505b67"><strong>SECTION 2: BEHAVIORS</strong></th>';
            $html .= '<th scope="col" align="center" height="30px" style="background: #e1e5e7; color: #505b67"><strong>HOW were results accomplished?</strong></th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: #a21c28; color: #fff;" colspan="2" align="center" height="30px">Read/Review the Minimum Behavioral Expectations outlined below before providing comments.</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table style="border-collapse: collapse; font-family: Helvetica; font-size: 11px;">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th style="color: #fff; background-color: #999999; text-align: center;" colspan="10">MINIMUM BEHAVIORAL EXPECTATIONS</th>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<th style="color: #fff; background-color: #999999; text-align: center;" colspan="10">State employees are expected to demonstrate all of the Core Beliefs and Core Values listed below.</th>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" colspan="10">Core Belief</td>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Understand Customer Needs</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Identify Problems</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Improve Processes</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Measure Results</strong></td>';
            $html .= '<td style="border: 1px solid #000000; color: #ffffff; background-color: #a21c28; text-align: center;" colspan="2"><strong>Leadership Expectations (for people managers, only)</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Listens to and confirms understanding with customers to identify needs</li>';
            $html .= '<li>Delivers solutions that meet the customers&rsquo; needs</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Observes, asks questions, gathers data to thoroughly understand the situation</li>';
            $html .= '<li>Identifies root causes and waste</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Actively participates in improvement activities</li>';
            $html .= '<li>Suggests ideas and acts to eliminate waste, solve problems and improve processes</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Measures work activities, analyzes data and makes changes to improve results</li>';
            $html .= '<li>Complies with quality, service, productivity and time standards</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="2" rowspan="4">';
            $html .= '<ul>';
            $html .= '<li>Models the Arizona Management System in word and action*</li>';
            $html .= '<li>Sets challenging expectations/goals and measures results</li>';
            $html .= '<li>Raises the level of team performance and capability</li>';
            $html .= '<li>Recognizes and addresses performance issues</li>';
            $html .= '<li>Motivates, recognizes and rewards individuals and the team</li>';
            $html .= '<li>*In agencies that have implemented AMS</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" colspan="8"><strong>Core Values</strong></td>';
            $html .= '</tr>';
            $html .= '<tr style="padding: 0px;">';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Do the Right Thing</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="3"><strong>Commit to Excellence</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="3"><strong>Care for One Another</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Communicates with honesty and transparency</li>';
            $html .= '<li>Takes responsibility for actions</li>';
            $html .= '<li>Adheres to State and agency Standards of Conduct and is a thoughtful steward of State resources</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="3">';
            $html .= '<ul>';
            $html .= '<li>Achieves standards of quality, service and timeliness</li>';
            $html .= '<li>Seeks to improve self</li>';
            $html .= '<li>Adapts to and is open to change</li>';
            $html .= '<li>Follows-through on commitments</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="3">';
            $html .= '<ul>';
            $html .= '<li>Communicates with honesty and transparency</li>';
            $html .= '<li>Takes responsibility for actions</li>';
            $html .= '<li>Adheres to State and agency Standards of Conduct and is a thoughtful steward of State resources</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" colspan="10">&nbsp;</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="text-align: justify;" colspan="10">Provide examples of how the employee demonstrated (or failed to demonstrate) the Core Beliefs and Values this rating period, and the impact of the employee s behaviors, especially on the results documented in Section 1. (NOTE: Managers need not comment on every Core Belief and/or Value.)</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="height: 200px;" colspan="10">';
            $html .=  empty($content['section2_behaviors_comment']) ? $whiteSpace : $content['section2_behaviors_comment'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" style="border-collapse: collapse; font-family: Helvetica; font-size: 12px;">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th style="color: #fff; background-color: #6b1b09; text-align: center;" colspan="5" height="30px">BEHAVIORS - RATING GUIDELINES</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td style="text-align: center;" height="30px"><strong>1</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>2</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>3</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>4</strong></td>';
            $html .= '<td style="text-align: center;" height="30px"><strong>5</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Fails to Meet</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Inconsistently Meets</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Meets</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Often Exceeds</strong></td>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" height="30px"><strong>Almost Always Exceeds</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td>Behaviors do not align with the Minimum Behavioral Expectations. Immediate and sustained improvement is required.</td>';
            $html .= '<td>Behaviors inconsistently align with the Minimum Behavioral Expectations. Immediate and sustained improvement is required.</td>';
            $html .= '<td>Overall, behaviors align with the Minimum Behavioral Expectations.</td>';
            $html .= '<td>Actions demonstrate strong alignment beyond the Minimum Behavioral Expectations leading to improved personal, team and organization results.</td>';
            $html .= '<td>Actions serve as role model alignment to the Minimum Behavioral Expectations. Influences others to change, improve and strengthen their behavior leading to improved personal, team and organizational results.</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" style="font-family: Helvetica">';
            $html .= '<thead style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: #000; color: #fff; text-align: center;" width="25%" height="60px"><b>BEHAVIORS RATING:</b></td>';
            $html .= '<td style="text-align: center;" width="25%" height="60px">Please utilize the Behaviors rating chart above to guide you in determining the rating.</td>';
            $html .= '<td style="text-align: center;" width="25%" height="60px">Performance Period Rating:</td>';
            $html .= '<td width="25%" style="text-align: center;" height="60px"><b>';
            $html .=  empty($content['section2_behaviors_rating']) ? $whiteSpace : $content['section2_behaviors_rating'];
            $html .= '</b></td>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '</table>';
            $html .= '</br>';

            ////----section 3
            $html .= '<table class="table" style="font-family: Helvetica">';
            $html .= '<thead style="font-size: 12px;">';
            $html .= '<tr class="thead-light">';
            $html .= '<th colspan="4" scope="col" align="left" style="background: #e1e5e7; color: #505b67" height="30px"><strong>SECTION 3: FINAL PERFORMANCE PERIOD RATING</strong></th>';
            $html .= '</tr>';
            $html .= '<tr style="background-color: #a21c28; color: #fff;">';
            $html .= '<th colspan="4" height="30px">&nbsp;</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: #000; color: #fff; text-align: center; width: 25%;" height="60px"><strong>FINAL PERFORMANCE PERIOD RATING:</strong></td>';
            $html .= '<td style="text-align: center; width: 25%;" height="60px">The Final Performance Period Rating is calculated by averaging the Results and Behaviors ratings:</td>';
            $html .= '<td style="text-align: center; width: 25%;" height="60px">&nbsp;</td>';
            $html .= '<td style="text-align: center; width: 25%;" height="60px"><b>Rating: ';
            $html .=  empty($content['section3_final_performance_rating']) ? $whiteSpace : $content['section3_final_performance_rating'];
            $html .= '</b></td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            ////----section 4
            $html .= '<table class="table" width="100%" style="page-break-after:always; font-family: Helvetica;">';
            $html .= '<thead style="font-size: 12px;">';
            $html .= '<tr class="thead-light">';
            $html .= '<th scope="col" align="left" style="background: #e1e5e7; color: #505b67" height="30px"><strong>SECTION 4: LOOKING FORWARD......</strong></th>';
            $html .= '</tr>';
            $html .= '<tr style="background-color: #a21c28; color: #fff;">';
            $html .= '<th height="30px">Recommended or required actions to achieve stronger results and/or behaviors</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="height: 150px;">';
            $html .=  empty($content['section4_looking_forward']) ? $whiteSpace : $content['section4_looking_forward'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            return $html;
        } catch (Exception $exception) {
            throw new Exception($exception);
        }
    }

    public function getContentFormalAppraisal(Array $appraisal, Array $user, Array $currentUser)
    {
        try {
            $supervisor      = AdoaUsers::find($appraisal['supervisor_id'])->toArray();

            $content     = !empty($appraisal['content']) ? json_decode($appraisal['content'], true) : [];
            $whiteSpace  = '';
            $whiteNumber = '0.00';

            ////----Formal Appraisal Pag. 1
            ////----Section 1
            $html = '';
            $html .= '<div style="background-color: #bd241f; padding: 12px;">';
            $html .= '<div style="padding-left: 50px;" align="left"><img draggable="false" src="https://doa.az.gov/sites/default/files/ADOA-White-300px.png" width="200px" /></div>';
            $html .= '</div>';
            $html .= '<h4 style="color: #bd241f; font-family: Helvetica; text-align: center"><strong>MANAGER APPRAISAL FOR EMPLOYEE</strong></h4>';

            $html .= '<table width="100%" style="border-collapse: collapse; font-family: Helvetica; font-size: 12px;">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th scope="col" width="20%" height="30px" style="background: #e1e5e7; color: #505b67"><strong>First Name</strong></th>';
            $html .= '<th scope="col" width="20%" height="30px" style="background: #e1e5e7; color: #505b67">Employee Last Name</th>';
            $html .= '<th colspan="2" scope="col" width="20%" height="30px" style="background: #e1e5e7; color: #505b67">EIN</th>';
            $html .= '<th colspan="4" scope="col" width="40%" height="30px" style="background: #e1e5e7; color: #505b67">Performance Period</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td>';
            $html .=  empty($user['firstname']) ? $whiteSpace : $user['firstname'];
            $html .= '</td>';
            $html .= '<td>';
            $html .=  empty($user['lastname']) ? $whiteSpace : $user['lastname'];
            $html .= '</td>';
            $html .= '<td colspan="2">';
            $html .=  empty($user['username']) ? $whiteSpace : $user['username'];
            $html .= '</td>';
            $html .= '<td>From</td>';
            $html .= '<td>';
            $html .=  empty($content['period_to']) ? $whiteSpace : $content['period_to'];
            $html .= '</td>';
            $html .= '<td>To</td>';
            $html .= '<td>';
            $html .=  empty($content['period_from']) ? $whiteSpace : $content['period_from'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';

            $html .= '<table width="100%" class="table" style="border-collapse: collapse; font-family: Helvetica; font-size: 12px;">';
            $html .= '<thead class="thead-light">';
            $html .= '<tr>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Supervisor First Name</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Supervisor Last Name</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Agency</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Division</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Team</th>';
            $html .= '<th scope="col" height="30px" style="background: #e1e5e7; color: #505b67">Employee Job Title</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td>';
            $html .=  empty($supervisor['firstname']) ? $whiteSpace : $supervisor['firstname'];
            $html .= '</td>';
            $html .= '<td>';
            $html .=  empty($supervisor['lastname']) ? $whiteSpace : $supervisor['lastname'];
            $html .= '</td>';
            $html .= '<td></td>';
            $html .= '<td>&nbsp;</td>';
            $html .= '<td>&nbsp;</td>';
            $html .= '<td>';
            $html .=  empty($user['meta']['title']) ? $whiteSpace : $user['meta']['title'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<p style="font-size: 12px; font-family: Helvetica; text-align: justify;">Note: This Performance Appraisal may be used as a quarterly or mid-cycle Performance Appraisal tool. If you choose to use it as a quarterly or mid-cycle tool, it is &lsquo;advisory&rsquo; only. It will not be placed in the employee&rsquo;s official personnel file, but will be maintained in the supervisor&rsquo;s file. Quarterly or mid-cycle ratings are not to be averaged to arrive at the final performance period (normally annual) rating. The final Results and Behaviors ratings will be determined by the supervisor and will be based on the employee&rsquo;s performance throughout the entire (normally annual) performance period.</p>';

            $html .= '<table class="table" style="border-collapse: collapse; font-family: Helvetica;">';
            $html .= '<thead class="thead-light" style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<th colspan="2" scope="col" align="Left with" height="30px" style="background: #e1e5e7; color: #505b67">SECTION 1: RESULTS</th>';
            $html .= '<th colspan="2" scope="col" align="Right" height="30px" style="background: #e1e5e7; color: #505b67">WHAT results were accomplished?</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style="font-size: 12px;">';
            $html .= '<tr style="background-color: #a21c28; color: #fff;">';
            $html .= '<td height="40">Consider</td>';
            $html .= '<td>';
            $html .= '<ul>';
            $html .= '<li>Job expectations and metrics</li>';
            $html .= '<li>Impact, contribution, value added</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td height="40px">';
            $html .= '<ul>';
            $html .= '<li>Key accomplishments</li>';
            $html .= '<li>Outcomes</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td height="40px">';
            $html .= '<ul>';
            $html .= '<li>Actions taken</li>';
            $html .= '<li>Goals</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td height="40px" colspan="4">Summarize the employee&rsquo;s results and describe how they contributed to or detracted from individual, team and/or organization success:</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="height: 250px;" colspan="4">';
            $html .=  empty($content['section1_results_comment']) ? $whiteSpace : $content['section1_results_comment'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';

            $html .= '<table class="table" style="border-collapse: collapse; font-family: Helvetica; font-size: 12px;">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th height="30px" style="color: #fff; background-color: #6b1b09; text-align: center;" colspan="5">RESULT - RATING GUIDELINES</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td height="30px" style="text-align: center;"><strong>1</strong></td>';
            $html .= '<td height="30px" style="text-align: center;"><strong>2</strong></td>';
            $html .= '<td height="30px" style="text-align: center;"><strong>3</strong></td>';
            $html .= '<td height="30px" style="text-align: center;"><strong>4</strong></td>';
            $html .= '<td height="30px" style="text-align: center;"><strong>5</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td height="30px" style="color: #fff; background-color: #a21c28; text-align: center;"><strong>Fails to Meet</strong></td>';
            $html .= '<td height="30px" style="color: #fff; background-color: #a21c28; text-align: center;"><strong>Inconsistently Meets</strong></td>';
            $html .= '<td height="30px" style="color: #fff; background-color: #a21c28; text-align: center;"><strong>Meets</strong></td>';
            $html .= '<td height="30px" style="color: #fff; background-color: #a21c28; text-align: center;"><strong>Often Exceeds</strong></td>';
            $html .= '<td height="30px" style="color: #fff; background-color: #a21c28; text-align: center;"><strong>Almost Always Exceeds</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td colspan="5"></td>';
            $html .= '</tr>';
            $html .= '<tr style="padding:10px;">';
            $html .= '<td>Results do not meet expectations. Immediate and sustained improvement is required.</td>';
            $html .= '<td>Results inconsistently meet expectations. Immediate and sustained improvement is required.</td>';
            $html .= '<td>Results meet expectations and contribute to the overall success of the team, division and/or agency.</td>';
            $html .= '<td>Results often exceed expectations, adding strong value/contribution to the success of team, division and/or agency goals.</td>';
            $html .= '<td>Achieves exceptional, high impact results, adding unique value and substantial contribution to the team, division and/or agency.</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" style="font-family: Helvetica">';
            $html .= '<thead style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: #000; color: #fff; text-align: center;" width="25%" height="60px"><strong>RESULTS RATING:</strong></td>';
            $html .= '<th style="text-align: center;" width="25%" height="60px">Please utilize the Results rating guidelines above to guide you in determining the rating.</th>';
            $html .= '<td style="text-align: center;" width="25%" height="60px">Performance Period Rating:</td>';
            $html .= '<th width="25%" height="60px">';
            $html .=  empty($content['section1_results_rating']) ? $whiteSpace : $content['section1_results_rating'];
            $html .= '</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" width="100%" style="border-collapse: collapse; font-family: Helvetica">';
            $html .= '<thead class="thead-light" style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<th scope="col" align="left" height="30px" style="background: #e1e5e7; color: #505b67"><strong>SECTION 2: BEHAVIORS</strong></th>';
            $html .= '<th scope="col" align="center" height="30px" style="background: #e1e5e7; color: #505b67"><strong>HOW were results accomplished?</strong></th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td height="30px" style="background-color: #a21c28; color: #fff;" colspan="2" align="center">Read/Review the Minimum Behavioral Expectations outlined below before providing comments.</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table style="border-collapse: collapse; font-family:Helvetica;">';
            $html .= '<thead style=" font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<th style="color: #fff; background-color: #999999; text-align: center;" colspan="10">MINIMUM BEHAVIORAL EXPECTATIONS</th>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<th style="color: #fff; background-color: #999999; text-align: center;" colspan="10">State employees are expected to demonstrate all of the Core Beliefs and Core Values listed below.</th>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" colspan="10">Core Belief</td>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style=" font-size: 11px;">';
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Understand Customer Needs</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Identify Problems</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Improve Processes</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Measure Results</strong></td>';
            $html .= '<td style="border: 1px solid #000000; color: #ffffff; background-color: #a21c28; text-align: center;" colspan="2"><strong>Leadership Expectations (for people managers, only)</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Listens to and confirms understanding with customers to identify needs</li>';
            $html .= '<li>Delivers solutions that meet the customers&rsquo; needs</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Observes, asks questions, gathers data to thoroughly understand the situation</li>';
            $html .= '<li>Identifies root causes and waste</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Actively participates in improvement activities</li>';
            $html .= '<li>Suggests ideas and acts to eliminate waste, solve problems and improve processes</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Measures work activities, analyzes data and makes changes to improve results</li>';
            $html .= '<li>Complies with quality, service, productivity and time standards</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="2" rowspan="4">';
            $html .= '<ul>';
            $html .= '<li>Models the Arizona Management System in word and action*</li>';
            $html .= '<li>Sets challenging expectations/goals and measures results</li>';
            $html .= '<li>Raises the level of team performance and capability</li>';
            $html .= '<li>Recognizes and addresses performance issues</li>';
            $html .= '<li>Motivates, recognizes and rewards individuals and the team</li>';
            $html .= '<li>*In agencies that have implemented AMS</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" colspan="8"><strong>Core Values</strong></td>';
            $html .= '</tr>';
            $html .= '<tr style="padding: 0px;">';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="2"><strong>Do the Right Thing</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="3"><strong>Commit to Excellence</strong></td>';
            $html .= '<td style="border: 1px solid; text-align: center;" colspan="3"><strong>Care for One Another</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid;" colspan="2">';
            $html .= '<ul>';
            $html .= '<li>Communicates with honesty and transparency</li>';
            $html .= '<li>Takes responsibility for actions</li>';
            $html .= '<li>Adheres to State and agency Standards of Conduct and is a thoughtful steward of State resources</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="3">';
            $html .= '<ul>';
            $html .= '<li>Achieves standards of quality, service and timeliness</li>';
            $html .= '<li>Seeks to improve self</li>';
            $html .= '<li>Adapts to and is open to change</li>';
            $html .= '<li>Follows-through on commitments</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '<td style="border: 1px solid;" colspan="3">';
            $html .= '<ul>';
            $html .= '<li>Communicates with honesty and transparency</li>';
            $html .= '<li>Takes responsibility for actions</li>';
            $html .= '<li>Adheres to State and agency Standards of Conduct and is a thoughtful steward of State resources</li>';
            $html .= '</ul>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="color: #fff; background-color: #a21c28; text-align: center;" colspan="10">&nbsp;</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="text-align: justify;" colspan="10">Provide examples of how the employee demonstrated (or failed to demonstrate) the Core Beliefs and Values this rating period, and the impact of the employees behaviors, especially on the results documented in Section 1. (NOTE: Managers need not comment on every Core Belief and/or Value.)</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="height: 200px;" colspan="10">';
            $html .=  empty($content['section2_behaviors_comment']) ? $whiteSpace : $content['section2_behaviors_comment'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" style="border-collapse:collapse; font-family: Helvetica; font-size: 12px;">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th height="30px" style="color: #fff; background-color: #6b1b09; text-align: center;" colspan="5">BEHAVIORS - RATING GUIDELINES</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td height="30px" style="text-align: center;"><strong>1</strong></td>';
            $html .= '<td height="30px" style="text-align: center;"><strong>2</strong></td>';
            $html .= '<td height="30px" style="text-align: center;"><strong>3</strong></td>';
            $html .= '<td height="30px" style="text-align: center;"><strong>4</strong></td>';
            $html .= '<td height="30px" style="text-align: center;"><strong>5</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td height="30px" style="color: #fff; background-color: #a21c28; text-align: center;"><strong>Fails to Meet</strong></td>';
            $html .= '<td height="30px" style="color: #fff; background-color: #a21c28; text-align: center;"><strong>Inconsistently Meets</strong></td>';
            $html .= '<td height="30px" style="color: #fff; background-color: #a21c28; text-align: center;"><strong>Meets</strong></td>';
            $html .= '<td height="30px" style="color: #fff; background-color: #a21c28; text-align: center;"><strong>Often Exceeds</strong></td>';
            $html .= '<td height="30px" style="color: #fff; background-color: #a21c28; text-align: center;"><strong>Almost Always Exceeds</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td>Behaviors do not align with the Minimum Behavioral Expectations. Immediate and sustained improvement is required.</td>';
            $html .= '<td>Behaviors inconsistently align with the Minimum Behavioral Expectations. Immediate and sustained improvement is required.</td>';
            $html .= '<td>Overall, behaviors align with the Minimum Behavioral Expectations.</td>';
            $html .= '<td>Actions demonstrate strong alignment beyond the Minimum Behavioral Expectations leading to improved personal, team and organization results.</td>';
            $html .= '<td>Actions serve as role model alignment to the Minimum Behavioral Expectations. Influences others to change, improve and strengthen their behavior leading to improved personal, team and organizational results. </td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" style="font-family: Helvetica">';
            $html .= '<thead style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: #000; color: #fff; text-align: center;" width="25%" height="60px"><b>BEHAVIORS RATING:</b></td>';
            $html .= '<th style="text-align: center;" width="25%" height="60px">Please utilize the Behaviors rating chart above to guide you in determining the rating.</th>';
            $html .= '<td style="text-align: center;" width="25%" height="60px">Performance Period Rating:</td>';
            $html .= '<th width="25%" height="60px">';
            $html .=  empty($content['section2_behaviors_rating']) ? $whiteSpace : $content['section2_behaviors_rating'];
            $html .= '</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" style="font-family: Helvetica">';
            $html .= '<thead style="font-size: 12px;">';
            $html .= '<tr class="thead-light">';
            $html .= '<th colspan="4" scope="col" align="left" height="30px" style="background: #e1e5e7; color: #505b67"><strong>SECTION 3: FINAL PERFORMANCE PERIOD RATING</strong></th>';
            $html .= '</tr>';
            $html .= '<tr style="background-color: #a21c28; color: #fff;">';
            $html .= '<th colspan="4" height="30px">&nbsp;</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="background-color: #000; color: #fff; text-align: center; width: 25%;" height="60px"><strong>FINAL PERFORMANCE PERIOD RATING:</strong></td>';
            $html .= '<td style="text-align: center; width: 25%;" height="60px">The Final Performance Period Rating is calculated by averaging the Results and Behaviors ratings:</td>';
            $html .= '<td style="text-align: center; width: 25%;" height="60px">&nbsp;</td>';
            $html .= '<td style="text-align: center; width: 25%;" height="60px"><b>Rating:';
            $html .=  empty($content['section3_final_performance_rating']) ? $whiteSpace : $content['section3_final_performance_rating'];
            $html .= '</b></td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" width="100%" style="font-family: Helvetica">';
            $html .= '<thead style="font-size: 12px;">';
            $html .= '<tr class="thead-light">';
            $html .= '<th scope="col" align="left" height="30px" style="background: #e1e5e7; color: #505b67"><strong>SECTION 4: LOOKING FORWARD......</strong></th>';
            $html .= '</tr>';
            $html .= '<tr style="background-color: #a21c28; color: #fff;">';
            $html .= '<th height="30px">Recommended or required actions to achieve stronger results and/or behaviors</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="height: 380px;">';
            $html .=  empty($content['section4_looking_forward']) ? $whiteSpace : $content['section4_looking_forward'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            $html .= '<table class="table" width="100%" style="font-family: Helvetica">';
            $html .= '<thead style="font-size: 12px;">';
            $html .= '<tr class="thead-light">';
            $html .= '<th scope="col" align="left" height="30px" style="background: #e1e5e7; color: #505b67"><strong>SECTION 5: EMPLOYEE COMMENTS</strong></th>';
            $html .= '</tr>';
            $html .= '<tr style="background-color: #a21c28; color: #fff;">';
            $html .= '<th height="30px">&nbsp;</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="height: 150px;">';
            $html .=  empty($content['section5_comments']) ? $whiteSpace : $content['section5_comments'];
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</br>';

            ////----section 6
            $html .= '<table class="table" style="page-break-after:always; font-family: Helvetica">';
            $html .= '<thead style="font-size: 12px;">';
            $html .= '<tr class="thead-light">';
            $html .= '<th colspan="4" scope="col" align="left" height="30px" style="background: #e1e5e7; color: #505b67"><strong>SECTION 6: ACKNOWLEDGEMENT AND SIGNATURES</strong></th>';
            $html .= '</tr>';
            $html .= '<tr style="background-color: #a21c28; color: #fff;">';
            $html .= '<th colspan="4">By typing their names below, the employee and manager acknowledge that they are electronically signing this form and that the employee has received a copy of this appraisal and it has been discussed. The employees electronic signature may or may not signify agreement with the appraisal.</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody style="font-size: 12px;">';
            $html .= '<tr>';
            $html .= '<td style="width: 20%;">Employee Signature:</td>';
            $html .= '<td style="width: 30%;">';
            $html .=  empty($user['firstname']) ? $whiteSpace : $user['firstname'] . ' ';
            $html .=  empty($user['lastname']) ? $whiteSpace : $user['lastname'];
            $html .= '</td>';
            $html .= '<td style="width: 20%;">Date:</td>';
            $html .= '<td style="width: 30%;">';
            $html .=  empty($appraisal['date']) ? $whiteSpace : date("m/d/Y", strtotime($appraisal['date']));
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="width: 20%;">Supervisor Signature:</td>';
            $html .= '<td style="width: 30%;">';
            $html .=  empty($supervisor['firstname']) ? $whiteSpace : $supervisor['firstname'] . ' ';
            $html .=  empty($supervisor['lastname']) ? $whiteSpace : $supervisor['lastname'];
            $html .= '</td>';
            $html .= '<td style="width: 20%;">Date:</td>';
            $html .= '<td style="width: 30%;">';
            $html .=  empty($appraisal['date']) ? $whiteSpace : date("m/d/Y", strtotime($appraisal['date']));
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';

            return $html;
        } catch (Exception $exception) {
            throw new Exception($exception);
        }
    }
}
