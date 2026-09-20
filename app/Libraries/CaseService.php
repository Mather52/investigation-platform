<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * منطق المعاملة المشترك: القراءة، الصلاحيات، السجل، الإشعارات، الانتقال بين المراحل.
 */
class CaseService
{
    public const APPROVAL_CHAIN = [1 => 'head', 2 => 'legal', 3 => 'gm'];

    protected BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    public function find(int $id): ?array
    {
        return $this->db->table('cases c')
            ->select('c.*, t.name_ar AS type_name, src.name_ar AS source_name, s.name_ar AS stage_name, s.is_final,
                      d.name_ar AS department_name, r.code AS holder_code, r.name_ar AS holder_name,
                      ie.full_name AS investigator_name, he.full_name AS head_name, ce.full_name AS creator_name,
                      a.alert_level, a.idle_days')
            ->join('case_types t', 't.id = c.case_type_id')
            ->join('case_sources src', 'src.code = c.source_code')
            ->join('case_stages s', 's.code = c.stage_code')
            ->join('departments d', 'd.id = c.department_id', 'left')
            ->join('roles r', 'r.id = c.holder_role_id', 'left')
            ->join('users iu', 'iu.id = c.investigator_user_id', 'left')
            ->join('employees ie', 'ie.id = iu.employee_id', 'left')
            ->join('users hu', 'hu.id = c.head_user_id', 'left')
            ->join('employees he', 'he.id = hu.employee_id', 'left')
            ->join('users cu', 'cu.id = c.created_by', 'left')
            ->join('employees ce', 'ce.id = cu.employee_id', 'left')
            ->join('v_case_alerts a', 'a.id = c.id', 'left')
            ->where('c.id', $id)
            ->get()->getRowArray();
    }

    /** نطاق المعاملات التي يراها المستخدم الحالي */
    public function scope($builder, string $alias = 'c')
    {
        if (Access::hasAny(['head', 'legal', 'gm'])) {
            return $builder;
        }
        $uid = (int) session('user_id');
        $builder->groupStart();
        if (in_array('investigator', Access::roles(), true)) {
            $builder->orWhere("{$alias}.investigator_user_id", $uid);
        }
        $builder->orWhere("{$alias}.created_by", $uid)->groupEnd();

        return $builder;
    }

    public function canView(array $case): bool
    {
        if (Access::hasAny(['head', 'legal', 'gm'])) {
            return true;
        }
        $uid = (int) session('user_id');

        return (int) $case['created_by'] === $uid
            || (in_array('investigator', Access::roles(), true) && (int) $case['investigator_user_id'] === $uid);
    }

    /** إجراءات التحقيق: المحقق المعيّن أو رئيس التحقيقات أثناء مرحلة التحقيق */
    public function canInvestigate(array $case): bool
    {
        if ($case['stage_code'] !== 'investigation') {
            return false;
        }

        return Access::isAdmin() || in_array('head', Access::roles(), true)
            || (int) $case['investigator_user_id'] === (int) session('user_id');
    }

    public function parties(int $caseId): array
    {
        return $this->db->table('case_parties p')
            ->select('p.*, COALESCE(e.full_name, p.external_name) AS name, e.employee_no, e.job_title, e.email, e.mobile, d.name_ar AS department_name, u.id AS user_id')
            ->join('employees e', 'e.id = p.employee_id', 'left')
            ->join('departments d', 'd.id = e.department_id', 'left')
            ->join('users u', 'u.employee_id = e.id', 'left')
            ->where('p.case_id', $caseId)
            ->orderBy("FIELD(p.party_role,'accused','complainant','witness','expert')", '', false)
            ->get()->getResultArray();
    }

    /** يسجل الإجراء (والمشغل في قاعدة البيانات يحدّث آخر إجراء) */
    public function log(int $caseId, string $code, string $description, array $meta = []): void
    {
        $this->db->table('case_actions')->insert([
            'case_id'     => $caseId,
            'user_id'     => session('user_id') ?: null,
            'action_code' => $code,
            'description' => $description,
            'meta'        => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
            'ip_address'  => service('request')->getIPAddress(),
        ]);
    }

    public function update(int $caseId, array $data): void
    {
        $this->db->table('cases')->where('id', $caseId)->update($data);
    }

    public function roleId(string $code): int
    {
        return (int) $this->db->table('roles')->select('id')->where('code', $code)->get()->getRow('id');
    }

    /** المستخدمون النشطون لدور معين */
    public function usersWithRole(string $code): array
    {
        return $this->db->table('users u')
            ->select('u.id, COALESCE(e.full_name, u.username) AS name')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('employees e', 'e.id = u.employee_id', 'left')
            ->where('r.code', $code)->where('u.is_active', 1)
            ->orderBy('name')
            ->get()->getResultArray();
    }

    public function notifyUser(int $userId, ?int $caseId, string $category, string $title, string $body, ?string $link = null, string $level = 'green'): void
    {
        if ($userId <= 0 || $userId === (int) session('user_id')) {
            return;
        }
        $this->db->table('notifications')->insert([
            'user_id'  => $userId,
            'case_id'  => $caseId,
            'category' => $category,
            'level'    => $level,
            'title'    => $title,
            'body'     => $body,
            'link'     => $link,
        ]);
    }

    public function notifyRole(string $role, ?int $caseId, string $category, string $title, string $body, ?string $link = null, string $level = 'green'): void
    {
        foreach ($this->usersWithRole($role) as $u) {
            $this->notifyUser((int) $u['id'], $caseId, $category, $title, $body, $link, $level);
        }
    }

    public function nextCaseNo(): string
    {
        $this->db->query('CALL next_case_no(@case_no)');

        return (string) $this->db->query('SELECT @case_no AS no')->getRow('no');
    }

    /** الخطوة الحالية المعلقة في دورة الاعتماد */
    public function pendingStep(int $memoId, int $version): ?array
    {
        return $this->db->table('approval_steps s')
            ->select('s.*, r.code AS role_code, r.name_ar AS role_name')
            ->join('roles r', 'r.id = s.role_id')
            ->where(['s.memo_id' => $memoId, 's.memo_version' => $version, 's.decision' => 'pending'])
            ->orderBy('s.step_order')->limit(1)
            ->get()->getRowArray();
    }

    /** يحفظ الملف المرفوع خارج public ويسجله */
    public function storeUpload($file, int $caseId, string $relatedType = 'case', ?int $relatedId = null): ?int
    {
        if ($file === null || ! $file->isValid() || $file->hasMoved()) {
            return null;
        }
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
        $ext     = strtolower($file->getClientExtension());
        if (! in_array($ext, $allowed, true) || $file->getSize() > 20 * 1024 * 1024) {
            return null;
        }
        $dir = WRITEPATH . 'case-files/' . $caseId . '/';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $original = $file->getClientName();
        $mime     = $file->getMimeType();
        $size     = $file->getSize();
        $name     = $file->getRandomName();
        $file->move($dir, $name);

        $this->db->table('attachments')->insert([
            'case_id'       => $caseId,
            'related_type'  => $relatedType,
            'related_id'    => $relatedId,
            'original_name' => $original,
            'stored_path'   => $caseId . '/' . $name,
            'mime_type'     => $mime,
            'size_bytes'    => $size,
            'sha256'        => hash_file('sha256', $dir . $name),
            'uploaded_by'   => session('user_id'),
        ]);

        return (int) $this->db->insertID();
    }

    /** يرفع كل الملفات من حقل متعدد */
    public function storeUploads(array $files, int $caseId, string $relatedType = 'case', ?int $relatedId = null): int
    {
        $count = 0;
        foreach ($files as $f) {
            if ($this->storeUpload($f, $caseId, $relatedType, $relatedId)) {
                $count++;
            }
        }

        return $count;
    }

    public function attachments(int $caseId, ?string $type = null, ?int $relatedId = null): array
    {
        $b = $this->db->table('attachments a')
            ->select('a.*, COALESCE(e.full_name, u.username) AS uploader')
            ->join('users u', 'u.id = a.uploaded_by', 'left')
            ->join('employees e', 'e.id = u.employee_id', 'left')
            ->where('a.case_id', $caseId);
        if ($type !== null) {
            $b->where('a.related_type', $type);
        }
        if ($relatedId !== null) {
            $b->where('a.related_id', $relatedId);
        }

        return $b->orderBy('a.created_at', 'DESC')->get()->getResultArray();
    }

    /**
     * يولّد إشعارات الإنذار (أصفر/أحمر) للمعاملات المتأخرة، مرة واحدة يومياً لكل مستوى.
     * يُستدعى من الأمر: php spark alerts:check أو آلياً عند فتح الإشعارات.
     */
    public function generateAlerts(): int
    {
        $rows = $this->db->table('v_case_alerts a')
            ->select('a.id, a.alert_level, a.idle_days, a.rule_code, c.case_no, c.stage_code, c.investigator_user_id, r.code AS holder_code')
            ->join('cases c', 'c.id = a.id')->join('roles r', 'r.id = c.holder_role_id', 'left')
            ->whereIn('a.alert_level', ['yellow', 'red'])->get()->getResultArray();
        $count = 0;
        foreach ($rows as $r) {
            $category = $r['rule_code'] === 'new_case' ? 'new_case' : match ($r['stage_code']) {
                'investigation' => 'session', 'approval' => 'approval', 'execution' => 'decision', default => 'new_case',
            };
            $users = $r['holder_code'] === 'investigator' && $r['investigator_user_id']
                ? [(int) $r['investigator_user_id']]
                : array_map('intval', array_column($r['holder_code'] ? $this->usersWithRole($r['holder_code']) : [], 'id'));
            $title = $r['alert_level'] === 'red' ? 'معاملة متأخرة' : 'اقتراب انتهاء المدة';
            $body  = "المعاملة {$r['case_no']} دون إجراء منذ {$r['idle_days']} يوم.";
            foreach ($users as $uid) {
                $exists = $this->db->table('notifications')->where(['user_id' => $uid, 'case_id' => $r['id'], 'level' => $r['alert_level']])
                    ->where('created_at >=', date('Y-m-d 00:00:00'))->countAllResults();
                if ($exists) {
                    continue;
                }
                $this->db->table('notifications')->insert([
                    'user_id' => $uid, 'case_id' => $r['id'], 'category' => $category, 'level' => $r['alert_level'],
                    'title' => $title, 'body' => $body, 'link' => 'cases/' . $r['id'],
                ]);
                $count++;
            }
        }

        return $count;
    }
}
