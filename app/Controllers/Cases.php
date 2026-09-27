<?php

namespace App\Controllers;

use App\Libraries\Access;
use CodeIgniter\Exceptions\PageNotFoundException;

class Cases extends BaseController
{
    /** أنواع ملف أصل المخالفة المقبولة من المصادر غير الكتابة المباشرة */
    private const SOURCE_FILE_TYPES = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'eml', 'msg'];

    /** قوائم القائمة الجانبية: [العنوان، المراحل، مفتاح القائمة، الصفحة المستهدفة] */
    private const VIEWS = [
        'all'           => ['المعاملات', null, 'cases', null],
        'referred'      => ['المعاملات المحالة', ['new', 'with_gm', 'referred'], 'referred', 'referral'],
        'investigation' => ['المعاملات قيد التحقيق', ['investigation'], 'investigation', null],
        'sessions'      => ['الجلسات', ['investigation'], 'sessions', 'attendance'],
        'statements'    => ['الشهود والمختصون', ['investigation'], 'statements', 'statements'],
        'letters'       => ['المراسلات', ['investigation'], 'letters', 'letters'],
        'memos'         => ['مذكرات التحقيق', ['investigation', 'approval'], 'memos', 'memo'],
        'approvals'     => ['الاعتمادات', ['approval'], 'approvals', 'review'],
        'execution'     => ['التوصيات', ['execution'], 'recs', 'execution'],
        'archive'       => ['الأرشيف', ['archived', 'closed_no_action'], 'archive', 'log'],
    ];

    public function index()
    {
        $viewKey = $this->request->getGet('view') ?? 'all';
        if (! isset(self::VIEWS[$viewKey])) {
            $viewKey = 'all';
        }
        [$title, $stagesFilter, $active, $target] = self::VIEWS[$viewKey];
        $q     = trim((string) $this->request->getGet('q'));
        $stage = (string) $this->request->getGet('stage');

        $db = db_connect();
        $b  = $db->table('cases c')
            ->select('c.id, c.case_no, c.subject, c.stage_code, c.created_at, c.last_action_at, c.confidentiality, t.name_ar AS type_name,
                      s.name_ar AS stage_name, src.name_ar AS source_name, r.name_ar AS holder_name, r.code AS holder_code,
                      ie.full_name AS investigator_name, a.alert_level, a.idle_days')
            ->join('case_types t', 't.id = c.case_type_id')
            ->join('case_stages s', 's.code = c.stage_code')
            ->join('case_sources src', 'src.code = c.source_code')
            ->join('roles r', 'r.id = c.holder_role_id', 'left')
            ->join('users iu', 'iu.id = c.investigator_user_id', 'left')
            ->join('employees ie', 'ie.id = iu.employee_id', 'left')
            ->join('v_case_alerts a', 'a.id = c.id', 'left');
        $this->cases->scope($b);

        if ($stagesFilter !== null) {
            $b->whereIn('c.stage_code', $stagesFilter);
        }
        if ($viewKey === 'approvals' && ! Access::isAdmin()) {
            $b->whereIn('c.holder_role_id', session('role_ids') ?: [0]);
        }
        if ($stage !== '') {
            $b->where('c.stage_code', $stage);
        }
        if ($q !== '') {
            $b->groupStart()->like('c.case_no', $q)->orLike('c.subject', $q)
                ->orWhere("EXISTS (SELECT 1 FROM case_parties p JOIN employees e ON e.id = p.employee_id WHERE p.case_id = c.id AND (e.full_name LIKE " . $db->escape('%' . $q . '%') . " OR e.employee_no = " . $db->escape($q) . "))", null, false)
                ->groupEnd();
        }

        $rows = $b->orderBy('c.last_action_at', 'DESC')->get()->getResultArray();

        return view('cases/index', [
            'title' => $title, 'subtitle' => $q !== '' ? 'نتائج البحث عن: ' . $q : 'المعاملات المتاحة لك حسب صلاحياتك',
            'active' => $active, 'crumbs' => [$title], 'rows' => $rows, 'viewKey' => $viewKey, 'target' => $target,
            'stages' => $db->table('case_stages')->orderBy('sort_order')->get()->getResultArray(), 'stage' => $stage, 'q' => $q,
        ]);
    }

    public function create()
    {
        $db = db_connect();

        return view('cases/create', [
            'title' => 'إنشاء مخالفة / شكوى', 'subtitle' => 'تسجيل مخالفة أو شكوى وبدء إجراءات التحقيق',
            'active' => 'new', 'crumbs' => [['المعاملات', 'cases'], 'إنشاء مخالفة / شكوى'],
            'types' => $db->table('case_types')->where('is_active', 1)->get()->getResultArray(),
            'sources' => $db->table('case_sources')->get()->getResultArray(),
            'departments' => $db->table('departments')->where('is_active', 1)->orderBy('name_ar')->get()->getResultArray(),
            'employees' => $this->employeeList(),
            'canImport' => Access::hasAny(['legal', 'head']),
            'preview' => date('Y') . '-XXXX',
        ]);
    }

    public function store()
    {
        $rules = [
            'case_type_id'   => 'required|is_not_unique[case_types.id]',
            'source_code'    => 'required|is_not_unique[case_sources.code]',
            'received_at'    => 'required|valid_date[Y-m-d]',
            'confidentiality'=> 'required|in_list[normal,confidential,top_secret]',
            'subject'        => 'required|max_length[255]',
            'incident_date'  => 'required|valid_date[Y-m-d]',
            'department_id'  => 'required|is_not_unique[departments.id]',
            'description'    => 'required|min_length[20]',
            'external_ref'   => 'permit_empty|max_length[60]',
            'incident_place' => 'permit_empty|max_length[150]',
            'source_date'    => 'permit_empty|valid_date[Y-m-d]',
        ];
        $messages = [
            'case_type_id' => ['required' => 'اختر نوع المعاملة.'],
            'received_at' => ['required' => 'حدد تاريخ الاستلام.', 'valid_date' => 'تاريخ الاستلام غير صحيح.'],
            'subject' => ['required' => 'اكتب موضوع المعاملة.', 'max_length' => 'الموضوع طويل جداً.'],
            'confidentiality' => ['required' => 'اختر درجة السرية.', 'in_list' => 'درجة السرية غير صحيحة.'],
            'source_code' => ['required' => 'اختر مصدر المعاملة.', 'is_not_unique' => 'مصدر غير معروف.'],
            'incident_date' => ['required' => 'حدد تاريخ الواقعة.', 'valid_date' => 'تاريخ الواقعة غير صحيح.'],
            'department_id' => ['required' => 'اختر الإدارة أو القسم.'],
            'description' => ['required' => 'اكتب وصف الواقعة.', 'min_length' => 'وصف الواقعة يجب ألا يقل عن 20 حرفاً.'],
            'external_ref' => ['max_length' => 'الرقم المرجعي طويل جداً.'],
            'source_date' => ['valid_date' => 'تاريخ المخالفة في النظام المصدر غير صحيح.'],
        ];
        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $source = (string) $this->request->getPost('source_code');
        if ($source !== 'manual' && ! Access::hasAny(['legal', 'head'])) {
            return redirect()->back()->withInput()->with('error', 'الاستيراد من الأنظمة أو المستندات الورقية متاح لمدير الشؤون القانونية ورئيس التحقيقات فقط.');
        }

        // المصادر غير الكتابة المباشرة: الرقم المرجعي وأصل المخالفة إلزاميان
        $isImport    = $source !== 'manual';
        $externalRef = $isImport ? trim((string) $this->request->getPost('external_ref')) : '';
        $sourceFile  = $isImport ? $this->request->getFile('source_file') : null;
        if ($isImport) {
            $errors = [];
            if ($externalRef === '') {
                $errors['external_ref'] = 'أدخل الرقم المرجعي في النظام المصدر.';
            }
            if ($sourceFile === null || ! $sourceFile->isValid()) {
                $errors['source_file'] = 'أرفق أصل المخالفة من النظام المصدر.';
            } elseif (! in_array(strtolower($sourceFile->getClientExtension()), self::SOURCE_FILE_TYPES, true) || $sourceFile->getSize() > 20 * 1024 * 1024) {
                $errors['source_file'] = 'أصل المخالفة يجب أن يكون PDF أو JPG أو PNG أو DOC أو DOCX أو EML أو MSG، ولا يتجاوز 20 ميجابايت.';
            }
            if ($errors !== []) {
                return redirect()->back()->withInput()->with('errors', $errors);
            }
        }

        $accused = $this->resolveEmployees((array) $this->request->getPost('accused'));
        if ($accused === []) {
            return redirect()->back()->withInput()->with('error', 'اختر الموظف محل التحقيق من القائمة.');
        }
        $complainant = $this->resolveEmployees([(string) $this->request->getPost('complainant')]);
        $isDraft     = $this->request->getPost('action') === 'draft';

        $db = db_connect();
        $db->transStart();
        $caseNo = $this->cases->nextCaseNo();
        $db->table('cases')->insert([
            'case_no'         => $caseNo,
            'case_type_id'    => (int) $this->request->getPost('case_type_id'),
            'source_code'     => $source,
            'external_ref'    => $isImport ? $externalRef : null,
            'subject'         => $this->request->getPost('subject'),
            'description'     => $this->request->getPost('description'),
            'incident_date'   => $this->request->getPost('incident_date'),
            'incident_place'  => $this->request->getPost('incident_place') ?: null,
            'department_id'   => (int) $this->request->getPost('department_id'),
            'received_at'     => $this->request->getPost('received_at'),
            'confidentiality' => $this->request->getPost('confidentiality'),
            'stage_code'      => $isDraft ? 'draft' : 'new',
            'holder_role_id'  => $isDraft ? null : $this->cases->roleId('legal'),
            'created_by'      => $this->uid(),
        ]);
        $caseId = (int) $db->insertID();

        foreach ($accused as $empId) {
            $db->table('case_parties')->insert(['case_id' => $caseId, 'employee_id' => $empId, 'party_role' => 'accused']);
        }
        if ($complainant !== []) {
            $db->table('case_parties')->insert(['case_id' => $caseId, 'employee_id' => $complainant[0], 'party_role' => 'complainant']);
        }

        $sourceAttachment = null;
        if ($isImport) {
            $sourceAttachment = $this->cases->storeUpload($sourceFile, $caseId, 'case');
            if ($sourceAttachment === null) {
                $db->transRollback();

                return redirect()->back()->withInput()->with('errors', ['source_file' => 'أرفق أصل المخالفة من النظام المصدر.']);
            }
        }

        $files = $this->request->getFileMultiple('attachments') ?? [];
        $this->cases->storeUploads($files, $caseId);

        $origin = $this->sourceName($source) . ($isImport ? ' · الرقم المرجعي: ' . $externalRef : '');
        $this->cases->log($caseId, 'created', $isDraft ? 'حفظ المعاملة كمسودة' . ($isImport ? ' (' . $origin . ')' : '') : 'إنشاء المعاملة (' . $origin . ')', $isImport ? [
            'source' => $source, 'external_ref' => $externalRef,
            'source_date' => $this->request->getPost('source_date') ?: null, 'source_attachment_id' => $sourceAttachment,
        ] : []);
        if (! $isDraft) {
            $this->cases->notifyRole('legal', $caseId, 'new_case', 'معاملة جديدة', "وردت المعاملة {$caseNo} وتنتظر الإحالة.", "cases/{$caseId}/referral");
        }
        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'تعذر حفظ المعاملة، حاول مرة أخرى.');
        }

        return redirect()->to(site_url("cases/{$caseId}"))
            ->with('message', $isDraft ? "حُفظت المعاملة {$caseNo} كمسودة." : "تم إنشاء المعاملة {$caseNo} وإرسالها لمدير الشؤون القانونية.");
    }

    public function submitDraft(int $id)
    {
        $case = $this->loadCase($id);
        if ($case['stage_code'] !== 'draft' || (int) $case['created_by'] !== $this->uid()) {
            return $this->deny();
        }
        $this->cases->update($id, ['stage_code' => 'new', 'holder_role_id' => $this->cases->roleId('legal')]);
        $this->cases->log($id, 'submitted', 'إرسال المعاملة للإحالة');
        $this->cases->notifyRole('legal', $id, 'new_case', 'معاملة جديدة', "وردت المعاملة {$case['case_no']} وتنتظر الإحالة.", "cases/{$id}/referral");

        return redirect()->to(site_url("cases/{$id}"))->with('message', 'تم إرسال المعاملة.');
    }

    public function show(int $id)
    {
        $case = $this->loadCase($id);
        $tab  = $this->request->getGet('tab') ?? 'data';
        $db   = db_connect();

        $data = [
            'title' => 'تفاصيل المعاملة', 'subtitle' => 'الملف الكامل للمعاملة وإجراءات التحقيق',
            'active' => $case['stage_code'] === 'investigation' ? 'investigation' : 'cases',
            'crumbs' => [['المعاملات', 'cases'], 'تفاصيل المعاملة'],
            'case' => $case, 'tab' => $tab, 'parties' => $this->cases->parties($id),
            'canInvestigate' => $this->cases->canInvestigate($case),
            'started' => $db->table('case_actions')->where(['case_id' => $id, 'action_code' => 'investigation_started'])->countAllResults() > 0,
            'counts' => [
                'attachments' => $db->table('attachments')->where('case_id', $id)->countAllResults(),
                'sessions' => $db->table('sessions')->where('case_id', $id)->countAllResults(),
                'statements' => $db->table('statement_requests')->where('case_id', $id)->countAllResults(),
                'letters' => $db->table('correspondences')->where('case_id', $id)->countAllResults(),
            ],
        ];

        if ($tab === 'attachments') {
            $data['attachments'] = $this->cases->attachments($id);
        } elseif ($tab === 'sessions') {
            $data['sessions'] = $db->table('sessions s')
                ->select('s.*, COALESCE(e.full_name, p.external_name) AS party_name, p.party_role,
                          (SELECT COUNT(*) FROM investigation_questions q WHERE q.session_id = s.id) AS entries')
                ->join('case_parties p', 'p.id = s.party_id')
                ->join('employees e', 'e.id = p.employee_id', 'left')
                ->where('s.case_id', $id)->orderBy('s.session_no')->get()->getResultArray();
        } elseif ($tab === 'statements') {
            $data['statements'] = $db->table('statement_requests r')
                ->select('r.*, COALESCE(e.full_name, p.external_name) AS party_name, d.name_ar AS department_name')
                ->join('case_parties p', 'p.id = r.party_id', 'left')
                ->join('employees e', 'e.id = p.employee_id', 'left')
                ->join('departments d', 'd.id = r.department_id', 'left')
                ->where('r.case_id', $id)->orderBy('r.created_at', 'DESC')->get()->getResultArray();
        } elseif ($tab === 'letters') {
            $data['letters'] = $db->table('correspondences l')->select('l.*, d.name_ar AS department_name')
                ->join('departments d', 'd.id = l.department_id')
                ->where('l.case_id', $id)->orderBy('l.sent_at', 'DESC')->get()->getResultArray();
        }

        return view('cases/show', $data);
    }

    public function upload(int $id)
    {
        $case = $this->loadCase($id);
        if ($case['is_final']) {
            return $this->deny('لا يمكن إضافة مرفقات لمعاملة مؤرشفة.');
        }
        $n = $this->cases->storeUploads($this->request->getFileMultiple('attachments') ?? [], $id);
        if ($n === 0) {
            return redirect()->back()->with('error', 'لم يُرفع أي ملف. تأكد من النوع (PDF، DOC، XLS، JPG، PNG) والحجم (20 ميجابايت كحد أقصى).');
        }
        $this->cases->log($id, 'attachment_added', "إرفاق {$n} ملف");
        if ($case['investigator_user_id']) {
            $this->cases->notifyUser((int) $case['investigator_user_id'], $id, 'attachment', 'مرفق جديد', "أُضيف {$n} مرفق إلى المعاملة {$case['case_no']}.", "cases/{$id}?tab=attachments");
        }

        return redirect()->to(site_url("cases/{$id}?tab=attachments"))->with('message', "تم رفع {$n} ملف.");
    }

    public function download(int $attachmentId)
    {
        $att = db_connect()->table('attachments')->where('id', $attachmentId)->get()->getRowArray();
        if ($att === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $this->loadCase((int) $att['case_id']);
        $path = WRITEPATH . 'case-files/' . $att['stored_path'];
        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound('الملف غير موجود على الخادم.');
        }

        return $this->response->download($path, null)->setFileName($att['original_name']);
    }

    public function start(int $id)
    {
        $case = $this->loadCase($id);
        if (! $this->cases->canInvestigate($case)) {
            return $this->deny();
        }
        $already = db_connect()->table('case_actions')->where(['case_id' => $id, 'action_code' => 'investigation_started'])->countAllResults();
        if (! $already) {
            $this->cases->log($id, 'investigation_started', 'بدء إجراءات التحقيق');
        }

        return redirect()->to(site_url("cases/{$id}/invitations/new"))->with('message', 'بدأت إجراءات التحقيق. أرسل دعوة الحضور للموظف.');
    }

    public function log(int $id)
    {
        $case = $this->loadCase($id);
        $rows = db_connect()->table('case_actions a')
            ->select('a.*, COALESCE(e.full_name, u.username) AS user_name, e.job_title')
            ->join('users u', 'u.id = a.user_id', 'left')
            ->join('employees e', 'e.id = u.employee_id', 'left')
            ->where('a.case_id', $id)->orderBy('a.created_at')->orderBy('a.id')
            ->get()->getResultArray();

        return view('cases/log', [
            'title' => 'سجل إجراءات المعاملة', 'subtitle' => 'سجل زمني كامل لكل إجراء تم على المعاملة',
            'active' => in_array($case['stage_code'], ['archived', 'closed_no_action'], true) ? 'archive' : 'cases',
            'crumbs' => [['المعاملات', 'cases'], [$case['case_no'], "cases/{$id}"], 'سجل الإجراءات'],
            'case' => $case, 'parties' => $this->cases->parties($id), 'rows' => $rows,
        ]);
    }

    private function employeeList(): array
    {
        return db_connect()->table('employees e')
            ->select('e.id, e.employee_no, e.full_name, e.job_title, d.name_ar AS department_name')
            ->join('departments d', 'd.id = e.department_id', 'left')
            ->where('e.is_active', 1)->orderBy('e.full_name')->get()->getResultArray();
    }

    /** يحوّل "رقم وظيفي — الاسم" إلى معرّفات موظفين */
    private function resolveEmployees(array $values): array
    {
        $ids = [];
        foreach ($values as $v) {
            $v = trim((string) $v);
            if ($v === '') {
                continue;
            }
            $no  = trim(explode('—', $v)[0]);
            $row = db_connect()->table('employees')->select('id')->where('employee_no', $no)->orWhere('full_name', $v)->get()->getRow();
            if ($row) {
                $ids[(int) $row->id] = (int) $row->id;
            }
        }

        return array_values($ids);
    }

    private function sourceName(string $code): string
    {
        return (string) db_connect()->table('case_sources')->select('name_ar')->where('code', $code)->get()->getRow('name_ar');
    }
}
