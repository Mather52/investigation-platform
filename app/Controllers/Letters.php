<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class Letters extends BaseController
{
    public function index(int $id)
    {
        $case = $this->loadCase($id);
        $db   = db_connect();
        $rows = $db->table('correspondences l')->select('l.*, d.name_ar AS department_name')
            ->join('departments d', 'd.id = l.department_id')
            ->where('l.case_id', $id)->orderBy('l.sent_at', 'DESC')->get()->getResultArray();
        $atts = [];
        foreach ($this->cases->attachments($id) as $a) {
            if (in_array($a['related_type'], ['correspondence', 'correspondence_reply'], true)) {
                $atts[$a['related_type']][$a['related_id']][] = $a;
            }
        }

        return view('letters/index', [
            'title' => 'المراسلات', 'subtitle' => 'المراسلات الداخلية مع الإدارات والأقسام', 'active' => 'letters',
            'crumbs' => [['المعاملات', 'cases'], [$case['case_no'], "cases/{$id}"], 'المراسلات'],
            'case' => $case, 'parties' => $this->cases->parties($id), 'rows' => $rows, 'atts' => $atts,
            'canEdit' => $this->cases->canInvestigate($case),
            'departments' => $db->table('departments')->where('is_active', 1)->orderBy('name_ar')->get()->getResultArray(),
        ]);
    }

    public function store(int $id)
    {
        $case = $this->loadCase($id);
        if (! $this->cases->canInvestigate($case)) {
            return $this->deny();
        }
        $rules = [
            'department_id' => 'required|is_not_unique[departments.id]', 'request_type' => 'required|max_length[100]',
            'subject' => 'required|max_length[255]', 'body' => 'required', 'due_date' => 'required|valid_date[Y-m-d]',
        ];
        $msgs = ['department_id' => ['required' => 'اختر الجهة المستلمة.'], 'request_type' => ['required' => 'اختر نوع الطلب.'], 'subject' => ['required' => 'اكتب الموضوع.'], 'body' => ['required' => 'اكتب نص المراسلة.'], 'due_date' => ['required' => 'حدد تاريخ الاستحقاق.']];
        if (! $this->validate($rules, $msgs)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $db = db_connect();
        $db->table('correspondences')->insert([
            'case_id' => $id, 'department_id' => (int) $this->request->getPost('department_id'),
            'request_type' => $this->request->getPost('request_type'), 'subject' => $this->request->getPost('subject'),
            'body' => $this->request->getPost('body'), 'due_date' => $this->request->getPost('due_date'), 'created_by' => $this->uid(),
        ]);
        $lid  = (int) $db->insertID();
        $this->cases->storeUploads($this->request->getFileMultiple('attachments') ?? [], $id, 'correspondence', $lid);
        $dept = $db->table('departments')->select('name_ar')->where('id', (int) $this->request->getPost('department_id'))->get()->getRow('name_ar');
        $this->cases->log($id, 'letter_sent', 'إرسال مراسلة إلى ' . $dept, ['note' => $this->request->getPost('subject')]);

        return redirect()->to(site_url("cases/{$id}/letters"))->with('message', 'تم إرسال المراسلة إلى ' . $dept . '.');
    }

    public function reply(int $lid)
    {
        $db = db_connect();
        $l  = $db->table('correspondences')->where('id', $lid)->get()->getRowArray();
        if ($l === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $case = $this->loadCase((int) $l['case_id']);
        if (! $this->cases->canInvestigate($case) || $l['status'] === 'replied') {
            return $this->deny();
        }
        $body = trim((string) $this->request->getPost('reply_body'));
        if ($body === '') {
            return redirect()->back()->with('error', 'اكتب نص الرد.');
        }
        $db->table('correspondences')->where('id', $lid)->update([
            'status' => 'replied', 'reply_body' => $body, 'replied_at' => date('Y-m-d H:i:s'),
            'replied_by' => trim((string) $this->request->getPost('replied_by')) ?: null,
        ]);
        $this->cases->storeUploads($this->request->getFileMultiple('attachments') ?? [], (int) $case['id'], 'correspondence_reply', $lid);
        $this->cases->log((int) $case['id'], 'letter_replied', 'تسجيل رد على مراسلة', ['note' => $l['subject']]);
        if ($case['investigator_user_id']) {
            $this->cases->notifyUser((int) $case['investigator_user_id'], (int) $case['id'], 'reply', 'رد على مراسلة', "ورد رد على: {$l['subject']}", "cases/{$case['id']}/letters");
        }

        return redirect()->back()->with('message', 'تم تسجيل الرد.');
    }
}
