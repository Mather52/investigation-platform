<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/** إدارة المستخدمين والموظفين والإدارات ومدد الإنذار (مدير النظام) */
class Users extends BaseController
{
    public function index()
    {
        $db  = db_connect();
        $tab = $this->request->getGet('tab') ?? 'users';

        $users = $db->table('users u')
            ->select("u.*, e.full_name, e.employee_no, d.name_ar AS department_name, GROUP_CONCAT(r.code ORDER BY r.id) AS role_codes")
            ->join('employees e', 'e.id = u.employee_id', 'left')->join('departments d', 'd.id = e.department_id', 'left')
            ->join('user_roles ur', 'ur.user_id = u.id', 'left')->join('roles r', 'r.id = ur.role_id', 'left')
            ->groupBy('u.id')->orderBy('e.full_name')->get()->getResultArray();

        return view('admin/index', [
            'title' => 'المستخدمون والإعدادات', 'subtitle' => 'الحسابات والصلاحيات وبيانات الموظفين ومدد الإنذار', 'active' => 'admin',
            'crumbs' => ['المستخدمون والإعدادات'], 'tab' => $tab, 'users' => $users,
            'roles' => $db->table('roles')->orderBy('id')->get()->getResultArray(),
            'employees' => $db->table('employees e')->select('e.*, d.name_ar AS department_name, u.id AS user_id')
                ->join('departments d', 'd.id = e.department_id', 'left')->join('users u', 'u.employee_id = e.id', 'left')
                ->orderBy('e.full_name')->get()->getResultArray(),
            'departments' => $db->table('departments d')->select('d.*, (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id) AS emp_count')->orderBy('d.name_ar')->get()->getResultArray(),
            'settings' => $db->table('alert_settings')->get()->getResultArray(),
            'edit' => (int) ($this->request->getGet('edit') ?? 0),
        ]);
    }

    public function store()
    {
        $rules = [
            'employee_id' => 'required|is_not_unique[employees.id]|is_unique[users.employee_id]',
            'username'    => 'required|alpha_numeric_punct|min_length[3]|max_length[60]|is_unique[users.username]',
            'password'    => 'required|min_length[8]',
        ];
        $msgs = [
            'employee_id' => ['required' => 'اختر الموظف.', 'is_unique' => 'لهذا الموظف حساب مسبقاً.'],
            'username' => ['required' => 'اكتب اسم المستخدم.', 'is_unique' => 'اسم المستخدم مستخدم مسبقاً.', 'min_length' => 'اسم المستخدم 3 أحرف على الأقل.'],
            'password' => ['required' => 'اكتب كلمة المرور.', 'min_length' => 'كلمة المرور 8 أحرف على الأقل.'],
        ];
        if (! $this->validate($rules, $msgs)) {
            return redirect()->to(site_url('admin/users'))->withInput()->with('errors', $this->validator->getErrors());
        }
        $roles = array_map('intval', (array) $this->request->getPost('roles'));
        if ($roles === []) {
            return redirect()->to(site_url('admin/users'))->withInput()->with('error', 'اختر دوراً واحداً على الأقل.');
        }
        $db = db_connect();
        $db->transStart();
        $db->table('users')->insert([
            'employee_id' => (int) $this->request->getPost('employee_id'),
            'username' => $this->request->getPost('username'),
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
        ]);
        $uid = (int) $db->insertID();
        foreach ($roles as $r) {
            $db->table('user_roles')->insert(['user_id' => $uid, 'role_id' => $r]);
        }
        $db->transComplete();

        return redirect()->to(site_url('admin/users'))->with('message', 'تم إنشاء الحساب.');
    }

    public function update(int $uid)
    {
        $db   = db_connect();
        $user = $db->table('users')->where('id', $uid)->get()->getRowArray();
        if ($user === null) {
            return redirect()->to(site_url('admin/users'));
        }
        $roles = array_map('intval', (array) $this->request->getPost('roles'));
        if ($roles === []) {
            return redirect()->back()->with('error', 'اختر دوراً واحداً على الأقل.');
        }
        $active = (int) (bool) $this->request->getPost('is_active');
        if ($uid === $this->uid() && ($active === 0 || ! in_array($this->cases->roleId('admin'), $roles, true))) {
            return redirect()->back()->with('error', 'لا يمكنك إيقاف حسابك أو إزالة صلاحية مدير النظام عن نفسك.');
        }
        $data = ['is_active' => $active];
        $pass = (string) $this->request->getPost('password');
        if ($pass !== '') {
            if (strlen($pass) < 8) {
                return redirect()->back()->with('error', 'كلمة المرور 8 أحرف على الأقل.');
            }
            $data += ['password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'failed_logins' => 0, 'locked_until' => null];
        }
        if ($this->request->getPost('unlock')) {
            $data += ['failed_logins' => 0, 'locked_until' => null];
        }
        $db->transStart();
        $db->table('users')->where('id', $uid)->update($data);
        $db->table('user_roles')->where('user_id', $uid)->delete();
        foreach ($roles as $r) {
            $db->table('user_roles')->insert(['user_id' => $uid, 'role_id' => $r]);
        }
        $db->transComplete();

        return redirect()->to(site_url('admin/users'))->with('message', 'تم تحديث الحساب. تسري الأدوار الجديدة عند الدخول التالي للمستخدم.');
    }

    public function storeEmployee()
    {
        $rules = [
            'employee_no' => 'required|max_length[20]|is_unique[employees.employee_no]',
            'full_name' => 'required|max_length[150]',
            'department_id' => 'required|is_not_unique[departments.id]',
            'email' => 'permit_empty|valid_email', 'mobile' => 'permit_empty|max_length[20]', 'job_title' => 'permit_empty|max_length[150]',
        ];
        $msgs = ['employee_no' => ['required' => 'اكتب الرقم الوظيفي.', 'is_unique' => 'الرقم الوظيفي مسجل مسبقاً.'], 'full_name' => ['required' => 'اكتب الاسم.'], 'department_id' => ['required' => 'اختر الإدارة.'], 'email' => ['valid_email' => 'البريد الإلكتروني غير صحيح.']];
        if (! $this->validate($rules, $msgs)) {
            return redirect()->to(site_url('admin/users?tab=employees'))->withInput()->with('errors', $this->validator->getErrors());
        }
        db_connect()->table('employees')->insert([
            'employee_no' => $this->request->getPost('employee_no'), 'full_name' => $this->request->getPost('full_name'),
            'job_title' => $this->request->getPost('job_title') ?: null, 'department_id' => (int) $this->request->getPost('department_id'),
            'email' => $this->request->getPost('email') ?: null, 'mobile' => $this->request->getPost('mobile') ?: null,
        ]);

        return redirect()->to(site_url('admin/users?tab=employees'))->with('message', 'تمت إضافة الموظف.');
    }

    public function storeDepartment()
    {
        $rules = ['name_ar' => 'required|max_length[150]', 'code' => 'permit_empty|max_length[30]|is_unique[departments.code]'];
        $msgs  = ['name_ar' => ['required' => 'اكتب اسم الإدارة.'], 'code' => ['is_unique' => 'الرمز مستخدم لإدارة أخرى.']];
        if (! $this->validate($rules, $msgs)) {
            return redirect()->to(site_url('admin/users?tab=departments'))->withInput()->with('errors', $this->validator->getErrors());
        }
        $name = trim((string) $this->request->getPost('name_ar'));
        db_connect()->table('departments')->insert(['name_ar' => mb_substr($name, 0, 150), 'code' => trim((string) $this->request->getPost('code')) ?: null]);

        return redirect()->to(site_url('admin/users?tab=departments'))->with('message', 'أُضيفت الإدارة.');
    }

    public function settings()
    {
        $db = db_connect();
        foreach ((array) $this->request->getPost('s') as $code => $v) {
            $g = (int) ($v['green_days'] ?? 0);
            $y = (int) ($v['yellow_days'] ?? 0);
            $r = (int) ($v['red_days'] ?? 0);
            if (! ($g > 0 && $g < $y && $y < $r && $r <= 90)) {
                return redirect()->to(site_url('admin/users?tab=settings'))->with('error', 'المدد يجب أن تكون تصاعدية: الأخضر أقل من الأصفر، والأصفر أقل من الأحمر.');
            }
            $db->table('alert_settings')->where('rule_code', $code)->update(['green_days' => $g, 'yellow_days' => $y, 'red_days' => $r]);
        }

        return redirect()->to(site_url('admin/users?tab=settings'))->with('message', 'حُفظت مدد الإنذار.');
    }
}
