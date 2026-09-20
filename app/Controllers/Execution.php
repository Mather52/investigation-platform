<?php

namespace App\Controllers;

use App\Libraries\Access;
use CodeIgniter\Exceptions\PageNotFoundException;

class Execution extends BaseController
{
    private function canManage(array $case): bool
    {
        return $case['stage_code'] === 'execution' && Access::has('legal');
    }

    public function index(int $id)
    {
        $case = $this->loadCase($id);
        $db   = db_connect();
        $memo = $db->table('memos')->where('case_id', $id)->get()->getRowArray();
        if ($memo === null || ! in_array($case['stage_code'], ['execution', 'archived'], true)) {
            return redirect()->to(site_url("cases/{$id}"))->with('error', 'تُتاح صفحة التنفيذ بعد الاعتماد النهائي للمذكرة.');
        }
        $recs = $db->table('recommendations r')->select('r.*, d.name_ar AS department_name')
            ->join('departments d', 'd.id = r.responsible_department_id')
            ->where('r.case_id', $id)->orderBy('r.id')->get()->getResultArray();
        $files = [];
        foreach ($this->cases->attachments($id, 'recommendation') as $a) {
            $files[$a['related_id']][] = $a;
        }
        $gm = $db->table('approval_steps s')->select('COALESCE(e.full_name, u.username) AS name, s.decided_at')
            ->join('users u', 'u.id = s.approver_user_id')->join('employees e', 'e.id = u.employee_id', 'left')
            ->where(['s.memo_id' => $memo['id'], 's.decision' => 'approved'])->orderBy('s.step_order', 'DESC')->limit(1)->get()->getRowArray();

        return view('execution/index', [
            'title' => 'تنفيذ التوصيات', 'subtitle' => 'متابعة تنفيذ التوصيات المعتمدة وأرشفة المعاملة', 'active' => $case['stage_code'] === 'archived' ? 'archive' : 'recs',
            'crumbs' => [['المعاملات', 'cases'], [$case['case_no'], "cases/{$id}"], 'تنفيذ التوصيات'],
            'case' => $case, 'memo' => $memo, 'recs' => $recs, 'files' => $files, 'gm' => $gm, 'canManage' => $this->canManage($case),
            'departments' => $db->table('departments')->where('is_active', 1)->orderBy('name_ar')->get()->getResultArray(),
            'edit' => (int) ($this->request->getGet('edit') ?? ($recs[0]['id'] ?? 0)),
        ]);
    }

    public function storeRecommendation(int $id)
    {
        $case = $this->loadCase($id);
        if (! $this->canManage($case)) {
            return $this->deny();
        }
        $body = trim((string) $this->request->getPost('body'));
        $dept = (int) $this->request->getPost('responsible_department_id');
        if ($body === '' || ! $dept) {
            return redirect()->back()->with('error', 'اكتب التوصية واختر الجهة المسؤولة.');
        }
        $db   = db_connect();
        $memo = $db->table('memos')->select('id')->where('case_id', $id)->get()->getRow('id');
        $db->table('recommendations')->insert([
            'case_id' => $id, 'memo_id' => $memo, 'body' => $body, 'responsible_department_id' => $dept,
            'due_date' => $this->request->getPost('due_date') ?: null, 'updated_by' => $this->uid(),
        ]);
        $this->cases->log($id, 'recommendation_added', 'إضافة توصية للمتابعة', ['note' => mb_substr($body, 0, 120)]);

        return redirect()->to(site_url("cases/{$id}/execution"))->with('message', 'أُضيفت التوصية.');
    }

    public function updateRecommendation(int $rid)
    {
        $db  = db_connect();
        $rec = $db->table('recommendations')->where('id', $rid)->get()->getRowArray();
        if ($rec === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $case = $this->loadCase((int) $rec['case_id']);
        if (! $this->canManage($case)) {
            return $this->deny();
        }
        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['not_started', 'in_progress', 'done'], true)) {
            return $this->deny('حالة غير صحيحة.');
        }
        $uploaded = $this->cases->storeUploads($this->request->getFileMultiple('evidence') ?? [], (int) $case['id'], 'recommendation', $rid);
        $hasEvidence = $uploaded > 0 || $db->table('attachments')->where(['related_type' => 'recommendation', 'related_id' => $rid])->countAllResults() > 0;
        if ($status === 'done' && ! $hasEvidence) {
            return redirect()->back()->with('error', 'أرفق إثبات التنفيذ قبل اعتبار التوصية منفذة.');
        }
        $db->table('recommendations')->where('id', $rid)->update([
            'status' => $status,
            'progress_notes' => trim((string) $this->request->getPost('progress_notes')) ?: $rec['progress_notes'],
            'due_date' => $this->request->getPost('due_date') ?: $rec['due_date'],
            'responsible_department_id' => (int) ($this->request->getPost('responsible_department_id') ?: $rec['responsible_department_id']),
            'completed_at' => $status === 'done' ? ($rec['completed_at'] ?? date('Y-m-d H:i:s')) : null,
            'updated_by' => $this->uid(),
        ]);
        $this->cases->log((int) $case['id'], 'recommendation_updated', 'تحديث حالة توصية: ' . label('rec', $status), ['note' => mb_substr($rec['body'], 0, 120)]);

        return redirect()->to(site_url("cases/{$case['id']}/execution?edit={$rid}"))->with('message', 'تم تحديث حالة التنفيذ.');
    }

    public function archive(int $id)
    {
        $case = $this->loadCase($id);
        if (! $this->canManage($case)) {
            return $this->deny();
        }
        $open = db_connect()->table('recommendations')->where('case_id', $id)->where('status !=', 'done')->countAllResults();
        if ($open > 0) {
            return redirect()->back()->with('error', "لا يمكن الأرشفة: بقيت {$open} توصية لم يكتمل تنفيذها.");
        }
        $now = date('Y-m-d H:i:s');
        $this->cases->update($id, ['stage_code' => 'archived', 'holder_role_id' => null, 'archived_at' => $now, 'closed_at' => $now]);
        $this->cases->log($id, 'archived', 'أرشفة المعاملة');

        return redirect()->to(site_url("cases/{$id}/log"))->with('message', 'تمت أرشفة المعاملة وحُفظت جميع مستنداتها وسجلاتها.');
    }
}
