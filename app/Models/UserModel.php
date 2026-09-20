<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'employee_id', 'username', 'password_hash', 'nafath_ref', 'is_active',
        'failed_logins', 'locked_until', 'last_login_at',
    ];

    public const MAX_FAILED_LOGINS = 5;
    public const LOCK_MINUTES      = 15;

    /** المستخدم مع بيانات الموظف */
    public function findForLogin(string $username): ?array
    {
        return $this->db->table('users u')
            ->select('u.*, e.full_name, e.job_title, e.employee_no, d.name_ar AS department_name')
            ->join('employees e', 'e.id = u.employee_id', 'left')
            ->join('departments d', 'd.id = e.department_id', 'left')
            ->where('u.username', $username)
            ->get()->getRowArray();
    }

    /** أدوار المستخدم (الرمز والاسم) */
    public function rolesFor(int $userId): array
    {
        return $this->db->table('user_roles ur')
            ->select('r.id, r.code, r.name_ar')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->orderBy('r.id')
            ->get()->getResultArray();
    }

    public function registerFailure(array $user): void
    {
        $failed = (int) $user['failed_logins'] + 1;
        $data   = ['failed_logins' => $failed];

        if ($failed >= self::MAX_FAILED_LOGINS) {
            $data['locked_until']  = date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60);
            $data['failed_logins'] = 0;
        }

        $this->update($user['id'], $data);
    }

    public function registerSuccess(int $userId): void
    {
        $this->update($userId, [
            'failed_logins' => 0,
            'locked_until'  => null,
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
