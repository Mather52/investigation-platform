<?php

/**
 * دوال مساعدة للواجهة: الأيقونات، الشارات، التسميات، التواريخ.
 */

if (! function_exists('icon')) {
    function icon(string $name, int $size = 20): string
    {
        static $paths = [
        'home' => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/>',
        'folder' => '<path d="M3 6h6l2 2h10v11H3z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'share' => '<path d="M14 5l6 6-6 6"/><path d="M20 11H9a5 5 0 00-5 5v3"/>',
        'search2' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'video' => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3 3-5 6-5s6 2 6 5"/><circle cx="17" cy="9" r="2"/><path d="M16 15c3 0 5 2 5 5"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'file' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 12h6M9 16h6"/>',
        'check' => '<path d="M5 12l5 5 9-10"/>',
        'checkc' => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
        'list' => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        'archive' => '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v12h14V8M10 12h4"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'bell' => '<path d="M6 16V11a6 6 0 0112 0v5l2 2H4z"/><path d="M10 20a2 2 0 004 0"/>',
        'logout' => '<path d="M15 4h4v16h-4"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-4 8-8 9-4-1-8-4-8-9V6z"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>',
        'cal' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/>',
        'clip' => '<path d="M20 11l-8 8a5 5 0 01-7-7l8-8a3.5 3.5 0 015 5l-8 8a2 2 0 01-3-3l7-7"/>',
        'send' => '<path d="M21 3L3 11l7 3 3 7z"/><path d="M10 14l11-11"/>',
        'save' => '<path d="M5 3h11l3 3v15H5z"/><path d="M8 3v5h7V3M8 21v-6h8v6"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>',
        'msg' => '<path d="M4 5h16v11H9l-5 4z"/>',
        'mic' => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0014 0M12 18v3"/>',
        'phone' => '<path d="M3 8c5 6 8 9 13 13l3-3-4-3-2 2c-2-1-4-3-5-5l2-2-3-4z"/>',
        'pause' => '<path d="M9 5v14M15 5v14"/>',
        'play' => '<path d="M7 4l13 8-13 8z"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'undo' => '<path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 010 10h-3"/>',
        'alert' => '<path d="M12 3l10 18H2z"/><path d="M12 10v4M12 17v.5"/>',
        'link' => '<path d="M10 14a5 5 0 007 0l3-3a5 5 0 00-7-7l-1 1"/><path d="M14 10a5 5 0 00-7 0l-3 3a5 5 0 007 7l1-1"/>',
        'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/>',
        'refresh' => '<path d="M20 11a8 8 0 10-2 6"/><path d="M20 4v7h-7"/>',
        'dots' => '<circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>',
        'bold' => '<path d="M7 5h6a3.5 3.5 0 010 7H7zM7 12h7a3.5 3.5 0 010 7H7z"/>',
        'ul' => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        'screen' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        ];
        $p = $paths[$name] ?? $paths['file'];

        return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    }
}

if (! function_exists('badge')) {
    /** $tone: green | yellow | red | gray | teal | blue */
    function badge(string $text, string $tone = 'gray', bool $dot = false): string
    {
        return '<span class="badge badge-' . esc($tone, 'attr') . '">' . ($dot ? '<i class="dot"></i>' : '') . esc($text) . '</span>';
    }
}

if (! function_exists('stage_tone')) {
    function stage_tone(string $code): string
    {
        return [
            'draft' => 'gray', 'new' => 'teal', 'with_gm' => 'teal', 'referred' => 'teal',
            'investigation' => 'blue', 'approval' => 'yellow', 'execution' => 'teal',
            'archived' => 'gray', 'closed_no_action' => 'gray',
        ][$code] ?? 'gray';
    }
}

if (! function_exists('alert_badge')) {
    function alert_badge(?string $level, $days = null): string
    {
        $d = $days === null ? '' : ' · ' . (int) $days . ' يوم';

        return match ($level) {
            'red'    => badge('متأخرة' . $d, 'red'),
            'yellow' => badge('إنذار أصفر' . $d, 'yellow'),
            'green'  => badge('إنذار أخضر' . $d, 'green'),
            'ok'     => badge('ضمن المدة', 'teal'),
            default  => badge('مغلقة', 'gray'),
        };
    }
}

if (! function_exists('label')) {
    /** تسميات القيم الثابتة */
    function label(string $group, ?string $value): string
    {
        static $map = [
            'party' => ['complainant' => 'مقدم الشكوى', 'accused' => 'الموظف محل التحقيق', 'witness' => 'شاهد', 'expert' => 'مختص'],
            'conf'  => ['normal' => 'عادي', 'confidential' => 'سري', 'top_secret' => 'سري للغاية'],
            'mode'  => ['in_person' => 'حضوري', 'remote' => 'عن بُعد'],
            'inv_status' => ['draft' => 'لم ترسل', 'sent' => 'تم الإرسال', 'read' => 'تمت القراءة'],
            'attendance' => ['pending' => 'بانتظار الموعد', 'attended' => 'حضر', 'excused' => 'لم يحضر بعذر', 'unexcused' => 'لم يحضر بدون عذر'],
            'session' => ['scheduled' => 'مجدولة', 'in_progress' => 'جارية', 'paused' => 'متوقفة مؤقتاً', 'closed' => 'منتهية'],
            'req_status' => ['new' => 'جديد', 'pending' => 'قيد الانتظار', 'answered' => 'تمت الإفادة'],
            'memo' => ['draft' => 'مسودة', 'submitted' => 'مرسلة للمراجعة', 'returned' => 'معادة للتعديل', 'approved' => 'معتمدة'],
            'decision' => ['pending' => 'بانتظار', 'approved' => 'تم الاعتماد', 'returned' => 'أُعيدت للتعديل'],
            'rec' => ['not_started' => 'لم تبدأ', 'in_progress' => 'قيد التنفيذ', 'done' => 'تم التنفيذ'],
            'topic' => ['not_started' => 'لم يبدأ', 'in_progress' => 'قيد الإعداد', 'done' => 'مكتمل'],
            'category' => ['new_case' => 'معاملات جديدة', 'invitation' => 'دعوات', 'session' => 'جلسات', 'statement' => 'طلبات إفادة', 'approval' => 'طلبات اعتماد', 'decision' => 'قرارات', 'attachment' => 'مرفقات', 'reply' => 'ردود'],
            'level' => ['green' => 'عادي', 'yellow' => 'يقترب الموعد', 'red' => 'متأخر'],
        ];

        return $map[$group][$value ?? ''] ?? (string) $value;
    }
}

if (! function_exists('tone')) {
    function tone(string $group, ?string $value): string
    {
        static $map = [
            'inv_status' => ['draft' => 'gray', 'sent' => 'blue', 'read' => 'green'],
            'attendance' => ['pending' => 'gray', 'attended' => 'green', 'excused' => 'yellow', 'unexcused' => 'red'],
            'session' => ['scheduled' => 'gray', 'in_progress' => 'green', 'paused' => 'yellow', 'closed' => 'gray'],
            'req_status' => ['new' => 'blue', 'pending' => 'yellow', 'answered' => 'green'],
            'memo' => ['draft' => 'gray', 'submitted' => 'yellow', 'returned' => 'red', 'approved' => 'green'],
            'decision' => ['pending' => 'gray', 'approved' => 'green', 'returned' => 'red'],
            'rec' => ['not_started' => 'gray', 'in_progress' => 'yellow', 'done' => 'green'],
            'topic' => ['not_started' => 'gray', 'in_progress' => 'yellow', 'done' => 'green'],
            'level' => ['green' => 'green', 'yellow' => 'yellow', 'red' => 'red'],
        ];

        return $map[$group][$value ?? ''] ?? 'gray';
    }
}

if (! function_exists('status_badge')) {
    function status_badge(string $group, ?string $value): string
    {
        return badge(label($group, $value), tone($group, $value), true);
    }
}

if (! function_exists('fdate')) {
    /** تاريخ مختصر */
    function fdate(?string $value, bool $withTime = false): string
    {
        if (empty($value)) {
            return '—';
        }
        $t = strtotime($value);

        return $withTime ? date('Y-m-d · H:i', $t) : date('Y-m-d', $t);
    }
}

if (! function_exists('initial')) {
    function initial(?string $name): string
    {
        $name = trim(preg_replace('/^د\.\s*/u', '', (string) $name));

        return $name === '' ? '؟' : mb_substr($name, 0, 1);
    }
}

if (! function_exists('file_size')) {
    function file_size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' م.ب' : max(1, round($bytes / 1024)) . ' ك.ب';
    }
}

if (! function_exists('field_error')) {
    function field_error(string $field): string
    {
        $errors = session()->getFlashdata('errors') ?? [];

        return isset($errors[$field]) ? '<span class="field-error">' . esc($errors[$field]) . '</span>' : '';
    }
}

if (! function_exists('case_next')) {
    /** الصفحة المناسبة للإجراء التالي حسب مرحلة المعاملة */
    function case_next(array $c): array
    {
        $id = $c['id'];

        return match ($c['stage_code']) {
            'new', 'with_gm', 'referred' => ['إحالة', "cases/{$id}/referral"],
            'investigation'              => ['متابعة', "cases/{$id}/attendance"],
            'approval'                   => ['مراجعة', "cases/{$id}/review"],
            'execution'                  => ['التنفيذ', "cases/{$id}/execution"],
            default                      => ['فتح', "cases/{$id}"],
        };
    }
}

if (! function_exists('partial')) {
    /**
     * يعرض جزءاً مشتركاً دون أن تطغى متغيراته على بيانات الصفحة
     * (مثل عنوان البطاقة على عنوان الصفحة في القالب الرئيسي).
     */
    function partial(string $name, array $data = []): string
    {
        return view($name, $data, ['saveData' => false]);
    }
}
