<template>
<v-dialog v-model="open" max-width="720" :persistent="saving">
  <v-card rounded="lg">
    <v-card-title class="d-flex align-center justify-space-between">
      <div>
        <div>수량 처리</div>
        <div class="app-supporting-text text-medium-emphasis mt-1">{{ product?.name || '-' }}의 로스·폐기·기타 출고·이월을 확인합니다.</div>
      </div>
      <v-btn icon="mdi-close" size="small" variant="text" :disabled="saving" @click="requestClose" />
    </v-card-title>
    <v-card-text>
      <v-alert v-if="available < allocated" type="error" variant="tonal" density="compact" class="mb-4 app-supporting-alert">처리 수량이 사용 가능 수량보다 많습니다.</v-alert>
      <div class="d-flex flex-wrap ga-2 mb-3">
        <v-btn variant="outlined" size="small" @click="markAllSold">전부 판매</v-btn>
        <span class="app-supporting-text align-self-center">남은 제품이 있으면 아래에서 로스·폐기·이월 수량만 입력합니다.</span>
      </div>
      <div class="production-flow-summary mb-4">
        <div>
          <span>사용 가능</span>
          <strong>{{ available }}개</strong>
        </div>
        <div>
          <span>처리 예정</span>
          <strong>{{ allocated }}개</strong>
        </div>
        <div>
          <span>계산 판매</span>
          <strong>{{ Math.max(0, available - allocated) }}개</strong>
        </div>
      </div>
      <div class="text-subtitle-2 mb-2">로스</div>
      <ReasonRows v-model="form.losses" :reason-options="lossReasons" />
      <v-btn size="small" variant="text" prepend-icon="mdi-plus" @click="form.losses.push(emptyReason())">사유 추가</v-btn>
      <v-divider class="my-4" />
      <div class="text-subtitle-2 mb-2">폐기</div>
      <ReasonRows v-model="form.wastes" :reason-options="wasteReasons" />
      <v-btn size="small" variant="text" prepend-icon="mdi-plus" @click="form.wastes.push(emptyReason())">사유 추가</v-btn>
      <v-divider class="my-4" />
      <div class="text-subtitle-2 mb-2">기타 출고</div>
      <ReasonRows v-model="form.otherOutflows" :reason-options="otherReasons" />
      <v-btn size="small" variant="text" prepend-icon="mdi-plus" @click="form.otherOutflows.push(emptyReason())">사유 추가</v-btn>
      <v-divider class="my-4" />
      <v-number-input v-model="form.carryoverOut" label="다음 영업일 이월" variant="outlined" :min="0" />
      <v-select v-if="form.carryoverOut > 0 && product?.carryover_in > 0" v-model="form.carryoverSource" :items="carryoverSources" item-title="title" item-value="value" label="이월할 재고 출처" variant="outlined"/>
      <v-alert v-if="form.carryoverOut > 0 && form.carryoverSource==='incoming'" type="warning" variant="tonal" density="compact" class="mb-3 app-supporting-alert">이미 이월된 제품을 다시 이월합니다. 원래 생산일은 유지됩니다.</v-alert>
      <v-textarea v-model="form.note" label="메모" variant="outlined" rows="2" />
    </v-card-text>
    <v-card-actions class="px-4 pb-4">
      <v-btn variant="text" :disabled="saving" @click="requestClose">취소</v-btn>
      <v-spacer />
      <v-btn variant="flat" :loading="saving" :disabled="saving || allocated > available" @click="askSave">저장</v-btn>
    </v-card-actions>
  </v-card>
</v-dialog>
<ConfirmDialog v-model="confirmSave" title="수량 처리 저장" :message="confirmMessage" :loading="saving" @confirm="save" />
<ConfirmDialog v-model="confirmClose" title="작성 취소" message="작성 중인 내용이 있습니다. 닫으시겠습니까?" @confirm="forceClose" />
</template>

<script setup>
import {
  computed, defineComponent, h, reactive, ref, resolveComponent, watch
}  from 'vue';
import ConfirmDialog from '../common/ConfirmDialog.vue';
const props = defineProps({
  modelValue: Boolean, product: Object, storeId: Number, workDate: String, lossReasons: Array, wasteReasons: Array
});
const emit = defineEmits(['update:modelValue', 'saved', 'error']);
const saving = ref(false);
const confirmSave = ref(false);
const confirmClose = ref(false);
const carryoverSources=[{
  value:'today',title:'오늘 생산분'
},{
  value:'incoming',title:'기존 이월분 · 재이월'
}];
const otherReasons = [{
  value: 'tasting', title: '시식'
}, {
  value: 'service', title: '고객 서비스'
}, {
  value: 'gift', title: '무료 증정'
}, {
  value: 'staff_use', title: '직원 사용'
}, {
  value: 'other', title: '기타'
}];
const form = reactive({
  losses: [], wastes: [], otherOutflows: [], carryoverOut: 0, carryoverSource: 'today', note: ''
});
const open = computed({
  get: () => props.modelValue, set: (value) => emit('update:modelValue', value)
});
const available = computed(() => (props.product?.production || 0) + (props.product?.carryover_in || 0));
const sumRows = (rows) => rows.reduce((sum, row) => sum + Number(row.quantity || 0), 0);
const allocated = computed(() => sumRows(form.losses) + sumRows(form.wastes) + sumRows(form.otherOutflows) + Number(form.carryoverOut || 0));
const confirmMessage = computed(() => {
  const base=`로스 ${sumRows(form.losses)}개 · 폐기 ${sumRows(form.wastes)}개 · 기타 출고 ${sumRows(form.otherOutflows)}개 · 이월 ${form.carryoverOut || 0}개로 저장하시겠습니까?`; return form.carryoverOut > 0 && form.carryoverSource === 'incoming' ? `이미 이월된 제품입니다. 다시 이월하시겠습니까?\n\n${base}` : base;
});
/** 반복되는 사유·수량 입력행을 가볍게 재사용하는 내부 컴포넌트입니다. */
const ReasonRows = defineComponent({
  props: {
    modelValue: {
      type: Array, required: true
    }, reasonOptions: {
      type: Array, default: () => []
    }
  }, emits: ['update:modelValue'], setup(innerProps, {
    emit: innerEmit
  }) {
    const update = (index, key, value) => {
      const next = innerProps.modelValue.map((row, i) => i === index ? {
        ...row, [key]: value
      } : row); innerEmit('update:modelValue', next);
    };
    const remove = (index) => innerEmit('update:modelValue', innerProps.modelValue.filter((_, i) => i !== index));
    const VSelect = resolveComponent('v-select');
    const VNumberInput = resolveComponent('v-number-input');
    const VBtn = resolveComponent('v-btn');
    return () => h('div', innerProps.modelValue.map((row, index) => h('div', {
      class: 'reason-row'
    }, [ h(VSelect, {
      modelValue: row.reason_code, 'onUpdate:modelValue': (v) => update(index, 'reason_code', v), items: innerProps.reasonOptions, 'item-title': 'title', 'item-value': 'value', label: '사유', variant: 'outlined', density: 'compact'
    }), h(VNumberInput, {
      modelValue: row.quantity, 'onUpdate:modelValue': (v) => update(index, 'quantity', v), label: '수량', variant: 'outlined', density: 'compact', min: 1
    }), h(VBtn, {
      icon: 'mdi-close', size: 'small', variant: 'text', onClick: () => remove(index)
    }), ])));
  },
});
/** 사용 가능 수량이 모두 판매된 경우 비판매 처리값을 0으로 빠르게 정리합니다. */
function markAllSold() {
  form.losses = [];
  form.wastes = [];
  form.otherOutflows = [];
  form.carryoverOut = 0;
  form.carryoverSource = 'today';
  confirmSave.value = true;
}
/** 새 사유 행의 기본값을 만듭니다. */
function emptyReason() {
  return {
    reason_code: '', reason_text: null, quantity: 1
  };
}
/** 제품이 바뀔 때 이전 입력이 섞이지 않도록 현재 저장값 기준으로 초기화합니다. */
watch(() => props.modelValue, (value) => {
  if (value) reset();
});
/** 선택 제품이 바뀔 때 이전 수량 처리 입력을 초기화합니다. */
function reset() {
  form.losses = [];
  form.wastes = [];
  form.otherOutflows = [];
  form.carryoverOut = Number(props.product?.carryover_out || 0);
  form.carryoverSource = props.product?.carryover_in > 0 ? 'incoming' : 'today';
  form.note = '';
}
/** 수량 처리에 입력된 값이 있는지 확인합니다. */
function isDirty() {
  return form.losses.length || form.wastes.length || form.otherOutflows.length || form.carryoverOut || form.note;
}
/** 작성 내용이 있으면 확인창을 거쳐 닫도록 요청합니다. */
function requestClose() {
  if (saving.value) return;
  if (isDirty()) {
    confirmClose.value = true;
    return;
  }  forceClose();
}
/** 사용자가 작성 취소를 확인한 뒤 다이얼로그를 닫습니다. */
function forceClose() {
  confirmClose.value = false;
  open.value = false;
}
/** 사유 입력의 필수값을 확인한 뒤 최종 저장 확인창을 엽니다. */
function askSave() {
  if ([...form.losses, ...form.wastes, ...form.otherOutflows].some((row) => !row.reason_code || !row.quantity)) {
    emit('error', '추가한 사유와 수량을 모두 입력해주세요.');
    return;
  }  confirmSave.value = true;
}
/** 최종 확인 뒤 모든 수량 처리를 한 요청으로 저장해 부분 저장을 방지합니다. */
async function save() {
  saving.value = true;
  try {
    await window.axios.put('/tillwhite/api/production-management/flow', {
      store_id: props.storeId, product_id: props.product.id, work_date: props.workDate, losses: form.losses, wastes: form.wastes, other_outflows: form.otherOutflows, carryover_out: Number(form.carryoverOut || 0), carryover_source: form.carryoverSource, note: form.note || null
    });
    confirmSave.value = false;
    open.value = false;
    emit('saved', '제품 수량 처리를 저장했습니다.');
  }  catch (error) {
    emit('error', error.response?.data?.message || '수량 처리를 저장하지 못했습니다.');
  }
  finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.production-flow-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.production-flow-summary > div {
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
}
.production-flow-summary span, .production-flow-summary strong {
  display: block;
}
.production-flow-summary span {
  font-size: .76rem;
  color: rgba(var(--v-theme-on-surface), .62);
}
.reason-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 120px auto;
  gap: 8px;
  align-items: start;
}
@media (max-width: 520px) {
  .production-flow-summary {
    grid-template-columns: 1fr;
  }
  .reason-row {
    grid-template-columns: minmax(0, 1fr) 100px auto;
  }
}
</style>
