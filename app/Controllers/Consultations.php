<?php

namespace App\Controllers;

use App\Libraries\Advisor;
use App\Libraries\ConsultationService;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

/**
 * الاستشارات القانونية: يكتب الموظف استشارته، ويولّد النظام مسودة رد مقترحة،
 * ويراجعها مستشار الشؤون القانونية ويعتمدها، ثم تصل الموظف. المسودة لا تظهر لغير legal و admin.
 */
class Consultations extends BaseController
{
    private const TABS = ['new' => 'جديدة', 'mine' => 'مُسندة إليّ', 'answered' => 'تمت الإجابة', 'library' => 'المكتبة'];

    private ConsultationService $service;

    public function __construct()
    {
        $this->service = new ConsultationService();
    }

    public function index()
    {
        if ($this->request->getGet('found')) {
            return redirect()->to(site_url('consultations'))->with('message', 'يسعدنا أنك وجدت إجابتك. لم يُرسل الطلب.');
        }

        $view = (string) $this->request->getGet('tab');
        if (ConsultationService::isStaff() && $view !== 'own') {
            $counts = $this->service->inboxCounts();
            $tab    = isset($counts[$view]) ? $view : 'new';

            return view('consultations/inbox', [
                'title' => 'الاستشارات القانونية', 'subtitle' => 'صندوق وارد الاستشارات ومراجعة الردود قبل اعتمادها', 'active' => 'consultations',
                'crumbs' => ['الاستشارات القانونية'], 'tab' => $tab, 'tabs' => array_intersect_key(self::TABS, $counts), 'counts' => $counts,
                'rows' => $this->anonymizeLibrary($tab, $this->service->inbox($tab)),
            ]);
        }

        return view('consultations/index', [
            'title' => 'استشاراتي', 'subtitle' => 'استشاراتك القانونية وحالتها ورد المستشار المعتمد', 'active' => 'consultations',
            'crumbs' => [['الاستشارات القانونية', 'consultations'], 'استشاراتي'], 'rows' => $this->service->mine(),
            'isStaff' => ConsultationService::isStaff(), 'tracksRead' => $this->service->col('consultations', 'read') !== null,
        ]);
    }

    public function create()
    {
        $subject = (string) old('subject', '');

        return view('consultations/create', [
            'title' => 'طلب استشارة قانونية', 'subtitle' => 'اكتب استشارتك وسيصلك الرد بعد اعتماده من مستشار الشؤون القانونية',
            'active' => 'consultations', 'crumbs' => [['الاستشارات القانونية', 'consultations'], 'طلب استشارة'],
            'categories' => $this->service->categories(), 'levels' => $this->service->confidentialityOptions(),
            'similar' => $subject === '' ? [] : $this->service->searchLibrary($subject, ['subject', 'answer']),
        ]);
    }

    /** إجابات مشابهة من المكتبة المنشورة أثناء كتابة الموضوع (JSON) */
    public function similar()
    {
        $q    = mb_substr(trim((string) $this->request->getGet('q')), 0, 255);
        $rows = $q === '' ? [] : $this->service->searchLibrary($q, ['subject', 'answer']);

        return $this->response->setJSON(array_map(static fn ($r) => [
            'ref_no' => $r['ref_no'], 'subject' => $r['subject'], 'category' => $r['category_name'], 'answer' => $r['answer'],
        ], $rows));
    }

    public function store()
    {
        $levels = $this->service->confidentialityOptions();
        $rules  = [
            'category_id'     => 'required|is_natural_no_zero|is_not_unique[consultation_categories.id]',
            'subject'         => 'required|max_length[255]',
            'body'            => 'required|min_length[10]|max_length[10000]',
            'confidentiality' => 'required|in_list[' . implode(',', $levels) . ']',
        ];
        $messages = [
            'category_id'     => ['required' => 'اختر تصنيف الاستشارة.', 'is_natural_no_zero' => 'اختر تصنيف الاستشارة.', 'is_not_unique' => 'تصنيف غير معروف.'],
            'subject'         => ['required' => 'اكتب موضوع الاستشارة.', 'max_length' => 'الموضوع طويل جداً.'],
            'body'            => ['required' => 'اكتب نص السؤال.', 'min_length' => 'نص السؤال يجب ألا يقل عن 10 أحرف.', 'max_length' => 'نص السؤال طويل جداً.'],
            'confidentiality' => ['required' => 'اختر درجة السرية.', 'in_list' => 'درجة السرية غير صحيحة.'],
        ];
        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'category_id'     => (int) $this->request->getPost('category_id'),
            'subject'         => trim((string) $this->request->getPost('subject')),
            'body'            => trim((string) $this->request->getPost('body')),
            'confidentiality' => (string) $this->request->getPost('confidentiality'),
        ];
        ['id' => $id, 'ref_no' => $refNo] = $this->service->create($data);
        $this->service->log($id, 'created', 'تقديم استشارة قانونية', ['ref_no' => $refNo]);

        $this->generateDraft($id);

        $this->cases->notifyRole('legal', null, $this->service->notificationCategory(), 'استشارة قانونية جديدة',
            "وصلت الاستشارة {$refNo}: " . mb_substr($data['subject'], 0, 150), 'consultations/' . $id);

        return redirect()->to(site_url('consultations/' . $id))
            ->with('message', "أُرسلت استشارتك برقم {$refNo}. سيصلك الرد بعد اعتماده من مستشار الشؤون القانونية.");
    }

    public function show(int $id)
    {
        $staff = ConsultationService::isStaff();
        $c     = $this->load($id, $staff);
        if (! $staff) {
            $this->service->markRead($c);
        }

        return view('consultations/show', [
            'title' => 'استشارة ' . $c['ref_no'], 'subtitle' => $c['subject'], 'active' => 'consultations',
            'crumbs' => [['الاستشارات القانونية', 'consultations'], $c['ref_no']],
            'c' => $c, 'isStaff' => $staff, 'isCounsel' => ConsultationService::isCounsel(), 'isOwner' => $this->service->isOwner($c),
            'counsels' => $staff && $this->service->hasAssignment() ? $this->cases->usersWithRole('legal') : [],
            'actions' => $staff ? $this->service->actions($id) : [],
        ]);
    }

    public function assign(int $id)
    {
        $c = $this->load($id, false);
        if (! ConsultationService::isCounsel() || ! $this->service->hasAssignment() || ! in_array($c['status'], ConsultationService::OPEN_STATUSES, true)) {
            return $this->deny('الإسناد متاح لمستشاري الشؤون القانونية على الاستشارات المفتوحة فقط.');
        }
        $to       = (int) $this->request->getPost('assigned_to');
        $counsels = array_column($this->cases->usersWithRole('legal'), 'name', 'id');
        if (! isset($counsels[$to])) {
            return redirect()->back()->with('error', 'اختر مستشاراً من القائمة.');
        }

        $this->service->update($id, ['assigned' => $to, 'assigned_at' => date('Y-m-d H:i:s'), 'status' => 'assigned']);
        $this->service->log($id, 'assigned', 'إسناد الاستشارة إلى ' . $counsels[$to], ['assigned_to' => $to]);
        $this->cases->notifyUser($to, null, $this->service->notificationCategory(), 'استشارة مُسندة إليك',
            "أُسندت إليك الاستشارة {$c['ref_no']}.", 'consultations/' . $id);

        return redirect()->to(site_url('consultations/' . $id))->with('message', 'تم إسناد الاستشارة إلى ' . $counsels[$to] . '.');
    }

    public function answer(int $id)
    {
        $c = $this->load($id, false);
        if (! ConsultationService::isCounsel()) {
            return $this->deny('اعتماد الرد متاح لمستشاري الشؤون القانونية فقط.');
        }
        if (! in_array($c['status'], ConsultationService::OPEN_STATUSES, true)) {
            return $this->deny('لا يمكن الرد على استشارة ' . label('cons_status', $c['status']) . '.');
        }
        $rules = ['answer' => 'required|min_length[20]|max_length[20000]'];
        $msgs  = ['answer' => ['required' => 'اكتب نص الرد.', 'min_length' => 'الرد يجب ألا يقل عن 20 حرفاً.', 'max_length' => 'الرد طويل جداً.']];
        if (! $this->validate($rules, $msgs)) {
            return redirect()->to(site_url("consultations/{$id}#answer"))->withInput()->with('errors', $this->validator->getErrors());
        }
        $publish = $this->request->getPost('publish') === '1';

        $this->service->update($id, [
            'answer' => trim((string) $this->request->getPost('answer')), 'answered_by' => $this->uid(),
            'answered_at' => date('Y-m-d H:i:s'), 'status' => 'answered', 'is_published' => $publish ? 1 : 0, 'read' => null,
        ]);
        $this->service->log($id, 'answered', 'اعتماد الرد على الاستشارة' . ($publish ? ' ونشره في المكتبة بعد إخفاء بيانات مقدمه' : ''), ['published' => $publish]);
        $this->cases->notifyUser((int) $c['owner_id'], null, $this->service->notificationCategory(), 'تم الرد على استشارتك',
            "اعتُمد رد المستشار على الاستشارة {$c['ref_no']}.", 'consultations/' . $id);

        return redirect()->to(site_url('consultations/' . $id))->with('message', 'اعتُمد الرد وأُرسل إلى صاحب الاستشارة.');
    }

    public function close(int $id)
    {
        $c = $this->load($id, false);
        if (! $this->service->isOwner($c) && ! ConsultationService::isCounsel()) {
            return $this->deny('إغلاق الاستشارة متاح لصاحبها أو لمستشاري الشؤون القانونية.');
        }
        if ($c['status'] === 'closed') {
            return $this->deny('الاستشارة مغلقة مسبقاً.');
        }

        $this->service->update($id, ['status' => 'closed', 'closed_at' => date('Y-m-d H:i:s')]);
        $this->service->log($id, 'closed', $this->service->isOwner($c) ? 'إغلاق الاستشارة من صاحبها' : 'إغلاق الاستشارة من الشؤون القانونية');
        if (! $this->service->isOwner($c)) {
            $this->cases->notifyUser((int) $c['owner_id'], null, $this->service->notificationCategory(), 'أُغلقت استشارتك',
                "أُغلقت الاستشارة {$c['ref_no']}.", 'consultations/' . $id);
        }

        return redirect()->to(site_url('consultations/' . $id))->with('message', 'أُغلقت الاستشارة.');
    }

    /** يحمّل الاستشارة ويتحقق من صلاحية الاطلاع عليها */
    private function load(int $id, bool $withDraft): array
    {
        $c = $this->service->find($id, $withDraft && ConsultationService::isStaff());
        if ($c === null || ! $this->service->canView($c)) {
            throw PageNotFoundException::forPageNotFound('الاستشارة غير موجودة أو غير متاحة لك.');
        }

        return $c;
    }

    /**
     * يولّد المسودة ويخزنها في ai_draft و ai_source و ai_generated_at فقط.
     * أي فشل يُسجَّل في سجل النظام ولا يظهر للموظف، وتبقى الاستشارة محفوظة بلا مسودة.
     */
    private function generateDraft(int $id): void
    {
        $advisor = new Advisor($this->service);
        try {
            $c     = $this->service->find($id);
            $draft = $c === null ? null : $advisor->draft($c);
            if ($draft === null) {
                $this->service->log($id, 'draft_unavailable', 'لا توجد مسودة مقترحة', ['source' => $advisor->source]);

                return;
            }
            $this->service->update($id, ['ai_draft' => $draft, 'ai_source' => $advisor->source, 'ai_generated_at' => date('Y-m-d H:i:s')]);
            $this->service->log($id, 'draft_generated', 'توليد مسودة رد مقترحة', ['source' => $advisor->source]);
        } catch (Throwable $e) {
            log_message('error', "Consultation {$id} draft ({$advisor->source}): " . $e->getMessage());
            try {
                $this->service->log($id, 'draft_failed', 'تعذّر توليد المسودة المقترحة', ['source' => $advisor->source]);
            } catch (Throwable) {
                // لا يوقف حفظ الاستشارة
            }
        }
    }

    /** المكتبة تُعرض بعد إخفاء بيانات مقدمي الاستشارات */
    private function anonymizeLibrary(string $tab, array $rows): array
    {
        return $tab === 'library' ? array_map(fn ($r) => $this->service->anonymize($r), $rows) : $rows;
    }
}
