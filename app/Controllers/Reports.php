<?php

namespace App\Controllers;

use App\Libraries\ConsultationService;
use Throwable;

class Reports extends BaseController
{
    public function index()
    {
        $period = $this->request->getGet('period') ?? 'monthly';
        $days   = ['weekly' => 7, 'monthly' => 30, 'quarterly' => 90][$period] ?? 30;
        $to     = date('Y-m-d 23:59:59');
        $from   = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        $db     = db_connect();
        $scoped = fn () => $this->cases->scope($db->table('cases c'));

        $created  = $scoped()->where('c.created_at >=', $from)->where('c.stage_code !=', 'draft')->countAllResults();
        $closed   = $scoped()->groupStart()->where('c.archived_at >=', $from)->orWhere('c.closed_at >=', $from)->groupEnd()->countAllResults();
        $open     = $scoped()->join('case_stages s', 's.code = c.stage_code')->where('s.is_final', 0)->where('c.stage_code !=', 'draft')->countAllResults();
        $late     = $scoped()->join('v_case_alerts a', 'a.id = c.id')->where('a.alert_level', 'red')->countAllResults();
        $avg      = $scoped()->select('AVG(DATEDIFF(c.archived_at, c.created_at)) AS v')->where('c.archived_at >=', $from)->get()->getRow('v');

        $byType = $scoped()->select('t.name_ar AS label, COUNT(*) AS n')->join('case_types t', 't.id = c.case_type_id')
            ->where('c.created_at >=', $from)->where('c.stage_code !=', 'draft')->groupBy('t.name_ar')->orderBy('n', 'DESC')->get()->getResultArray();
        $byDept = $scoped()->select('d.name_ar AS label, COUNT(*) AS n')->join('departments d', 'd.id = c.department_id')
            ->where('c.created_at >=', $from)->where('c.stage_code !=', 'draft')->groupBy('d.name_ar')->orderBy('n', 'DESC')->limit(8)->get()->getResultArray();
        $byStage = $scoped()->select('s.name_ar AS label, s.code, COUNT(*) AS n')->join('case_stages s', 's.code = c.stage_code')
            ->groupBy('s.code, s.name_ar, s.sort_order')->orderBy('s.sort_order')->get()->getResultArray();

        $recs = $scoped()->select("COUNT(r.id) AS total, SUM(r.status = 'done') AS done, SUM(r.status = 'in_progress') AS progress,
                SUM(r.status = 'not_started') AS not_started, SUM(r.status <> 'done' AND r.due_date < CURDATE()) AS late")
            ->join('recommendations r', 'r.case_id = c.id')->get()->getRowArray();

        $workload = $scoped()->select("COALESCE(e.full_name, u.username) AS name,
                SUM(c.stage_code IN ('investigation','approval')) AS open_n,
                SUM(c.archived_at >= " . $db->escape($from) . ") AS closed_n, COUNT(*) AS total")
            ->join('users u', 'u.id = c.investigator_user_id')->join('employees e', 'e.id = u.employee_id', 'left')
            ->groupBy('u.id, name')->orderBy('open_n', 'DESC')->get()->getResultArray();

        $recipients = [];
        foreach (['gm' => 'المدير العام التنفيذي', 'legal' => 'مدير إدارة الشؤون القانونية والالتزام', 'head' => 'رئيس التحقيقات'] as $code => $label) {
            foreach ($this->cases->usersWithRole($code) as $u) {
                $recipients[] = [$u['name'], $label];
            }
        }

        try {
            $consultations = (new ConsultationService())->monthStats();
        } catch (Throwable $e) {
            log_message('error', 'Consultation stats: ' . $e->getMessage());
            $consultations = null;
        }

        return view('reports/index', [
            'title' => 'التقارير', 'subtitle' => 'تقارير دورية عن أعمال قسم التحقيق', 'active' => 'reports', 'crumbs' => ['التقارير'],
            'period' => $period, 'from' => $from, 'to' => $to,
            'kpis' => [['معاملات واردة', $created, 'plus'], ['معاملات منجزة', $closed, 'checkc'], ['قيد الإجراء الآن', $open, 'clock'], ['متأخرة الآن', $late, 'alert'], ['متوسط مدة الإنجاز (يوم)', $avg === null ? '—' : round((float) $avg, 1), 'chart']],
            'byType' => $byType, 'byDept' => $byDept, 'byStage' => $byStage, 'recs' => $recs, 'workload' => $workload, 'recipients' => $recipients,
            'consultations' => $consultations,
        ]);
    }
}
