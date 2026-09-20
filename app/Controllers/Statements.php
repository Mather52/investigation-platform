<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class Statements extends BaseController
{
    public function index(int $id)
    {
        $case = $this->loadCase($id);
        $db   = db_connect();
        $rows = $db->table('statement_requests r')
            ->select('r.*, COALESCE(e.full_name, p.external_name) AS party_name, e.job_title, d.name_ar AS department_name')
            ->join('case_parties p', 'p.id = r.party_id', 'left')->join('employees e', 'e.id = p.employee_id', 'left')
            ->join('departments d', 'd.id = r.department_id', 'left')
            ->where('r.case_id', $id)->orderBy('r.created_at', 'DESC')->get()->getResultArray();

        return view('statements/index', [
            'title' => 'الشهود والمختصون', 'subtitle' => 'طلب إفادات الشهود والآراء الفنية المتخصصة', 'active' => 'statements',
            'crumbs' => [['المعاملات', 'cases'], [$case['case_no'], "cases/{$id}"], 'الشهود والمختصون'],
            'case' => $case, 'parties' => $this->cases->parties($id), 'rows' => $rows, 'canEdit' => $this->cases->canInvestigate($case),
            'employees' => $db->table('employees e')->select('e.employee_no, e.full_name, d.name_ar AS dept')->join('departments d', 'd.id = e.department_id', 'left')->where('e.is_active', 1)->orderBy('e.full_name')->get()->getResultArray(),
            'departments' => $db->table('departments')->where('is_active', 1)->orderBy('name_ar')->get()->getResultArray(),
        ]);
    }

    public function store(int $id)
    {
        $case = $this->loadCase($id);
        if (! $this->cases->canInvestigate($case)) {
            return $this->deny();
        }
        $type = $this->request->getPost('request_type') === 'expert' ? 'expert' : 'witness';
        $db   = db_connect();

        if ($type === 'witness') {
            $rules = ['witness' => 'required', 'reason' => 'required', 'subject' => 'required|max_length[255]', 'proposed_at' => 'required|valid_date'];
            $msgs  = ['witness' => ['required' => 'اختر الشاهد.'], 'reason' => ['required' => 'اختر سبب طلب الإفادة.'], 'subject' => ['required' => 'اكتب موضوع الإفادة.'], 'proposed_at' => ['required' => 'حدد الموعد المقترح.']];
            if (! $this->validate($rules, $msgs)) {
                return redirect()->to(site_url("cases/{$id}/statements#witness"))->withInput()->with('errors', $this->validator->getErrors());
            }
            $no  = trim(explode('—', (string) $this->request->getPost('witness'))[0]);
            $emp = $db->table('employees')->where('employee_no', $no)->get()->getRowArray();
            if ($emp === null) {
                return redirect()->to(site_url("cases/{$id}/statements#witness"))->withInput()->with('error', 'اختر الشاهد من القائمة.');
            }
            $party = $db->table('case_parties')->where(['case_id' => $id, 'employee_id' => $emp['id'], 'party_role' => 'witness'])->get()->getRowArray();
            if ($party === null) {
                $db->table('case_parties')->insert(['case_id' => $id, 'employee_id' => $emp['id'], 'party_role' => 'witness']);
                $partyId = (int) $db->insertID();
            } else {
                $partyId = (int) $party['id'];
            }
            $db->table('statement_requests')->insert([
                'case_id' => $id, 'request_type' => 'witness', 'party_id' => $partyId, 'department_id' => $emp['department_id'],
                'reason' => $this->request->getPost('reason'), 'subject' => $this->request->getPost('subject'),
                'details' => $this->request->getPost('details') ?: null,
                'proposed_at' => str_replace('T', ' ', (string) $this->request->getPost('proposed_at')), 'created_by' => $this->uid(),
            ]);
            $this->cases->log($id, 'statement_requested', 'طلب إفادة شاهد: ' . $emp['full_name']);
            $uid = $db->table('users')->select('id')->where('employee_id', $emp['id'])->get()->getRow('id');
            if ($uid) {
                $this->cases->notifyUser((int) $uid, $id, 'statement', 'طلب إفادة', "طُلبت إفادتك في المعاملة {$case['case_no']}.", null);
            }

            return redirect()->to(site_url("cases/{$id}/statements"))->with('message', 'أُرسل طلب الإفادة. أرسل دعوة الحضور للشاهد من قائمة الطلبات.');
        }

        $rules = ['specialty' => 'required|max_length[150]', 'department_id' => 'required|is_not_unique[departments.id]', 'subject' => 'required|max_length[255]', 'details' => 'required'];
        $msgs  = ['specialty' => ['required' => 'اكتب التخصص المطلوب.'], 'department_id' => ['required' => 'اختر الجهة.'], 'subject' => ['required' => 'اكتب موضوع الطلب.'], 'details' => ['required' => 'اكتب تفاصيل المطلوب.']];
        if (! $this->validate($rules, $msgs)) {
            return redirect()->to(site_url("cases/{$id}/statements#expert"))->withInput()->with('errors', $this->validator->getErrors());
        }
        $db->table('statement_requests')->insert([
            'case_id' => $id, 'request_type' => 'expert', 'department_id' => (int) $this->request->getPost('department_id'),
            'specialty' => $this->request->getPost('specialty'), 'subject' => $this->request->getPost('subject'),
            'details' => $this->request->getPost('details'), 'status' => 'pending', 'created_by' => $this->uid(),
        ]);
        $rid = (int) $db->insertID();
        $this->cases->storeUploads($this->request->getFileMultiple('attachments') ?? [], $id, 'statement_request', $rid);
        $this->cases->log($id, 'statement_requested', 'طلب رأي مختص: ' . $this->request->getPost('specialty'));

        return redirect()->to(site_url("cases/{$id}/statements"))->with('message', 'أُرسل طلب الرأي المختص.');
    }

    public function respond(int $rid)
    {
        $db  = db_connect();
        $req = $db->table('statement_requests')->where('id', $rid)->get()->getRowArray();
        if ($req === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $case = $this->loadCase((int) $req['case_id']);
        if (! $this->cases->canInvestigate($case)) {
            return $this->deny();
        }
        $status   = $this->request->getPost('status') === 'answered' ? 'answered' : 'pending';
        $response = trim((string) $this->request->getPost('response'));
        if ($status === 'answered' && $response === '') {
            return redirect()->back()->with('error', 'اكتب ملخص الإفادة أو الرأي.');
        }
        $db->table('statement_requests')->where('id', $rid)->update([
            'status' => $status, 'response' => $response ?: $req['response'],
            'responded_at' => $status === 'answered' ? date('Y-m-d H:i:s') : null,
        ]);
        $this->cases->storeUploads($this->request->getFileMultiple('attachments') ?? [], (int) $case['id'], 'statement_request', $rid);
        if ($status === 'answered') {
            $this->cases->log((int) $case['id'], 'statement_answered', 'تسجيل ' . ($req['request_type'] === 'witness' ? 'إفادة شاهد' : 'رأي مختص'));
        }

        return redirect()->back()->with('message', 'تم تحديث الطلب.');
    }
}
