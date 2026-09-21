# Till White 데모 완성본

업로드된 Till White 프로젝트와 대화에서 확정한 설계를 기준으로 기능을 확장한 데모입니다.
로그인 화면의 400px 카드, `Till White` 제목, 회색 본문 배경을 모든 업무 화면의 기준 디자인으로 유지했습니다.

## 구현된 화면

- 로그인 / 세션 인증 / 권한 기반 메뉴
- 메인 / 사용자 정보 / 빠른 메뉴
- 생산·폐기·로스: 입력, 조회, 삭제, 합계 통계, 범위 제한, 감사 로그
- 근무: 스케줄 조회·저장 API, 휴가 신청, 희망휴무 신청, 관리자 승인/반려
- 제품: 제품/카테고리/가격 조회, 제품 등록/활성화, 레시피·재료·공정 등록
- 매출: 일 매출 조회/등록
- 직원: 직원 조회/등록/상태 관리 API
- 점포: 조회/등록
- 시스템: 설정, 역할·권한, 직급 조회 및 설정/역할 권한 API
- 감사 로그: 권한 범위 조회
- 본사/점포/부서/권한별 서버 접근 제한
- 14개 기본 사용자 Seeder 유지
- 데모 제품, 가격, 근무코드, 당일 스케줄, 생산 기록, 시스템 설정 Seeder 추가

## 실행 전 권장 명령

Windows PowerShell에서 프로젝트 폴더로 이동한 뒤 기존 프로젝트와 동일한 PHP/Node 환경을 사용합니다.

```powershell
composer install
npm.cmd install
php artisan migrate:fresh --seed
npm.cmd run build
composer run dev
```

개발 DB를 초기화하므로 기존 데이터가 필요한 프로젝트에 `migrate:fresh`를 실행하지 마세요. 이 ZIP은 별도 데모 복사본으로 실행하는 것을 권장합니다.

## 중요

- `.env`는 업로드본을 그대로 포함하므로 외부 공유 전 반드시 제거하거나 민감값을 교체하세요.
- 실제 권한 판정은 Vue가 아니라 Laravel API에서 수행하도록 구성했습니다.
- 업로드본에서 DB 스키마와 맞지 않던 과거 `Product`, `AuthController`, `StoreProduct`, `WasteRecord` 흔적을 현재 32개 migration 설계 기준으로 정리했습니다.
- 이 실행 환경에는 업로드된 Windows `node_modules`가 호환되지 않았고 네트워크 패키지 재설치가 시간 제한되어 Vite production build까지는 검증하지 못했습니다. PHP 소스 문법 검사와 Laravel route 등록은 검증했습니다.
