<?php

namespace App\Controllers;

use App\Libraries\ConsultationService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use mysqli_sql_exception;

/**
 * الاستشارات القانونية: محادثة بين الموظف وإدارة الشؤون القانونية.
 * الموظف يرى محادثاته فقط، والشؤون القانونية (ومدير النظام) ترى كل المحادثات وترد عليها.
 */
class Consultations extends BaseController
{
    private ConsultationService $service;

    public function __construct()
    {
        $this->service = new ConsultationService();
    }

    /** صفحة المحادثات: القائمة، والمحادثة المختارة أو نموذج محادثة جديدة */
    public function index()
    {
        $staff = ConsultationService::isStaff();
        $tab   = $staff && $this->request->getGet('tab') === 'all' ? 'all' : 'waiting';

        try {
            $selId   = (int) $this->request->getGet('c');
            $current = $selId > 0 ? $this->load($selId) : null;
            if ($current !== null && ! $staff) {
                $this->service->markRead($current);
            }

            return view('consultations/chat', [
                'title' => 'الاستشارات القانونية', 'active' => 'consultations',
                'subtitle' => $staff ? 'محادثات الموظفين مع إدارة الشؤون القانونية والالتزام' : 'تواصل مع إدارة الشؤون القانونية والالتزام',
                'crumbs' => ['الاستشارات القانونية'],
                'isStaff' => $staff, 'tab' => $tab, 'waiting' => $staff ? $this->service->waitingCount() : 0,
                'list' => $staff ? $this->service->conversations(true, $tab === 'waiting') : $this->service->mine(),
                'current' => $current, 'messages' => $current ? $this->service->thread($current) : [],
                'isOwner' => $current ? $this->service->isOwner($current) : false,
                'composeNew' => $current === null && ($this->request->getGet('new') !== null || ! $staff),
                'categories' => $this->service->categories(), 'levels' => $this->service->confidentialityOptions(),
            ]);
        } catch (DatabaseException|mysqli_sql_exception $e) {
            log_message('error', 'Consultations: ' . $e->getMessage());

            return view('consultations/unavailable', [
                'title' => 'الاستشارات القانونية', 'active' => 'consultations', 'crumbs' => ['الاستشارات القانونية'],
            ]);
        }
    }

    public function create()
    {
        return redirect()->to(site_url('consultations?new=1'));
    }

    /** روابط الإشعارات القديمة والجديدة: consultations/{id} */
    public function show(int $id)
    {
        return redirect()->to(site_url('consultations?c=' . $id));
    }

    /** بدء محادثة جديدة: أول رسالة تُحفظ كنص الاستشارة */
    public function store()
    {
        $levels = $this->service->confidentialityOptions();
        $rules  = [
            'category_id'     => 'required|is_natural_no_zero|is_not_unique[consultation_categories.id]',
            'subject'         => 'required|max_length[255]',
            'body'            => 'required|max_length[10000]',
            'confidentiality' => 'required|in_list[' . implode(',', $levels) . ']',
        ];
        $messages = [
            'category_id'     => ['required' => 'اختر تصنيف الاستشارة.', 'is_natural_no_zero' => 'اختر تصنيف الاستشارة.', 'is_not_unique' => 'تصنيف غير معروف.'],
            'subject'         => ['required' => 'اكتب موضوع الاستشارة.', 'max_length' => 'الموضوع طويل جداً.'],
            'body'            => ['required' => 'اكتب رسالتك.', 'max_length' => 'الرسالة طويلة جداً.'],
            'confidentiality' => ['required' => 'اختر درجة السرية.', 'in_list' => 'درجة السرية غير صحيحة.'],
        ];
        if (! $this->validate($rules, $messages)) {
            return redirect()->to(site_url('consultations?new=1'))->withInput()->with('errors', $this->validator->getErrors());
        }

        $subject = trim((string) $this->request->getPost('subject'));
        ['id' => $id, 'ref_no' => $refNo] = $this->service->create([
            'category_id'     => (int) $this->request->getPost('category_id'),
            'subject'         => $subject,
            'body'            => trim((string) $this->request->getPost('body')),
            'confidentiality' => (string) $this->request->getPost('confidentiality'),
        ]);
        $this->service->log($id, 'created', 'بدء محادثة استشارة قانونية', ['ref_no' => $refNo]);
        $this->cases->notifyRole('legal', null, $this->service->notificationCategory(), 'استشارة قانونية جديدة',
            "وصلت الاستشارة {$refNo}: " . mb_substr($subject, 0, 150), 'consultations/' . $id);

        return redirect()->to(site_url('consultations?c=' . $id))->with('message', "أُرسلت استشارتك برقم {$refNo}، وستصلك الردود هنا.");
    }

    /** رسالة جديدة في المحادثة من صاحبها أو من الشؤون القانونية */
    public function message(int $id)
    {
        $c = $this->load($id);
        if ($c['status'] === 'closed') {
            return $this->deny('المحادثة مغلقة.');
        }
        $body = trim((string) $this->request->getPost('body'));
        if ($body === '' || mb_strlen($body) > 10000) {
            return redirect()->to(site_url('consultations?c=' . $id))->with('error', $body === '' ? 'اكتب رسالتك.' : 'الرسالة طويلة جداً.');
        }

        $this->service->addMessage($id, $body);
        $fromOwner = $this->service->isOwner($c);
        if ($fromOwner) {
            // رسالة من الموظف: تعود المحادثة إلى "بانتظار الرد"
            $this->service->update($id, ['status' => 'new']);
            if ($c['status'] !== 'new') {
                $this->cases->notifyRole('legal', null, $this->service->notificationCategory(), 'رسالة جديدة في استشارة',
                    "رسالة جديدة في الاستشارة {$c['ref_no']}.", 'consultations/' . $id);
            }
        } else {
            $update = ['status' => 'answered', 'read' => null];
            if ($c['answered_at'] === null) {
                $update += ['answered_by' => $this->uid(), 'answered_at' => date('Y-m-d H:i:s')];
                $this->service->log($id, 'answered', 'أول رد من الشؤون القانونية');
            }
            $this->service->update($id, $update);
            $this->cases->notifyUser((int) $c['owner_id'], null, $this->service->notificationCategory(), 'رد على استشارتك',
                "ردت إدارة الشؤون القانونية على الاستشارة {$c['ref_no']}.", 'consultations/' . $id);
        }

        return redirect()->to(site_url('consultations?c=' . $id) . '#end');
    }

    public function close(int $id)
    {
        $c = $this->load($id);
        if ($c['status'] === 'closed') {
            return $this->deny('المحادثة مغلقة مسبقاً.');
        }
        $this->service->update($id, ['status' => 'closed', 'closed_at' => date('Y-m-d H:i:s')]);
        $this->service->log($id, 'closed', $this->service->isOwner($c) ? 'إغلاق المحادثة من صاحبها' : 'إغلاق المحادثة من الشؤون القانونية');
        if (! $this->service->isOwner($c)) {
            $this->cases->notifyUser((int) $c['owner_id'], null, $this->service->notificationCategory(), 'أُغلقت استشارتك',
                "أُغلقت الاستشارة {$c['ref_no']}.", 'consultations/' . $id);
        }

        return redirect()->to(site_url('consultations?c=' . $id))->with('message', 'أُغلقت المحادثة.');
    }

    /** يحمّل الاستشارة ويتحقق من صلاحية الاطلاع: صاحبها أو الشؤون القانونية */
    private function load(int $id): array
    {
        $c = $this->service->find($id);
        if ($c === null || ! $this->service->canView($c)) {
            throw PageNotFoundException::forPageNotFound('الاستشارة غير موجودة أو غير متاحة لك.');
        }

        return $c;
    }
}
