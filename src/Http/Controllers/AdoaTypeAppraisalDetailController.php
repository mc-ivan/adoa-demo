<?php
namespace ProcessMaker\Package\Adoa\Http\Controllers;

use ProcessMaker\Http\Controllers\Controller;
use ProcessMaker\Http\Resources\ApiCollection;
use ProcessMaker\Package\Adoa\Models\AdoaUsers;
use ProcessMaker\Package\Adoa\Models\AdoaTypeAppraisalDetail;
use RBAC;
use Illuminate\Http\Request;
use URL;
use \DateTime;
use \DB;


class AdoaTypeAppraisalDetailController extends Controller
{
    public function index()
    {
        // return view('testpackage::index');
    }

    public function store(Request $request){
        $typeAppraisal = new AdoaTypeAppraisalDetail();
        $typeAppraisal->fill($request->json()->all());
        $typeAppraisal->saveOrFail();
        return $typeAppraisal;
    }
}
  
