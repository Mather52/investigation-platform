<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * منطق الاستشارات القانونية المشترك: القراءة، الصلاحيات، السجل، الإشعارات، البحث في المكتبة.
 *
 * الجداول consultations و consultation_categories و consultation_actions قائمة مسبقاً في قاعدة البيانات
 * ولا يُعدَّل مخططها. الأعمدة التي لم يُحدَّد اسمها في المتطلبات تُقرأ من قاعدة البيانات
 * مرة واحدة لكل طلب (انظر CANDIDATES)، وتُوحَّد أسماؤها في الاستعلامات عبر الأسماء المستعارة.
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

    public function hasAssignment(): bool
    {
        return $this->col('consultations', 'assigned') !== null;
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

    /** مستشار الشؤون القانونية (دور legal تحديداً): يسند ويعتمد الرد */
    public static function isCounsel(): bool
    {
        return in_array('legal', Access::roles(), true);
    }

    /** يطّلع على كل الاستشارات والمسودات: legal و admin */
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
     * استعلام موحّد الأسماء. المسودة المقترحة لا تُقرأ من قاعدة البيانات إطلاقاً إلا مع $withDraft.
     */
    public function builder(bool $withDraft = false): BaseBuilder
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
            $withDraft ? 'c.ai_draft, c.ai_source, c.ai_generated_at' : 'NULL AS ai_draft, NULL AS ai_source, NULL AS ai_generated_at',
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

    public function find(int $id, bool $withDraft = false): ?array
    {
        return $this->builder($withDraft)->where('c.id', $id)->get()->getRowArray();
    }

    /** استشارات المستخدم الحالي */
    public function mine(): array
    {
        return $this->builder()->where('c.' . $this->must('consultations', 'owner'), (int) session('user_id'))
            ->orderBy('c.created_at', 'DESC')->get()->getResultArray();
    }

    /** تبويبات صندوق الوارد لدى الشؤون القانونية */
    public function inbox(string $tab): array
    {
        $b = $this->builder();
        $this->applyTab($b, $tab);
        $order = in_array($tab, ['answered', 'library'], true) ? 'c.answered_at' : 'c.created_at';

        return $b->orderBy($order, $tab === 'new' ? 'ASC' : 'DESC')->limit(200)->get()->getResultArray();
    }

    public function inboxCounts(): array
    {
        $out = [];
        foreach (['new', 'mine', 'answered', 'library'] as $tab) {
            if ($tab === 'mine' && ! $this->hasAssignment()) {
                continue;
            }
            $b = $this->db->table('consultations c');
            $this->applyTab($b, $tab);
            $out[$tab] = $b->countAllResults();
        }

        return $out;
    }

    private function applyTab(BaseBuilder $b, string $tab): void
    {
        match ($tab) {
            'mine'     => $b->where('c.' . $this->must('consultations', 'assigned'), (int) session('user_id'))->where('c.status', 'assigned'),
            'answered' => $b->where('c.status', 'answered'),
            'library'  => $b->where('c.is_published', 1),
            default    => $b->where('c.status', 'new'),
        };
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
    // المكتبة والبحث
    // ------------------------------------------------------------------

    /** كلمات البحث من النص بعد حذف أدوات الربط الشائعة */
    public static function keywords(string $text, int $max = 8): array
    {
        static $stop = ['في', 'من', 'على', 'إلى', 'الى', 'عن', 'ما', 'ماذا', 'هل', 'أن', 'ان', 'إن', 'التي', 'الذي', 'الذين', 'مع', 'هذا', 'هذه', 'ذلك', 'تلك',
            'أو', 'او', 'كان', 'كانت', 'لا', 'لم', 'لن', 'كيف', 'متى', 'أين', 'اين', 'لماذا', 'بعد', 'قبل', 'عند', 'حول', 'بين', 'كل', 'أي', 'اي', 'غير',
            'يجوز', 'يمكن', 'هناك', 'لدي', 'لدى', 'عليه', 'عليها', 'فيه', 'فيها', 'منه', 'منها', 'بشأن', 'حيث', 'أنا', 'انا', 'نحن', 'هو', 'هي'];
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out   = [];
        foreach ($words as $w) {
            if (in_array($w, $stop, true)) {
                continue;
            }
            // إزالة أداة التعريف لتوسيع المطابقة: "الإجازة" تطابق "إجازة"
            if (mb_strlen($w) > 4 && preg_match('/^(وال|بال|فال|كال|لل|ال)/u', $w, $m)) {
                $w = mb_substr($w, mb_strlen($m[1]));
            }
            if (mb_strlen($w) >= 3 && ! in_array($w, $out, true)) {
                $out[] = $w;
            }
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * أقرب الاستشارات المنشورة (is_published = 1) للنص، مرتبة بعدد الكلمات المطابقة.
     * $fields من: subject, body, answer. تُعاد بعد إخفاء بيانات مقدميها.
     */
    public function searchLibrary(string $text, array $fields, int $limit = 3, ?int $excludeId = null): array
    {
        $words = self::keywords($text);
        if ($words === []) {
            return [];
        }
        $cols = array_map(fn ($f) => 'c.' . ($f === 'body' ? $this->must('consultations', 'body') : $f), $fields);
        $parts = [];
        foreach ($words as $w) {
            $like = $this->db->escape('%' . $this->db->escapeLikeString($w) . '%');
            foreach ($cols as $i => $col) {
                // المطابقة في الموضوع أثقل وزناً
                $parts[] = "(CASE WHEN {$col} LIKE {$like} ESCAPE '!' THEN " . ($i === 0 ? 2 : 1) . ' ELSE 0 END)';
            }
        }
        $score = implode(' + ', $parts);

        $b = $this->builder()->select("({$score}) AS score", false)
            ->where('c.is_published', 1)->where('c.answer IS NOT NULL', null, false)
            ->where("({$score}) >", 0, false);
        if ($excludeId !== null) {
            $b->where('c.id !=', $excludeId);
        }
        $rows = $b->orderBy('score', 'DESC')->orderBy('c.answered_at', 'DESC')->limit($limit)->get()->getResultArray();

        return array_map(fn ($r) => $this->anonymize($r), $rows);
    }

    /** يخفي بيانات مقدم الاستشارة من النصوص ويحذف حقول هويته */
    public function anonymize(array $r): array
    {
        $needles = array_values(array_filter([$r['owner_name'] ?? null, $r['owner_no'] ?? null, $r['owner_username'] ?? null], static fn ($v) => is_string($v) && mb_strlen(trim($v)) >= 3));
        foreach (['subject', 'body', 'answer'] as $f) {
            if (isset($r[$f]) && $needles !== []) {
                $r[$f] = str_replace($needles, '[مُخفى]', (string) $r[$f]);
            }
        }
        foreach (['owner_id', 'owner_name', 'owner_username', 'owner_no', 'owner_title', 'owner_department', 'owner_read_at', 'ai_draft', 'ai_source', 'ai_generated_at'] as $f) {
            unset($r[$f]);
        }

        return $r;
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
