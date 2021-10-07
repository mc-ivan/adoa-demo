<?php
namespace ProcessMaker\Package\Adoa\Http\Controllers;

use ProcessMaker\Http\Controllers\Controller;
use ProcessMaker\Http\Resources\ApiCollection;
use ProcessMaker\Package\Adoa\Models\AdoaUsers;
use ProcessMaker\Package\Adoa\Models\AdoaProcessRequest;
use ProcessMaker\Package\Adoa\Models\AdoaEmployeeAppraisal;
use ProcessMaker\Models\EnvironmentVariable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use RBAC;
use Illuminate\Http\Request;
use URL;
use DateTime;
use DB;


class AdoaRwaReportController extends Controller
{
    public function index(){
        $userLogged   = auth()->user();
        $adoaUser     = AdoaUsers::select('id', 'firstname', 'lastname', 'meta')
        ->where('id', auth()->user()->id)->get()->toArray();
        $manager      = new AdoaUsers();
        $isManager    = $manager->isAdoaManager(auth()->user()->id);
        $agreementCollectionId = EnvironmentVariable::whereName('agreements_collection_id')->first()->value;


        return view('adoa::adoaRwaReport', [
            'adoaUser' => empty($adoaUser[0]) ? [] : $adoaUser[0],
            'isManager' => $isManager,
            'isSysAdmin' => $userLogged->is_administrator,
            'agreementCollectionId' => $agreementCollectionId
        ]);
    }

    public function getRwaByEmployeByEin (Request $request)
    {
        try {
            $userEin    = !empty($request->get('userEin')) ? $request->get('userEin') :  '';

            $query = AdoaProcessRequest::select('process_requests.data->EMPLOYEE_LAST_NAME as lastname',
                'process_requests.data->EMPLOYEE_FIRST_NAME as firstname',
                'process_requests.data->EMPLOYEE_EIN as ein',
                'process_requests.data->ADOA_RWA_REMOTE_AGREEMENT_START_DATE as date_from',
                'process_requests.data->ADOA_RWA_REMOTE_AGREEMENT_END_DATE as date_to',
                'process_requests.id as request_id',
                'media.id as file_id')
            ->where('process_requests.name', 'Remote Work - Initiate or Terminate Agreement')
            ->join('media', 'process_requests.id', '=', 'media.model_id')
            ->where('data->ADOA_RWA_REMOTE_AGREEMENT_VALID', 'Y')
            ->where('data->EMPLOYEE_EIN', $userEin)
            ->get()
            ->toArray();

            return $query;
        } catch (Exception $exception) {
            throw new Exception('Error function getRwaByEmployeByEin: ' . $exception->getMessage());

        }
    }
}