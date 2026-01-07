# 발주·재고·입고 통합 관리 시스템

Apache + PHP 7.4 + MySQL 8.0 + PDO 기반 예제 프로젝트입니다.

## 구성

- 대시보드: 부족 품목 TOP 5, 최근 발주 5건, 최근 입고 5건
- 분석 대시보드: 기간별 발주/입고 현황(KPI + 차트) 및 드릴다운 리스트(/dashboard.php)
- 일정관리(캘린더): 발주 신청일/입고완료일을 월간 캘린더로 조회(/schedule.php)
- 품목 조회 → 부족 품목 식별 → 발주 등록 → 발주 조회 → 입고 처리 → 재고 반영
- role 기반 접근 제어(user/admin)
- 발주 생성/입고 처리는 트랜잭션 처리
- (추가) 사용자 → 거래처 소속(supplier_id) 기반 데이터 제한(일반 사용자)
 - (추가) 라이트/다크 모드 토글(UI 테마 저장: localStorage)

## 설치/실행

1) DB 생성

- DB 이름 예시: `homepage_demo` (원하는 이름으로 변경 가능)

2) 스키마/시드 반영

- `schema.sql` 실행
- `seed.sql` 실행

### 기존 DB를 유지하고 업그레이드하는 경우(중요)

이미 운영 중인 DB가 있고 `schema.sql`을 다시 import하지 않으려면, 아래 마이그레이션을 먼저 실행하세요.

- `migrate_add_supplier_id.sql` 실행
- (추가) 공지사항 기능을 사용하려면 `migrate_add_notices.sql` 실행
- (추가) 공지사항 중요/일반 구분을 사용하려면 `migrate_add_notice_priority.sql` 실행
- (추가) 공지 작성자 표시를 사용하려면 `migrate_add_notice_author_id.sql` 실행
- (추가) 1:1 문의 기능을 사용하려면 `migrate_add_inquiries.sql` 실행
- (추가) 1:1 문의 답변 기능을 사용하려면 `migrate_add_inquiry_messages.sql` 실행
- (추가) 동일 SKU를 거래처별로 사용하려면 `migrate_adjust_items_sku_unique.sql` 실행
- (추가) 일정관리(캘린더) 조회 성능을 올리려면 `migrate_add_schedule_indexes.sql` 실행

3) DB 접속 정보 설정

- 설정 파일: includes/config.php
- 기본값:
  - DB_HOST=127.0.0.1
  - DB_NAME=homepage_demo
  - DB_USER=root
   - DB_PASS=0000

4) 서브폴더 설치 시(선택)

- 기본적으로 `DOCUMENT_ROOT` 기준으로 자동 감지합니다.
- 예: `http://localhost/homepage` 처럼 설치했고 자동 감지가 어려운 환경이면 환경변수 `APP_BASE=/homepage`로 오버라이드하세요.

5) 접속

- `http://localhost/` 또는 `http://localhost/homepage/`

## 기본 계정(시드)

- 관리자
  - email: `admin@example.com`
  - password: `password`

- 일반 사용자
  - email: `user1@example.com`
  - password: `password`

> 일반 사용자는 거래처 소속이 필수이며, 프로필에서 변경할 수 있습니다.

## 시나리오

1) 관리자 로그인
2) 관리자 메뉴에서 거래처/품목/재고 확인
   - /admin/suppliers.php
   - /admin/items.php
   - /admin/inventory.php
3) 사용자(또는 관리자)로 품목 목록에서 부족 품목 확인
   - /items.php (부족 표시 확인)
4) 발주 등록
   - /po_create.php
5) 발주 조회/상세 확인
   - /po_list.php → /po_view.php
6) 입고 처리
   - /receipt_create.php (발주 연결 선택 가능)
7) 입고 조회/상세 확인
   - /receipt_list.php → /receipt_view.php
8) 품목 상세에서 재고 증가 확인
   - /item_view.php

## 분석 대시보드

- 화면: `/dashboard.php`
   - 기간(range)별 발주/입고 요약(KPI) + 차트(도넛/막대) + 하단 근거 리스트 드릴다운
   - 관리자(admin): 전체/거래처 선택 가능
   - 일반 사용자(vendor): 항상 본인 거래처 데이터만 노출(supplier_id 강제)
- API(내부 호출)
   - `/api_dashboard_summary.php` : KPI + 도넛 데이터
   - `/api_dashboard_bar.php` : 막대 그래프 데이터(admin: 거래처 TOP / vendor: 품목 TOP)
   - `/api_dashboard_list.php` : 드릴다운 근거 리스트
- 주요 쿼리 파라미터
   - `range`: `7d` | `30d` | `thisMonth` | `lastMonth`
   - `supplier_id`: 관리자만 의미 있음(일반 사용자는 무시됨)
   - `status`: `complete` | `partial` | `none` (드릴다운)
   - `item_id`: 품목 드릴다운

## UI 테마(라이트/다크)

- 상단바의 “라이트 모드/다크 모드” 버튼으로 테마를 토글합니다.
- 선택값은 `localStorage.theme`에 저장되며, 라이트 모드는 옅은 파랑 톤이 섞인 배경 팔레트로 적용됩니다.

## 일정관리(캘린더)

- 화면: `/schedule.php`
   - 발주 신청일: `purchase_orders.created_at` 기준
   - 입고완료일: `receipts.created_at` 기준(현재 스키마에 `received_at` 컬럼이 없으므로)
   - 일반 사용자(vendor): 본인 거래처 데이터만 노출(supplier_id 강제)
   - 이벤트 선택 시 빠른 이동
      - 발주: 발주 상세(/po_view.php) + 입고등록(/receipt_create.php)
      - 입고: 입고 상세(/receipt_view.php)
- API(내부 호출)
   - `/api_schedule_events.php?start=YYYY-MM-DD&end=YYYY-MM-DD`
      - start/end 범위 기반으로 발주/입고 이벤트를 반환합니다(최대 45일 제한).

## 프로필(회원정보 수정)

- /profile.php
- 이름/비밀번호/거래처 소속을 수정할 수 있습니다.

## 테스트 항목 (TC-001 ~ TC-010)

- TC-001 로그인 성공 후 세션 재생성(session_regenerate_id) 확인
- TC-002 로그인 실패 시 에러 메시지 표시 확인
- TC-003 권한: 일반 사용자로 /admin/* 접근 시 차단 확인
- TC-004 품목 검색: 품목명/sku 검색 및 거래처 필터 동작 확인
- TC-005 부족 표시: on_hand ≤ min_stock 인 품목이 "부족"으로 표시되는지 확인
- TC-006 발주 생성 트랜잭션: purchase_orders + purchase_order_items가 함께 생성되는지 확인
- TC-007 발주 조회 필터: 기간/거래처/키워드(품목명) 필터 동작 확인
- TC-008 입고 처리 트랜잭션: receipts + receipt_items + inventory 증가가 함께 반영되는지 확인
- TC-009 대시보드: 부족 TOP5/최근 발주/최근 입고가 표시되는지 확인
- TC-010 XSS 방지: 테이블/상세 출력이 e(htmlspecialchars) 처리되는지 확인

추가 테스트 시나리오 문서:

- `DASHBOARD_TEST_SCENARIOS.md`

## 복수 품목 발주(카트형) 테스트 시나리오

- 서로 다른 품목 3개를 카트에 담고 수량 변경 후 “발주 제출”
- 동일 품목을 2번 추가했을 때 qty가 합산되는지 확인
- 예외 처리
   - supplier 미선택 상태에서 add/submit 시 에러
   - 품목이 비어있을 때 submit 시 에러
   - qty=0 또는 음수 입력 시 에러

## 세션 카트 구조(간단)

- `$_SESSION['po_cart_supplier_id']`: 선택된 거래처 ID
- `$_SESSION['po_cart']`: `[ item_id => qty, ... ]`

## 트랜잭션 적용 위치

- 발주 제출: classes/PurchaseOrder.php 의 `PurchaseOrder::create()`
- 입고 처리: classes/Receipt.php 의 `Receipt::create()`

## 테이블

- users, suppliers, items, inventory, purchase_orders, purchase_order_items, receipts, receipt_items

## 주의

- 본 프로젝트는 예제 목적이며 CSRF/세분화된 부분입고/정교한 상태머신 등은 범위 밖으로 단순화되어 있습니다.
