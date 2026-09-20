<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class Sessions extends BaseController
{
    public function startFromInvitation(int $invId)
    {
        $db  = db_connect();
        $inv = $db->table('invitations')->where('id', $invId)->get()->getRowArray();
        if ($inv === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $case = $this->loadCase((int) $inv['case_id']);
        if (! $this->cases->canInvestigate($case) || $inv['attendance_status'] !== 'attended') {
            return $this->deny('تبدأ الجلسة بعد تسجيل حضور المدعو.');
        }
        $existing = $db->table('sessions')->where('invitation_id', $invId)->get()->getRow('id');
        if ($existing) {
            return redirect()->to(site_url('sessions/' . $existing));
        }

        $db->transStart();
        $no = (int) $db->table('sessions')->selectMax('session_no')->where('case_id', $case['id'])->get()->getRow('session_no') + 1;
        $db->table('sessions')->insert([
            'case_id' => $case['id'], 'party_id' => $inv['party_id'], 'invitation_id' => $invId,
            'session_no' => $no, 'mode' => $inv['attendance_mode'], 'status' => 'scheduled',
            'investigator_user_id' => $case['investigator_user_id'] ?: $this->uid(),
        ]);
        $sid   = (int) $db->insertID();
        $party = $db->table('case_parties')->where('id', $inv['party_id'])->get()->getRowArray();
        $db->table('session_participants')->insertBatch([
            ['session_id' => $sid, 'user_id' => $case['investigator_user_id'] ?: $this->uid(), 'party_id' => null, 'role_label' => 'investigator'],
            ['session_id' => $sid, 'user_id' => $this->partyUser($party), 'party_id' => $party['id'], 'role_label' => $party['party_role']],
        ]);
        $db->transComplete();

        return redirect()->to(site_url('sessions/' . $sid));
    }

    public function show(int $sid)
    {
        [$s, $case, $isInvestigator, $me] = $this->load($sid);
        $db = db_connect();

        $participants = $db->table('session_participants sp')
            ->select('sp.*, COALESCE(ue.full_name, pe.full_name, p.external_name, u.username) AS name')
            ->join('users u', 'u.id = sp.user_id', 'left')->join('employees ue', 'ue.id = u.employee_id', 'left')
            ->join('case_parties p', 'p.id = sp.party_id', 'left')->join('employees pe', 'pe.id = p.employee_id', 'left')
            ->where('sp.session_id', $sid)->orderBy('sp.id')->get()->getResultArray();

        $minutes = $db->table('investigation_questions q')->select('q.*, t.title AS topic_title')
            ->join('investigation_topics t', 't.id = q.topic_id')
            ->where('q.session_id', $sid)->orderBy('q.asked_at')->orderBy('q.id')->get()->getResultArray();

        $messages = $db->table('session_messages m')
            ->select('m.*, sp.user_id, sp.role_label, COALESCE(ue.full_name, pe.full_name, p.external_name) AS name')
            ->join('session_participants sp', 'sp.id = m.participant_id')
            ->join('users u', 'u.id = sp.user_id', 'left')->join('employees ue', 'ue.id = u.employee_id', 'left')
            ->join('case_parties p', 'p.id = sp.party_id', 'left')->join('employees pe', 'pe.id = p.employee_id', 'left')
            ->where('m.session_id', $sid)->orderBy('m.id')->get()->getResultArray();

        return view('sessions/show', [
            'title' => 'جلسة التحقيق', 'subtitle' => 'جلسة تحقيق آمنة مع محضر مكتوب', 'active' => 'sessions',
            'crumbs' => [['المعاملات', 'cases'], [$case['case_no'], "cases/{$case['id']}"], 'جلسة التحقيق'],
            's' => $s, 'case' => $case, 'isInvestigator' => $isInvestigator, 'me' => $me,
            'participants' => $participants, 'minutes' => $minutes, 'messages' => $messages,
            'topics' => $db->table('investigation_topics')->where('case_id', $case['id'])->orderBy('sort_order')->get()->getResultArray(),
        ]);
    }

    public function status(int $sid)
    {
        [$s, $case, $isInvestigator] = $this->load($sid);
        if (! $isInvestigator || $s['status'] === 'closed') {
            return $this->deny();
        }
        $to  = (string) $this->request->getPost('to');
        $map = ['start' => 'in_progress', 'pause' => 'paused', 'resume' => 'in_progress', 'end' => 'closed'];
        if (! isset($map[$to])) {
            return $this->deny();
        }
        $data = ['status' => $map[$to]];
        if ($to === 'start' && ! $s['started_at']) {
            $data['started_at'] = date('Y-m-d H:i:s');
        }
        if ($to === 'end') {
            if (! $s['started_at']) {
                $data['started_at'] = date('Y-m-d H:i:s');
            }
            $data['closed_at'] = date('Y-m-d H:i:s');
        }
        db_connect()->table('sessions')->where('id', $sid)->update($data);

        $labels = ['start' => ['session_started', 'بدء جلسة التحقيق رقم ' . $s['session_no']], 'pause' => ['session_paused', 'إيقاف مؤقت للجلسة رقم ' . $s['session_no']],
            'resume' => ['session_started', 'استئناف الجلسة رقم ' . $s['session_no']], 'end' => ['session_closed', 'إنهاء جلسة التحقيق رقم ' . $s['session_no'] . ' وحفظ المحضر']];
        $this->cases->log((int) $case['id'], $labels[$to][0], $labels[$to][1]);

        if ($to === 'end') {
            return redirect()->to(site_url("cases/{$case['id']}/topics"))->with('message', 'انتهت الجلسة وحُفظ المحضر.');
        }

        return redirect()->back();
    }

    public function minutes(int $sid)
    {
        [$s, $case, $isInvestigator] = $this->load($sid);
        if (! $isInvestigator || $s['status'] !== 'in_progress') {
            return $this->deny('يُسجَّل المحضر أثناء الجلسة الجارية فقط.');
        }
        $db = db_connect();

        // الإجابة على سؤال قائم
        if ($qid = (int) $this->request->getPost('question_id')) {
            $answer = trim((string) $this->request->getPost('answer'));
            if ($answer === '') {
                return redirect()->back()->with('error', 'اكتب الإجابة.');
            }
            $db->table('investigation_questions')->where(['id' => $qid, 'session_id' => $sid])
                ->update(['answer' => $answer, 'answered_at' => date('Y-m-d H:i:s')]);

            return redirect()->to(site_url("sessions/{$sid}#minutes"));
        }

        $question = trim((string) $this->request->getPost('question'));
        if ($question === '') {
            return redirect()->back()->with('error', 'اكتب السؤال.');
        }
        $topicId = (int) $this->request->getPost('topic_id');
        if (! $topicId) {
            $topicId = (int) $db->table('investigation_topics')->select('id')->where(['case_id' => $case['id'], 'title' => 'أسئلة عامة'])->get()->getRow('id');
            if (! $topicId) {
                $db->table('investigation_topics')->insert(['case_id' => $case['id'], 'title' => 'أسئلة عامة', 'sort_order' => 99, 'status' => 'in_progress', 'created_by' => $this->uid()]);
                $topicId = (int) $db->insertID();
            }
        }
        $answer = trim((string) $this->request->getPost('answer'));
        $order  = (int) $db->table('investigation_questions')->selectMax('sort_order')->where('topic_id', $topicId)->get()->getRow('sort_order') + 1;
        $db->table('investigation_questions')->insert([
            'topic_id' => $topicId, 'session_id' => $sid, 'question' => $question,
            'answer' => $answer ?: null, 'asked_at' => date('Y-m-d H:i:s'),
            'answered_at' => $answer ? date('Y-m-d H:i:s') : null, 'sort_order' => $order,
        ]);
        $db->table('investigation_topics')->where(['id' => $topicId, 'status' => 'not_started'])->update(['status' => 'in_progress']);

        return redirect()->to(site_url("sessions/{$sid}#minutes"));
    }

    public function message(int $sid)
    {
        [$s, $case, , $me] = $this->load($sid);
        $body = trim((string) $this->request->getPost('body'));
        if ($body === '' || $s['status'] === 'closed') {
            return redirect()->back();
        }
        $db = db_connect();
        if ($me === null) {
            $db->table('session_participants')->insert(['session_id' => $sid, 'user_id' => $this->uid(), 'role_label' => 'investigator', 'admitted_at' => date('Y-m-d H:i:s')]);
            $me = ['id' => $db->insertID()];
        }
        $db->table('session_messages')->insert(['session_id' => $sid, 'participant_id' => $me['id'], 'body' => mb_substr($body, 0, 2000)]);

        return redirect()->to(site_url("sessions/{$sid}#chat"));
    }

    /** @return array{0: array, 1: array, 2: bool, 3: ?array} */
    private function load(int $sid): array
    {
        $db = db_connect();
        $s  = $db->table('sessions s')
            ->select('s.*, COALESCE(e.full_name, p.external_name) AS party_name, p.party_role, i.meeting_link, i.session_at, ie.full_name AS investigator_name')
            ->join('case_parties p', 'p.id = s.party_id')->join('employees e', 'e.id = p.employee_id', 'left')
            ->join('invitations i', 'i.id = s.invitation_id', 'left')
            ->join('users iu', 'iu.id = s.investigator_user_id')->join('employees ie', 'ie.id = iu.employee_id', 'left')
            ->where('s.id', $sid)->get()->getRowArray();
        if ($s === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $me = $db->table('session_participants')->where(['session_id' => $sid, 'user_id' => $this->uid()])->get()->getRowArray();
        $case = $this->cases->find((int) $s['case_id']);
        if (! $this->cases->canView($case) && $me === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return [$s, $case, $this->cases->canInvestigate($case), $me];
    }

    private function partyUser(array $party): ?int
    {
        if (! $party['employee_id']) {
            return null;
        }
        $id = db_connect()->table('users')->select('id')->where('employee_id', $party['employee_id'])->get()->getRow('id');

        return $id ? (int) $id : null;
    }
}
