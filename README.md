# Joongna Marketplace Implementation
Q-stone (qstone@kw.ac.kr)

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
- 리버스 프록시 구조
- 보안 요소 충실히 구현 (CSRF 방지, XSS 방지, SQLi 방지 (prepared Stmt), 파일 업로드 취약점 방지, IDOR 방지 등등)

## 설치

### 요구사항

- Docker 24+
- docker compose v2

### 실행

```bash
git clone https://github.com/Q-stone/whs_joongna_platform_implementation.git
cd whs_joongna_platform_implementation
docker compose up -d
```

### 접속

| 서비스 | URL |
|--------|-----|
| 플랫폼 | http://localhost:8082 |

### 초기 관리자 계정

```
ID: admin
PW: admin1234!!
```

관리자 계정은 TOTP가 설정되어 있어야 합니다. 마이페이지에서 TOTP 설정 후 관리자 페이지 접근 가능합니다.  

### 포트 변경

`docker-compose.yml`에서 `8082:8082`를 원하는 포트로 변경:

```yaml
ports:
  - "80:8082"
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
