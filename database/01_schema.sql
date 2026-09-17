-- =====================================================================
-- منصة التحقيق الإداري — هيكل قاعدة البيانات
-- MySQL 8.0+ / MariaDB 10.6+ · InnoDB · utf8mb4
-- =====================================================================

CREATE DATABASE IF NOT EXISTS investigation_platform
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE investigation_platform;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1) الجداول المرجعية
-- ---------------------------------------------------------------------

CREATE TABLE departments (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code        VARCHAR(30)  NULL,
  name_ar     VARCHAR(150) NOT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_departments_code (code)
) ENGINE=InnoDB COMMENT='الإدارات والأقسام';

CREATE TABLE roles (
  id        TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code      VARCHAR(30)  NOT NULL,
  name_ar   VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB COMMENT='الأدوار الوظيفية في المنصة';

CREATE TABLE case_stages (
  code        VARCHAR(30)  NOT NULL,
  name_ar     VARCHAR(100) NOT NULL,
  sort_order  TINYINT UNSIGNED NOT NULL,
  is_final    TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (code)
) ENGINE=InnoDB COMMENT='مراحل المعاملة';

CREATE TABLE case_sources (
  code     VARCHAR(30)  NOT NULL,
  name_ar  VARCHAR(100) NOT NULL,
  PRIMARY KEY (code)
) ENGINE=InnoDB COMMENT='مصادر المعاملة';

CREATE TABLE case_types (
  id        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name_ar   VARCHAR(100) NOT NULL,
  is_active TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB COMMENT='أنواع المعاملات';

CREATE TABLE alert_settings (
  rule_code    VARCHAR(30) NOT NULL COMMENT 'new_case = معاملة بلا إجراء، after_action = بعد آخر إجراء',
  green_days   TINYINT UNSIGNED NOT NULL,
  yellow_days  TINYINT UNSIGNED NOT NULL,
  red_days     TINYINT UNSIGNED NOT NULL,
  day_type     ENUM('calendar','working') NOT NULL DEFAULT 'calendar',
  updated_at   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (rule_code),
  CONSTRAINT chk_alert_order CHECK (green_days < yellow_days AND yellow_days < red_days)
) ENGINE=InnoDB COMMENT='مدد الإنذار';

CREATE TABLE holidays (
  holiday_date DATE NOT NULL,
  name_ar      VARCHAR(100) NOT NULL,
  PRIMARY KEY (holiday_date)
) ENGINE=InnoDB COMMENT='الإجازات الرسمية لحساب أيام العمل';

CREATE TABLE case_number_sequences (
  year         SMALLINT UNSIGNED NOT NULL,
  last_number  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (year)
) ENGINE=InnoDB COMMENT='تسلسل أرقام المعاملات لكل سنة';

-- ---------------------------------------------------------------------
-- 2) الموظفون والمستخدمون والصلاحيات
-- ---------------------------------------------------------------------

CREATE TABLE employees (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_no    VARCHAR(20)  NOT NULL,
  full_name      VARCHAR(150) NOT NULL,
  job_title      VARCHAR(150) NULL,
  department_id  INT UNSIGNED NULL,
  email          VARCHAR(150) NULL,
  mobile         VARCHAR(20)  NULL,
  data_source    ENUM('manual','hr_sync') NOT NULL DEFAULT 'manual',
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_employees_no (employee_no),
  KEY idx_employees_name (full_name),
  CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments (id)
) ENGINE=InnoDB COMMENT='موظفو المدينة الطبية';

CREATE TABLE users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id    INT UNSIGNED NULL,
  username       VARCHAR(60)  NOT NULL,
  password_hash  VARCHAR(255) NULL COMMENT 'password_hash() في PHP، فارغ عند الدخول عبر نفاذ',
  nafath_ref     VARCHAR(100) NULL,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  failed_logins  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until   DATETIME     NULL,
  last_login_at  DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_employee (employee_id),
  CONSTRAINT fk_users_employee FOREIGN KEY (employee_id) REFERENCES employees (id)
) ENGINE=InnoDB COMMENT='حسابات الدخول';

CREATE TABLE user_roles (
  user_id  INT UNSIGNED NOT NULL,
  role_id  TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB;

CREATE TABLE login_acknowledgements (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED NOT NULL,
  confidentiality  TINYINT(1) NOT NULL COMMENT 'إقرار السرية',
  accuracy         TINYINT(1) NOT NULL COMMENT 'إقرار صحة المعلومات',
  ip_address       VARCHAR(45) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ack_user (user_id),
  CONSTRAINT fk_ack_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB COMMENT='إقرارات الدخول';

-- ---------------------------------------------------------------------
-- 3) المعاملة وأطرافها والإحالة
-- ---------------------------------------------------------------------

CREATE TABLE cases (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_no                VARCHAR(20)  NOT NULL COMMENT 'مثال: INV-2026-0147',
  case_type_id           SMALLINT UNSIGNED NOT NULL,
  source_code            VARCHAR(30)  NOT NULL,
  external_ref           VARCHAR(60)  NULL COMMENT 'الرقم في إتقان أو إفادة أو البريد',
  subject                VARCHAR(255) NOT NULL,
  description            TEXT         NOT NULL,
  incident_date          DATE         NULL,
  incident_place         VARCHAR(150) NULL,
  department_id          INT UNSIGNED NULL,
  received_at            DATE         NOT NULL,
  confidentiality        ENUM('normal','confidential','top_secret') NOT NULL DEFAULT 'confidential',
  stage_code             VARCHAR(30)  NOT NULL DEFAULT 'draft',
  holder_role_id         TINYINT UNSIGNED NULL COMMENT 'الدور المطلوب منه الإجراء الآن',
  head_user_id           INT UNSIGNED NULL COMMENT 'رئيس التحقيقات المحال إليه',
  investigator_user_id   INT UNSIGNED NULL,
  via_gm                 TINYINT(1)   NOT NULL DEFAULT 0,
  created_by             INT UNSIGNED NOT NULL,
  last_action_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  action_count           INT UNSIGNED NOT NULL DEFAULT 0,
  closed_at              DATETIME     NULL,
  archived_at            DATETIME     NULL,
  created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cases_no (case_no),
  KEY idx_cases_stage (stage_code),
  KEY idx_cases_holder (holder_role_id),
  KEY idx_cases_investigator (investigator_user_id),
  KEY idx_cases_last_action (last_action_at),
  CONSTRAINT fk_cases_type        FOREIGN KEY (case_type_id)         REFERENCES case_types (id),
  CONSTRAINT fk_cases_source      FOREIGN KEY (source_code)          REFERENCES case_sources (code),
  CONSTRAINT fk_cases_stage       FOREIGN KEY (stage_code)           REFERENCES case_stages (code),
  CONSTRAINT fk_cases_department  FOREIGN KEY (department_id)        REFERENCES departments (id),
  CONSTRAINT fk_cases_holder      FOREIGN KEY (holder_role_id)       REFERENCES roles (id),
  CONSTRAINT fk_cases_head        FOREIGN KEY (head_user_id)         REFERENCES users (id),
  CONSTRAINT fk_cases_investigator FOREIGN KEY (investigator_user_id) REFERENCES users (id),
  CONSTRAINT fk_cases_creator     FOREIGN KEY (created_by)           REFERENCES users (id)
) ENGINE=InnoDB COMMENT='المعاملات (المخالفات والشكاوى)';

CREATE TABLE case_parties (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_id       INT UNSIGNED NOT NULL,
  employee_id   INT UNSIGNED NULL,
  external_name VARCHAR(150) NULL COMMENT 'لطرف من خارج قائمة الموظفين',
  party_role    ENUM('complainant','accused','witness','expert') NOT NULL,
  notes         VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_party (case_id, employee_id, party_role),
  CONSTRAINT fk_parties_case     FOREIGN KEY (case_id)     REFERENCES cases (id),
  CONSTRAINT fk_parties_employee FOREIGN KEY (employee_id) REFERENCES employees (id),
  CONSTRAINT chk_party_identity CHECK (employee_id IS NOT NULL OR external_name IS NOT NULL)
) ENGINE=InnoDB COMMENT='أطراف المعاملة';

CREATE TABLE referrals (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_id       INT UNSIGNED NOT NULL,
  action        ENUM('refer_head','refer_gm','assign_investigator') NOT NULL,
  from_user_id  INT UNSIGNED NOT NULL,
  to_role_id    TINYINT UNSIGNED NOT NULL,
  to_user_id    INT UNSIGNED NULL,
  reason        VARCHAR(150) NULL,
  notes         TEXT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_referrals_case (case_id),
  CONSTRAINT fk_ref_case FOREIGN KEY (case_id)      REFERENCES cases (id),
  CONSTRAINT fk_ref_from FOREIGN KEY (from_user_id) REFERENCES users (id),
  CONSTRAINT fk_ref_role FOREIGN KEY (to_role_id)   REFERENCES roles (id),
  CONSTRAINT fk_ref_to   FOREIGN KEY (to_user_id)   REFERENCES users (id)
) ENGINE=InnoDB COMMENT='مسار الإحالة';

-- ---------------------------------------------------------------------
-- 4) الدعوات والجلسات ومحاور التحقيق
-- ---------------------------------------------------------------------

CREATE TABLE invitations (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_id             INT UNSIGNED NOT NULL,
  party_id            INT UNSIGNED NOT NULL,
  invitation_type     ENUM('accused','witness','complainant','expert') NOT NULL,
  session_at          DATETIME NOT NULL,
  attendance_mode     ENUM('in_person','remote') NOT NULL,
  meeting_link        VARCHAR(255) NULL,
  body                TEXT NOT NULL,
  via_email           TINYINT(1) NOT NULL DEFAULT 1,
  via_sms             TINYINT(1) NOT NULL DEFAULT 0,
  via_enjaz           TINYINT(1) NOT NULL DEFAULT 0,
  status              ENUM('draft','sent','read') NOT NULL DEFAULT 'draft',
  sent_at             DATETIME NULL,
  read_at             DATETIME NULL,
  attendance_status   ENUM('pending','attended','excused','unexcused') NOT NULL DEFAULT 'pending',
  excuse_note         VARCHAR(500) NULL,
  absence_action_at   DATETIME NULL COMMENT 'وقت تطبيق إجراء الغياب',
  reissued_from_id    INT UNSIGNED NULL,
  created_by          INT UNSIGNED NOT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inv_case (case_id),
  KEY idx_inv_session_at (session_at),
  CONSTRAINT fk_inv_case    FOREIGN KEY (case_id)          REFERENCES cases (id),
  CONSTRAINT fk_inv_party   FOREIGN KEY (party_id)         REFERENCES case_parties (id),
  CONSTRAINT fk_inv_reissue FOREIGN KEY (reissued_from_id) REFERENCES invitations (id),
  CONSTRAINT fk_inv_creator FOREIGN KEY (created_by)       REFERENCES users (id)
) ENGINE=InnoDB COMMENT='دعوات الحضور';

CREATE TABLE sessions (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_id        INT UNSIGNED NOT NULL,
  party_id       INT UNSIGNED NOT NULL COMMENT 'الطرف الرئيسي في الجلسة',
  invitation_id  INT UNSIGNED NULL,
  session_no     SMALLINT UNSIGNED NOT NULL,
  mode           ENUM('in_person','remote') NOT NULL,
  status         ENUM('scheduled','in_progress','paused','closed') NOT NULL DEFAULT 'scheduled',
  started_at     DATETIME NULL,
  closed_at      DATETIME NULL,
  recording_ref  VARCHAR(255) NULL,
  investigator_user_id INT UNSIGNED NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_session_no (case_id, session_no),
  CONSTRAINT fk_sess_case  FOREIGN KEY (case_id)       REFERENCES cases (id),
  CONSTRAINT fk_sess_party FOREIGN KEY (party_id)      REFERENCES case_parties (id),
  CONSTRAINT fk_sess_inv   FOREIGN KEY (invitation_id) REFERENCES invitations (id),
  CONSTRAINT fk_sess_investigator FOREIGN KEY (investigator_user_id) REFERENCES users (id)
) ENGINE=InnoDB COMMENT='جلسات التحقيق';

CREATE TABLE session_participants (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id  INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NULL,
  party_id    INT UNSIGNED NULL,
  role_label  ENUM('investigator','accused','witness','expert','complainant') NOT NULL,
  admitted_at DATETIME NULL,
  left_at     DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_sp_session (session_id),
  CONSTRAINT fk_sp_session FOREIGN KEY (session_id) REFERENCES sessions (id),
  CONSTRAINT fk_sp_user    FOREIGN KEY (user_id)    REFERENCES users (id),
  CONSTRAINT fk_sp_party   FOREIGN KEY (party_id)   REFERENCES case_parties (id)
) ENGINE=InnoDB COMMENT='المشاركون في الجلسة';

CREATE TABLE session_messages (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id      INT UNSIGNED NOT NULL,
  participant_id  INT UNSIGNED NOT NULL,
  body            TEXT NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_msg_session (session_id),
  CONSTRAINT fk_msg_session FOREIGN KEY (session_id)     REFERENCES sessions (id),
  CONSTRAINT fk_msg_part    FOREIGN KEY (participant_id) REFERENCES session_participants (id)
) ENGINE=InnoDB COMMENT='المحادثة الكتابية';

CREATE TABLE investigation_topics (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_id     INT UNSIGNED NOT NULL,
  title       VARCHAR(255) NOT NULL,
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status      ENUM('not_started','in_progress','done') NOT NULL DEFAULT 'not_started',
  created_by  INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_topics_case (case_id, sort_order),
  CONSTRAINT fk_topics_case    FOREIGN KEY (case_id)    REFERENCES cases (id),
  CONSTRAINT fk_topics_creator FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB COMMENT='محاور التحقيق';

CREATE TABLE investigation_questions (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  topic_id            INT UNSIGNED NOT NULL,
  session_id          INT UNSIGNED NULL COMMENT 'الجلسة التي طُرح فيها السؤال، وبها يتكوّن المحضر',
  question            TEXT NOT NULL,
  answer              TEXT NULL,
  investigator_notes  TEXT NULL COMMENT 'داخلية لا تظهر للموظف',
  asked_at            DATETIME NULL,
  answered_at         DATETIME NULL,
  sort_order          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_q_topic (topic_id, sort_order),
  KEY idx_q_session (session_id),
  CONSTRAINT fk_q_topic   FOREIGN KEY (topic_id)   REFERENCES investigation_topics (id) ON DELETE CASCADE,
  CONSTRAINT fk_q_session FOREIGN KEY (session_id) REFERENCES sessions (id)
) ENGINE=InnoDB COMMENT='الأسئلة والإجابات (محضر الجلسة)';

-- ---------------------------------------------------------------------
-- 5) الشهود والمختصون والمراسلات والمرفقات
-- ---------------------------------------------------------------------

CREATE TABLE statement_requests (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_id         INT UNSIGNED NOT NULL,
  request_type    ENUM('witness','expert') NOT NULL,
  party_id        INT UNSIGNED NULL COMMENT 'للشاهد',
  department_id   INT UNSIGNED NULL,
  specialty       VARCHAR(150) NULL COMMENT 'للرأي المختص',
  reason          VARCHAR(255) NULL,
  subject         VARCHAR(255) NOT NULL,
  details         TEXT NULL,
  proposed_at     DATETIME NULL,
  status          ENUM('new','pending','answered') NOT NULL DEFAULT 'new',
  response        TEXT NULL,
  responded_at    DATETIME NULL,
  created_by      INT UNSIGNED NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sr_case (case_id),
  CONSTRAINT fk_sr_case    FOREIGN KEY (case_id)       REFERENCES cases (id),
  CONSTRAINT fk_sr_party   FOREIGN KEY (party_id)      REFERENCES case_parties (id),
  CONSTRAINT fk_sr_dept    FOREIGN KEY (department_id) REFERENCES departments (id),
  CONSTRAINT fk_sr_creator FOREIGN KEY (created_by)    REFERENCES users (id)
) ENGINE=InnoDB COMMENT='طلبات إفادة الشهود والرأي المختص';

CREATE TABLE correspondences (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_id        INT UNSIGNED NOT NULL,
  department_id  INT UNSIGNED NOT NULL,
  request_type   VARCHAR(100) NOT NULL,
  subject        VARCHAR(255) NOT NULL,
  body           TEXT NOT NULL,
  due_date       DATE NOT NULL,
  sent_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status         ENUM('awaiting','replied') NOT NULL DEFAULT 'awaiting' COMMENT 'التأخر يُحسب من due_date',
  reply_body     TEXT NULL,
  replied_at     DATETIME NULL,
  replied_by     VARCHAR(150) NULL,
  created_by     INT UNSIGNED NOT NULL,
  updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_corr_case (case_id),
  KEY idx_corr_due (status, due_date),
  CONSTRAINT fk_corr_case    FOREIGN KEY (case_id)       REFERENCES cases (id),
  CONSTRAINT fk_corr_dept    FOREIGN KEY (department_id) REFERENCES departments (id),
  CONSTRAINT fk_corr_creator FOREIGN KEY (created_by)    REFERENCES users (id)
) ENGINE=InnoDB COMMENT='المراسلات والردود';

CREATE TABLE attachments (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_id         INT UNSIGNED NOT NULL,
  related_type    ENUM('case','invitation','session','statement_request','correspondence','correspondence_reply','memo','recommendation') NOT NULL DEFAULT 'case',
  related_id      INT UNSIGNED NULL,
  original_name   VARCHAR(255) NOT NULL,
  stored_path     VARCHAR(500) NOT NULL COMMENT 'المسار على خادم الملفات، وليس الملف نفسه',
  mime_type       VARCHAR(100) NOT NULL,
  size_bytes      INT UNSIGNED NOT NULL,
  sha256          CHAR(64) NOT NULL,
  uploaded_by     INT UNSIGNED NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_att_case (case_id),
  KEY idx_att_related (related_type, related_id),
  CONSTRAINT fk_att_case FOREIGN KEY (case_id)     REFERENCES cases (id),
  CONSTRAINT fk_att_user FOREIGN KEY (uploaded_by) REFERENCES users (id),
  CONSTRAINT chk_att_size CHECK (size_bytes <= 20971520)
) ENGINE=InnoDB COMMENT='المرفقات (حد 20 ميجابايت)';

-- ---------------------------------------------------------------------
-- 6) المذكرة ودورة الاعتماد والتوصيات
-- ---------------------------------------------------------------------

CREATE TABLE memos (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_id          INT UNSIGNED NOT NULL,
  version          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status           ENUM('draft','submitted','returned','approved') NOT NULL DEFAULT 'draft',
  opening          MEDIUMTEXT NULL COMMENT 'الافتتاحية',
  parties_info     MEDIUMTEXT NULL COMMENT 'البيانات الأساسية للموظف / الشهود',
  topics           MEDIUMTEXT NULL COMMENT 'موضوعات التحقيق',
  findings         MEDIUMTEXT NULL COMMENT 'النتائج',
  recommendations  MEDIUMTEXT NULL COMMENT 'التوصيات',
  prepared_by      INT UNSIGNED NOT NULL,
  submitted_at     DATETIME NULL,
  approved_at      DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_memo_case (case_id),
  CONSTRAINT fk_memo_case FOREIGN KEY (case_id)     REFERENCES cases (id),
  CONSTRAINT fk_memo_user FOREIGN KEY (prepared_by) REFERENCES users (id)
) ENGINE=InnoDB COMMENT='مذكرة التحقيق التفصيلية';

CREATE TABLE memo_versions (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  memo_id     INT UNSIGNED NOT NULL,
  version     SMALLINT UNSIGNED NOT NULL,
  snapshot    LONGTEXT NOT NULL COMMENT 'نسخة JSON كاملة من الأقسام الخمسة عند الإرسال',
  created_by  INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_memo_version (memo_id, version),
  CONSTRAINT fk_mv_memo FOREIGN KEY (memo_id)    REFERENCES memos (id),
  CONSTRAINT fk_mv_user FOREIGN KEY (created_by) REFERENCES users (id),
  CONSTRAINT chk_mv_json CHECK (JSON_VALID(snapshot))
) ENGINE=InnoDB COMMENT='نسخ المذكرة المرسلة للاعتماد';

CREATE TABLE approval_steps (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  memo_id           INT UNSIGNED NOT NULL,
  memo_version      SMALLINT UNSIGNED NOT NULL,
  step_order        TINYINT UNSIGNED NOT NULL COMMENT '1 رئيس التحقيقات، 2 الشؤون القانونية، 3 المدير العام',
  role_id           TINYINT UNSIGNED NOT NULL,
  approver_user_id  INT UNSIGNED NULL,
  decision          ENUM('pending','approved','returned') NOT NULL DEFAULT 'pending',
  notes             TEXT NULL,
  signature_ref     VARCHAR(255) NULL COMMENT 'مرجع التوقيع الإلكتروني',
  decided_at        DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_step (memo_id, memo_version, step_order),
  KEY idx_steps_pending (decision, role_id),
  CONSTRAINT fk_step_memo FOREIGN KEY (memo_id)          REFERENCES memos (id),
  CONSTRAINT fk_step_role FOREIGN KEY (role_id)          REFERENCES roles (id),
  CONSTRAINT fk_step_user FOREIGN KEY (approver_user_id) REFERENCES users (id),
  CONSTRAINT chk_return_note CHECK (decision <> 'returned' OR (notes IS NOT NULL AND notes <> ''))
) ENGINE=InnoDB COMMENT='دورة الاعتماد';

CREATE TABLE recommendations (
  id                         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_id                    INT UNSIGNED NOT NULL,
  memo_id                    INT UNSIGNED NOT NULL,
  body                       TEXT NOT NULL,
  responsible_department_id  INT UNSIGNED NOT NULL,
  due_date                   DATE NULL,
  status                     ENUM('not_started','in_progress','done') NOT NULL DEFAULT 'not_started',
  progress_notes             TEXT NULL,
  completed_at               DATETIME NULL,
  updated_by                 INT UNSIGNED NULL,
  created_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                 DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rec_case (case_id),
  KEY idx_rec_status (status, due_date),
  CONSTRAINT fk_rec_case FOREIGN KEY (case_id)                   REFERENCES cases (id),
  CONSTRAINT fk_rec_memo FOREIGN KEY (memo_id)                   REFERENCES memos (id),
  CONSTRAINT fk_rec_dept FOREIGN KEY (responsible_department_id) REFERENCES departments (id),
  CONSTRAINT fk_rec_user FOREIGN KEY (updated_by)                REFERENCES users (id)
) ENGINE=InnoDB COMMENT='التوصيات المعتمدة ومتابعة تنفيذها';

-- ---------------------------------------------------------------------
-- 7) سجل الإجراءات والإشعارات
-- ---------------------------------------------------------------------

CREATE TABLE case_actions (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_id      INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NULL COMMENT 'NULL = إجراء آلي من النظام',
  action_code  VARCHAR(40)  NOT NULL COMMENT 'created, referred, investigator_assigned, invitation_sent, session_closed, memo_submitted, approved, returned, recommendation_updated, archived …',
  description  VARCHAR(500) NOT NULL,
  meta         LONGTEXT NULL,
  ip_address   VARCHAR(45) NULL,
  created_at   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY idx_actions_case (case_id, created_at),
  CONSTRAINT fk_actions_case FOREIGN KEY (case_id) REFERENCES cases (id),
  CONSTRAINT fk_actions_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT chk_actions_meta CHECK (meta IS NULL OR JSON_VALID(meta))
) ENGINE=InnoDB COMMENT='سجل التدقيق — إضافة فقط';

CREATE TABLE notifications (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  case_id     INT UNSIGNED NULL,
  category    ENUM('new_case','invitation','session','statement','approval','decision','attachment','reply') NOT NULL,
  level       ENUM('green','yellow','red') NOT NULL DEFAULT 'green',
  title       VARCHAR(150) NOT NULL,
  body        VARCHAR(500) NOT NULL,
  link        VARCHAR(255) NULL,
  read_at     DATETIME NULL,
  emailed_at  DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, read_at, created_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_notif_case FOREIGN KEY (case_id) REFERENCES cases (id)
) ENGINE=InnoDB COMMENT='الإشعارات';

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 8) حماية سجل التدقيق من التعديل والحذف
-- ---------------------------------------------------------------------

DELIMITER //
CREATE TRIGGER trg_case_actions_no_update BEFORE UPDATE ON case_actions
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'case_actions is append-only';
END//

CREATE TRIGGER trg_case_actions_no_delete BEFORE DELETE ON case_actions
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'case_actions is append-only';
END//

-- كل إجراء جديد يحدّث آخر إجراء وعدد الإجراءات في المعاملة (أساس التنبيهات)
CREATE TRIGGER trg_case_actions_touch AFTER INSERT ON case_actions
FOR EACH ROW
BEGIN
  UPDATE cases
     SET last_action_at = NEW.created_at,
         action_count   = action_count + 1
   WHERE id = NEW.case_id;
END//

-- توليد رقم المعاملة بأمان حتى مع الإدخال المتزامن: INV-YYYY-NNNN
CREATE PROCEDURE next_case_no(OUT p_case_no VARCHAR(20))
BEGIN
  DECLARE v_year SMALLINT UNSIGNED DEFAULT YEAR(CURDATE());
  DECLARE v_next INT UNSIGNED;
  INSERT INTO case_number_sequences (year, last_number) VALUES (v_year, 1)
    ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1);
  SET v_next = IF(ROW_COUNT() = 1, 1, LAST_INSERT_ID());
  SET p_case_no = CONCAT('INV-', v_year, '-', LPAD(v_next, 4, '0'));
END//
DELIMITER ;

-- ---------------------------------------------------------------------
-- 9) عرض مساعد للوحة التحكم والتنبيهات (أيام تقويمية)
-- ---------------------------------------------------------------------

CREATE OR REPLACE VIEW v_case_alerts AS
SELECT
  c.id,
  c.case_no,
  c.stage_code,
  c.holder_role_id,
  c.investigator_user_id,
  c.last_action_at,
  CASE WHEN c.action_count <= 1 THEN 'new_case' ELSE 'after_action' END AS rule_code,
  DATEDIFF(CURDATE(), CASE WHEN c.action_count <= 1 THEN c.created_at ELSE c.last_action_at END) AS idle_days,
  CASE
    WHEN s.is_final = 1 THEN NULL
    WHEN DATEDIFF(CURDATE(), CASE WHEN c.action_count <= 1 THEN c.created_at ELSE c.last_action_at END) >= a.red_days    THEN 'red'
    WHEN DATEDIFF(CURDATE(), CASE WHEN c.action_count <= 1 THEN c.created_at ELSE c.last_action_at END) >= a.yellow_days THEN 'yellow'
    WHEN DATEDIFF(CURDATE(), CASE WHEN c.action_count <= 1 THEN c.created_at ELSE c.last_action_at END) >= a.green_days  THEN 'green'
    ELSE 'ok'
  END AS alert_level
FROM cases c
JOIN case_stages s ON s.code = c.stage_code
JOIN alert_settings a ON a.rule_code = CASE WHEN c.action_count <= 1 THEN 'new_case' ELSE 'after_action' END;
