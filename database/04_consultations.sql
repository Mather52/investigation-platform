-- =====================================================================
-- الاستشارات القانونية (محادثة بين الموظف وإدارة الشؤون القانونية)
-- يُستورد بعد 01_schema.sql. آمن للتشغيل أكثر من مرة: لا ينشئ جدولاً موجوداً.
-- =====================================================================
USE investigation_platform;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS consultation_categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name_ar     VARCHAR(100) NOT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cons_cat_name (name_ar)
) ENGINE=InnoDB COMMENT='تصنيفات الاستشارات';

CREATE TABLE IF NOT EXISTS consultations (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ref_no           VARCHAR(20)  NOT NULL COMMENT 'مثال: CONS-2026-0001',
  user_id          INT UNSIGNED NOT NULL COMMENT 'مقدم الاستشارة',
  category_id      INT UNSIGNED NOT NULL,
  subject          VARCHAR(255) NOT NULL,
  body             TEXT         NOT NULL COMMENT 'أول رسالة في المحادثة',
  confidentiality  ENUM('normal','confidential') NOT NULL DEFAULT 'normal',
  status           ENUM('new','assigned','answered','closed') NOT NULL DEFAULT 'new' COMMENT 'new = بانتظار رد الشؤون القانونية',
  assigned_to      INT UNSIGNED NULL,
  assigned_at      DATETIME     NULL,
  answer           TEXT         NULL,
  answered_by      INT UNSIGNED NULL COMMENT 'أول من رد من الشؤون القانونية',
  answered_at      DATETIME     NULL,
  read_at          DATETIME     NULL COMMENT 'آخر قراءة لصاحب الاستشارة بعد الرد',
  is_published     TINYINT(1)   NOT NULL DEFAULT 0,
  ai_draft         MEDIUMTEXT   NULL,
  ai_source        VARCHAR(40)  NULL,
  ai_generated_at  DATETIME     NULL,
  closed_at        DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cons_ref (ref_no),
  KEY idx_cons_user (user_id),
  KEY idx_cons_status (status),
  CONSTRAINT fk_cons_user     FOREIGN KEY (user_id)     REFERENCES users (id),
  CONSTRAINT fk_cons_category FOREIGN KEY (category_id) REFERENCES consultation_categories (id)
) ENGINE=InnoDB COMMENT='الاستشارات القانونية';

CREATE TABLE IF NOT EXISTS consultation_messages (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  consultation_id  INT UNSIGNED NOT NULL,
  user_id          INT UNSIGNED NOT NULL,
  body             TEXT         NOT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cons_msg (consultation_id, id),
  CONSTRAINT fk_cons_msg_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB COMMENT='رسائل محادثة الاستشارة';

CREATE TABLE IF NOT EXISTS consultation_actions (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  consultation_id  INT UNSIGNED NOT NULL,
  user_id          INT UNSIGNED NULL,
  action_code      VARCHAR(40)  NOT NULL,
  description      VARCHAR(500) NOT NULL,
  meta             LONGTEXT     NULL,
  ip_address       VARCHAR(45)  NULL,
  created_at       DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY idx_cons_actions (consultation_id, created_at)
) ENGINE=InnoDB COMMENT='سجل إجراءات الاستشارات';

-- التصنيفات الأساسية (لا تتكرر عند إعادة التشغيل)
INSERT IGNORE INTO consultation_categories (name_ar, sort_order) VALUES
  ('الموارد البشرية وشؤون الموظفين', 1),
  ('العقود والاتفاقيات', 2),
  ('الأنظمة واللوائح والسياسات', 3),
  ('المخالفات والتحقيق', 4),
  ('أخرى', 5);
