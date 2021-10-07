<?php

namespace ProcessMaker\Package\Adoa\Models;
use Illuminate\Database\Eloquent\Model;
use Exception;

class AdoaProcessRequest extends Model
{
    protected $table = 'process_requests';

    protected $fillable = [
        'id',
        'process_id',
        'process_collaboration_id',
        'user_id',
        'parent_request_id',
        'participant_id',
        'status',
        'data'
    ];

    public function getDataByRequest($requestId)
    {
        try {
            return static::select('process_requests.data')
                ->where('id', '=', $requestId)
                ->get()
                ->toArray();
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: getDataByRequest ' . $error->getMessage();
        }
    }

    public function getFormalAppraisalDataByUser($userId)
    {
        try {
            $row = static::select('process_requests.id', 'process_requests.data')
                ->where('name', '=', 'Formal Employee Appraisal')
                ->where('status', '=', 'ACTIVE')
                ->where('user_id', '=', $userId)
                ->first();
            $row = (empty($row)) ? array() : $row->toArray();

            return $row;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: getDataByRequest ' . $error->getMessage();
        }
    }
}
