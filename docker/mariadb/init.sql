-- 나무장터 초기 스키마
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS joongna CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE joongna;

DROP TABLE IF EXISTS users;
CREATE TABLE users (
  acc_number      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  acc_id          VARCHAR(20) NOT NULL,
  acc_nick        VARCHAR(20) NOT NULL DEFAULT '',
  acc_pw          CHAR(64) NOT NULL,
  acc_email       VARCHAR(80) NOT NULL,
  acc_intro       VARCHAR(300) NOT NULL DEFAULT '',
  acc_phone       VARCHAR(20) NOT NULL DEFAULT '',
  acc_address     VARCHAR(120) NOT NULL DEFAULT '',
  acc_group       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  acc_status      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  acc_onelogin    INT UNSIGNED NOT NULL DEFAULT 1,
  acc_report_ban  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  acc_totp_secret VARCHAR(64) NOT NULL DEFAULT '',
  acc_totp_ok     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  acc_balance     BIGINT NOT NULL DEFAULT 0,
  acc_trust       DECIMAL(4,2) NOT NULL DEFAULT 50.00,
  acc_trade_count INT UNSIGNED NOT NULL DEFAULT 0,
  acc_sell_count  INT UNSIGNED NOT NULL DEFAULT 0,
  acc_buy_count   INT UNSIGNED NOT NULL DEFAULT 0,
  acc_login_ip    VARCHAR(45) NOT NULL DEFAULT '',
  acc_login_cc    VARCHAR(2) NOT NULL DEFAULT '',
  registertime    DATETIME NOT NULL,
  lastlogin       DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (acc_number),
  UNIQUE KEY uk_acc_id (acc_id),
  UNIQUE KEY uk_acc_email (acc_email)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS tags;
CREATE TABLE tags (tag_id INT UNSIGNED NOT NULL, tag_name VARCHAR(20) NOT NULL, PRIMARY KEY (tag_id)) ENGINE=InnoDB;
INSERT INTO tags (tag_id, tag_name) VALUES (0,'식품'),(1,'전자기기'),(2,'산업/전문기기'),(3,'위생/화장용품'),(4,'도서/학용'),(5,'의류/잡화'),(6,'가구/인테리어'),(7,'스포츠/레저'),(8,'취미/게임'),(9,'자동차/부품'),(10,'유아/아동'),(11,'반려동물용품'),(12,'식물/원예'),(13,'티켓/교환권'),(14,'디지털콘텐츠'),(15,'음악/악기'),(16,'공구/작업도구'),(17,'뷰티/미용'),(18,'건강/의료'),(19,'기타');

DROP TABLE IF EXISTS products;
CREATE TABLE products (
  prd_number   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  prd_seller   BIGINT UNSIGNED NOT NULL,
  prd_tag      INT UNSIGNED NOT NULL DEFAULT 19,
  prd_title    VARCHAR(120) NOT NULL,
  prd_desc     TEXT NOT NULL,
  prd_price    BIGINT NOT NULL DEFAULT 0,
  prd_blind    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  prd_sold     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  prd_report_cnt INT UNSIGNED NOT NULL DEFAULT 0,
  prd_time     DATETIME NOT NULL,
  prd_revised  DATETIME NOT NULL,
  PRIMARY KEY (prd_number),
  KEY idx_seller (prd_seller),
  KEY idx_tag (prd_tag),
  KEY idx_time (prd_time DESC),
  CONSTRAINT fk_prd_seller FOREIGN KEY (prd_seller) REFERENCES users(acc_number) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS product_media;
CREATE TABLE product_media (
  pm_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pm_prd       BIGINT UNSIGNED NOT NULL,
  pm_kind      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  pm_save_name VARCHAR(120) NOT NULL,
  pm_orig_name VARCHAR(120) NOT NULL,
  pm_size      BIGINT UNSIGNED NOT NULL,
  pm_mime      VARCHAR(40) NOT NULL,
  pm_is_main   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  pm_time      DATETIME NOT NULL,
  PRIMARY KEY (pm_id),
  KEY idx_prd (pm_prd),
  CONSTRAINT fk_pm_prd FOREIGN KEY (pm_prd) REFERENCES products(prd_number) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS chat_rooms;
CREATE TABLE chat_rooms (
  room_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_type TINYINT UNSIGNED NOT NULL DEFAULT 1,
  room_key  VARCHAR(64) NOT NULL DEFAULT '',
  room_time DATETIME NOT NULL,
  cr_blocked TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (room_id),
  UNIQUE KEY uk_room_key (room_key)
) ENGINE=InnoDB;
INSERT INTO chat_rooms (room_id, room_type, room_key, room_time) VALUES (1,0,'GLOBAL',NOW());

DROP TABLE IF EXISTS chat_members;
CREATE TABLE chat_members (
  cm_room BIGINT UNSIGNED NOT NULL,
  cm_user BIGINT UNSIGNED NOT NULL,
  cm_last_read DATETIME NOT NULL DEFAULT '1970-01-01 01:00:01',
  PRIMARY KEY (cm_room, cm_user),
  CONSTRAINT fk_cm_room FOREIGN KEY (cm_room) REFERENCES chat_rooms(room_id) ON DELETE CASCADE,
  CONSTRAINT fk_cm_user FOREIGN KEY (cm_user) REFERENCES users(acc_number) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS chat_messages;
CREATE TABLE chat_messages (
  msg_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  msg_room  BIGINT UNSIGNED NOT NULL,
  msg_user  BIGINT UNSIGNED NOT NULL,
  msg_text  TEXT NOT NULL,
  msg_kind  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  msg_ref   VARCHAR(120) NOT NULL DEFAULT '',
  msg_time  DATETIME NOT NULL,
  PRIMARY KEY (msg_id),
  KEY idx_room_time (msg_room, msg_id),
  CONSTRAINT fk_msg_room FOREIGN KEY (msg_room) REFERENCES chat_rooms(room_id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_user FOREIGN KEY (msg_user) REFERENCES users(acc_number) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS reports;
CREATE TABLE reports (
  rpt_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rpt_reporter BIGINT UNSIGNED NOT NULL,
  rpt_target   TINYINT UNSIGNED NOT NULL,
  rpt_ref      BIGINT UNSIGNED NOT NULL,
  rpt_reason   VARCHAR(500) NOT NULL,
  rpt_status   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  rpt_time     DATETIME NOT NULL,
  PRIMARY KEY (rpt_id),
  KEY idx_ref (rpt_target, rpt_ref, rpt_status),
  CONSTRAINT fk_rpt_rep FOREIGN KEY (rpt_reporter) REFERENCES users(acc_number) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS reviews;
CREATE TABLE reviews (
  rv_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rv_writer  BIGINT UNSIGNED NOT NULL,
  rv_target  BIGINT UNSIGNED NOT NULL,
  rv_prd     BIGINT UNSIGNED NULL DEFAULT NULL,
  rv_score   TINYINT UNSIGNED NOT NULL DEFAULT 50,
  rv_comment VARCHAR(300) NOT NULL DEFAULT '',
  rv_time    DATETIME NOT NULL,
  PRIMARY KEY (rv_id),
  UNIQUE KEY uk_one (rv_writer, rv_target, rv_prd),
  KEY idx_target (rv_target),
  CONSTRAINT fk_rv_w FOREIGN KEY (rv_writer) REFERENCES users(acc_number) ON DELETE CASCADE,
  CONSTRAINT fk_rv_t FOREIGN KEY (rv_target) REFERENCES users(acc_number) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS wallet_ledger;
CREATE TABLE wallet_ledger (
  led_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  led_user   BIGINT UNSIGNED NOT NULL,
  led_kind   TINYINT UNSIGNED NOT NULL,
  led_amount BIGINT NOT NULL,
  led_counterparty BIGINT UNSIGNED NULL DEFAULT NULL,
  led_memo   VARCHAR(200) NOT NULL DEFAULT '',
  led_balance_after BIGINT NOT NULL,
  led_time   DATETIME NOT NULL,
  PRIMARY KEY (led_id),
  KEY idx_user_time (led_user, led_time DESC),
  KEY idx_counter (led_counterparty),
  CONSTRAINT fk_led_user FOREIGN KEY (led_user) REFERENCES users(acc_number) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS email_verify;
CREATE TABLE email_verify (
  ev_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ev_user  BIGINT UNSIGNED NOT NULL,
  ev_code  CHAR(6) NOT NULL,
  ev_expire DATETIME NOT NULL,
  PRIMARY KEY (ev_id),
  KEY idx_user (ev_user),
  CONSTRAINT fk_ev_user FOREIGN KEY (ev_user) REFERENCES users(acc_number) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS login_log;
CREATE TABLE login_log (
  lg_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lg_user  BIGINT UNSIGNED NULL DEFAULT NULL,
  lg_ip    VARCHAR(45) NOT NULL,
  lg_cc    VARCHAR(2) NOT NULL DEFAULT '',
  lg_agent VARCHAR(255) NOT NULL DEFAULT '',
  lg_ok    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  lg_time  DATETIME NOT NULL,
  PRIMARY KEY (lg_id),
  KEY idx_user (lg_user),
  KEY idx_ip (lg_ip)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS ip_security;
CREATE TABLE ip_security (
  is_user       BIGINT UNSIGNED NOT NULL,
  is_check_ip   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  is_check_cc   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  is_allow_cc   VARCHAR(2) NOT NULL DEFAULT '',
  PRIMARY KEY (is_user),
  CONSTRAINT fk_is_user FOREIGN KEY (is_user) REFERENCES users(acc_number) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS admin_log;
CREATE TABLE admin_log (
  am_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  am_admin  BIGINT UNSIGNED NOT NULL,
  am_action VARCHAR(50) NOT NULL,
  am_target BIGINT NULL DEFAULT NULL,
  am_memo   VARCHAR(300) NOT NULL DEFAULT '',
  am_time   DATETIME NOT NULL,
  PRIMARY KEY (am_id),
  KEY idx_admin (am_admin),
  CONSTRAINT fk_am FOREIGN KEY (am_admin) REFERENCES users(acc_number) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS ip_firewall;
CREATE TABLE ip_firewall (
  fw_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fw_type    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  fw_rule    VARCHAR(20) NOT NULL DEFAULT 'ip',
  fw_pattern VARCHAR(120) NOT NULL,
  fw_memo    VARCHAR(200) NOT NULL DEFAULT '',
  fw_time    DATETIME NOT NULL,
  PRIMARY KEY (fw_id),
  KEY idx_rule_type (fw_rule, fw_type)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS system_status;
CREATE TABLE system_status (
  ss_key    VARCHAR(40) NOT NULL,
  ss_value  TEXT NOT NULL,
  ss_time   DATETIME NOT NULL,
  PRIMARY KEY (ss_key)
) ENGINE=InnoDB;
INSERT INTO system_status (ss_key, ss_value, ss_time) VALUES ('maintenance','0',NOW()),('notice','반갑습니다. 나무장터에 오신 것을 환영합니다.',NOW()),('notice_urgent','',NOW()),('notice_popup','0',NOW());

DROP TABLE IF EXISTS user_payment_info;
CREATE TABLE user_payment_info (
  upi_user      BIGINT UNSIGNED NOT NULL,
  upi_real_name VARCHAR(40) NOT NULL DEFAULT '',
  upi_bank      VARCHAR(40) NOT NULL DEFAULT '',
  upi_account   VARCHAR(60) NOT NULL DEFAULT '',
  upi_phone     VARCHAR(20) NOT NULL DEFAULT '',
  upi_address   VARCHAR(120) NOT NULL DEFAULT '',
  upi_time      DATETIME NOT NULL,
  PRIMARY KEY (upi_user),
  CONSTRAINT fk_upi_user FOREIGN KEY (upi_user) REFERENCES users(acc_number) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS request_log;
CREATE TABLE request_log (
  rl_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rl_user BIGINT NULL DEFAULT NULL,
  rl_user_name VARCHAR(20) NOT NULL DEFAULT '',
  rl_mode VARCHAR(40) NOT NULL,
  rl_msg VARCHAR(300) NOT NULL DEFAULT '',
  rl_ok TINYINT UNSIGNED NOT NULL,
  rl_ip VARCHAR(45) NOT NULL DEFAULT '',
  rl_ua VARCHAR(255) NOT NULL DEFAULT '',
  rl_memo VARCHAR(300) NOT NULL DEFAULT '',
  rl_time DATETIME NOT NULL,
  PRIMARY KEY (rl_id),
  KEY idx_user(rl_user), KEY idx_mode(rl_mode), KEY idx_time(rl_time DESC), KEY idx_ip(rl_ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS site_docs;
CREATE TABLE site_docs (
  sd_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sd_kind VARCHAR(20) NOT NULL,
  sd_body TEXT NOT NULL,
  sd_time DATETIME NOT NULL,
  sd_admin BIGINT NULL DEFAULT NULL,
  PRIMARY KEY (sd_id),
  KEY idx_kind_time (sd_kind, sd_time DESC)
) ENGINE=InnoDB;
INSERT INTO site_docs (sd_kind,sd_body,sd_time,sd_admin) VALUES ('terms','제1조(목적) 본 약관은 나무장터의 중고거래 서비스 이용조건 및 절차, 이용자와 플랫폼의 권리의무책임을 규정합니다.\n제2조(이용자의 의무) 허위 정보 등록, 사기, 욕설혐오, 타인 권리 침해 금지. 위반 시 서비스 이용 제한.\n제3조(거래) 본 플랫폼은 거래 중개 역할만 수행하며, 거래 책임은 당사자에게 있습니다. 가상지갑은 실제 금전이 아닙니다.',NOW(),NULL),('privacy','수집 항목: 아이디, 비밀번호(해시), 이메일, 전화번호, 주소, 접속 IP, 서비스 이용 기록.\n이용 목적: 회원 식별, 거래 제공, 부정이용 방지, 고객지원.\n보유 기간: 회원 탈퇴 시까지. 관련 법령에 따라 보관이 필요한 경우 해당 기간 동안 보관.\n이용자는 언제든지 마이페이지에서 정보를 수정하거나 전체기기 로그아웃할 수 있습니다.',NOW(),NULL);

DROP TABLE IF EXISTS notice_board;
CREATE TABLE notice_board (
  nb_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nb_writer   BIGINT UNSIGNED NOT NULL,
  nb_title    VARCHAR(120) NOT NULL,
  nb_content  TEXT NOT NULL,
  nb_pinned   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  nb_popup    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  nb_time     DATETIME NOT NULL,
  nb_revised  DATETIME NOT NULL,
  PRIMARY KEY (nb_id),
  KEY idx_time (nb_time DESC),
  CONSTRAINT fk_nb_writer FOREIGN KEY (nb_writer) REFERENCES users(acc_number) ON DELETE CASCADE
) ENGINE=InnoDB;
INSERT INTO notice_board (nb_writer,nb_title,nb_content,nb_pinned,nb_popup,nb_time,nb_revised) SELECT acc_number, '나무장터 오픈 안내', '나무장터가 오픈했습니다.\n중고거래를 자유롭게 이용해 보세요.\n<b>안전거래</b> 부탁드립니다.', 1, 0, NOW(), NOW() FROM users WHERE acc_group=1 LIMIT 1;

DROP TABLE IF EXISTS visitor_log;
CREATE TABLE visitor_log (
  vl_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vl_ip      VARCHAR(45) NOT NULL,
  vl_cc      VARCHAR(2) NOT NULL DEFAULT '',
  vl_referer VARCHAR(500) NOT NULL DEFAULT '',
  vl_page    VARCHAR(200) NOT NULL DEFAULT '',
  vl_ua      VARCHAR(255) NOT NULL DEFAULT '',
  vl_time    DATETIME NOT NULL,
  PRIMARY KEY (vl_id),
  KEY idx_time (vl_time DESC),
  KEY idx_cc (vl_cc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO users (acc_id, acc_nick, acc_pw, acc_email, acc_group, acc_status, acc_onelogin, registertime)
SELECT 'admin', 'Admin', '76601eebb929777e64fe77c9de51ff4d2746b445b81808d2f1590c65eea6399a', 'admin@joongna.local', 1, 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE acc_id='admin');
INSERT INTO ip_security (is_user, is_check_ip, is_check_cc, is_allow_cc)
SELECT acc_number, 0, 0, 'KR' FROM users WHERE acc_id='admin'
AND NOT EXISTS (SELECT 1 FROM ip_security);