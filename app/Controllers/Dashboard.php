<?php

namespace App\Controllers;

use App\Libraries\Access;

class Dashboard extends BaseController
{
    public function index()
    {
        $db = db_connect();

        // المعاملات حسب المرحلة (ضمن نطاق المستخدم)
        $b = $db->table('cases c')->select('c.stage_code, COUNT(*) AS n')->groupBy('c.stage_code');
        $counts = array_column($this->cases->scope($b)->get()->getResultArray(), 'n', 'stage_code');
        $stages = $db->table('case_stages')->orderBy('sort_order')->get()->getResultArray();
        $c = static fn (string ...$codes) => array_sum(array_map(static fn ($k) => (int) ($counts[$k] ?? 0), $codes));

        $kpis = [
            ['إجمالي المعاملات', array_sum($counts), 'folder', 'teal', 'cases'],
            ['المعاملات الجديدة', $c('new', 'with_gm', 'referred'), 'plus', 'blue', 'cases?view=referred'],
            ['قيد التحقيق', $c('investigation'), 'search2', 'gray', 'cases?view=investigation'],
            ['بانتظار الاعتماد', $c('approval'), 'checkc', 'yellow', 'cases?view=approvals'],
            ['قيد تنفيذ التوصيات', $c('execution'), 'list', 'teal', 'cases?view=execution'],
            ['المعاملات المؤرشفة', $c('archived', 'closed_no_action'), 'archive', 'gray', 'cases?view=archive'],
        ];

        $byType = $this->cases->scope(
            $db->table('cases c')->select('t.name_ar AS label, COUNT(*) AS n')->join('case_types t', 't.id = c.case_type_id')->groupBy('t.name_ar')
        )->orderBy('n', 'DESC')->get()->getResultArray();

        $bySource = $this->cases->scope(
            $db->table('case_sources s')->select('s.name_ar AS label, COUNT(c.id) AS n')->join('cases c', 'c.source_code = s.code', 'left')->groupBy('s.code, s.name_ar')
        )->orderBy('n', 'DESC')->get()->getResultArray();

        // متوسط مدة كل مرحلة بالأيام (آخر 180 يوماً)
        $rows = $db->table('case_actions')
            ->select('case_id, action_code, MIN(created_at) AS at')
            ->whereIn('action_code', ['created', 'referred', 'investigator_assigned', 'memo_submitted', 'memo_approved', 'archived'])
            ->where('created_at >=', date('Y-m-d', strtotime('-180 days')))
            ->groupBy('case_id, action_code')
            ->get()->getResultArray();
        $per = [];
        foreach ($rows as $r) {
            $per[$r['case_id']][$r['action_code']] = strtotime($r['at']);
        }
        $phases = [
            ['الإحالة', 'created', 'investigator_assigned'],
            ['التحقيق', 'investigator_assigned', 'memo_submitted'],
            ['الاعتماد', 'memo_submitted', 'memo_approved'],
            ['التنفيذ', 'memo_approved', 'archived'],
        ];
        $durations = [];
        foreach ($phases as [$label, $from, $to]) {
            $vals = [];
            foreach ($per as $p) {
                if (isset($p[$from], $p[$to]) && $p[$to] >= $p[$from]) {
                    $vals[] = ($p[$to] - $p[$from]) / 86400;
                }
            }
            $durations[] = [$label, $vals ? round(array_sum($vals) / count($vals), 1) : null, count($vals)];
        }

        // المعاملات التي تحتاج إجراء
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
            $base = $n['rule_code'] === 'new_case' ? 'new_case' : 'after_action';
            $n['due'] = date('Y-m-d', strtotime($n['last_action_at'] . ' +' . $settings[$base]['red_days'] . ' days'));
        }
        unset($n);

        $today = date('Y-m-d');
        if (class_exists(\IntlDateFormatter::class)) {
            $today = (new \IntlDateFormatter('ar@numbers=latn;calendar=gregorian', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE))->format(time()) ?: $today;
        }
        $name = trim(preg_replace('/^د\.\s*/u', '', (string) session('full_name')));

        return view('dashboard/index', [
            'title' => 'الرئيسية', 'bare' => true, 'active' => 'dashboard',
            'greeting' => (int) date('G') < 12 ? 'صباح الخير' : 'مساء الخير',
            'firstName' => $name === '' ? '' : explode(' ', $name)[0], 'today' => $today,
            'canReports' => Access::hasAny(['investigator', 'head', 'legal', 'gm']),
            'kpis' => $kpis, 'stages' => $stages, 'counts' => $counts, 'byType' => $byType, 'bySource' => $bySource,
            'durations' => $durations, 'need' => $need,
        ]);
    }
}
