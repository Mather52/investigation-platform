-- =====================================================================
-- البيانات المرجعية الأساسية (تُشغَّل بعد 01_schema.sql)
-- =====================================================================
USE investigation_platform;
SET NAMES utf8mb4;

INSERT INTO roles (code, name_ar) VALUES
  ('employee',     'موظف'),
  ('investigator', 'محقق'),
  ('head',         'رئيس التحقيقات'),
  ('legal',        'مدير الشؤون القانونية والالتزام'),
  ('gm',           'المدير العام التنفيذي'),
  ('admin',        'مدير النظام');

INSERT INTO case_stages (code, name_ar, sort_order, is_final) VALUES
  ('draft',          'مسودة',                 1, 0),
  ('new',            'جديدة — بانتظار الإحالة', 2, 0),
  ('with_gm',        'لدى المدير العام',       3, 0),
  ('referred',       'محالة لرئيس التحقيقات',  4, 0),
  ('investigation',  'قيد التحقيق',            5, 0),
  ('approval',       'في دورة الاعتماد',       6, 0),
  ('execution',      'قيد تنفيذ التوصيات',     7, 0),
  ('archived',       'مؤرشفة',                 8, 1),
  ('closed_no_action','محفوظة دون إجراء',       9, 1);

INSERT INTO case_sources (code, name_ar) VALUES
  ('manual', 'كتابة مباشرة'),
  ('paper',  'مستند ورقي'),
  ('etqan',  'نظام إتقان'),
  ('efada',  'نظام إفادة'),
  ('email',  'البريد الإلكتروني');

INSERT INTO case_types (name_ar) VALUES
  ('مخالفة إدارية'),
  ('شكوى موظف'),
  ('مخالفة سلوكية'),
  ('مخالفة مالية');

INSERT INTO alert_settings (rule_code, green_days, yellow_days, red_days, day_type) VALUES
  ('new_case',     3, 5, 7,  'calendar'),
  ('after_action', 5, 7, 10, 'calendar');

INSERT INTO departments (code, name_ar) VALUES
  ('LEGAL', 'إدارة الشؤون القانونية والالتزام'),
  ('HR',    'إدارة الموارد البشرية'),
  ('IT',    'إدارة تقنية المعلومات'),
  ('QPS',   'إدارة الجودة وسلامة المرضى');
