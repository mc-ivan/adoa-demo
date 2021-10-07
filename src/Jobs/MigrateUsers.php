<?php

namespace ProcessMaker\Package\Adoa\Jobs;

use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Log;
use ProcessMaker\Jobs\ThrowSignalEvent;
use ProcessMaker\Models\User;
use Throwable;

class MigrateUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $users = [];

    private $createdUsers = 0;

    private $updatedUsers = 0;

    private $password;

    private $url;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 7200;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($url)
    {
        $this->url = $url;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->generatePassword();
        $this->loadExistingUsers();
        $this->deactivateExistingUsers();
        $this->importAdoaExternalUsers([$this, 'saveUserInformation']);

        ThrowSignalEvent::dispatch('adoa_migration', [
            'created_users' => $this->createdUsers,
            'updated_users' => $this->updatedUsers,
        ]);
    }

    private function loadExistingUsers()
    {
        User::select('id', 'username')->get()->each(function($user) {
            $this->users[$user->id] = $user->username;
        });
    }

    private function deactivateExistingUsers()
    {
        User::where('status', 'ACTIVE')
            ->where('username', '!=', 'admin')
            ->where('username', '!=', '_pm4_anon_user')
            ->whereNotIn('id', function($query) {
                $query->selectRaw('member_id')
                    ->from('group_members')
                    ->where('group_id', 8);
            })
            ->update(['status' => 'INACTIVE']);
    }

    public function importAdoaExternalUsers($callback)
    {
        $csvPath = $this->download();
        $this->readCsv($csvPath, $callback);
        unlink($csvPath);
    }

    private function newOrExistingUser($import)
    {
        $id = array_search($import['EMPLOYEE'], $this->users);

        if ($id !== false) {
            $this->updatedUsers++;
            return User::find($id);
        } else {
            $this->createdUsers++;
            return new User;
        }
    }

    private function generateEmail($import)
    {
        return trim($import['EMPLOYEE']) . '@hris.az.gov';
    }

    private function generatePassword()
    {
        $this->password = Hash::make(Str::random(20));
    }

    private function saveUserInformation($import)
    {
        $user = $this->newOrExistingUser($import);

        $user->fill([
            'email' => $this->generateEmail($import),
            'firstname' => trim($import['FIRST_NAME']),
            'lastname' => trim($import['LAST_NAME']),
            'username' => trim($import['EMPLOYEE']),
            //'password' => $this->password,
            'address' => trim($import['ADDRESS']),
            'phone' => trim($import['WORK_PHONE']),
            'is_administrator' => false,
            'status' => 'ACTIVE',
            'meta' => [
                'ein' => trim($import['EMPLOYEE']),
                'email' => trim($import['WORK_EMAIL']),
                'position' => trim($import['POSITION']),
                'manager' => trim($import['MANAGER']),
                'super_position' => trim($import['SUPER_POSITION']),
                'title' => trim($import['TITLE']),
                'agency' => trim($import['AGENCY']),
                'agency_name' => trim($import['AGENCY_NAME']),
                'process_level' => trim($import['PROCESS_LEVEL']),
                'department' => trim($import['DEPARTMENT']),
                'term_date' => trim($import['TERM_DATE']),
                'flsa_status' => trim($import['FLSA_STATUS']),
                'indirect_super_position' => trim($import['INDIRECT_SUPER_POSITION'])
            ],
        ]);

        try {
            $user->save();
        } catch (Throwable $e) {
            Log::error('Unable to import ADOA user ' . $import['EMPLOYEE'], [
                'error' => $e->getMessage(),
                'adoa_user' => $import,
            ]);
        }

        $groups = [];
        $groups[] = config('adoa.employee_group_id');

        if (trim($import['MANAGER']) == 'Y') {
            $groups[] = config('adoa.manager_group_id');
        }

        try {
            $user->groups()->sync($groups);
        } catch (Throwable $e) {
            Log::error('Unable to update groups for ADOA user ' . $import['EMPLOYEE'], [
                'error' => $e->getMessage(),
                'adoa_user' => $import,
            ]);
        }
    }

    private function readCsv($csvPath, $callback)
    {
        $handle = fopen($csvPath, "r");
        $row = 0;
        $headers = null;
        while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
            if ($row === 0) {
                $headers = $data;
                $row++;
                continue;
            }
            $data = array_combine($headers, $data);
            $response = $callback($data, $row);
            if ($response === false) {
                break;
            }
            $row++;
        }
        fclose($handle);
    }

    private function download()
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'import');
        $this->client()->request('GET', $this->url, ['sink' => $tempPath]);
        return $tempPath;
    }

    public function client()
    {
        $adoaHeaders = [
            "Authorization" => "Bearer 3-5738379ecfaa4e9fb2eda707779732c7"
        ];

        return new Client([
            'headers' => $adoaHeaders
        ]);
    }
}
