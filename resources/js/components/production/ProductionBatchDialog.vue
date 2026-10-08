<template>
<v-dialog
    v-model="open"
    max-width="620"
    persistent
>
  <v-card
      rounded="lg"
      class="app-dialog-card production-dialog-card"
  >
    <v-card-title class="app-dialog-header d-flex align-center justify-space-between">
      <div>{{ editingBatch ? '생산 기록 수정' : '생산' }} - {{ product?.name || '-' }}</div>
    </v-card-title>
    <v-card-text class="app-dialog-body">
      <div class="production-dialog-overview">
        <div><span>현재 생산</span><strong>{{ Number(product?.production || 0) }}개</strong></div>
        <div><span>생산 기록</span><strong>{{ product?.batches?.length || 0 }}건</strong></div>
        <div><span>확인 상태</span><strong>{{ product?.production_confirmed ? '완료' : '미확인' }}</strong></div>
      </div>
      <div class="production-dialog-guide overview-guide">생산 기록을 확인하고 새 생산량을 추가하거나 기존 기록을 수정할 수 있습니다.</div>
      <section class="production-record-section">
        <div class="production-dialog-section-title">오늘 생산 기록</div>
        <div v-if="!product?.batches?.length" class="production-friendly-empty">
          <v-icon icon="mdi-clipboard-text-outline" size="22" />
          <div><strong>아직 생산 기록이 없습니다.</strong><span>아래에서 첫 생산 기록을 등록해 주세요.</span></div>
        </div>
        <div v-for="batch in product?.batches || []" :key="batch.id" class="production-record-card" :class="{ 'record-editing': editingBatch?.id === batch.id }">
          <div class="production-record-main">
            <strong>{{ batch.quantity }}개</strong>
            <span>{{ new Date(batch.created_at).toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' }) }}</span>
          </div>
          <div class="production-record-workers">작업자: {{ batch.workers?.map(worker => worker.name).join(', ') || '기록 없음' }}</div>
          <div v-if="batch.note" class="production-record-note">{{ batch.note }}</div>
          <div class="production-record-actions">
            <v-btn size="small" variant="text" prepend-icon="mdi-pencil-outline" @click="editBatch(batch)">수정</v-btn>
            <v-btn size="small" variant="text" prepend-icon="mdi-delete-outline" @click="askDelete(batch)">삭제</v-btn>
          </div>
        </div>
      </section>
      <v-divider class="my-4" />
      <div ref="editorSection" class="production-record-editor">
        <v-alert v-if="editingBatch" type="info" variant="tonal" density="compact" class="mb-3">
          <strong>수정 중인 기록 · {{ editingBatch.quantity }}개</strong>
          <div>기존 값을 변경한 후 수정 내용을 저장해 주세요.</div>
        </v-alert>
      <div class="production-dialog-section-title">{{ editingBatch ? '생산 기록 수정' : '새 생산 기록' }}</div>
      <div class="production-dialog-guide">오늘 생산한 수량을 입력해 주세요.</div>
      <v-number-input
          v-model="form.quantity"
          label="생산 수량"
          variant="outlined"
          density="compact"
          :min="1"
          class="primary-quantity-input"
      />
      <v-select
        v-model="form.workerIds"
        :items="workers"
        item-title="name"
        item-value="id"
        label="작업자 · 필수"
        :error-messages="workerError ? [workerError] : []"
        @update:model-value="workerError = ''"
        variant="outlined"
        multiple
        chips
        clearable
        no-data-text="선택할 수 있는 작업자가 없습니다"
      />
      <v-divider class="my-3" />
      <div class="production-dialog-section-title">레시피 변경</div>
      <div class="production-dialog-guide">레시피와 다르게 작업한 경우 변경 내용을 기록해 주세요.</div>
      <v-checkbox
          v-model="form.recipeDeviated"
          label="레시피 변경"
          density="compact"
      />
      <v-textarea
          v-if="form.recipeDeviated"
          v-model="form.recipeDeviationNote"
          label="달라진 작업 내용"
          variant="outlined"
          rows="2"
      />
      <v-divider class="my-3" />
      <div class="production-dialog-section-title">추천 생산량 참고</div>
      <div class="production-dialog-guide">추천 정보가 없더라도 참고 여부를 기록할 수 있습니다.</div>
      <v-checkbox
          v-model="form.recommendationReferenced"
          label="추천 생산량 참고"
          density="compact"
          hide-details
      />
      <div v-if="form.recommendationReferenced" class="production-dialog-field-help">
        추천 생산량을 확인하고 생산 수량을 결정한 경우입니다.
      </div>
      <!-- 참고 여부와 추천 범위 이탈 사유는 서로 다른 정보입니다.
           참고 체크 여부에 관계없이 이탈 사유를 선택적으로 기록할 수 있도록 유지합니다. -->
      <v-text-field
          v-model="form.recommendationDeviationReason"
          label="추천 범위와 다르게 생산한 경우의 이유 · 선택"
          hint="추천 범위를 벗어나 생산한 경우에만 입력해 주세요."
          persistent-hint
          variant="outlined"
      />
      <div class="production-dialog-guide">
        생산 중 특이사항, 작업 변경 내용 외에 다음 근무자가 참고할 내용을 적어 주세요.
      </div>
      <v-textarea
          v-model="form.note"
          label="메모 · 선택"
          variant="outlined"
          density="compact"
          rows="2"
      />
      <div v-if="!product?.recipe" class="recipe-empty"><v-icon icon="mdi-book-open-variant-outline" size="18"/><div><strong>등록된 레시피가 없습니다.</strong><span>레시피가 필요한 경우 제품 관리에서 등록해 주세요.</span></div></div>
      </div>
      <v-divider class="my-3" />
      <div class="production-dialog-section-title">오늘 생산하지 않은 경우</div>
      <div class="production-dialog-guide">
        실제 생산이 없었던 날은 사유를 선택해 0개로 확인합니다. 목록에 맞는 사유가 없으면 직접입력을 선택해 주세요.
      </div>
      <v-select
          v-model="zeroReason"
          :items="displayZeroReasons"
          item-title="title"
          item-value="value"
          label="생산 0개 사유"
          variant="outlined"
          density="compact"
          clearable
      />
      <v-text-field
          v-if="zeroReason === 'other'"
          v-model="zeroReasonText"
          label="사유 직접입력"
          variant="outlined"
          density="compact"
      />
      <v-btn
          variant="outlined"
          block
          :disabled="!canConfirmZero || saving"
          @click="confirmZeroOpen = true"
      >
        생산 0개 확인
      </v-btn>
    </v-card-text>
    <v-card-actions class="app-dialog-footer px-4 pb-4">
      <v-btn variant="text" :disabled="saving" @click="requestClose">닫기</v-btn>
      <v-spacer />
      <v-btn v-if="editingBatch" variant="text" :disabled="saving" @click="cancelEdit">수정 취소</v-btn>
      <v-btn variant="flat" :loading="saving" :disabled="saving" @click="askSave">{{ editingBatch ? '수정 내용 저장' : '생산 기록 저장' }}</v-btn>
    </v-card-actions>
  </v-card>
</v-dialog>
<ConfirmDialog
    v-model="saveConfirmOpen"
    :title="editingBatch ? '생산 기록 수정 확인' : '생산 기록 등록 확인'"
    :message="saveConfirmMessage"
    :loading="saving"
    @confirm="save"
/>
<ConfirmDialog
    v-model="deleteConfirmOpen"
    title="생산 기록 삭제"
    message="이 생산 기록을 삭제하시겠습니까? 이미 이월과 연결된 기록은 삭제할 수 없습니다."
    :loading="saving"
    @confirm="deleteBatch"
/>
<ConfirmDialog
    v-model="confirmZeroOpen"
    title="생산 0개 확인"
    message="오늘 이 제품을 생산하지 않은 것으로 확정하시겠습니까? 선택한 사유는 추천 분석에서 일반 수요와 구분해 사용합니다."
    :loading="saving"
    @confirm="saveZero"
/>
<ConfirmDialog
    v-model="confirmClose"
    title="작성 취소"
    message="작성 중인 내용이 있습니다. 닫으시겠습니까?"
    @confirm="forceClose"
/>
</template>

<script setup>
import {
    computed,
    reactive,
    ref,
    watch,
} from 'vue';
import ConfirmDialog from '../common/ConfirmDialog.vue';
const props = defineProps({
    modelValue: Boolean,
    product: Object,
    storeId: Number,
    workDate: String,
    workers: {
        type: Array,
        default: () => [],
    },
    zeroReasons: {
        type: Array,
        default: () => [],
    },
});
const emit = defineEmits(['update:modelValue', 'saved', 'error']);
const saving = ref(false);
const saveConfirmOpen = ref(false);
const workerError = ref('');
const editorSection = ref(null);
const confirmClose = ref(false);
const confirmZeroOpen = ref(false);
const deleteConfirmOpen = ref(false);
const editingBatch = ref(null);
const deletingBatch = ref(null);
const zeroReason = ref(null);
const zeroReasonText = ref('');
const form = reactive({
    quantity: 1,
    workerIds: [],
    recipeDeviated: false,
    recipeDeviationNote: '',
    recommendationReferenced: false,
    recommendationDeviationReason: '',
    note: '',
});
const open = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

// 기존 other 코드는 호환성을 위해 유지하고 화면에서는 직접입력으로 안내합니다.
const displayZeroReasons = computed(() => props.zeroReasons.map((reason) => ({
    ...reason,
    title: reason.value === 'other' ? '직접입력' : reason.title,
})));

const saveConfirmMessage = computed(() => editingBatch.value
    ? `생산 수량 ${editingBatch.value.quantity}개 → ${form.quantity}개로 수정하시겠습니까? 작업자 변경도 함께 저장됩니다.`
    : `생산 ${form.quantity}개를 선택한 작업자와 함께 등록하시겠습니까?`);

// 입력 검증을 통과한 뒤에만 공통 확인창을 열어 실제 저장을 승인받습니다.
function askSave() {
  if (!props.product?.id || !Number.isInteger(Number(form.quantity)) || Number(form.quantity) < 1) {
    emit('error', '올바른 생산 수량을 입력해 주세요.');
    return;
  }
  if (!form.workerIds.length) {
    workerError.value = '작업자를 선택해 주세요.';
    return;
  }
  if (form.recipeDeviated && !form.recipeDeviationNote.trim()) {
    emit('error', '변경한 작업 내용을 입력해 주세요.');
    return;
  }
  saveConfirmOpen.value = true;
}
const canConfirmZero = computed(() => (
    Boolean(zeroReason.value)
    && (zeroReason.value !== 'other' || Boolean(zeroReasonText.value.trim()))
));
// 다이얼로그가 열릴 때 이전 제품의 입력값이 남지 않도록 초기화합니다.
watch(() => props.modelValue, (value) => {
  if (value) reset();
});
// 현재 입력값을 새 생산 기록의 기본값으로 되돌립니다.
function reset() {
  editingBatch.value = null;
  workerError.value = '';
  saveConfirmOpen.value = false;
  deletingBatch.value = null;
  zeroReason.value = null;
  zeroReasonText.value = '';
  form.quantity = 1;
  form.workerIds = [];
  form.recipeDeviated = false;
  form.recipeDeviationNote = '';
  form.recommendationReferenced = false;
  form.recommendationDeviationReason = '';
  form.note = '';
}
// 사용자가 입력한 내용이 있는지 확인해 실수로 닫히는 것을 방지합니다.
function isDirty() {
  return form.quantity !== 1
      || form.workerIds.length > 0
      || form.recipeDeviated
      || Boolean(form.recipeDeviationNote)
      || form.recommendationReferenced
      || Boolean(form.recommendationDeviationReason)
      || Boolean(form.note)
      || Boolean(zeroReason.value)
      || Boolean(zeroReasonText.value);
}
// 작성 내용이 있으면 확인창을 거친 뒤 닫습니다.
function requestClose() {
  if (saving.value) return;
  if (isDirty()) {
    confirmClose.value = true;
    return;
  }

  forceClose();
}
// 확인을 마친 뒤 생산 입력 다이얼로그를 닫습니다.
function forceClose() {
  confirmClose.value = false;
  open.value = false;
}
// 기존 생산 기록을 수정할 수 있도록 해당 값을 입력 폼에 불러옵니다.
function editBatch(batch) {
  editingBatch.value = batch;
  form.quantity = batch.quantity;
  form.note = batch.note || '';
  form.workerIds = (batch.workers || []).map(worker => worker.id);
  form.recipeDeviated = Boolean(batch.recipe_deviated);
  form.recipeDeviationNote = batch.recipe_deviation_note || '';
  workerError.value = '';
  editorSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
// 생산 수정 모드를 끝내고 신규 입력 상태로 돌아갑니다.
function cancelEdit() {
  editingBatch.value = null;
  form.quantity = 1;
  form.note = '';
  form.workerIds = [];
  form.recipeDeviated = false;
  form.recipeDeviationNote = '';
  workerError.value = '';
}
// 삭제할 생산 기록을 기억하고 중요 작업 확인창을 엽니다.
function askDelete(batch) {
  deletingBatch.value = batch;
  deleteConfirmOpen.value = true;
}
// 서버 검증 뒤 생산 기록을 Soft Delete하고 성공 시 목록을 다시 조회합니다.
async function deleteBatch() {
  if (!deletingBatch.value) return;
  saving.value = true;
  try {
    await window.axios.delete(`/tillwhite/api/production-management/batches/${deletingBatch.value.id}`, {
      data: {
        memo: null
      }
    });
    deleteConfirmOpen.value = false;
    open.value = false;
    emit('saved', '생산 기록을 삭제했습니다.');
  } catch (error) {
    emit('error', error.response?.data?.message || '생산 기록을 삭제하지 못했습니다.');
  } finally {
    saving.value = false;
  }
}
// 생산하지 않은 날은 0과 사유를 명시적으로 확인해 미입력과 구분합니다.
async function saveZero() {
  saving.value = true;
  try {
    await window.axios.post('/tillwhite/api/production-management/zero-production', {
      store_id: props.storeId,
      product_id: props.product.id,
      work_date: props.workDate,
      reason: zeroReason.value,
      reason_text: zeroReason.value === 'other' ? zeroReasonText.value.trim() : null,
      note: form.note || null,
    });
    confirmZeroOpen.value = false;
    open.value = false;
    emit('saved', '생산 0개를 확인했습니다.');
  } catch (error) {
    emit('error', error.response?.data?.message || '생산 0개를 확인하지 못했습니다.');
  } finally {
    saving.value = false;
  }
}
// 서버 확인이 끝난 생산 기록만 성공 처리하고 실패 시 입력값을 유지합니다.
async function save() {
  if (!props.product?.id || !form.quantity || !form.workerIds.length) return;
  if (form.recipeDeviated && !form.recipeDeviationNote.trim()) {
    emit('error', '변경한 작업 내용을 입력해 주세요.');
    return;
  }
  saving.value = true;
  try {
    if (editingBatch.value) {
      await window.axios.put(`/tillwhite/api/production-management/batches/${editingBatch.value.id}`, {
        quantity: form.quantity, note: form.note || null, lock_version: editingBatch.value.lock_version,
        workers: form.workerIds.map(userId => ({ user_id: userId })),
      });
      saveConfirmOpen.value = false;
      open.value = false;
      emit('saved', '생산 기록을 수정했습니다.');
    } else {
      await window.axios.post('/tillwhite/api/production-management/batches', {
        store_id: props.storeId,
        product_id: props.product.id,
        work_date: props.workDate,
        quantity: form.quantity,
        recipe_deviated: form.recipeDeviated,
        recipe_deviation_note: form.recipeDeviationNote || null,
        note: form.note || null,
        recommendation_referenced: form.recommendationReferenced,
        recommendation_deviation_reason: form.recommendationDeviationReason || null,
        workers: form.workerIds.map((userId) => ({
          user_id: userId,
          process_type: 'all',
        })),
      });
      saveConfirmOpen.value = false;
      open.value = false;
      emit('saved', '생산 기록을 저장했습니다.');
    }
  } catch (error) {
    emit('error', error.response?.data?.message || '생산 기록을 저장하지 못했습니다.');
  }
  finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.production-record-section { margin-bottom: 12px; }
.production-record-card { border: 1px solid rgba(var(--v-theme-on-surface), .13); border-radius: 10px; padding: 12px; margin-top: 9px; }
.production-record-card.record-editing { border-color: rgb(var(--v-theme-primary)); background: rgba(var(--v-theme-primary), .06); }
.production-record-main { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.production-record-main strong { font-size: .95rem; }
.production-record-main span, .production-record-workers, .production-record-note { font-size: .75rem; color: rgba(var(--v-theme-on-surface), .68); }
.production-record-workers, .production-record-note { margin-top: 6px; }
.production-record-actions { display: flex; justify-content: flex-end; border-top: 1px solid rgba(var(--v-theme-on-surface), .08); margin-top: 9px; padding-top: 5px; }
.production-friendly-empty { display: flex; align-items: center; gap: 10px; padding: 16px 12px; border: 1px dashed rgba(var(--v-theme-on-surface), .18); border-radius: 10px; }
.production-friendly-empty strong, .production-friendly-empty span { display: block; font-size: .75rem; }
.production-friendly-empty span { color: rgba(var(--v-theme-on-surface), .6); margin-top: 3px; }
.production-record-editor { scroll-margin-top: 72px; }

.overview-guide {
    margin-bottom: 14px;
}

.primary-quantity-input :deep(input) {
    font-size: 0.9rem;
    font-weight: 650;
    font-variant-numeric: tabular-nums;
}

.recipe-empty {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    margin: 4px 0 10px;
    padding: 9px 10px;
    border-radius: 9px;
    background: rgba(var(--v-theme-on-surface), 0.04);
}

.recipe-empty strong,
.recipe-empty span {
    display: block;
}

.recipe-empty strong {
    font-size: 0.72rem;
    font-weight: 600;
}

.recipe-empty span {
    margin-top: 2px;
    font-size: 0.65rem;
    color: rgba(var(--v-theme-on-surface), 0.58);
}
</style>
