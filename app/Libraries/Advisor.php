<?php

namespace App\Libraries;

use Config\Services;
use RuntimeException;
use Throwable;

/**
 * يولّد مسودة رد مقترحة للاستشارة القانونية، يراجعها المستشار قبل اعتمادها.
 *
 * طريقة التوليد يحددها advisor.driver في ملف .env:
 *   library (الافتراضي) — ملخص من أقرب ثلاث إجابات منشورة في مكتبة الاستشارات، بلا أي اتصال خارجي.
 *   api                 — يرسل السؤال إلى مزود ذكاء اصطناعي (advisor.endpoint و advisor.key و advisor.model اختياري
 *                         و advisor.timeout بالثواني اختياري). المفتاح يُقرأ من .env فقط.
 *
 * الاستشارة السرية (confidential أو أعلى) لا تُرسل إلى api إطلاقاً وتُعالَج بطريقة library.
 * بعد كل استدعاء لـ draft() يحمل $source الطريقة المستخدمة لتخزينها في ai_source.
 */
class Advisor
{
    public const SOURCE_LIBRARY      = 'library';
    public const SOURCE_API          = 'api';
    public const SOURCE_CONFIDENTIAL = 'library:confidential';

    /** الطريقة المستخدمة في آخر توليد */
    public ?string $source = null;

    private ConsultationService $service;

    public function __construct(?ConsultationService $service = null)
    {
        $this->service = $service ?? new ConsultationService();
    }

    /**
     * @param array $consultation حقول: id, subject, body, confidentiality, category_name (اختياري)
     *
     * @return string|null نص المسودة، أو null إن لم يمكن توليدها
     */
    public function draft(array $consultation): ?string
    {
        $driver = strtolower(trim((string) env('advisor.driver', self::SOURCE_LIBRARY)));

        // أي درجة غير عادية (confidential وما فوقها، أو قيمة غير معروفة) لا تغادر المنصة
        if (ConsultationService::isConfidential($consultation['confidentiality'] ?? null)) {
            $this->source = $driver === self::SOURCE_API ? self::SOURCE_CONFIDENTIAL : self::SOURCE_LIBRARY;

            return $this->fromLibrary($consultation);
        }
        if ($driver === self::SOURCE_API) {
            $this->source = self::SOURCE_API;

            return $this->fromApi($consultation);
        }
        $this->source = self::SOURCE_LIBRARY;

        return $this->fromLibrary($consultation);
    }

    /** ملخص من أقرب ثلاث إجابات منشورة بمطابقة الكلمات في الموضوع ونص السؤال */
    private function fromLibrary(array $c): ?string
    {
        $rows = $this->service->searchLibrary(
            trim(($c['subject'] ?? '') . ' ' . ($c['body'] ?? '')),
            ['subject', 'body'],
            3,
            isset($c['id']) ? (int) $c['id'] : null
        );
        if ($rows === []) {
            return null;
        }

        $out = ['مسودة مقترحة مبنية على ' . count($rows) . ' من الاستشارات السابقة المنشورة في المكتبة:', ''];
        foreach ($rows as $i => $r) {
            $out[] = ($i + 1) . ') ' . $r['ref_no'] . ' — ' . $r['subject'];
            $out[] = self::excerpt((string) $r['answer'], 600);
            $out[] = '';
        }
        $out[] = 'المراجع: ' . implode('، ', array_column($rows, 'ref_no')) . '.';
        $out[] = 'تُراجَع المسودة وتُكيَّف مع وقائع الاستشارة الحالية قبل اعتمادها.';

        return implode("\n", $out);
    }

    /** يرسل السؤال إلى مزود الذكاء الاصطناعي. لا يُرسل اسم مقدم الاستشارة ولا أي بيانات تعريفية */
    private function fromApi(array $c): ?string
    {
        $endpoint = trim((string) env('advisor.endpoint', ''));
        $key      = trim((string) env('advisor.key', ''));
        if ($endpoint === '' || $key === '' || ! str_starts_with(strtolower($endpoint), 'https://')) {
            throw new RuntimeException('advisor.endpoint (https) و advisor.key مطلوبان في ملف .env لطريقة api.');
        }
        $timeout = max(5, min(120, (int) env('advisor.timeout', 30)));

        $payload = [
            'messages' => [
                ['role' => 'system', 'content' => 'أنت مساعد لمستشار في إدارة الشؤون القانونية والالتزام. اكتب بالعربية الفصحى مسودة رد أولية مختصرة وواضحة على الاستشارة، '
                    . 'مع الإشارة إلى الأنظمة واللوائح ذات الصلة بصورة عامة. المسودة للمراجعة الداخلية ولن تصل للموظف قبل اعتماد المستشار.'],
                ['role' => 'user', 'content' => 'التصنيف: ' . ($c['category_name'] ?? '—') . "\nالموضوع: " . ($c['subject'] ?? '') . "\n\nنص الاستشارة:\n" . ($c['body'] ?? '')],
            ],
            'max_tokens' => 1200,
        ];
        if (($model = trim((string) env('advisor.model', ''))) !== '') {
            $payload['model'] = $model;
        }

        try {
            $response = Services::curlrequest(['timeout' => $timeout, 'connect_timeout' => 5, 'http_errors' => false], null, null, false)
                ->post($endpoint, [
                    'headers' => ['Authorization' => 'Bearer ' . $key, 'Accept' => 'application/json'],
                    'json'    => $payload,
                ]);
        } catch (Throwable $e) {
            throw new RuntimeException('تعذّر الاتصال بمزود المسودات: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('مزود المسودات أعاد الحالة ' . $status);
        }
        $json = json_decode((string) $response->getBody(), true);
        if (! is_array($json)) {
            throw new RuntimeException('استجابة مزود المسودات ليست JSON صالحاً.');
        }

        // صيغ الاستجابة الشائعة لدى المزودين
        $text = $json['choices'][0]['message']['content']
            ?? $json['content'][0]['text']
            ?? $json['output_text']
            ?? $json['text']
            ?? $json['draft']
            ?? null;
        $text = is_string($text) ? trim($text) : '';

        return $text === '' ? null : mb_substr($text, 0, 20000);
    }

    private static function excerpt(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
    }
}
