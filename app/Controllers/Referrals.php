<?php

namespace App\Controllers;

use App\Libraries\Access;

class Referrals extends BaseController
{
    /** الإجراءات المتاحة للمستخدم حسب المرحلة */
    private function actions(array $case): array
    {
        $a = [];
        if ($case['stage_code'] === 'new' && Access::has('legal')) {
            $a = ['refer_head', 'refer_gm', 'close'];
        } elseif ($case['stage_code'] === 'with_gm' && Access::has('gm')) {
            $a = ['refer_head', 'close'];
        } elseif ($case['stage_code'] === 'referred' && Access::has('head')) {
            $a = ['assign'];
        }

        return $a;
    }

    public function show(int $id)
    {
        $case = $this->loadCase($id);
        $db   = db_connect();
        $hist = $db->table('referrals f')
            ->select('f.*, COALESCE(fe.full_name, fu.username) AS from_name, COALESCE(te.full_name, tu.username) AS to_name, r.name_ar AS role_name')
            ->join('users fu', 'fu.id = f.from_user_id')->join('employees fe', 'fe.id = fu.employee_id', 'left')
            ->join('users tu', 'tu.id = f.to_user_id', 'left')->join('employees te', 'te.id = tu.employee_id', 'left')
            ->join('roles r', 'r.id = f.to_role_id')
            ->where('f.case_id', $id)->orderBy('f.created_at')->get()->getResultArray();

        return view('cases/referral', [
            'title' => 'إحالة المعاملة', 'subtitle' => 'توجيه المعاملة إلى الجهة المختصة لبدء الإجراءات',
            'active' => 'referred', 'crumbs' => [['المعاملات', 'cases'], [$case['case_no'], "cases/{$id}"], 'إحالة المعاملة'],
            'case' => $case, 'parties' => $this->cases->parties($id), 'actions' => $this->actions($case), 'history' => $hist,
            'heads' => $this->cases->usersWithRole('head'), 'investigators' => $this->cases->usersWithRole('investigator'),
        ]);
    }

    public function act(int $id)
    {
        $case    = $this->loadCase($id);
        $action  = (string) $this->request->getPost('action');
        $allowed = $this->actions($case);
        if (! in_array($action, $allowed, true)) {
            return $this->deny();
        }
        $reason = trim((string) $this->request->getPost('reason'));
        $notes  = trim((string) $this->request->getPost('notes'));
        $db     = db_connect();
        $db->transStart();

        switch ($action) {
            case 'refer_head':
                $headId = (int) $this->request->getPost('head_user_id');
                if (! in_array($headId, array_map('intval', array_column($this->cases->usersWithRole('head'), 'id')), true)) {
                    return redirect()->back()->withInput()->with('error', 'اختر رئيس التحقيقات.');
                }
                $this->insertReferral($id, 'refer_head', 'head', $headId, $reason, $notes);
                $this->cases->update($id, ['stage_code' => 'referred', 'holder_role_id' => $this->cases->roleId('head'), 'head_user_id' => $headId]);
                $this->cases->log($id, 'referred', 'إحالة المعاملة إلى رئيس التحقيقات', ['note' => $notes ?: $reason]);
                $this->cases->notifyUser($headId, $id, 'new_case', 'معاملة محالة إليك', "أُحيلت إليك المعاملة {$case['case_no']} لتعيين محقق.", "cases/{$id}/referral");
                $msg = 'تمت إحالة المعاملة إلى رئيس التحقيقات.';
                break;

            case 'refer_gm':
                $this->insertReferral($id, 'refer_gm', 'gm', null, $reason, $notes);
                $this->cases->update($id, ['stage_code' => 'with_gm', 'holder_role_id' => $this->cases->roleId('gm'), 'via_gm' => 1]);
                $this->cases->log($id, 'referred_gm', 'موافقة وإحالة للمدير العام التنفيذي', ['note' => $notes ?: $reason]);
                $this->cases->notifyRole('gm', $id, 'new_case', 'معاملة للاطلاع والتوجيه', "أُحيلت المعاملة {$case['case_no']} للمدير العام التنفيذي.", "cases/{$id}/referral");
                $msg = 'تمت إحالة المعاملة إلى المدير العام التنفيذي.';
                break;

            case 'assign':
                $invId = (int) $this->request->getPost('investigator_user_id');
                if (! in_array($invId, array_map('intval', array_column($this->cases->usersWithRole('investigator'), 'id')), true)) {
                    return redirect()->back()->withInput()->with('error', 'اختر المحقق.');
                }
                $this->insertReferral($id, 'assign_investigator', 'investigator', $invId, $reason, $notes);
                $this->cases->update($id, ['stage_code' => 'investigation', 'holder_role_id' => $this->cases->roleId('investigator'), 'investigator_user_id' => $invId]);
                $this->cases->log($id, 'investigator_assigned', 'تعيين المحقق', ['note' => $notes ?: $reason]);
                $this->cases->notifyUser($invId, $id, 'new_case', 'معاملة مسندة إليك', "أُسندت إليك المعاملة {$case['case_no']} للتحقيق.", "cases/{$id}");
                $msg = 'تم تعيين المحقق وبدأت مرحلة التحقيق.';
                break;

            default: // close
                if ($notes === '') {
                    return redirect()->back()->withInput()->with('error', 'اكتب سبب الحفظ في الملاحظات.');
                }
                $this->cases->update($id, ['stage_code' => 'closed_no_action', 'holder_role_id' => null, 'closed_at' => date('Y-m-d H:i:s')]);
                $this->cases->log($id, 'closed_no_action', 'حفظ المعاملة دون إجراء', ['note' => $notes]);
                $this->cases->notifyUser((int) $case['created_by'], $id, 'decision', 'قرار على معاملة', "حُفظت المعاملة {$case['case_no']} دون إجراء.", "cases/{$id}");
                $msg = 'تم حفظ المعاملة دون إجراء.';
        }

        $db->transComplete();

        return redirect()->to(site_url("cases/{$id}"))->with('message', $msg);
    }

    private function insertReferral(int $caseId, string $action, string $role, ?int $toUser, string $reason, string $notes): void
    {
        db_connect()->table('referrals')->insert([
            'case_id' => $caseId, 'action' => $action, 'from_user_id' => $this->uid(),
            'to_role_id' => $this->cases->roleId($role), 'to_user_id' => $toUser,
            'reason' => $reason ?: null, 'notes' => $notes ?: null,
        ]);
    }
}
