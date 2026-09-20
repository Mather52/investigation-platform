<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class Invitations extends BaseController
{
    private const TYPES = ['accused' => 'دعوة موظف محل التحقيق', 'witness' => 'دعوة شاهد', 'complainant' => 'دعوة مقدم الشكوى', 'expert' => 'دعوة مختص'];

    public function create(int $id)
    {
        $case = $this->loadCase($id);
        if (! $this->cases->canInvestigate($case)) {
            return $this->deny('إرسال الدعوات متاح للمحقق أثناء مرحلة التحقيق.');
        }
        $parties = $this->cases->parties($id);
        $sel     = (int) ($this->request->getGet('party') ?? 0);
        $party   = null;
        foreach ($parties as $p) {
            if ($sel === 0 && $p['party_role'] === 'accused' || (int) $p['id'] === $sel) {
                $party = $p;
                if ($sel) {
                    break;
                }
                $sel = (int) $p['id'];
            }
        }
        $reissue = (int) ($this->request->getGet('reissue') ?? 0);

        return view('invitations/create', [
            'title' => 'دعوة للحضور', 'subtitle' => 'إرسال دعوة حضور جلسة تحقيق', 'active' => 'sessions',
            'crumbs' => [['المعاملات', 'cases'], [$case['case_no'], "cases/{$id}"], 'دعوة للحضور'],
            'case' => $case, 'parties' => $parties, 'party' => $party, 'types' => self::TYPES, 'reissue' => $reissue,
            'link' => 'https://investigation.medcity.sa/s/' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 7)),
        ]);
    }

    public function store(int $id)
    {
        $case = $this->loadCase($id);
        if (! $this->cases->canInvestigate($case)) {
            return $this->deny();
        }
        $rules = [
            'party_id'        => 'required|is_natural_no_zero',
            'invitation_type' => 'required|in_list[accused,witness,complainant,expert]',
            'session_date'    => 'required|valid_date[Y-m-d]',
            'session_time'    => 'required|regex_match[/^\d{2}:\d{2}$/]',
            'attendance_mode' => 'required|in_list[in_person,remote]',
            'body'            => 'required|min_length[20]',
            'meeting_link'    => 'permit_empty|valid_url_strict[https]|max_length[255]',
        ];
        $messages = [
            'party_id' => ['required' => 'اختر المدعو.'], 'session_date' => ['required' => 'حدد تاريخ الجلسة.'],
            'session_time' => ['required' => 'حدد وقت الجلسة.'], 'body' => ['required' => 'نص الدعوة مطلوب.'],
            'meeting_link' => ['valid_url_strict' => 'رابط الجلسة يجب أن يبدأ بـ https.'],
        ];
        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $partyId = (int) $this->request->getPost('party_id');
        $party   = null;
        foreach ($this->cases->parties($id) as $p) {
            if ((int) $p['id'] === $partyId) {
                $party = $p;
            }
        }
        if ($party === null) {
            return redirect()->back()->withInput()->with('error', 'المدعو ليس من أطراف المعاملة.');
        }
        $when   = $this->request->getPost('session_date') . ' ' . $this->request->getPost('session_time') . ':00';
        $mode   = (string) $this->request->getPost('attendance_mode');
        $draft  = $this->request->getPost('action') === 'draft';
        $via    = (array) $this->request->getPost('via');
        if (! $draft && $via === []) {
            return redirect()->back()->withInput()->with('error', 'اختر وسيلة إرسال واحدة على الأقل.');
        }
        if (! $draft && strtotime($when) < time()) {
            return redirect()->back()->withInput()->with('error', 'موعد الجلسة يجب أن يكون في المستقبل.');
        }

        $link = $mode === 'remote' ? ($this->request->getPost('meeting_link') ?: null) : null;
        $body = strtr((string) $this->request->getPost('body'), [
            '{التاريخ}' => date('Y-m-d', strtotime($when)),
            '{الوقت}' => date('H:i', strtotime($when)),
            '{طريقة_الحضور}' => $mode === 'remote' ? 'عن بُعد عبر الرابط: ' . ($link ?? '—') : 'حضورياً في مقر قسم التحقيق',
        ]);

        $db = db_connect();
        $db->table('invitations')->insert([
            'case_id' => $id, 'party_id' => $partyId,
            'invitation_type' => $this->request->getPost('invitation_type'),
            'session_at' => $when, 'attendance_mode' => $mode,
            'meeting_link' => $link,
            'body' => $body,
            'via_email' => in_array('email', $via, true) ? 1 : 0,
            'via_sms' => in_array('sms', $via, true) ? 1 : 0,
            'via_enjaz' => in_array('enjaz', $via, true) ? 1 : 0,
            'status' => $draft ? 'draft' : 'sent',
            'sent_at' => $draft ? null : date('Y-m-d H:i:s'),
            'reissued_from_id' => ((int) $this->request->getPost('reissued_from_id')) ?: null,
            'created_by' => $this->uid(),
        ]);
        $invId = (int) $db->insertID();

        if ($draft) {
            $this->cases->log($id, 'invitation_draft', 'حفظ دعوة كمسودة: ' . $party['name']);

            return redirect()->to(site_url("cases/{$id}/attendance"))->with('message', 'حُفظت الدعوة كمسودة.');
        }

        $this->cases->log($id, 'invitation_sent', 'إرسال دعوة حضور: ' . $party['name'], ['note' => 'موعد ' . date('Y-m-d H:i', strtotime($when))]);
        if (! empty($party['user_id'])) {
            $this->cases->notifyUser((int) $party['user_id'], $id, 'invitation', 'دعوة لحضور جلسة تحقيق', 'لديك دعوة لحضور جلسة بتاريخ ' . date('Y-m-d H:i', strtotime($when)) . '.', "invitations/{$invId}");
        }

        return redirect()->to(site_url("cases/{$id}/attendance"))->with('message', 'تم إرسال الدعوة إلى ' . $party['name'] . '.');
    }

    public function attendance(int $id)
    {
        $case = $this->loadCase($id);
        $rows = db_connect()->table('invitations i')
            ->select('i.*, COALESCE(e.full_name, p.external_name) AS name, p.party_role, p.id AS pid,
                      (SELECT s.id FROM sessions s WHERE s.invitation_id = i.id ORDER BY s.id DESC LIMIT 1) AS session_id,
                      (SELECT COUNT(*) FROM invitations x WHERE x.reissued_from_id = i.id) AS reissued')
            ->join('case_parties p', 'p.id = i.party_id')
            ->join('employees e', 'e.id = p.employee_id', 'left')
            ->where('i.case_id', $id)->orderBy('i.session_at')->get()->getResultArray();

        return view('invitations/attendance', [
            'title' => 'متابعة الحضور', 'subtitle' => 'حالة الدعوات والحضور لجميع أطراف المعاملة', 'active' => 'sessions',
            'crumbs' => [['المعاملات', 'cases'], [$case['case_no'], "cases/{$id}"], 'متابعة الحضور'],
            'case' => $case, 'parties' => $this->cases->parties($id), 'rows' => $rows, 'canAct' => $this->cases->canInvestigate($case),
        ]);
    }

    public function setAttendance(int $invId)
    {
        [$inv, $case] = $this->loadInvitation($invId);
        if (! $this->cases->canInvestigate($case) || $inv['status'] === 'draft') {
            return $this->deny();
        }
        $status = (string) $this->request->getPost('attendance_status');
        if (! in_array($status, ['attended', 'excused', 'unexcused'], true)) {
            return $this->deny('حالة غير صحيحة.');
        }
        db_connect()->table('invitations')->where('id', $invId)->update([
            'attendance_status' => $status,
            'excuse_note' => $status === 'excused' ? (trim((string) $this->request->getPost('excuse_note')) ?: null) : null,
        ]);
        $this->cases->log((int) $case['id'], 'attendance', 'تسجيل الحضور: ' . label('attendance', $status));

        return redirect()->back()->with('message', 'تم تسجيل حالة الحضور.');
    }

    public function applyAbsence(int $invId)
    {
        [$inv, $case] = $this->loadInvitation($invId);
        if (! $this->cases->canInvestigate($case) || $inv['attendance_status'] !== 'unexcused' || $inv['absence_action_at']) {
            return $this->deny();
        }
        db_connect()->table('invitations')->where('id', $invId)->update(['absence_action_at' => date('Y-m-d H:i:s')]);
        $this->cases->log((int) $case['id'], 'absence_action', 'تطبيق إجراء الغياب بدون عذر', ['note' => 'يُتعامل مع المعاملة وفق الإثباتات المتوفرة']);

        return redirect()->back()->with('message', 'تم تطبيق إجراء الغياب وتوثيقه في سجل المعاملة.');
    }

    /** عرض الدعوة للمدعو نفسه (ويُعلَّم كمقروءة) */
    public function view(int $invId)
    {
        $inv = db_connect()->table('invitations i')
            ->select('i.*, c.case_no, c.id AS cid, COALESCE(e.full_name, p.external_name) AS name, u.id AS invitee_user')
            ->join('cases c', 'c.id = i.case_id')->join('case_parties p', 'p.id = i.party_id')
            ->join('employees e', 'e.id = p.employee_id', 'left')->join('users u', 'u.employee_id = e.id', 'left')
            ->where('i.id', $invId)->get()->getRowArray();
        if ($inv === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $isInvitee = (int) $inv['invitee_user'] === $this->uid();
        if (! $isInvitee) {
            $this->loadCase((int) $inv['cid']);
        }
        if ($isInvitee && $inv['status'] === 'sent') {
            db_connect()->table('invitations')->where('id', $invId)->update(['status' => 'read', 'read_at' => date('Y-m-d H:i:s')]);
            $inv['status'] = 'read';
        }
        $session = db_connect()->table('sessions')->where('invitation_id', $invId)->whereIn('status', ['in_progress', 'paused'])->get()->getRowArray();

        return view('invitations/view', [
            'title' => 'دعوة لحضور جلسة تحقيق', 'subtitle' => 'قسم التحقيق — إدارة الشؤون القانونية والالتزام', 'active' => 'notifications',
            'crumbs' => [['الإشعارات', 'notifications'], 'دعوة للحضور'], 'inv' => $inv, 'session' => $session, 'isInvitee' => $isInvitee,
        ]);
    }

    private function loadInvitation(int $invId): array
    {
        $inv = db_connect()->table('invitations')->where('id', $invId)->get()->getRowArray();
        if ($inv === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return [$inv, $this->loadCase((int) $inv['case_id'])];
    }
}
