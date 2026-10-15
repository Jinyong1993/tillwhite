<template>
  <div class="inventory-dialog inventory-dialog__waste-rate-tab">
    <div class="inventory-dialog__guide">
      <div class="inventory-dialog__guide-heading">
        <v-icon icon="mdi-information-outline" size="18" />
        <strong>폐기율 안내</strong>
      </div>

      <p class="inventory-dialog__form-help">
        폐기율은 최초 생산일 기준으로 계산하며,
        이월 재고를 나중에 폐기해도 최초 생산일에 반영됩니다.
      </p>

      <div class="inventory-dialog__formula">
        <span>계산식</span>
        <strong>폐기 수량 ÷ 생산 수량 × 100</strong>
      </div>

      <p class="inventory-dialog__example">
        <strong>예시</strong>
        10/3 생산 10개 중 2개를 10/5에 폐기 → 10/3 폐기율 20%
      </p>
    </div>
  </div>
</template>

<style scoped>
/* 생산탭과 동일한 기본 글씨 */
.inventory-dialog__waste-rate-tab {
  font-size: 12px;
}

/* 폐기율 안내 카드 */
.inventory-dialog__guide {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 12px;
  background: transparent;
  text-align: left;
}

/* 생산 관리 제목과 동일한 글씨 */
.inventory-dialog__guide-heading {
  display: flex;
  align-items: center;
  gap: 7px;
}

.inventory-dialog__guide-heading strong {
  font-size: 13px;
  font-weight: 600;
}

/* 생산탭 안내 문구와 동일한 글씨 */
.inventory-dialog__form-help {
  margin: 0;
  font-size: 11px;
  line-height: 1.5;
  opacity: 0.7;
  text-align: left;
}

/* 계산식 */
.inventory-dialog__formula {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.inventory-dialog__formula span {
  font-size: 11px;
  opacity: 0.7;
}

.inventory-dialog__formula strong {
  font-size: 11px;
  font-weight: 600;
}

/* 예시 */
.inventory-dialog__example {
  margin: 0;
  font-size: 11px;
  line-height: 1.5;
  opacity: 0.7;
  text-align: left;
}

.inventory-dialog__example strong {
  margin-right: 6px;
  font-weight: 600;
}

/* 모바일 대응 */
@media (max-width: 600px) {
  .inventory-dialog__guide {
    padding: 12px;
  }
}
</style>