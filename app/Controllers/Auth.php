<?php

namespace App\Controllers;

use App\Models\UserModel;

class Auth extends BaseController
{
    public function login()
    {
        if (session()->get('user_id')) {
            return redirect()->to(site_url('dashboard'));
        }

        return view('auth/login');
    }

    public function attempt()
    {
        $rules = [
            'username' => 'required|max_length[60]',
            'password' => 'required|max_length[255]',
            'ack_confidentiality' => 'required',
            'ack_accuracy'        => 'required',
        ];
        $messages = [
            'username' => ['required' => 'اكتب اسم المستخدم.'],
            'password' => ['required' => 'اكتب كلمة المرور.'],
            'ack_confidentiality' => ['required' => 'يجب الإقرار بالمحافظة على سرية المعلومات.'],
            'ack_accuracy'        => ['required' => 'يجب الإقرار بصحة المعلومات.'],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $users    = new UserModel();
        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');
        $user     = $users->findForLogin($username);
        $generic  = 'اسم المستخدم أو كلمة المرور غير صحيحة.';

        if ($user === null || empty($user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', $generic);
        }

        if (! (int) $user['is_active']) {
            return redirect()->back()->withInput()->with('error', 'الحساب غير مفعّل. تواصل مع مدير النظام.');
        }

        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            $minutes = (int) ceil((strtotime($user['locked_until']) - time()) / 60);

            return redirect()->back()->withInput()
                ->with('error', "تم إيقاف الحساب مؤقتاً بسبب محاولات خاطئة متكررة. حاول بعد {$minutes} دقيقة.");
        }

        if (! password_verify($password, $user['password_hash'])) {
            $users->registerFailure($user);

            return redirect()->back()->withInput()->with('error', $generic);
        }

        $roles = $users->rolesFor((int) $user['id']);
        if ($roles === []) {
            return redirect()->back()->withInput()->with('error', 'لا توجد صلاحيات مرتبطة بهذا الحساب.');
        }

        $users->registerSuccess((int) $user['id']);

        db_connect()->table('login_acknowledgements')->insert([
            'user_id'         => $user['id'],
            'confidentiality' => 1,
            'accuracy'        => 1,
            'ip_address'      => $this->request->getIPAddress(),
        ]);

        session()->regenerate(true);
        session()->set([
            'user_id'    => (int) $user['id'],
            'username'   => $user['username'],
            'full_name'  => $user['full_name'] ?: $user['username'],
            'job_title'  => $user['job_title'] ?? '',
            'roles'      => array_column($roles, 'code'),
            'role_names' => array_column($roles, 'name_ar'),
            'role_ids'   => array_map('intval', array_column($roles, 'id')),
        ]);

        return redirect()->to(site_url('dashboard'));
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to(site_url('login'))->with('message', 'تم تسجيل الخروج.');
    }
}
