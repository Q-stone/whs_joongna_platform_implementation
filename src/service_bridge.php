<?php
declare(strict_types=1);
/**
 * service_bridge.php - 비동기 API 컨트롤러 (객체지향)
 *
 * 아키텍처:
 *  - Bridge\Controller 클래스가 모든 mode 라우팅 담당.
 *  - 각 모드는 컨트롤러의 메서드로 매핑 (switch/case 절차체 스타일 배제).
 *  - 공통 보안 절차는 메서드에서 명시적으로 호출 (CSRF guard / 인증 / 휴면 / 로깅).
 *  - 모든 DB 처리는 Database::run() Prepared Statement.
 *  - 모든 입력 검증/이스케이핑/욕설 필터 적용.
 *  - 모든 요청/처리결과는 Logger::request()로 기록.
 *  - 응답은 JSON. __new_csrf로 클라이언트 동기화(세션 안정 토큰이라 동일값).
 *
 * (참고: 게시판 service_bridge.php 의 보안 절차를 계승하되, 구조를 클래스로 재구성)
 */
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_secure','0');
ini_set('session.cookie_samesite','Strict');
session_start();
const main_index_files = 1024;
$system_v = [];
$sql = '';

include_once __DIR__ . '/phpresources/resource_check.php';
include_once __DIR__ . '/phpresources/is_valid_user.php';
require __DIR__ . '/phpresources/language/language_kor.php';

(new Bridge\Controller())->dispatch()->respond();