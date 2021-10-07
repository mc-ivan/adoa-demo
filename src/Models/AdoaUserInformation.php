<?php

namespace ProcessMaker\Package\Adoa\Models;

use Illuminate\Database\Eloquent\Model;
use Exception;

class AdoaUserInformation extends Model
{
    protected $table      = 'adoa_user_information';
    protected $primaryKey = 'user_id';

    protected $fillable = [
        'position',
        'manager',
        'super_position',
        'title',
        'ein',
        'agency',
        'agency_name',
        'process_level',
        'department'
    ];

    public function insertNewUserInformation($newUserInformation)
    {
        try {
            $result = static::insert($newUserInformation);
            return $result;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: insertNewUserInformation ' . $error->getMessage();
        }
    }

    public function insertUserInformationByUserName($newUserInformation)
    {
        try {

            $user = static::select('user_id')
                ->where('user_id', '=', $newUserInformation['user_id'])
                ->get()->toArray();

            if (empty($user)) {
                $result = static::insert($newUserInformation);
            } else {
                $result = 'false';
            }
            return $result;

        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: insertNewUserInformation ' . $error->getMessage();
        }
    }

    public function updateUserInformation($updateUserInformation)
    {
        try {
            $result = static::where('adoa_user_information.ein', '=', $updateUserInformation['ein'])
                ->where('adoa_user_information.user_id', '=', $updateUserInformation['user_id'])
                ->update($updateUserInformation);
            return $result;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: insertNewUser ' . $error->getMessage();
        }
    }

    public function deleteAllUsersInformation()
    {
        try {
            $response = static::truncate();
            return $response;
        } catch(Exception $error) {
            return $response['error'] = 'There are errors in the Function: deleteAllUsers ' . $error->getMessage();
        }
    }

    // public function isAdoaManager(int $userId)
    // {
    //     try {
    //         $isAdoaManager = AdoaUserInformation::find($userId);
    //         return empty($isAdoaManager) ? false : $isAdoaManager->manager == 'Y' ? true : false;
    //     } catch (Exception $exception) {
    //         return false;
    //     }
    // }

    public function user()
    {
        return $this->belongsTo('ProcessMaker\Package\Adoa\Models\AdoaUsers', 'user_id');
    }

    public function getAllUserInformationByUserId($userId)
    {
        try {
            $result = static::where('user_id', '=', $userId)
                ->get()
                ->all();
            return $result;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: getAllUserInformationByUserId ' . $error->getMessage();
        }
    }
}
