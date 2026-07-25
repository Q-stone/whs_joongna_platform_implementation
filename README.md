# 나무장터 (Joongna Marketplace)

중고 거래 플랫폼. PHP 8.2 + MariaDB + Apache + Nginx + Docker.

## 기능

- 회원가입 / 로그인 / TOTP 2FA
- 상품 등록·수정·삭제 (사진·동영상 업로드)
- 상품 기반 1:1 채팅 (구매자↔판매자)
- 가상 지갑 (충전·송금·구매)
- 거래 평가 (리뷰·신뢰도)
- 관리자 패널 (사용자·신고·로그·방화벽·공지·상품·채팅방 관리)
- ECDH X25519 + AES-256-GCM E2E 암호화
- IP/UA/TOR 차단 (방화벽)
- SPA 라우팅 (페이지 전환 간 암호화)

## 설치

### 요구사항

- Docker 24+, docker compose v2

### 환경 설정

```bash
cp .env.example .env
# .env 파일을 열어 비밀번호 등 보안 값 변경 (필수)
```

### 실행

```bash
docker compose up -d
```

접속: http://localhost:8082

### 관리자 계정 생성

최초 실행 시 메인 페이지에 마스터 관리자 생성 폼이 나타납니다 (localhost/사설망 전용).

1. TOTP 시크릿 생성 버튼 클릭
2. QR 코드를 Authenticator 앱으로 스캔
3. 6자리 TOTP 코드 입력 + ID/비밀번호/이메일 설정
4. 생성 완료 후 로그인 → 마이페이지에서 TOTP 상태 확인

## 보안

| 항목 | 구현 |
|------|------|
| 비밀번호 | SHA-256 + 이중 솔트 (`.env` 환경변수) |
| 세션 | HttpOnly + SameSite=Strict + regenerate_id |
| API 암호화 | ECDH X25519 + AES-256-GCM |
| CSRF | 세션 안정 토큰 |
| XSS | htmlspecialchars + purify_rich |
| SQLi | Prepared Statements |
| IDOR | 판매자/소유자 검증 |
| 파일 업로드 | 확장자·MIME 검증 + 난수 파일명 |
| 방화벽 | IP·UA·TOR 차단 |
| IaC | `.env`로 모든 비밀값 분리 (Git 미포함) |

## 프로덕션 이메일

기본: 이메일 인증 없이 회원가입 즉시 활성. 이메일 발송 필요 시 msmtp 설정:

```bash
docker exec joongna_apache sh -c "cat > /etc/msmtprc << 'EOF'
account default
host smtp.gmail.com
port 587
from your-email@gmail.com
auth on
user your-email@gmail.com
password your-app-password
tls on
tls_starttls on
EOF"
```

## 포트 변경

`.env`에서 `APP_PORT` 수정:

```
APP_PORT=80
```

## 구조

```
src/
├── index.php              # 프론트 컨트롤러 (SPA 셸)
├── service_bridge.php     # 비동기 API
├── pages/                 # 페이지 템플릿
├── phpresources/          # PHP 클래스·함수
│   ├── Bridge/            # Controller, Mailer
│   ├── Database.php       # MySQLi 래퍼
│   ├── Security.php       # CSRF·TOTP·정제
│   ├── Logger.php         # 감사 로깅
│   ├── E2EEncrypt.php     # ECDH+AES 암호화
│   └── tor_ip.php         # TOR IP 목록
├── cdn/                   # 정적 파일
│   ├── css/style.css
│   └── js/
│       ├── common.js      # 핵심 프레임워크 + SPA 라우터
│       └── chat.js        # 채팅 폴링
└── uploads/               # 업로드 미디어
docker/
├── nginx/                 # Nginx 설정
├── apache/                # Apache + PHP Dockerfile
└── mariadb/               # init.sql 스키마
```

## 라이선스

MIT
