<?php

namespace App\Controllers;

use App\Libraries\Access;

class Approvals extends BaseController
{
    public function review(int $id)
    {
        $case = $this->loadCase($id);
        $db   = db_connect();
        $memo = $db->table('memos')->where('case_id', $id)->get()->getRowArray();
        if ($memo === null) {
            return redirect()->to(site_url("cases/{$id}/memo"))->with('error', 'لم تُعد المذكرة بعد.');
        }
        $version = (int) $db->table('memo_versions')->selectMax('version')->where('memo_id', $memo['id'])->get()->getRow('version');
        if ($version === 0) {
            return redirect()->to(site_url("cases/{$id}/memo"))->with('error', 'لم تُرسل المذكرة للمراجعة بعد.');
        }
        $snap = $db->table('memo_versions')->where(['memo_id' => $memo['id'], 'version' => $version])->get()->getRowArray();
        $doc  = array_merge($memo, json_decode($snap['snapshot'], true) ?: []);

        $steps = $db->table('approval_steps s')
            ->select('s.*, r.code AS role_code, r.name_ar AS role_name, COALESCE(e.full_name, u.username) AS approver')
            ->join('roles r', 'r.id = s.role_id')->join('users u', 'u.id = s.approver_user_id', 'left')->join('employees e', 'e.id = u.employee_id', 'left')
            ->where(['s.memo_id' => $memo['id'], 's.memo_version' => $version])->orderBy('s.step_order')->get()->getResultArray();
        foreach ($steps as &$st) {
            if (! $st['approver']) {
                $st['approver'] = $st['role_code'] === 'head' && $case['head_name'] ? $case['head_name'] : implode('، ', array_column($this->cases->usersWithRole($st['role_code']), 'name'));
            }
        }
        unset($st);

        $returns = $db->table('approval_steps s')
            ->select('s.memo_version, s.notes, s.decided_at, r.name_ar AS role_name, COALESCE(e.full_name, u.username) AS approver')
            ->join('roles r', 'r.id = s.role_id')->join('users u', 'u.id = s.approver_user_id', 'left')->join('employees e', 'e.id = u.employee_id', 'left')
            ->where(['s.memo_id' => $memo['id'], 's.decision' => 'returned'])->orderBy('s.decided_at', 'DESC')->get()->getResultArray();

        $pending = $this->cases->pendingStep((int) $memo['id'], $version);
        $canDecide = $case['stage_code'] === 'approval' && $pending !== null && Access::has($pending['role_code']);

        return view('approvals/review', [
            'title' => 'مراجعة مذكرة التحقيق',
            'subtitle' => $pending ? 'بانتظار: ' . $pending['role_name'] : 'دورة الاعتماد',
            'active' => 'approvals', 'crumbs' => [['المعاملات', 'cases'], [$case['case_no'], "cases/{$id}"], 'مراجعة المذكرة'],
            'case' => $case, 'memo' => $memo, 'doc' => $doc, 'version' => $version, 'steps' => $steps, 'returns' => $returns,
            'pending' => $pending, 'canDecide' => $canDecide, 'sections' => Memos::SECTIONS, 'submittedAt' => $snap['created_at'],
            'signatures' => array_values(array_filter($steps, fn ($s) => $s['decision'] === 'approved')),
        ]);
    }

    public function decide(int $id)
    {
        $case = $this->loadCase($id);
        $db   = db_connect();
        $memo = $db->table('memos')->where('case_id', $id)->get()->getRowArray();
        if ($memo === null || $case['stage_code'] !== 'approval') {
            return $this->deny();
        }
        $version = (int) $db->table('memo_versions')->selectMax('version')->where('memo_id', $memo['id'])->get()->getRow('version');
        $step    = $this->cases->pendingStep((int) $memo['id'], $version);
        if ($step === null || ! Access::has($step['role_code'])) {
            return $this->deny('هذه المرحلة من دورة الاعتماد ليست لدورك.');
        }
        $decision = $this->request->getPost('decision') === 'return' ? 'returned' : 'approved';
        $notes    = trim((string) $this->request->getPost('notes'));
        if ($decision === 'returned' && $notes === '') {
            return redirect()->back()->with('error', 'اكتب سبب الإعادة والملاحظات.');
        }

        $db->transStart();
        $now = date('Y-m-d H:i:s');
        $db->table('approval_steps')->where('id', $step['id'])->update([
            'decision' => $decision, 'notes' => $notes ?: null, 'approver_user_id' => $this->uid(), 'decided_at' => $now,
            'signature_ref' => $decision === 'approved' ? 'ESIG-' . strtoupper(substr(hash('sha256', $step['id'] . $this->uid() . $now), 0, 16)) : null,
        ]);

        if ($decision === 'returned') {
            $db->table('approval_steps')->where(['memo_id' => $memo['id'], 'memo_version' => $version, 'decision' => 'pending'])->delete();
            $db->table('memos')->where('id', $memo['id'])->update(['status' => 'returned', 'version' => $version + 1]);
            $this->cases->update($id, ['stage_code' => 'investigation', 'holder_role_id' => $this->cases->roleId('investigator')]);
            $this->cases->log($id, 'memo_returned', 'إعادة المذكرة للتعديل من ' . $step['role_name'], ['note' => $notes]);
            $this->cases->notifyUser((int) $case['investigator_user_id'], $id, 'decision', 'أُعيدت المذكرة للتعديل', "{$step['role_name']}: {$notes}", "cases/{$id}/memo", 'yellow');
            $msg = 'أُعيدت المذكرة إلى المحقق.';
        } else {
            $this->cases->log($id, 'step_approved', 'اعتماد المذكرة من ' . $step['role_name'], ['note' => $notes]);
            $next = $this->cases->pendingStep((int) $memo['id'], $version);
            if ($next !== null) {
                $this->cases->update($id, ['holder_role_id' => $next['role_id']]);
                $this->cases->notifyRole($next['role_code'], $id, 'approval', 'طلب اعتماد', "مذكرة المعاملة {$case['case_no']} بانتظار اعتمادك.", "cases/{$id}/review");
                $msg = 'تم الاعتماد وأُحيلت المذكرة إلى ' . $next['role_name'] . '.';
            } else {
                $db->table('memos')->where('id', $memo['id'])->update(['status' => 'approved', 'approved_at' => $now]);
                $this->cases->update($id, ['stage_code' => 'execution', 'holder_role_id' => $this->cases->roleId('legal')]);
                $this->createRecommendations($case, $memo);
                $this->cases->log($id, 'memo_approved', 'الاعتماد النهائي للمذكرة');
                $this->cases->notifyRole('legal', $id, 'decision', 'صدور قرار', "اعتُمدت مذكرة المعاملة {$case['case_no']} وتنتظر تنفيذ التوصيات.", "cases/{$id}/execution");
                $this->cases->notifyUser((int) $case['investigator_user_id'], $id, 'decision', 'صدور قرار', "اعتُمدت مذكرة المعاملة {$case['case_no']}.", "cases/{$id}/review");
                $msg = 'تم الاعتماد النهائي، وانتقلت المعاملة لتنفيذ التوصيات.';
            }
        }
        $db->transComplete();

        return redirect()->to(site_url("cases/{$id}/review"))->with('message', $msg);
    }

    /** كل سطر في قسم التوصيات يصبح توصية للمتابعة */
    private function createRecommendations(array $case, array $memo): void
    {
        $db    = db_connect();
        $lines = preg_split('/\R+/u', (string) $memo['recommendations']) ?: [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^\s*(\d+[\)\.\-]|[-•*])\s*/u', '', $line));
            if (mb_strlen($line) < 3) {
                continue;
            }
            $db->table('recommendations')->insert([
                'case_id' => $case['id'], 'memo_id' => $memo['id'], 'body' => $line,
                'responsible_department_id' => $case['department_id'] ?: (int) $db->table('departments')->select('id')->limit(1)->get()->getRow('id'),
                'due_date' => date('Y-m-d', strtotime('+30 days')),
            ]);
        }
    }
}
