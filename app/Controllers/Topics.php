<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class Topics extends BaseController
{
    public function index(int $id)
    {
        $case   = $this->loadCase($id);
        $db     = db_connect();
        $topics = $db->table('investigation_topics')->where('case_id', $id)->orderBy('sort_order')->orderBy('id')->get()->getResultArray();
        $qs     = $topics ? $db->table('investigation_questions q')->select('q.*, s.session_no')
            ->join('sessions s', 's.id = q.session_id', 'left')
            ->whereIn('q.topic_id', array_column($topics, 'id'))->orderBy('q.sort_order')->orderBy('q.id')->get()->getResultArray() : [];
        foreach ($topics as &$t) {
            $t['questions'] = array_values(array_filter($qs, fn ($q) => (int) $q['topic_id'] === (int) $t['id']));
        }
        unset($t);

        return view('topics/index', [
            'title' => 'محاور التحقيق', 'subtitle' => 'تنظيم أسئلة التحقيق وإجاباتها حسب المحاور', 'active' => 'investigation',
            'crumbs' => [['المعاملات', 'cases'], [$case['case_no'], "cases/{$id}"], 'محاور التحقيق'],
            'case' => $case, 'parties' => $this->cases->parties($id), 'topics' => $topics,
            'canEdit' => $this->cases->canInvestigate($case), 'open' => (int) ($this->request->getGet('open') ?? 0),
        ]);
    }

    public function store(int $id)
    {
        $case = $this->loadCase($id);
        if (! $this->cases->canInvestigate($case)) {
            return $this->deny();
        }
        $title = trim((string) $this->request->getPost('title'));
        if ($title === '') {
            return redirect()->back()->with('error', 'اكتب عنوان المحور.');
        }
        $db    = db_connect();
        $order = (int) $db->table('investigation_topics')->selectMax('sort_order')->where(['case_id' => $id, 'sort_order <' => 99])->get()->getRow('sort_order') + 1;
        $db->table('investigation_topics')->insert(['case_id' => $id, 'title' => mb_substr($title, 0, 255), 'sort_order' => $order, 'created_by' => $this->uid()]);
        $tid = (int) $db->insertID();
        $this->cases->log($id, 'topic_added', 'إضافة محور تحقيق: ' . $title);

        return redirect()->to(site_url("cases/{$id}/topics?open={$tid}#t{$tid}"))->with('message', 'أُضيف المحور.');
    }

    public function save(int $tid)
    {
        $db    = db_connect();
        $topic = $db->table('investigation_topics')->where('id', $tid)->get()->getRowArray();
        if ($topic === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $case = $this->loadCase((int) $topic['case_id']);
        if (! $this->cases->canInvestigate($case)) {
            return $this->deny();
        }

        $db->transStart();
        $db->table('investigation_topics')->where('id', $tid)->update([
            'title'  => mb_substr(trim((string) $this->request->getPost('title')) ?: $topic['title'], 0, 255),
            'status' => in_array($this->request->getPost('status'), ['not_started', 'in_progress', 'done'], true) ? $this->request->getPost('status') : $topic['status'],
        ]);
        foreach ((array) $this->request->getPost('q') as $qid => $row) {
            $question = trim((string) ($row['question'] ?? ''));
            if ($question === '') {
                continue;
            }
            $answer = trim((string) ($row['answer'] ?? ''));
            $current = $db->table('investigation_questions')->where(['id' => (int) $qid, 'topic_id' => $tid])->get()->getRowArray();
            if ($current === null) {
                continue;
            }
            $db->table('investigation_questions')->where('id', (int) $qid)->update([
                'question' => $question, 'answer' => $answer ?: null,
                'investigator_notes' => trim((string) ($row['notes'] ?? '')) ?: null,
                'answered_at' => $answer && ! $current['answered_at'] ? date('Y-m-d H:i:s') : $current['answered_at'],
            ]);
        }
        $new = (array) $this->request->getPost('new');
        if (trim((string) ($new['question'] ?? '')) !== '') {
            $order  = (int) $db->table('investigation_questions')->selectMax('sort_order')->where('topic_id', $tid)->get()->getRow('sort_order') + 1;
            $answer = trim((string) ($new['answer'] ?? ''));
            $db->table('investigation_questions')->insert([
                'topic_id' => $tid, 'question' => trim($new['question']), 'answer' => $answer ?: null,
                'investigator_notes' => trim((string) ($new['notes'] ?? '')) ?: null,
                'answered_at' => $answer ? date('Y-m-d H:i:s') : null, 'sort_order' => $order,
            ]);
            if ($topic['status'] === 'not_started' && $this->request->getPost('status') === 'not_started') {
                $db->table('investigation_topics')->where('id', $tid)->update(['status' => 'in_progress']);
            }
        }
        $db->transComplete();

        return redirect()->to(site_url("cases/{$case['id']}/topics?open={$tid}#t{$tid}"))->with('message', 'تم حفظ المحور.');
    }
}
