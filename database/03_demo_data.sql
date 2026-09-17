-- =====================================================================
-- بيانات تجريبية للتشغيل على جهازك فقط — لا تُشغَّل على خادم حقيقي
-- جميع الأسماء افتراضية. كلمة المرور لكل الحسابات: Test@12345
-- =====================================================================
USE investigation_platform;
SET NAMES utf8mb4;

INSERT INTO departments (code, name_ar) VALUES ('RAD', 'إدارة الأشعة التشخيصية'), ('ER', 'إدارة الطوارئ');

SET @d_legal = (SELECT id FROM departments WHERE code = 'LEGAL');
SET @d_rad   = (SELECT id FROM departments WHERE code = 'RAD');
SET @d_er    = (SELECT id FROM departments WHERE code = 'ER');

INSERT INTO employees (employee_no, full_name, job_title, department_id, email, mobile) VALUES
  ('100001', 'ريم خالد الدوسري',       'مدير الشؤون القانونية والالتزام', @d_legal, 'legal@example.test', '0500000001'),
  ('100002', 'عبدالرحمن ناصر الشهري',  'رئيس التحقيقات',                  @d_legal, 'head@example.test',  '0500000002'),
  ('100003', 'فهد سعد العتيبي',        'محقق',                            @d_legal, 'inv@example.test',   '0500000003'),
  ('100004', 'ماجد علي الحربي',        'المدير العام التنفيذي',           NULL,     'gm@example.test',    '0500000004'),
  ('104582', 'خالد عبدالله الزهراني',  'فني أشعة',                        @d_rad,   'emp@example.test',   '0500000005'),
  ('104900', 'سارة محمد القحطاني',     'مشرفة تمريض',                     @d_er,    'nurse@example.test', '0500000006'),
  ('105120', 'منى أحمد الشمري',        'أخصائية أشعة',                    @d_rad,   'wit@example.test',   '0500000007');

SET @hash = '$2y$10$VhdqVr3VDPtznidvCGn.tON7C1BO5VEJg3rXBLY9JRwYwh2uResZC';

INSERT INTO users (employee_id, username, password_hash)
SELECT id, CASE employee_no
             WHEN '100001' THEN 'legal'
             WHEN '100002' THEN 'head'
             WHEN '100003' THEN 'investigator'
             WHEN '100004' THEN 'gm'
             WHEN '104900' THEN 'employee'
           END, @hash
FROM employees WHERE employee_no IN ('100001','100002','100003','100004','104900');

INSERT INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u JOIN roles r ON r.code = CASE u.username
  WHEN 'legal' THEN 'legal' WHEN 'head' THEN 'head' WHEN 'investigator' THEN 'investigator'
  WHEN 'gm' THEN 'gm' WHEN 'employee' THEN 'employee' END;
INSERT INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u, roles r WHERE u.username = 'legal' AND r.code = 'admin';

SET @u_legal = (SELECT id FROM users WHERE username = 'legal');
SET @u_head  = (SELECT id FROM users WHERE username = 'head');
SET @u_inv   = (SELECT id FROM users WHERE username = 'investigator');

-- معاملة تجريبية في مرحلة التحقيق
CALL next_case_no(@no);
INSERT INTO cases (case_no, case_type_id, source_code, subject, description, incident_date, incident_place,
                   department_id, received_at, confidentiality, stage_code, holder_role_id,
                   head_user_id, investigator_user_id, created_by, created_at)
VALUES (@no, 1, 'etqan', 'تأخر إصدار تقارير الأشعة العاجلة',
        'تأخر متكرر في إصدار تقارير الأشعة العاجلة لقسم الطوارئ خلال المناوبات الليلية، مع عدم الالتزام بإجراءات التسليم والاستلام بين المناوبات.',
        CURDATE() - INTERVAL 7 DAY, 'قسم الأشعة — المبنى الرئيسي', @d_rad, CURDATE() - INTERVAL 3 DAY,
        'confidential', 'investigation', (SELECT id FROM roles WHERE code = 'investigator'),
        @u_head, @u_inv, @u_legal, NOW() - INTERVAL 3 DAY);
SET @case = LAST_INSERT_ID();

INSERT INTO case_parties (case_id, employee_id, party_role) VALUES
  (@case, (SELECT id FROM employees WHERE employee_no = '104582'), 'accused'),
  (@case, (SELECT id FROM employees WHERE employee_no = '104900'), 'complainant'),
  (@case, (SELECT id FROM employees WHERE employee_no = '105120'), 'witness');

INSERT INTO referrals (case_id, action, from_user_id, to_role_id, to_user_id, reason, created_at) VALUES
  (@case, 'refer_head',          @u_legal, (SELECT id FROM roles WHERE code = 'head'),         @u_head, 'مخالفة تستوجب التحقيق', NOW() - INTERVAL 3 DAY),
  (@case, 'assign_investigator', @u_head,  (SELECT id FROM roles WHERE code = 'investigator'), @u_inv,  NULL,                     NOW() - INTERVAL 2 DAY);

INSERT INTO invitations (case_id, party_id, invitation_type, session_at, attendance_mode, meeting_link, body,
                         via_email, via_sms, via_enjaz, status, sent_at, created_by)
SELECT @case, id, 'accused', NOW() + INTERVAL 3 DAY, 'remote', 'https://example.test/session/demo',
       'نفيدكم بأنه تقرر دعوتكم للحضور أمام قسم التحقيق للإدلاء بأقوالكم.', 1, 1, 1, 'sent', NOW() - INTERVAL 1 DAY, @u_inv
FROM case_parties WHERE case_id = @case AND party_role = 'accused';

INSERT INTO case_actions (case_id, user_id, action_code, description, created_at) VALUES
  (@case, @u_legal, 'created',               'إنشاء المعاملة (استيراد من إتقان)', NOW() - INTERVAL 3 DAY),
  (@case, @u_legal, 'referred',              'إحالة لرئيس التحقيقات',             NOW() - INTERVAL 3 DAY),
  (@case, @u_head,  'investigator_assigned', 'تعيين المحقق',                      NOW() - INTERVAL 2 DAY),
  (@case, @u_inv,   'invitation_sent',       'إرسال دعوة حضور',                   NOW() - INTERVAL 1 DAY);

INSERT INTO memos (case_id, prepared_by) VALUES (@case, @u_inv);
