<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * إنشاء مستخدم من سطر الأوامر:
 *   php spark user:create tester "Admin@12345" --roles all --name "مستخدم اختبار"
 */
class CreateUser extends BaseCommand
{
    protected $group       = 'Investigation';
    protected $name        = 'user:create';
    protected $description = 'إنشاء مستخدم للمنصة أو تحديث كلمة مروره وأدواره.';
    protected $usage       = 'user:create <username> <password> [--roles all|legal,head,...] [--name "الاسم"]';
    protected $arguments   = [
        'username' => 'اسم المستخدم',
        'password' => 'كلمة المرور (8 أحرف على الأقل)',
    ];
    protected $options = [
        '--roles' => 'all لكل الأدوار، أو رموز مفصولة بفاصلة: employee,investigator,head,legal,gm,admin',
        '--name'  => 'الاسم الظاهر (ينشئ سجل موظف تجريبي)',
    ];

    public function run(array $params)
    {
        $username = $params[0] ?? CLI::prompt('اسم المستخدم', null, 'required');
        $password = $params[1] ?? CLI::prompt('كلمة المرور', null, 'required');
        $rolesArg = (string) (CLI::getOption('roles') ?? 'all');
        $name     = (string) (CLI::getOption('name') ?? $username);

        if (strlen($password) < 8) {
            CLI::error('كلمة المرور يجب أن تكون 8 أحرف على الأقل.');

            return EXIT_ERROR;
        }

        $db    = db_connect();
        $roles = $db->table('roles')->get()->getResultArray();
        $codes = array_column($roles, 'id', 'code');

        $wanted = $rolesArg === 'all' ? array_keys($codes) : array_map('trim', explode(',', $rolesArg));
        $unknown = array_diff($wanted, array_keys($codes));
        if ($unknown !== []) {
            CLI::error('أدوار غير معروفة: ' . implode(', ', $unknown));

            return EXIT_ERROR;
        }

        $db->transStart();

        $user = $db->table('users')->where('username', $username)->get()->getRowArray();

        if ($user === null) {
            $empNo = 'T' . substr((string) time(), -6);
            $db->table('employees')->insert([
                'employee_no' => $empNo,
                'full_name'   => $name,
                'job_title'   => 'حساب اختبار',
            ]);
            $db->table('users')->insert([
                'employee_id'   => $db->insertID(),
                'username'      => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $db->insertID();
            $action = 'تم إنشاء';
        } else {
            $userId = (int) $user['id'];
            $db->table('users')->where('id', $userId)->update([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'is_active'     => 1,
                'failed_logins' => 0,
                'locked_until'  => null,
            ]);
            $db->table('user_roles')->where('user_id', $userId)->delete();
            $action = 'تم تحديث';
        }

        foreach ($wanted as $code) {
            $db->table('user_roles')->insert(['user_id' => $userId, 'role_id' => $codes[$code]]);
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            CLI::error('تعذر حفظ المستخدم.');

            return EXIT_ERROR;
        }

        CLI::write("{$action} المستخدم {$username} بالأدوار: " . implode(', ', $wanted), 'green');

        return EXIT_SUCCESS;
    }
}
