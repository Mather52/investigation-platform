<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class Notifications extends BaseController
{
    public const ICONS = ['new_case' => 'folder', 'invitation' => 'mail', 'session' => 'video', 'statement' => 'users', 'approval' => 'checkc', 'decision' => 'shield', 'attachment' => 'clip', 'reply' => 'msg'];

    public function index()
    {
        // توليد الإنذارات آلياً مرة كل ساعة كحد أقصى
        $cache = cache();
        if (! $cache->get('alerts_checked')) {
            $this->cases->generateAlerts();
            $cache->save('alerts_checked', 1, 3600);
        }

        $db       = db_connect();
        $category = (string) $this->request->getGet('category');
        $level    = (string) $this->request->getGet('level');
        $counts   = array_column($db->table('notifications')->select('category, COUNT(*) AS n')->where('user_id', $this->uid())->groupBy('category')->get()->getResultArray(), 'n', 'category');

        $b = $db->table('notifications n')->select('n.*, c.case_no')->join('cases c', 'c.id = n.case_id', 'left')->where('n.user_id', $this->uid());
        if (isset(self::ICONS[$category])) {
            $b->where('n.category', $category);
        }
        if (in_array($level, ['green', 'yellow', 'red'], true)) {
            $b->where('n.level', $level);
        }
        $rows   = $b->orderBy('n.created_at', 'DESC')->limit(100)->get()->getResultArray();
        $unread = $db->table('notifications')->where('user_id', $this->uid())->where('read_at', null)->countAllResults();

        return view('notifications/index', [
            'title' => 'مركز الإشعارات', 'subtitle' => 'تنبيهات المعاملات والجلسات والاعتمادات', 'active' => 'notifications',
            'crumbs' => ['الإشعارات'], 'rows' => $rows, 'counts' => $counts, 'category' => $category, 'level' => $level, 'unread' => $unread,
        ]);
    }

    public function open(int $nid)
    {
        $db = db_connect();
        $n  = $db->table('notifications')->where(['id' => $nid, 'user_id' => $this->uid()])->get()->getRowArray();
        if ($n === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        if (! $n['read_at']) {
            $db->table('notifications')->where('id', $nid)->update(['read_at' => date('Y-m-d H:i:s')]);
        }

        return redirect()->to(site_url($n['link'] ?: ($n['case_id'] ? 'cases/' . $n['case_id'] : 'notifications')));
    }

    public function readAll()
    {
        db_connect()->table('notifications')->where('user_id', $this->uid())->where('read_at', null)->update(['read_at' => date('Y-m-d H:i:s')]);

        return redirect()->back()->with('message', 'تم تعليم كل الإشعارات كمقروءة.');
    }
}
