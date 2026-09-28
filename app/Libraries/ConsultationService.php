<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * منطق الاستشارات القانونية: محادثة بين الموظف وإدارة الشؤون القانونية.
 * القراءة، الصلاحيات، الرسائل، السجل، الإشعارات.
 *
 * الجداول في database/04_consultations.sql. إن كانت الجداول قائمة مسبقاً بأسماء أعمدة مختلفة
 * فالأعمدة غير الثابتة تُقرأ من قاعدة البيانات (انظر CANDIDATES) وتُوحَّد بالأسماء المستعارة.
 */
class ConsultationService
{
    public const OPEN_STATUSES = ['new', 'assigned'];

    /** درجات السرية غير المقيدة؛ ما عداها (confidential وما فوقها أو قيمة غير معروفة) يُعامل كسري */
    public const PUBLIC_LEVELS = ['normal', 'public', 'general'];

    /** الأسماء المحتملة للأعمدة، بالترتيب */
    private const CANDIDATES = [
        'consultations' => [
            'ref'             => ['ref_no', 'reference_no', 'consultation_no', 'cons_no', 'ref', 'reference', 'code'],
            'owner'           => ['user_id', 'created_by', 'requester_id', 'requested_by', 'asked_by'],
            'category'        => ['category_id', 'consultation_category_id'],
            'body'            => ['body', 'question', 'details', 'description'],
            'confidentiality' => ['confidentiality', 'secrecy', 'secrecy_level', 'confidentiality_level'],
            'assigned'        => ['assigned_to', 'assigned_user_id', 'assignee_id', 'assigned_to_user_id'],
            'assigned_at'     => ['assigned_at'],
            'read'            => ['read_at', 'owner_read_at', 'answer_read_at', 'seen_at'],
            'closed_at'       => ['closed_at'],
        ],
        'consultation_categories' => [
            'name'   => ['name_ar', 'name', 'title', 'label'],
            'active' => ['is_active', 'active'],
            'sort'   => ['sort_order', 'sort', 'position'],
        ],
        'consultation_actions' => [
            'fk'          => ['consultation_id'],
            'user'        => ['user_id', 'created_by', 'actor_id'],
            'code'        => ['action_code', 'action', 'code'],
            'description' => ['description', 'details', 'note', 'notes'],
            'meta'        => ['meta', 'data'],
            'ip'          => ['ip_address', 'ip'],
        ],
    ];

    private static array $fields = [];
    private static array $enums  = [];

    protected BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    // ------------------------------------------------------------------
    // الأعمدة
    // ------------------------------------------------------------------

    /** اسم العمود الفعلي أو null إن لم يوجد */
    public function col(string $table, string $key): ?string
    {
        self::$fields[$table] ??= $this->db->getFieldNames($table);
        foreach (self::CANDIDATES[$table][$key] ?? [$key] as $name) {
            if (in_array($name, self::$fields[$table], true)) {
                return $name;
            }
        }

        return null;
    }

    /** اسم العمود الفعلي، أو خطأ واضح إن لم يوجد عمود مطابق */
    public function must(string $table, string $key): string
    {
        return $this->col($table, $key) ?? throw new RuntimeException(
            "لا يوجد في الجدول {$table} عمود يطابق ({$key}): " . implode(', ', self::CANDIDATES[$table][$key] ?? [$key])
        );
    }

    /** قيم عمود ENUM، أو null إن لم يكن العمود من هذا النوع */
    public function enumValues(string $table, string $column): ?array
    {
        $key = $table . '.' . $column;
        if (! array_key_exists($key, self::$enums)) {
            $type = (string) ($this->db->query('SHOW COLUMNS FROM ' . $this->db->protectIdentifiers($table) . ' LIKE ' . $this->db->escape($column))->getRow('Type') ?? '');
            self::$enums[$key] = null;
            if (preg_match('/^enum\((.*)\)$/i', $type, $m) && preg_match_all("/'((?:[^']|'')*)'/", $m[1], $vals)) {
                self::$enums[$key] = array_map(static fn ($v) => str_replace("''", "'", $v), $vals[1]);
            }
        }

        return self::$enums[$key];
    }

    public static function isConfidential(?string $level): bool
    {
        return ! in_array($level ?? '', self::PUBLIC_LEVELS, true);
    }

    /** درجات السرية المتاحة كما يعرّفها العمود في قاعدة البيانات */
    public function confidentialityOptions(): array
    {
        return $this->enumValues('consultations', $this->must('consultations', 'confidentiality')) ?? ['normal', 'confidential'];
    }

    /** تصنيف الإشعار: consultation إن قبله حقل category، وإلا reply */
    public function notificationCategory(): string
    {
        $allowed = $this->enumValues('notifications', 'category');

        return $allowed === null || in_array('consultation', $allowed, true) ? 'consultation' : 'reply';
    }

    // ------------------------------------------------------------------
    // الصلاحيات
    // ------------------------------------------------------------------

    /** الشؤون القانونية (ومدير النظام): تطّلع على كل المحادثات وترد عليها */
    public static function isStaff(): bool
    {
        return Access::hasAny(['legal']);
    }

    public function isOwner(array $c): bool
    {
        return (int) $c['owner_id'] === (int) session('user_id');
    }

    public function canView(array $c): bool
    {
        return self::isStaff() || $this->isOwner($c);
    }

    // ------------------------------------------------------------------
    // القراءة
    // ------------------------------------------------------------------

    /**
     * استعلام موحّد الأسماء مع وقت آخر نشاط ونص آخر رسالة في المحادثة.
     */
    public function builder(): BaseBuilder
    {
        $t        = 'consultations';
        $name     = $this->must('consultation_categories', 'name');
        $assigned = $this->col($t, 'assigned');
        $optional = static fn (?string $col, string $alias) => $col ? "c.{$col} AS {$alias}" : "NULL AS {$alias}";

        $select = [
            'c.id', 'c.subject', 'c.status', 'c.answer', 'c.answered_by', 'c.answered_at', 'c.is_published', 'c.created_at',
            'c.' . $this->must($t, 'ref') . ' AS ref_no',
            'c.' . $this->must($t, 'owner') . ' AS owner_id',
            'c.' . $this->must($t, 'category') . ' AS category_id',
            'c.' . $this->must($t, 'body') . ' AS body',
            'c.' . $this->must($t, 'confidentiality') . ' AS confidentiality',
            $optional($assigned, 'assigned_to'),
            $optional($this->col($t, 'assigned_at'), 'assigned_at'),
            $optional($this->col($t, 'read'), 'owner_read_at'),
            $optional($this->col($t, 'closed_at'), 'closed_at'),
            '(SELECT MAX(m.created_at) FROM consultation_messages m WHERE m.consultation_id = c.id) AS last_message_at',
            '(SELECT m.body FROM consultation_messages m WHERE m.consultation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message',
            "cat.{$name} AS category_name",
            'COALESCE(oe.full_name, ou.username) AS owner_name', 'ou.username AS owner_username', 'oe.employee_no AS owner_no',
            'oe.job_title AS owner_title', 'od.name_ar AS owner_department',
            'COALESCE(be.full_name, bu.username) AS answered_by_name',
            $assigned ? 'COALESCE(se.full_name, su.username) AS assigned_name' : 'NULL AS assigned_name',
        ];

        $b = $this->db->table('consultations c')->select(implode(', ', $select), false)
            ->join('consultation_categories cat', 'cat.id = c.' . $this->must($t, 'category'), 'left')
            ->join('users ou', 'ou.id = c.' . $this->must($t, 'owner'), 'left')
            ->join('employees oe', 'oe.id = ou.employee_id', 'left')
            ->join('departments od', 'od.id = oe.department_id', 'left')
            ->join('users bu', 'bu.id = c.answered_by', 'left')
            ->join('employees be', 'be.id = bu.employee_id', 'left');
        if ($assigned) {
            $b->join('users su', "su.id = c.{$assigned}", 'left')->join('employees se', 'se.id = su.employee_id', 'left');
        }

        return $b;
    }

    public function find(int $id): ?array
    {
        return $this->builder()->where('c.id', $id)->get()->getRowArray();
    }

    /** استشارات المستخدم الحالي، الأحدث أولاً */
    public function mine(): array
    {
        return $this->conversations(false);
    }

    /**
     * قائمة المحادثات مرتبة بآخر نشاط: للشؤون القانونية كل المحادثات، ولغيرها محادثات المستخدم فقط.
     * $waiting يقصرها على المحادثات بانتظار رد الشؤون القانونية.
     */
    public function conversations(bool $all, bool $waiting = false): array
    {
        $b = $this->builder();
        if (! $all) {
            $b->where('c.' . $this->must('consultations', 'owner'), (int) session('user_id'));
        }
        if ($waiting) {
            $b->whereIn('c.status', self::OPEN_STATUSES);
        }

        return $b->orderBy('COALESCE((SELECT MAX(m2.created_at) FROM consultation_messages m2 WHERE m2.consultation_id = c.id), c.created_at)', 'DESC', false)
            ->limit(300)->get()->getResultArray();
    }

    /** عدد المحادثات بانتظار رد الشؤون القانونية */
    public function waitingCount(): int
    {
        return $this->db->table('consultations')->whereIn('status', self::OPEN_STATUSES)->countAllResults();
    }

    /**
     * رسائل المحادثة بالترتيب: السؤال الأول، ثم الرد المعتمد السابق إن وُجد (من الإصدار السابق)، ثم الرسائل.
     * كل رسالة: body, created_at, user_id, name, from_owner
     */
    public function thread(array $c): array
    {
        $out = [[
            'body' => $c['body'], 'created_at' => $c['created_at'], 'user_id' => (int) $c['owner_id'],
            'name' => $c['owner_name'], 'from_owner' => true,
        ]];
        if ($c['answer'] !== null && $c['answer'] !== '' && $c['answered_at'] !== null) {
            $out[] = ['body' => $c['answer'], 'created_at' => $c['answered_at'], 'user_id' => (int) $c['answered_by'], 'name' => $c['answered_by_name'], 'from_owner' => false];
        }
        $rows = $this->db->table('consultation_messages m')
            ->select('m.body, m.created_at, m.user_id, COALESCE(e.full_name, u.username) AS name')
            ->join('users u', 'u.id = m.user_id', 'left')->join('employees e', 'e.id = u.employee_id', 'left')
            ->where('m.consultation_id', (int) $c['id'])->orderBy('m.id')->get()->getResultArray();
        foreach ($rows as $r) {
            $r['user_id']    = (int) $r['user_id'];
            $r['from_owner'] = $r['user_id'] === (int) $c['owner_id'];
            $out[]           = $r;
        }
        usort($out, static fn ($a, $b) => strcmp((string) $a['created_at'], (string) $b['created_at']));

        return $out;
    }

    public function addMessage(int $id, string $body): void
    {
        $this->db->table('consultation_messages')->insert([
            'consultation_id' => $id, 'user_id' => (int) session('user_id'), 'body' => $body,
        ]);
    }

    public function categories(): array
    {
        $name   = $this->must('consultation_categories', 'name');
        $active = $this->col('consultation_categories', 'active');
        $sort   = $this->col('consultation_categories', 'sort');
        $b      = $this->db->table('consultation_categories')->select("id, {$name} AS name");
        if ($active) {
            $b->where($active, 1);
        }
        if ($sort) {
            $b->orderBy($sort);
        }

        return $b->orderBy($name)->get()->getResultArray();
    }

    /** سجل إجراءات الاستشارة */
    public function actions(int $id): array
    {
        $t    = 'consultation_actions';
        $user = $this->col($t, 'user');
        $desc = $this->col($t, 'description');
        $b    = $this->db->table("{$t} a")
            ->select('a.' . $this->must($t, 'code') . ' AS action_code, ' . ($desc ? "a.{$desc}" : 'NULL') . ' AS description, a.created_at'
                . ($user ? ', COALESCE(e.full_name, u.username) AS user_name' : ', NULL AS user_name'), false)
            ->where('a.' . $this->must($t, 'fk'), $id);
        if ($user) {
            $b->join('users u', "u.id = a.{$user}", 'left')->join('employees e', 'e.id = u.employee_id', 'left');
        }

        return $b->orderBy('a.id', 'ASC')->get()->getResultArray();
    }

    // ------------------------------------------------------------------
    // الكتابة
    // ------------------------------------------------------------------

    /**
     * يحفظ الاستشارة بحالة new ورقم مرجعي CONS-YYYY-NNNN.
     * يُحجز التسلسل بقفل مسمّى في MySQL حتى لا يتكرر الرقم مع الإدخال المتزامن دون جدول تسلسل إضافي.
     */
    public function create(array $data): array
    {
        $t = 'consultations';
        $this->db->query("SELECT GET_LOCK('consultation_ref', 10)");
        try {
            $ref    = $this->must($t, 'ref');
            $prefix = 'CONS-' . date('Y') . '-';
            $last   = $this->db->table($t)->select($ref)->like($ref, $prefix, 'after')
                ->orderBy("LENGTH({$ref})", 'DESC', false)->orderBy($ref, 'DESC')->limit(1)->get()->getRow($ref);
            $refNo = $prefix . str_pad((string) ($last ? (int) substr($last, strlen($prefix)) + 1 : 1), 4, '0', STR_PAD_LEFT);

            $this->db->table($t)->insert([
                $ref                               => $refNo,
                $this->must($t, 'owner')           => (int) session('user_id'),
                $this->must($t, 'category')        => (int) $data['category_id'],
                'subject'                          => $data['subject'],
                $this->must($t, 'body')            => $data['body'],
                $this->must($t, 'confidentiality') => $data['confidentiality'],
                'status'                           => 'new',
            ]);
            $id = (int) $this->db->insertID();
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('consultation_ref')");
        }

        return ['id' => $id, 'ref_no' => $refNo];
    }

    /** يحدّث الاستشارة بأسماء منطقية (assigned, assigned_at, read, closed_at) أو بأسماء الأعمدة الثابتة */
    public function update(int $id, array $data): void
    {
        $row = [];
        foreach ($data as $key => $value) {
            $col = isset(self::CANDIDATES['consultations'][$key]) ? $this->col('consultations', $key) : $key;
            if ($col !== null) {
                $row[$col] = $value;
            }
        }
        if ($row !== []) {
            $this->db->table('consultations')->where('id', $id)->update($row);
        }
    }

    /** يسجل الإجراء في consultation_actions على نمط CaseService::log */
    public function log(int $id, string $code, string $description, array $meta = []): void
    {
        $t   = 'consultation_actions';
        $row = [
            $this->must($t, 'fk')   => $id,
            $this->must($t, 'code') => $code,
        ];
        foreach ([
            'user'        => session('user_id') ?: null,
            'description' => $description,
            'meta'        => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
            'ip'          => service('request')->getIPAddress(),
        ] as $key => $value) {
            if ($col = $this->col($t, $key)) {
                $row[$col] = $value;
            }
        }
        $this->db->table($t)->insert($row);
    }

    /** يعلّم رد المستشار مقروءاً عند فتح صاحب الاستشارة لها */
    public function markRead(array $c): void
    {
        if ($c['status'] !== 'answered' && $c['answer'] === null) {
            return;
        }
        if ($this->col('consultations', 'read') && $c['owner_read_at'] === null) {
            $this->update((int) $c['id'], ['read' => date('Y-m-d H:i:s')]);
        }
        $this->db->table('notifications')->where('user_id', (int) session('user_id'))->where('read_at', null)
            ->where('link', 'consultations/' . $c['id'])->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    // ------------------------------------------------------------------
    // العدّادات والتقارير
    // ------------------------------------------------------------------

    /**
     * عدّاد القائمة الجانبية: الجديدة والمسندة للشؤون القانونية ومدير النظام،
     * والردود غير المقروءة لبقية المستخدمين.
     */
    public function navCount(): int
    {
        if (self::isStaff()) {
            return $this->db->table('consultations')->whereIn('status', self::OPEN_STATUSES)->countAllResults();
        }
        $uid = (int) session('user_id');
        if ($read = $this->col('consultations', 'read')) {
            return $this->db->table('consultations')->where($this->must('consultations', 'owner'), $uid)
                ->where('status', 'answered')->where($read, null)->countAllResults();
        }

        // بلا عمود للقراءة: إشعارات الرد غير المقروءة المرتبطة باستشارات المستخدم
        return $this->db->table('notifications n')
            ->join('consultations c', "n.link = CONCAT('consultations/', c.id)", 'inner', false)
            ->where('n.user_id', $uid)->where('n.read_at', null)
            ->where('c.' . $this->must('consultations', 'owner'), $uid)->where('c.status', 'answered')
            ->countAllResults();
    }

    /** إحصاءات الشهر الحالي لبطاقة التقارير */
    public function monthStats(): array
    {
        $from  = date('Y-m-01 00:00:00');
        $name  = $this->must('consultation_categories', 'name');
        $total = $this->db->table('consultations')->where('created_at >=', $from)->countAllResults();
        $ans   = $this->db->table('consultations')->select('COUNT(*) AS n, AVG(TIMESTAMPDIFF(HOUR, created_at, answered_at)) / 24 AS avg_days', false)
            ->where('answered_at >=', $from)->get()->getRowArray();
        $top = $this->db->table('consultations c')->select("cat.{$name} AS label, COUNT(*) AS n", false)
            ->join('consultation_categories cat', 'cat.id = c.' . $this->must('consultations', 'category'))
            ->where('c.created_at >=', $from)->groupBy("cat.id, cat.{$name}")->orderBy('n', 'DESC')->limit(3)->get()->getResultArray();

        return [
            'total'    => $total,
            'answered' => (int) ($ans['n'] ?? 0),
            'avg_days' => ($ans['avg_days'] ?? null) === null ? null : round((float) $ans['avg_days'], 1),
            'top'      => $top,
            'from'     => $from,
        ];
    }

    /** للاستدعاء من القالب: لا يُسقط الصفحة إن تعذّر الوصول للجداول */
    public static function safeNavCount(): int
    {
        try {
            return (new self())->navCount();
        } catch (Throwable $e) {
            log_message('error', 'Consultations nav count: ' . $e->getMessage());

            return 0;
        }
    }
}
