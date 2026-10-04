<template>
<v-dialog v-model="open" max-width="620" :persistent="saving">
  <v-card rounded="lg" class="app-dialog-card">
    <v-card-title class="app-dialog-header d-flex align-center justify-space-between">
      <div>
        <div>생산</div>
        <div class="app-supporting-text text-medium-emphasis mt-1">{{ product?.name || '-' }}의 생산 수량과 작업 내용을 기록합니다.</div>
      </div>
      <v-btn icon="mdi-close" size="small" variant="text" :disabled="saving" @click="requestClose" />
    </v-card-title>
    <v-card-text class="app-dialog-body">
      <div v-if="product?.batches?.length" class="mb-4">
        <div class="text-subtitle-2 mb-2">오늘 생산 기록</div>
        <v-list density="compact" border rounded>
          <v-list-item v-for="batch in product.batches" :key="batch.id" :title="`${batch.quantity}개`" :subtitle="new Date(batch.created_at).toLocaleTimeString('ko-KR',{hour:'2-digit',minute:'2-digit'})">
            <template #append>
              <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="editBatch(batch)"/>
              <v-btn icon="mdi-delete-outline" size="small" variant="text" @click="askDelete(batch)"/>
            </template>
          </v-list-item>
        </v-list>
      </div>
      <div class="text-subtitle-2 mb-2">{{ editingBatch ? '생산 기록 수정' : '새 생산 기록' }}</div>
      <v-number-input v-model="form.quantity" label="생산 수량" variant="outlined" :min="1" />
      <v-select v-model="form.workerIds" :items="workers" item-title="name" item-value="id" label="작업자" variant="outlined" multiple chips clearable />
      <v-checkbox v-model="form.recipeDeviated" label="레시피와 다르게 작업함" density="compact" />
      <v-textarea v-if="form.recipeDeviated" v-model="form.recipeDeviationNote" label="달라진 작업 내용" variant="outlined" rows="2" />
      <v-checkbox v-model="form.recommendationReferenced" label="추천 참고" density="compact"/>
      <v-text-field v-if="form.recommendationReferenced" v-model="form.recommendationDeviationReason" label="추천 범위와 다르게 생산한 이유 · 선택" variant="outlined"/>
      <v-textarea v-model="form.note" label="메모" variant="outlined" rows="2" />
      <v-divider class="my-3" />
      <div class="text-subtitle-2 mb-2">오늘 생산하지 않은 경우</div>
      <v-select v-model="zeroReason" :items="zeroReasons" item-title="title" item-value="value" label="생산 0개 사유" variant="outlined" clearable />
      <v-btn variant="outlined" block :disabled="!zeroReason || saving" @click="confirmZeroOpen = true">생산 0개 확인</v-btn>
    </v-card-text>
    <v-card-actions class="app-dialog-footer px-4 pb-4">
      <v-btn variant="text" :disabled="saving" @click="requestClose">취소</v-btn>
      <v-spacer />
      <v-btn v-if="editingBatch" variant="text" :disabled="saving" @click="cancelEdit">수정 취소</v-btn>
      <v-btn variant="flat" :loading="saving" :disabled="saving" @click="save">{{ editingBatch ? '수정' : '저장' }}</v-btn>
    </v-card-actions>
  </v-card>
</v-dialog>
<ConfirmDialog v-model="deleteConfirmOpen" title="생산 기록 삭제" message="이 생산 기록을 삭제하시겠습니까? 이미 이월과 연결된 기록은 삭제할 수 없습니다." :loading="saving" @confirm="deleteBatch" />
<ConfirmDialog v-model="confirmZeroOpen" title="생산 0개 확인" message="오늘 이 제품을 생산하지 않은 것으로 확정하시겠습니까? 선택한 사유는 추천 분석에서 일반 수요와 구분해 사용합니다." :loading="saving" @confirm="saveZero" />
<ConfirmDialog v-model="confirmClose" title="작성 취소" message="작성 중인 내용이 있습니다. 닫으시겠습니까?" @confirm="forceClose" />
</template>

<script setup>
import {
  computed, reactive, ref, watch
} from 'vue';
import ConfirmDialog from '../common/ConfirmDialog.vue';
const props = defineProps({
  modelValue: Boolean, product: Object, storeId: Number, workDate: String, workers: {
    type: Array, default: () => []
  }, zeroReasons: {
    type: Array, default: () => []
  }
});
const emit = defineEmits(['update:modelValue', 'saved', 'error']);
const saving = ref(false);
const confirmClose = ref(false);
const confirmZeroOpen = ref(false);
const deleteConfirmOpen = ref(false);
const editingBatch = ref(null);
const deletingBatch = ref(null);
const zeroReason = ref(null);
const form = reactive({
  quantity: 1, workerIds: [], recipeDeviated: false, recipeDeviationNote: '', recommendationReferenced: false, recommendationDeviationReason: '', note: ''
});
const open = computed({
  get: () => props.modelValue, set: (value) => emit('update:modelValue', value)
});
/** 다이얼로그가 열릴 때 이전 제품의 입력값이 남지 않도록 초기화합니다. */
watch(() => props.modelValue, (value) => {
  if (value) reset();
});
/** 현재 입력값을 새 생산 기록의 기본값으로 되돌립니다. */
function reset() {
  editingBatch.value = null;
  deletingBatch.value = null;
  zeroReason.value = null;
  form.quantity = 1;
  form.workerIds = [];
  form.recipeDeviated = false;
  form.recipeDeviationNote = '';
  form.recommendationReferenced = false;
  form.recommendationDeviationReason = '';
  form.note = '';
}
/** 사용자가 입력한 내용이 있는지 확인해 실수로 닫히는 것을 방지합니다. */
function isDirty() {
  return form.quantity !== 1 || form.workerIds.length > 0 || form.recipeDeviated || !!form.recipeDeviationNote || form.recommendationReferenced || !!form.recommendationDeviationReason || !!form.note;
}
/** 작성 내용이 있으면 확인창을 거친 뒤 닫습니다. */
function requestClose() {
  if (saving.value) return;
  if (isDirty()) {
    confirmClose.value = true;
    return;
  }

  forceClose();
}
/** 확인을 마친 뒤 생산 입력 다이얼로그를 닫습니다. */
function forceClose() {
  confirmClose.value = false;
  open.value = false;
}
/** 기존 생산 기록을 수정할 수 있도록 해당 값을 입력 폼에 불러옵니다. */
function editBatch(batch) {
  editingBatch.value = batch;
  form.quantity = batch.quantity;
  form.note = batch.note || '';
}
/** 생산 수정 모드를 끝내고 신규 입력 상태로 돌아갑니다. */
function cancelEdit() {
  editingBatch.value = null;
  form.quantity = 1;
  form.note = '';
}
/** 삭제할 생산 기록을 기억하고 중요 작업 확인창을 엽니다. */
function askDelete(batch) {
  deletingBatch.value = batch;
  deleteConfirmOpen.value = true;
}
/** 서버 검증 뒤 생산 기록을 Soft Delete하고 성공 시 목록을 다시 조회합니다. */
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
/** 생산하지 않은 날은 0과 사유를 명시적으로 확인해 미입력과 구분합니다. */
async function saveZero() {
  saving.value = true;
  try {
    await window.axios.post('/tillwhite/api/production-management/zero-production', {
      store_id: props.storeId, product_id: props.product.id, work_date: props.workDate, reason: zeroReason.value,
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
/** 서버 확인이 끝난 생산 기록만 성공 처리하고 실패 시 입력값을 유지합니다. */
async function save() {
  if (!props.product?.id || !form.quantity) return;
  if (form.recipeDeviated && !form.recipeDeviationNote.trim()) {
    emit('error', '레시피와 다르게 작업한 내용을 입력해주세요.');
    return;
  }
  saving.value = true;
  try {
    if (editingBatch.value) {
      await window.axios.put(`/tillwhite/api/production-management/batches/${editingBatch.value.id}`, {
        quantity: form.quantity, note: form.note || null, lock_version: editingBatch.value.lock_version,
      });
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
