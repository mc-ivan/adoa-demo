<?php

namespace ProcessMaker\Package\Adoa\Models;

use Illuminate\Database\Eloquent\Model;
use Exception;
use \DB;

class AdoaUsers extends Model
{
    protected $table = 'users';

    protected $casts = [
        'meta' => 'array',
    ];

    protected $fillable = [
        'id',
        'username',
        'email',
        // 'password',
        'firstname',
        'lastname',
        'status',
        // 'address',
        // 'city',
        // 'state',
        // 'postal',
        // 'country',
        // 'phone',
        // 'fax',
        'cell',
        // 'title',
        // 'birthdate',
        // 'timezone',
        // 'datetime_format',
        // 'language',
        // 'meta',
    ];

    public function getFullName()
    {
        return $this->firstname . ' ' . $this->lastname;
    }

    public function getAllUsers()
    {
        try {
            $response = static::all()
                ->toArray();
            return $response;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: getAllUsers ' . $error->getMessage();
        }
    }

    public function getAllUsersByEin()
    {
        try {
            $users = static::select('users.id', 'users.username')
                ->get()
                ->toArray();
            return $users;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: getAllUsersByEIN ' . $error->getMessage();
        }
    }

    public function getUserIdByEin($ein)
    {
        try {
            $response = static::select('users.id', 'users.*')
                ->where('username', $ein)
                ->first()
                ->toArray();
            return $response;
        } catch(Exception $error) {
            return $response['error'] = 'There are errors in the Function: deleteAllUsers ' . $error->getMessage();
        }
    }

    public function getUserIdById($id)
    {
        try {
            $response = static::select('users.id', 'users.*')
                ->where('id', $id)
                ->first()
                ->toArray();
            return $response;
        } catch(Exception $error) {
            return $response['error'] = 'There are errors in the Function: deleteAllUsers ' . $error->getMessage();
        }
    }

    public function getUserInformationByEin($ein)
    {
        try {
            return static::select('users.id', 'users.*')
                ->where('users.meta->ein', $ein)
                ->first()
                ->toArray();
        } catch (Exception $exception) {
            return $response['error'] = 'There are errors in the Function: getUserInformationByEin ' . $exception->getMessage();
        }
    }

    public function getAllUserInformationByEin(String $ein)
    {
        try {
            $response = static::select('users.id as id',  'adoa_user_information.ein as title', 'users.firstname as firstname', 'users.lastname as lastname',
                'users.email as email', 'users.username as username', 'users.status as status')
                ->join('adoa_user_information', 'users.id', '=', 'adoa_user_information.user_id')
                ->where('adoa_user_information.ein', $ein)
                ->first()
                ->toArray();
            return $response;
        } catch(Exception $error) {
            return $response['error'] = 'There are errors in the Function: getAllUserInformationByEin ' . $error->getMessage();
        }
    }

    public function deleteAllUsers()
    {
        try {
            $response = static::where('id', '!=', 1)->delete();
            return $response;
        } catch(Exception $error) {
            return $response['error'] = 'There are errors in the Function: deleteAllUsers ' . $error->getMessage();
        }
    }

    public function adoaUserInformation()
    {
        return $this->hasOne('ProcessMaker\Package\Adoa\Models\AdoaUserInformation', 'user_id', 'id');
    }

    public function insertUser($userData) {
        try {
            $response = static::insertGetId($userData);
            return $response;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: insertUser ' . $error->getMessage();
        }
    }

    public function updateUser($userData) {
        try {

            $response = static::where('id', '=', $userData['id'])->update($userData);
            return $response;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: updateUser ' . $error->getMessage();
        }
    }

    public function isAdoaManager($userId)
    {
        try {
            $userData      = static::find($userId);
            if (!empty($userData)) {
                if ($userData['meta']['manager'] == 'Y') {
                    return true;
                } else {
                    return false;
                }
            }
        } catch (Exception $exception) {
            return false;
        }
    }

    public function inactiveAllUsers()
    {
        try {
            return  static::where('status', '=', 'ACTIVE')
                ->where('username', '!=', 'admin')
                ->where('username', '!=', '_pm4_anon_user')
                ->update(array('status' => 'INACTIVE'));
        } catch (Exception $exception) {
            return $response['error'] = 'There are errors in the Function: inactiveAllUsers ' . $exception->getMessage();
        }
    }

    public function getManagerById($id)
    {
        try {
            return static::select(DB::raw("CONCAT(firstname,' ',lastname) AS text"), 'id', 'users.meta', 'status')
                ->where('id', $id)
                ->where('meta->manager', 'Y')->first()
                ->toArray();
        } catch (Exception $exception) {
            return $response['error'] = 'There are errors in the Function: getManagerById ' . $exception->getMessage();
        }
    }

    public function getAllEmployeesBySuperPositionAndAgency($superPosition, $agency)
    {
        try {
            return static::select(DB::raw("CONCAT(firstname,' ',lastname) AS text"), 'id', 'users.meta', 'status')
                ->where('meta->agency', $agency)
                ->where('meta->super_position', $superPosition)
                ->get()
                ->toArray();
        } catch (Exception $exception) {
            return $response['error'] = 'There are errors in the Function: getAllEmployeesBySuperPositionAndAgengy ' . $exception->getMessage();
        }
    }
}
