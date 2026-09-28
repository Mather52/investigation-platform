<?php

namespace App\Controllers;

use App\Libraries\Access;
use App\Libraries\ConsultationService;
use Throwable;

/**
 * الصفحة الرئيسية حسب دور المستخدم (وفق تصور الشاشات المعتمد):
 * الموظف، المحقق، رئيس قسم التحقيق، مدير الشؤون القانونية، المدير العام التنفيذي، ومدير النظام.
 */
class Dashboard extends BaseController
{
    /** ترتيب أولوية الدور الأساسي عند تعدد الأدوار */
    private const ROLE_ORDER = ['gm', 'legal', 'head', 'investigator', 'admin', 'employee'];

    private const ROLE_LABELS = [
        'employee' => 'موظف المدينة الطبية', 'investigator' => 'المحقق', 'head' => 'رئيس قسم التحقيق',
        'legal' => 'مدير إدارة الشؤون القانونية والالتزام', 'gm' => 'المدير العام التنفيذي', 'admin' => 'مدير النظام',
    ];

    public function index()
    {
        $role = $this->primaryRole();
        $db   = db_connect();

        $data = [
            'title' => 'الرئيسية', 'bare' => true, 'active' => 'dashboard', 'role' => $role, 'roleLabel' => self::ROLE_LABELS[$role],
            'greeting' => (int) date('G') < 12 ? 'صباح الخير' : 'مساء الخير', 'firstName' => $this->firstName(), 'today' => $this->today(),
            'canReports' => Access::hasAny(['investigator', 'head', 'legal', 'gm']),
            'need' => $role === 'employee' ? [] : $this->needAction(),
        ];

        if ($role === 'employee') {
            return view('dashboard/index', $data + $this->employeeData());
        }
        if ($role === 'investigator') {
            return view('dashboard/index', $data + $this->investigatorData());
        }

        // رئيس القسم والشؤون القانونية والمدير التنفيذي ومدير النظام: الصورة العامة للمعاملات
        $b      = $db->table('cases c')->select('c.stage_code, COUNT(*) AS n')->groupBy('c.stage_code');
        $counts = array_column($this->cases->scope($b)->get()->getResultArray(), 'n', 'stage_code');
        $c      = static fn (string ...$codes) => array_sum(array_map(static fn ($k) => (int) ($counts[$k] ?? 0), $codes));
        $mine   = fn (string $code) => $db->table('cases')->where('stage_code', 'approval')->where('holder_role_id', $this->cases->roleId($code))->countAllResults();
        $late   = $db->table('v_case_alerts')->where('alert_level', 'red')->countAllResults();

        $kpis = match ($role) {
            'head' => [
                ['محالة بانتظار تعيين محقق', $c('referred'), 'share', 'blue', 'cases?view=referred'],
                ['قيد التحقيق', $c('investigation'), 'search2', 'gray', 'cases?view=investigation'],
                ['بانتظار اعتمادي', $mine('head'), 'checkc', 'yellow', 'cases?view=approvals'],
                ['قيد تنفيذ التوصيات', $c('execution'), 'list', 'teal', 'cases?view=execution'],
                ['متأخرة', $late, 'alert', 'red', 'cases'],
            ],
            'legal' => [
                ['جديدة بانتظار الإحالة', $c('new'), 'share', 'blue', 'cases?view=referred'],
                ['بانتظار اعتمادي', $mine('legal'), 'checkc', 'yellow', 'cases?view=approvals'],
                ['قيد التحقيق', $c('investigation'), 'search2', 'gray', 'cases?view=investigation'],
                ['قيد تنفيذ التوصيات', $c('execution'), 'list', 'teal', 'cases?view=execution'],
                ['استشارات بانتظار الرد', $this->openConsultations(), 'msg', 'blue', 'consultations'],
                ['متأخرة', $late, 'alert', 'red', 'cases'],
            ],
            'gm' => [
                ['لدى المدير العام', $c('with_gm'), 'share', 'blue', 'cases?view=referred'],
                ['بانتظار اعتمادي', $mine('gm'), 'checkc', 'yellow', 'cases?view=approvals'],
                ['قيد التحقيق', $c('investigation'), 'search2', 'gray', 'cases?view=investigation'],
                ['قيد تنفيذ التوصيات', $c('execution'), 'list', 'teal', 'cases?view=execution'],
                ['المؤرشفة', $c('archived', 'closed_no_action'), 'archive', 'gray', 'cases?view=archive'],
                ['متأخرة', $late, 'alert', 'red', 'cases'],
            ],
            default => [
                ['إجمالي المعاملات', array_sum($counts), 'folder', 'teal', 'cases'],
                ['المعاملات الجديدة', $c('new', 'with_gm', 'referred'), 'plus', 'blue', 'cases?view=referred'],
                ['قيد التحقيق', $c('investigation'), 'search2', 'gray', 'cases?view=investigation'],
                ['بانتظار الاعتماد', $c('approval'), 'checkc', 'yellow', 'cases?view=approvals'],
                ['قيد تنفيذ التوصيات', $c('execution'), 'list', 'teal', 'cases?view=execution'],
                ['المعاملات المؤرشفة', $c('archived', 'closed_no_action'), 'archive', 'gray', 'cases?view=archive'],
            ],
        };

        $byType = $this->cases->scope(
            $db->table('cases c')->select('t.name_ar AS label, COUNT(*) AS n')->join('case_types t', 't.id = c.case_type_id')->groupBy('t.name_ar')
        )->orderBy('n', 'DESC')->get()->getResultArray();

        $bySource = $this->cases->scope(
            $db->table('case_sources s')->select('s.name_ar AS label, COUNT(c.id) AS n')->join('cases c', 'c.source_code = s.code', 'left')->groupBy('s.code, s.name_ar')
        )->orderBy('n', 'DESC')->get()->getResultArray();

        return view('dashboard/index', $data + [
            'kpis' => $kpis, 'stages' => $db->table('case_stages')->orderBy('sort_order')->get()->getResultArray(), 'counts' => $counts,
            'byType' => $byType, 'bySource' => $bySource, 'durations' => $this->durations(),
            'workload' => $role === 'head' || $role === 'admin' ? $this->workload() : [],
        ]);
    }

    /** الموظف: معاملاته، ودعواته للحضور، واستشاراته */
    private function employeeData(): array
    {
        $db  = db_connect();
        $uid = $this->uid();

        $myCases = $db->table('cases c')
            ->select('c.id, c.case_no, c.subject, c.stage_code, c.created_at, s.name_ar AS stage_name')
            ->join('case_stages s', 's.code = c.stage_code')
            ->where('c.created_by', $uid)->orderBy('c.created_at', 'DESC')->limit(8)->get()->getResultArray();

        $invites = $db->table('invitations i')
            ->select('i.id, i.session_at, i.attendance_mode, i.status, i.attendance_status, i.invitation_type, c.case_no')
            ->join('case_parties p', 'p.id = i.party_id')
            ->join('users u', 'u.employee_id = p.employee_id')
            ->join('cases c', 'c.id = i.case_id')
            ->where('u.id', $uid)->whereIn('i.status', ['sent', 'read'])
            ->orderBy('i.session_at', 'DESC')->limit(6)->get()->getResultArray();
        $upcoming = count(array_filter($invites, static fn ($i) => strtotime($i['session_at']) >= time() && $i['attendance_status'] === 'pending'));

        $all = [];
        try {
            $all = (new ConsultationService())->mine();
        } catch (Throwable $e) {
            log_message('error', 'Dashboard consultations: ' . $e->getMessage());
        }
        $consultations = array_slice($all, 0, 5);
        $openCons      = count(array_filter($all, static fn ($r) => in_array($r['status'], ConsultationService::OPEN_STATUSES, true)));

        return [
            'kpis' => [
                ['معاملاتي المقدَّمة', $db->table('cases')->where('created_by', $uid)->where('stage_code !=', 'draft')->countAllResults(), 'folder', 'teal', 'cases'],
                ['مسودات لم تُرسل', $db->table('cases')->where('created_by', $uid)->where('stage_code', 'draft')->countAllResults(), 'save', 'gray', 'cases?stage=draft'],
                ['دعوات حضور قادمة', $upcoming, 'cal', 'yellow', 'notifications?category=invitation'],
                ['استشارات بانتظار الرد', $openCons, 'msg', 'blue', 'consultations'],
            ],
            'myCases' => $myCases, 'invites' => $invites, 'consultations' => $consultations,
        ];
    }

    /** المحقق: المعاملات المسندة إليه وجلساتها وطلباتها */
    private function investigatorData(): array
    {
        $db  = db_connect();
        $uid = $this->uid();
        $now = date('Y-m-d H:i:s');

        $sessions = $db->table('invitations i')
            ->select('i.id, i.case_id, i.session_at, i.attendance_mode, i.invitation_type, c.case_no, COALESCE(e.full_name, p.external_name) AS party_name')
            ->join('cases c', 'c.id = i.case_id')
            ->join('case_parties p', 'p.id = i.party_id')
            ->join('employees e', 'e.id = p.employee_id', 'left')
            ->where('c.investigator_user_id', $uid)->where('i.status !=', 'draft')->where('i.attendance_status', 'pending')
            ->where('i.session_at >=', $now)->orderBy('i.session_at')->limit(6)->get()->getResultArray();

        return [
            'kpis' => [
                ['قيد التحقيق لديّ', $db->table('cases')->where('investigator_user_id', $uid)->where('stage_code', 'investigation')->countAllResults(), 'search2', 'blue', 'cases?view=investigation'],
                ['جلسات قادمة', $db->table('invitations i')->join('cases c', 'c.id = i.case_id')->where('c.investigator_user_id', $uid)
                    ->where('i.status !=', 'draft')->where('i.attendance_status', 'pending')->where('i.session_at >=', $now)->countAllResults(), 'video', 'teal', 'cases?view=sessions'],
                ['طلبات إفادة معلّقة', $db->table('statement_requests r')->join('cases c', 'c.id = r.case_id')->where('c.investigator_user_id', $uid)
                    ->where('r.status !=', 'answered')->countAllResults(), 'users', 'yellow', 'cases?view=statements'],
                ['مذكرات معادة للتعديل', $db->table('memos m')->join('cases c', 'c.id = m.case_id')->where('c.investigator_user_id', $uid)
                    ->where('m.status', 'returned')->countAllResults(), 'file', 'red', 'cases?view=memos'],
                ['متأخرة', $db->table('v_case_alerts')->where('investigator_user_id', $uid)->where('alert_level', 'red')->countAllResults(), 'alert', 'red', 'cases?view=investigation'],
            ],
            'sessions' => $sessions,
        ];
    }

    /** المعاملات التي تنتظر إجراء من المستخدم الحالي */
    private function needAction(): array
    {
        $db   = db_connect();
        $need = $db->table('cases c')
            ->select('c.id, c.case_no, c.stage_code, c.last_action_at, c.action_count, c.holder_role_id, t.name_ar AS type_name, s.name_ar AS stage_name,
                      r.code AS holder_code, r.name_ar AS holder_name, ie.full_name AS investigator_name, a.alert_level, a.idle_days, a.rule_code')
            ->join('case_types t', 't.id = c.case_type_id')
            ->join('case_stages s', 's.code = c.stage_code')
            ->join('roles r', 'r.id = c.holder_role_id', 'left')
            ->join('users iu', 'iu.id = c.investigator_user_id', 'left')
            ->join('employees ie', 'ie.id = iu.employee_id', 'left')
            ->join('v_case_alerts a', 'a.id = c.id')
            ->where('s.is_final', 0);
        if (! Access::isAdmin()) {
            $need->whereIn('c.holder_role_id', session('role_ids') ?: [0]);
            if (! Access::hasAny(['head', 'legal', 'gm'])) {
                $need->where('c.investigator_user_id', $this->uid());
            }
        }
        $need = $need->orderBy('a.idle_days', 'DESC')->limit(12)->get()->getResultArray();

        $settings = array_column($db->table('alert_settings')->get()->getResultArray(), null, 'rule_code');
        foreach ($need as &$n) {
            $base     = $n['rule_code'] === 'new_case' ? 'new_case' : 'after_action';
            $n['due'] = date('Y-m-d', strtotime($n['last_action_at'] . ' +' . $settings[$base]['red_days'] . ' days'));
        }
        unset($n);

        return $need;
    }

    /** متوسط مدة كل مرحلة بالأيام (آخر 180 يوماً) */
    private function durations(): array
    {
        $rows = db_connect()->table('case_actions')
            ->select('case_id, action_code, MIN(created_at) AS at')
            ->whereIn('action_code', ['created', 'referred', 'investigator_assigned', 'memo_submitted', 'memo_approved', 'archived'])
            ->where('created_at >=', date('Y-m-d', strtotime('-180 days')))
            ->groupBy('case_id, action_code')
            ->get()->getResultArray();
        $per = [];
        foreach ($rows as $r) {
            $per[$r['case_id']][$r['action_code']] = strtotime($r['at']);
        }
        $out = [];
        foreach ([['الإحالة', 'created', 'investigator_assigned'], ['التحقيق', 'investigator_assigned', 'memo_submitted'], ['الاعتماد', 'memo_submitted', 'memo_approved'], ['التنفيذ', 'memo_approved', 'archived']] as [$label, $from, $to]) {
            $vals = [];
            foreach ($per as $p) {
                if (isset($p[$from], $p[$to]) && $p[$to] >= $p[$from]) {
                    $vals[] = ($p[$to] - $p[$from]) / 86400;
                }
            }
            $out[] = [$label, $vals ? round(array_sum($vals) / count($vals), 1) : null, count($vals)];
        }

        return $out;
    }

    /** توزيع العمل على المحققين (لرئيس القسم) */
    private function workload(): array
    {
        return db_connect()->table('cases c')
            ->select("COALESCE(e.full_name, u.username) AS name, SUM(c.stage_code = 'investigation') AS open_n,
                      SUM(c.stage_code = 'approval') AS approval_n, COUNT(*) AS total", false)
            ->join('users u', 'u.id = c.investigator_user_id')->join('employees e', 'e.id = u.employee_id', 'left')
            ->groupBy('u.id, name')->orderBy('open_n', 'DESC')->get()->getResultArray();
    }

    private function openConsultations(): int
    {
        try {
            return db_connect()->table('consultations')->whereIn('status', ConsultationService::OPEN_STATUSES)->countAllResults();
        } catch (Throwable) {
            return 0;
        }
    }

    private function primaryRole(): string
    {
        $roles = Access::roles();
        foreach (self::ROLE_ORDER as $code) {
            if (in_array($code, $roles, true)) {
                return $code;
            }
        }

        return 'employee';
    }

    private function firstName(): string
    {
        $name = trim(preg_replace('/^د\.\s*/u', '', (string) session('full_name')));

        return $name === '' ? '' : explode(' ', $name)[0];
    }

    private function today(): string
    {
        if (class_exists(\IntlDateFormatter::class)) {
            $f = (new \IntlDateFormatter('ar@numbers=latn;calendar=gregorian', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE))->format(time());
            if ($f) {
                return $f;
            }
        }

        return date('Y-m-d');
    }
}
