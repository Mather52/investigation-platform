<?php

namespace App\Controllers;

use App\Libraries\Access;
use App\Libraries\CaseService;

class Memos extends BaseController
{
    public const SECTIONS = [
        'opening' => 'الافتتاحية',
        'parties_info' => 'البيانات الأساسية للموظف / الشهود',
        'topics' => 'موضوعات التحقيق',
        'findings' => 'النتائج',
        'recommendations' => 'التوصيات',
    ];

    private function memo(array $case): array
    {
        $db   = db_connect();
        $memo = $db->table('memos')->where('case_id', $case['id'])->get()->getRowArray();
        if ($memo === null) {
            $db->table('memos')->insert(['case_id' => $case['id'], 'prepared_by' => $case['investigator_user_id'] ?: $this->uid()]);
            $memo = $db->table('memos')->where('case_id', $case['id'])->get()->getRowArray();
        }

        return $memo;
    }

    private function canEdit(array $case, array $memo): bool
    {
        return $this->cases->canInvestigate($case) && in_array($memo['status'], ['draft', 'returned'], true)
            || (Access::hasAny(['legal']) && $case['stage_code'] === 'investigation');
    }

    /** نص مقترح يُبنى من بيانات المعاملة */
    private function suggestions(array $case, array $parties): array
    {
        $db   = db_connect();
        $info = [];
        foreach ($parties as $p) {
            $info[] = '- ' . label('party', $p['party_role']) . ': ' . $p['name'] . ($p['job_title'] ? ' — ' . $p['job_title'] : '') . ($p['employee_no'] ? ' — الرقم الوظيفي ' . $p['employee_no'] : '') . ($p['department_name'] ? ' — ' . $p['department_name'] : '');
        }
        $topics = array_column($db->table('investigation_topics')->where('case_id', $case['id'])->orderBy('sort_order')->get()->getResultArray(), 'title');

        return [
            'opening' => "سعادة مدير إدارة الشؤون القانونية والالتزام،\nإشارة إلى المعاملة رقم {$case['case_no']} بشأن: {$case['subject']}، المحالة إلى قسم التحقيق، نرفع لسعادتكم نتائج التحقيق الذي أُجري بشأنها.",
            'parties_info' => implode("\n", $info),
            'topics' => implode("\n", array_map(static fn ($t, $i) => ($i + 1) . ') ' . $t, $topics, array_keys($topics))),
        ];
    }

    public function edit(int $id)
    {
        $case    = $this->loadCase($id);
        $memo    = $this->memo($case);
        $parties = $this->cases->parties($id);
        $lastReturn = db_connect()->table('approval_steps s')->select('s.notes, s.decided_at, r.name_ar AS role_name, COALESCE(e.full_name, u.username) AS approver')
            ->join('roles r', 'r.id = s.role_id')->join('users u', 'u.id = s.approver_user_id', 'left')->join('employees e', 'e.id = u.employee_id', 'left')
            ->where(['s.memo_id' => $memo['id'], 's.decision' => 'returned'])->orderBy('s.decided_at', 'DESC')->limit(1)->get()->getRowArray();

        return view('memos/edit', [
            'title' => 'مذكرة التحقيق التفصيلية', 'subtitle' => 'إعداد مذكرة العرض بأقسامها الخمسة', 'active' => 'memos',
            'crumbs' => [['المعاملات', 'cases'], [$case['case_no'], "cases/{$id}"], 'مذكرة التحقيق'],
            'case' => $case, 'memo' => $memo, 'parties' => $parties, 'sections' => self::SECTIONS,
            'suggest' => $this->suggestions($case, $parties), 'canEdit' => $this->canEdit($case, $memo), 'lastReturn' => $lastReturn,
        ]);
    }

    public function save(int $id)
    {
        $case = $this->loadCase($id);
        $memo = $this->memo($case);
        if (! $this->canEdit($case, $memo)) {
            return $this->deny('لا يمكن تعديل المذكرة في هذه المرحلة.');
        }
        $data = [];
        foreach (array_keys(self::SECTIONS) as $k) {
            $data[$k] = trim((string) $this->request->getPost($k)) ?: null;
        }
        $db = db_connect();
        $db->table('memos')->where('id', $memo['id'])->update($data);

        if ($this->request->getPost('action') !== 'submit') {
            $this->cases->log($id, 'memo_saved', 'حفظ مسودة المذكرة (النسخة ' . $memo['version'] . ')');

            return redirect()->to(site_url("cases/{$id}/memo"))->with('message', 'حُفظت المسودة.');
        }

        $missing = array_keys(array_filter($data, static fn ($v) => $v === null));
        if ($missing !== []) {
            return redirect()->to(site_url("cases/{$id}/memo"))->with('error', 'أكمل كل الأقسام قبل الإرسال: ' . implode('، ', array_map(static fn ($k) => self::SECTIONS[$k], $missing)));
        }
        if (! $this->cases->canInvestigate($case)) {
            return $this->deny('الإرسال للمراجعة يتم من المحقق.');
        }

        $db->transStart();
        $version = (int) $memo['version'];
        $db->table('memo_versions')->insert([
            'memo_id' => $memo['id'], 'version' => $version, 'created_by' => $this->uid(),
            'snapshot' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
        foreach (CaseService::APPROVAL_CHAIN as $order => $role) {
            $db->table('approval_steps')->insert(['memo_id' => $memo['id'], 'memo_version' => $version, 'step_order' => $order, 'role_id' => $this->cases->roleId($role)]);
        }
        $db->table('memos')->where('id', $memo['id'])->update(['status' => 'submitted', 'submitted_at' => date('Y-m-d H:i:s')]);
        $this->cases->update($id, ['stage_code' => 'approval', 'holder_role_id' => $this->cases->roleId('head')]);
        $this->cases->log($id, 'memo_submitted', 'إرسال المذكرة للمراجعة (النسخة ' . $version . ')');
        if ($case['head_user_id']) {
            $this->cases->notifyUser((int) $case['head_user_id'], $id, 'approval', 'طلب اعتماد', "مذكرة المعاملة {$case['case_no']} بانتظار مراجعتك.", "cases/{$id}/review");
        } else {
            $this->cases->notifyRole('head', $id, 'approval', 'طلب اعتماد', "مذكرة المعاملة {$case['case_no']} بانتظار مراجعتك.", "cases/{$id}/review");
        }
        $db->transComplete();

        return redirect()->to(site_url("cases/{$id}/review"))->with('message', 'أُرسلت المذكرة إلى رئيس التحقيقات للمراجعة.');
    }

    public function preview(int $id)
    {
        $case = $this->loadCase($id);
        $memo = $this->memo($case);

        return view('memos/preview', [
            'title' => 'معاينة المذكرة', 'subtitle' => 'مذكرة العرض التفصيلية — للطباعة', 'active' => 'memos',
            'crumbs' => [['المعاملات', 'cases'], [$case['case_no'], "cases/{$id}"], 'معاينة المذكرة'],
            'case' => $case, 'memo' => $memo, 'sections' => self::SECTIONS,
        ]);
    }
}
