<?php

namespace ProcessMaker\Package\Adoa\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AdoaEmployeeAppraisal extends Model
{
    protected $table   = 'adoa_employee_appraisal';
    public $timestamps = true;

    protected $fillable = [
        'request_id',
        'user_id',
        'user_ein',
        'position_number',
        'evaluator_id',
        'supervisor_id',
        'supervisor_ein',
        'type',
        'content',
        'date'
    ];


    public function getDateAttribute( $value ) {
        $date = str_replace('/', '-', $value );
        $newDate = date('m/d/Y H:i:s', strtotime($date));
        return $newDate;
    }
}
