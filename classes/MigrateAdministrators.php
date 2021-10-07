<?php

namespace ProcessMaker\Adoa\classes;

use ProcessMaker\Package\Adoa\Models\AdoaUsers;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Client;
use Exception;
use DB;

class MigrateAdministrators
{
    public function migrateAdminInformation($groupId)
    {
        try {
            $adoaUsers = new AdoaUsers();
            $usersList = $adoaUsers->getAllUsersByEin();
            $adoaAdministrators = $this->getAdoaExternalAdministrators();
            $updatedUsers = 0;
            $createdUsers = 0;

            if (!empty($usersList)) {
                foreach ($usersList as $userId) {
                    $localUsersList[$userId['username']] = $userId['id'];
                }
            }

            if (!empty($adoaAdministrators->rows)) {
                foreach ($adoaAdministrators->rows as $administrator) {
                    if (!empty($localUsersList[$administrator[0]])) {

                        $UserEmail = $administrator[0] . '@hris.az.gov';

                        $metaEmail = '';
                        if (!empty($administrator[8])) {
                            $metaEmail = trim($administrator[8]);
                        }

                        $metaUpdateInformationData = array(
                            'ein' => $administrator[5],
                            'email' => $metaEmail,
                            'agency' => $administrator[1],
                            'employee_process_level' => $administrator[2],
                            'pm_process_id' => $administrator[3],
                            'update_date' => $administrator[4],
                        );

                        $metaUpdateInformationData = json_encode($metaUpdateInformationData);


                        $updateUserData = array (
                            'id' => $localUsersList[$administrator[0]],
                            'email' => $UserEmail,
                            'firstname'=> $administrator[6],
                            'lastname'=> $administrator[7],
                            'is_administrator'=> true,
                            'status'=> 'ACTIVE',
                            'meta' => $metaUpdateInformationData,
                            'updated_at'=> date('Y-m-d H:i:s'),
                        );

                        $updated = $adoaUsers->updateUser($updateUserData);
                        if ($updated == 1) {
                            $groupMember = array(
                                'group_id' => $groupId,
                                'member_type' => 'ProcessMaker\Models\User',
                                'member_id' => $localUsersList[$administrator[1]],
                                'created_at' => date('Y-m-d H:i:s')
                            );

                            $updatedUsers = $updatedUsers + 1;
                        }
                    } elseif (empty($localUsersList[$administrator[0]])) {

                        $UserEmail = $administrator[0] . '@hris.az.gov';

                        $metaEmail = '';
                        if (!empty($administrator[8])) {
                            $metaEmail = trim($administrator[8]);
                        }

                        $metaInformationData = array(
                            'ein' => $administrator[5],
                            'email' => $metaEmail,
                            'agency' => $administrator[1],
                            'employee_process_level' => $administrator[2],
                            'pm_process_id' => $administrator[3],
                            'update_date' => $administrator[4],
                        );
                        $metaInformationData = json_encode($metaInformationData);

                        $password = Hash::make('p^@)YUvVB"j4.J*F');
                        $newUserData = array(
                            'email' => $UserEmail,
                            'firstname'=> $administrator[6],
                            'lastname'=> $administrator[7],
                            'username'=> $administrator[0],
                            'password'=> $password,
                            'is_administrator'=> true,
                            'status'=> 'ACTIVE',
                            'meta' => $metaInformationData,
                            'created_at'=> date('Y-m-d H:i:s'),
                        );

                        $userUid = $adoaUsers->insertUser($newUserData);

                        if (!empty($userUid)) {
                            $groupMember = array(
                                'group_id' => $groupId,
                                'member_type' => 'ProcessMaker\Models\User',
                                'member_id' => $userUid,
                                'created_at' => date('Y-m-d H:i:s')
                            );

                            DB::table('group_members')
                                ->insert($groupMember);
                            $createdUsers = $createdUsers + 1;
                        }
                    }
                }
            }
            $total = array (
                'createdUsers' => $createdUsers,
                'updatedUsers' => $updatedUsers
            );

            return $total;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: migrateAdminInformation ' . $error->getMessage();
        }
    }

    public function getAdoaExternalAdministrators()
    {
        try {
            $adoaHeaders = array(
                "Accept: application/json",
                "Authorization: Bearer 3-5738379ecfaa4e9fb2eda707779732c7",
            );
            //$url = 'https://hrsieapi.azdoa.gov/api/hrorg/PMAgencyAdmins.json';
            $url = 'https://hrsieapitest.azdoa.gov/api/hrorg/PMAgencyAdmins.json';

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $adoaHeaders);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            $resp = curl_exec($curl);
            curl_close($curl);

            $userInformationList = json_decode($resp);
            return $userInformationList;
        } catch (Exception $error) {
            return $response['error'] = 'There are errors in the Function: getAdoaExternalUsers ' . $error->getMessage();
        }
    }
}
