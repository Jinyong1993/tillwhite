<template>
<div class="calendar-page">
  <section class="app-date-toolbar calendar-toolbar">
    <div class="app-date-navigation">
      <v-btn 
        icon="mdi-chevron-left" 
        variant="text" 
        size="small" 
        aria-label="이전 달" 
        @click="moveMonth(-1)" 
      />
      <div class="calendar-month-copy app-date-main">
        <strong>
          {{ monthLabel }}
        </strong>
        <span>
          날짜별 생산·폐기와 마감 상태를 확인합니다.
        </span>
      </div>
      <v-btn 
        icon="mdi-chevron-right" 
        variant="text" 
        size="small" 
        aria-label="다음 달" 
        @click="moveMonth(1)" 
      />
    </div>
    <div class="app-date-today-slot">
      <v-btn 
        v-show="month !== today.slice(0, 7)" 
        size="small" 
        variant="outlined" 
        class="app-date-today" 
        @click="goToday">
        오늘
      </v-btn>
    </div>
  </section>

  <div class="calendar-status-guide app-supporting-text mb-3">
    <span><i class="status-dot today-dot" />오늘</span>
    <span><i class="status-dot open-dot" />마감 전</span>
    <span><i class="status-dot closed-dot" />마감 완료</span>
    <span><i class="status-dot off-dot" />휴점</span>
  </div>

  <div class="calendar-grid calendar-week">
    <div v-for="name in weekNames" :key="name">{{ name }}</div>
  </div>

  <div v-if="loading && !days.length" class="calendar-grid calendar-loading" aria-label="캘린더 불러오는 중">
    <div v-for="blank in leadingBlanks" :key="`loading-blank-${blank}`" class="calendar-cell blank" />
    <div v-for="day in daysInMonth" :key="`loading-${day}`" class="calendar-cell skeleton-cell"><span>{{ day }}</span></div>
  </div>

  <div v-else class="calendar-grid">
    <div v-for="blank in leadingBlanks" :key="`blank-${blank}`" class="calendar-cell blank" />
    <button v-for="day in days" :key="day.date" type="button" class="calendar-cell" :class="dayClass(day)" @click="openDay(day)">
      <div class="calendar-cell-head">
        <span class="day-number">{{ Number(day.date.slice(-2)) }}</span>
        <span v-if="day.events?.length" class="event-dot" title="등록된 일정 있음" />
      </div>
      <template v-if="day.status === 'store_closed'">
        <span class="day-state">휴점</span>
      </template>
      <template v-else>
        <div class="day-summary">
          <span class="calendar-production">생산 <b>{{ day.totals?.production || 0 }}</b></span>
          <span class="calendar-waste">폐기 <b>{{ day.totals?.waste || 0 }}</b></span>
        </div>
        <span class="day-state">{{ calendarStatusText(day) }}</span>
      </template>
    </button>
  </div>

  <div class="calendar-actions">
    <v-btn v-if="canMutate" variant="flat" prepend-icon="mdi-calendar-plus" @click="openEventDialog">행사</v-btn>
  </div>
  <v-dialog
      v-model="dayOpen"
      max-width="640"
      persistent
  >
    <v-card
        rounded="lg"
        class="app-dialog-card"
    >
      <v-card-title class="app-dialog-header">{{ selectedDay?.date || '-' }} 일일 현황</v-card-title>
      <v-card-text class="app-dialog-body">
        <div v-if="selectedDay">
          <div class="day-dialog-status mb-3">
            <strong>{{ calendarStatusText(selectedDay) }}</strong>
            <span v-if="selectedDay.status !== 'store_closed'">{{ dayCheckText(selectedDay) }}</span>
          </div>
          <div v-if="!hasDayData(selectedDay)" class="calendar-empty-state">
            <v-icon
                icon="mdi-calendar-blank-outline"
                size="22"
            />
            <div><strong>이 날짜에는 기록이 없습니다.</strong><span>생산·이월·로스·폐기 기록이 생기면 여기에 표시됩니다.</span></div>
          </div>
          <div class="day-detail">
            <div ><span>생산</span><strong>{{ selectedDay.totals.production }}</strong></div>
            <div><span>이월</span><strong>{{ selectedDay.totals.carryover }}</strong></div>
            <div><span>로스</span><strong>{{ selectedDay.totals.loss }}</strong></div>
            <div ><span>폐기</span><strong>{{ selectedDay.totals.waste }}</strong></div>
            <div><span>폐기율</span><strong>{{ selectedDay.totals.waste_rate == null ? '-' : `${selectedDay.totals.waste_rate}%` }}</strong></div>
          </div>
          <div v-if="hasDayData(selectedDay)" class="calendar-analysis">
            <div class="calendar-analysis-title">하루 분석</div>
            <strong>{{ selectedDayInsight.title }}</strong>
            <span>{{ selectedDayInsight.text }}</span>
          </div>
          <div v-if="dayDetailLoading" class="day-record-loading">일일 기록을 불러오는 중입니다.</div>
          <template v-else-if="selectedDayDaily?.rows?.length">
            <v-btn-toggle
                v-model="dayFilter"
                mandatory
                density="compact"
                variant="text"
                class="day-filter"
            >
              <v-btn value="all">전체</v-btn><v-btn value="missing">확인 필요</v-btn><v-btn value="complete">기록 완료</v-btn>
            </v-btn-toggle>
            <div class="day-product-list">
              <div v-for="row in filteredDayRows" :key="row.id" class="day-product-row">
                <div class="day-product-head"><strong>{{ row.name }}</strong><span>{{ row.complete ? '완료' : '확인 필요' }}</span></div>
                <div class="day-product-values">
                  <span>생산 <b>{{ row.production_confirmed ? row.production : '-' }}</b></span>
                  <span>이월 <b>{{ row.disposition_confirmed ? row.carryover_in : '-' }}</b></span>
                  <span>로스 <b>{{ row.loss_confirmed ? row.loss : '-' }}</b></span>
                  <span>폐기 <b>{{ row.waste_confirmed ? row.waste : '-' }}</b></span>
                  <span>폐기율 <b>{{ row.waste_rate == null ? '-' : `${row.waste_rate}%` }}</b></span>
                </div>
              </div>
            </div>
          </template>
        </div>
        <div v-if="selectedDay?.events?.length" class="mt-4">
          <v-chip v-for="event in selectedDay.events" :key="event.id" class="mr-1 mb-1" size="small">{{ event.title }}</v-chip>
        </div>
      </v-card-text>
      <v-card-actions class="app-dialog-footer">
        <v-btn variant="text"  :disabled="jumping" @click="dayOpen=false">닫기</v-btn>
        <v-spacer/>
        <v-btn v-if="canMutate" variant="text" :disabled="jumping" @click="confirmDayStatus=true">{{ selectedDay?.status==='store_closed' ? '휴점 해제' : '휴점 설정' }}</v-btn>
        <v-btn variant="text" :loading="jumping" :disabled="jumping" @click="jumpToList">목록에서 보기</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog
      v-model="eventOpen"
      max-width="620"
      persistent
  >
    <v-card
        rounded="lg"
        class="app-dialog-card"
    >
      <v-card-title class="app-dialog-header">행사</v-card-title>
      <v-card-text class="app-dialog-body event-form">
        <div class="dialog-intro">행사 내용을 입력하면 해당 날짜의 분석에 함께 반영됩니다.</div>
        <v-select
            v-model="eventForm.event_type"
            :items="eventTypes"
            item-title="title"
            item-value="value"
            label="행사 종류"
            variant="outlined"
        />
        <v-text-field
            v-model="eventForm.title"
            label="행사명"
            variant="outlined"
        />
        <div class="event-date-fields">
          <v-text-field
              v-model="eventForm.start_date"
              type="date"
              label="시작일"
              variant="outlined"
          />
          <v-text-field
              v-model="eventForm.end_date"
              type="date"
              label="종료일"
              variant="outlined"
          />
        </div>

        <div class="field-label">대상 제품</div>
        <v-btn-toggle
            v-model="eventTarget"
            mandatory
            density="compact"
            class="event-target-toggle"
        >
          <v-btn value="all">전체 제품</v-btn>
          <v-btn value="selected">특정 제품</v-btn>
        </v-btn-toggle>
        <v-select
          v-if="eventTarget === 'selected'"
          v-model="eventForm.product_ids"
          :items="products"
          item-title="name"
          item-value="id"
          label="제품 선택"
          variant="outlined"
          multiple
          chips
          clearable
        />

        <v-select
            v-model="eventForm.discount_type"
            :items="discountTypes"
            item-title="title"
            item-value="value"
            label="할인 방식"
            variant="outlined"
            clearable
        />
        <v-number-input
          v-if="eventForm.discount_type === 'percent'"
          v-model="eventForm.discount_value"
          label="할인율 (%)"
          variant="outlined"
          :min="0"
          :max="100"
        />
        <v-number-input
          v-else-if="eventForm.discount_type === 'amount'"
          v-model="eventForm.discount_value"
          label="할인 금액"
          variant="outlined"
          :min="0"
        />
        <div v-else-if="eventForm.discount_type === 'one_plus_one'" class="event-note">1+1 행사로 저장됩니다. 별도의 할인 값은 입력하지 않습니다.</div>
        <v-number-input
            v-if="eventForm.event_type === 'group_order'"
            v-model="eventForm.order_quantity"
            label="단체주문 수량"
            variant="outlined"
            :min="0"
        />
        <v-textarea
            v-model="eventForm.memo"
            label="메모"
            variant="outlined"
            rows="2"
        />
      </v-card-text>
      <v-card-actions class="app-dialog-footer px-4 pb-4">
        <v-btn variant="text" @click="eventOpen=false">취소</v-btn>
        <v-spacer/>
        <v-btn variant="flat" @click="confirmEvent=true">저장</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <ConfirmDialog
      v-model="confirmDayStatus"
      title="영업일 상태 변경"
      :message="selectedDay?.status==='store_closed' ? '이 날짜의 휴점을 해제하시겠습니까?' : '이 날짜를 휴점일로 설정하시겠습니까? 기존 업무 기록이 있으면 설정할 수 없습니다.'"
      :loading="saving"
      @confirm="saveDayStatus"
  />
  <ConfirmDialog
      v-model="confirmEvent"
      title="행사 저장"
      message="입력한 일정과 조건으로 행사를 저장하시겠습니까?"
      :loading="saving"
      @confirm="saveEvent"
  />
</div>
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
  storeId: Number,
  workDate: String,
  products: {
    type: Array,
    default: () => [],
  },
  canMutate: Boolean,
});

const emit = defineEmits(['error', 'success', 'jump-date']);
const today = new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Seoul' });
const month = ref(props.workDate.slice(0, 7));
const days = ref([]);
const loading = ref(false);
const monthCache = new Map();
const dayOpen = ref(false);
const confirmDayStatus = ref(false);
const selectedDay = ref(null);
const selectedDayDaily = ref(null);
const dayDetailLoading = ref(false);
const dayFilter = ref('all');
const eventOpen = ref(false);
const eventTarget = ref('all');
const confirmEvent = ref(false);
const saving = ref(false);
const jumping = ref(false);
let calendarRequest = null;

const weekNames = ['일', '월', '화', '수', '목', '금', '토'];
const discountTypes = [
  { value: 'percent', title: '퍼센트 할인' },
  { value: 'amount', title: '금액 할인' },
  { value: 'one_plus_one', title: '1+1' },
];
const eventTypes = [
  { value: 'holiday', title: '공휴일' },
  { value: 'department_event', title: '백화점 행사' },
  { value: 'nearby_event', title: '주변 행사' },
  { value: 'promotion', title: '프로모션' },
  { value: 'group_order', title: '단체주문' },
  { value: 'hours_change', title: '영업시간 변경' },
  { value: 'other', title: '기타' },
];

const eventForm = reactive({
  event_type: 'promotion',
  title: '',
  start_date: props.workDate,
  end_date: props.workDate,
  discount_type: null,
  discount_value: null,
  order_quantity: null,
  product_ids: [],
  memo: '',
});

// 할인 방식에 필요하지 않은 값이 이전 선택에서 남지 않도록 즉시 정리합니다.
watch(() => eventForm.discount_type, (type) => {
  if (!['percent', 'amount'].includes(type)) {
    eventForm.discount_value = null;
  }
});

// 전체 제품을 선택하면 서버에는 빈 제품 목록을 보내 기존 전체 적용 규칙을 유지합니다.
watch(eventTarget, (target) => {
  if (target === 'all') {
    eventForm.product_ids = [];
  }
});

// 행사 입력창을 열 때 대상 선택 상태를 현재 값과 맞춥니다.
function openEventDialog() {
  eventTarget.value = eventForm.product_ids.length ? 'selected' : 'all';
  eventOpen.value = true;
}

const monthLabel = computed(() => {
  const [year, monthNumber] = month.value.split('-');
  return `${year}년 ${Number(monthNumber)}월`;
});


const filteredDayRows = computed(() => {
  const rows = (selectedDayDaily.value?.rows || []).filter((row) => row.is_active);
  if (dayFilter.value === 'missing') return rows.filter((row) => !row.complete);
  if (dayFilter.value === 'complete') return rows.filter((row) => row.complete);
  return rows;
});
const selectedDayInsight = computed(() => {
  const day = selectedDay.value;
  if (!day?.totals) return { title: '기록을 확인할 수 없습니다.', text: '목록에서 상세 기록을 확인해 주세요.' };
  const production = Number(day.totals.production || 0);
  const waste = Number(day.totals.waste || 0);
  const loss = Number(day.totals.loss || 0);
  if (!production && !waste && !loss && !Number(day.totals.carryover || 0)) {
    return { title: '수량 기록이 없는 날짜입니다.', text: day.status === 'closed' ? '0개 기록으로 마감된 날짜입니다.' : '아직 입력된 수량이 없습니다.' };
  }
  if (waste > 0) {
    return { title: `폐기 ${waste}개가 기록되었습니다.`, text: day.totals.waste_rate == null ? '생산량이 없어 폐기율을 계산하지 않습니다.' : `생산 대비 폐기율은 ${day.totals.waste_rate}%입니다.` };
  }
  if (loss > 0) return { title: `로스 ${loss}개가 기록되었습니다.`, text: `생산 ${production}개와 함께 원인 기록을 목록에서 확인할 수 있습니다.` };
  return { title: `생산 ${production}개가 기록되었습니다.`, text: '폐기·로스 수량은 현재 0개입니다.' };
});

const daysInMonth = computed(() => {
  const [year, monthNumber] = month.value.split('-').map(Number);
  return new Date(year, monthNumber, 0).getDate();
});

const leadingBlanks = computed(() => {
  const [year, monthNumber] = month.value.split('-').map(Number);
  return new Date(year, monthNumber - 1, 1).getDay();
});

watch(() => props.storeId, () => load(), { immediate: true });

// 선택 월의 날짜별 요약을 조회하며 이미 확인한 월은 메모리 캐시를 재사용합니다.
async function load({ force = false } = {}) {
  if (!props.storeId) return;

  const key = `${props.storeId}:${month.value}`;
  if (!force && monthCache.has(key)) {
    days.value = monthCache.get(key);
    return;
  }

  calendarRequest?.abort();
  loading.value = true;
  const controller = new AbortController();
  calendarRequest = controller;

  try {
    const { data } = await window.axios.get('/tillwhite/api/production-management/calendar', {
      params: {
        store_id: props.storeId,
        month: month.value,
      },
      signal: controller.signal,
      timeout: 20000,
    });

    if (controller.signal.aborted) return;

    const nextDays = data.days || [];
    monthCache.set(key, nextDays);
    days.value = nextDays;
  } catch (error) {
    if (controller.signal.aborted) return;
    emit('error', error.response?.data?.message || '캘린더를 불러오지 못했습니다.');
  } finally {
    if (calendarRequest === controller) {
      calendarRequest = null;
      loading.value = false;
    }
  }
}

// 이전 또는 다음 달로 이동하고 해당 월 데이터를 조회합니다.
function moveMonth(amount) {
  const [year, monthNumber] = month.value.split('-').map(Number);
  const nextMonth = new Date(year, monthNumber - 1 + amount, 1);
  month.value = `${nextMonth.getFullYear()}-${String(nextMonth.getMonth() + 1).padStart(2, '0')}`;
  load();
}

// 일요일·토요일·공휴일과 업무 상태에 맞는 날짜 셀 클래스를 반환합니다.
function dayClass(day) {
  const [year, monthNumber, dateNumber] = day.date.split('-').map(Number);
  const dayOfWeek = new Date(year, monthNumber - 1, dateNumber).getDay();
  const holiday = day.events?.some((event) => event.event_type === 'holiday');

  return {
    sun: dayOfWeek === 0 || holiday,
    sat: dayOfWeek === 6 && !holiday,
    closed: day.status === 'store_closed',
    today: day.date === props.workDate,
    incomplete: day.status !== 'closed' && day.status !== 'store_closed',
  };
}

// 내부 상태 코드를 직원이 바로 이해할 수 있는 업무 상태로 바꿉니다.
function calendarStatusText(day) {
  if (day.status === 'store_closed') return '휴점';
  if (day.status === 'closed') return '마감 완료';
  return '마감 전';
}

// 애매한 '확인 필요' 대신 남은 제품 수를 구체적으로 설명합니다.
function dayCheckText(day) {
  if (day.status === 'closed') return '이 날짜의 업무가 마감되었습니다.';
  return '세부 확인이나 입력이 필요하면 목록에서 확인해 주세요.';
}

// 현재 월로 즉시 돌아가며 날짜 위치가 흔들리지 않도록 전용 영역을 사용합니다.
function goToday() {
  month.value = today.slice(0, 7);
  load();
}

// 기록이 전혀 없는 날짜와 실제 0개 기록을 안내 문구에서 구분하기 위한 표시 판단입니다.
function hasDayData(day) {
  if (!day?.totals) return false;
  return ['production', 'carryover', 'loss', 'waste'].some((key) => Number(day.totals[key] || 0) > 0)
    || day.status === 'closed'
    || Boolean(day.events?.length);
}

// 선택 날짜의 생산·이월·로스·폐기 요약을 상세 다이얼로그로 엽니다.
async function openDay(day) {
  selectedDay.value = day;
  selectedDayDaily.value = null;
  dayFilter.value = 'all';
  dayOpen.value = true;
  dayDetailLoading.value = true;

  try {
    const { data } = await window.axios.get('/tillwhite/api/production-management/daily', {
      params: { store_id: props.storeId, date: day.date },
      timeout: 20000,
    });
    selectedDayDaily.value = data;
  } catch (error) {
    emit('error', error.response?.data?.message || '일일 상세 기록을 불러오지 못했습니다.');
  } finally {
    dayDetailLoading.value = false;
  }
}

// 목록 이동이 끝날 때까지 상세 다이얼로그를 잠그고 성공한 경우에만 닫습니다.
function jumpToList() {
  if (!selectedDay.value || jumping.value) return;

  jumping.value = true;
  emit('jump-date', selectedDay.value.date, (success) => {
    jumping.value = false;

    if (success) {
      dayOpen.value = false;
    }
  });
}

// 휴점 설정·해제는 확인 후 서버 검증을 거쳐 저장하고 현재 월 캐시를 갱신합니다.
async function saveDayStatus() {
  if (!selectedDay.value) return;

  saving.value = true;

  try {
    const nextStatus = selectedDay.value.status === 'store_closed' ? 'open' : 'closed';
    await window.axios.put('/tillwhite/api/production-management/day-status', {
      store_id: props.storeId,
      work_date: selectedDay.value.date,
      status: nextStatus,
    });

    confirmDayStatus.value = false;
    dayOpen.value = false;
    emit('success', nextStatus === 'closed' ? '휴점일로 설정했습니다.' : '휴점을 해제했습니다.');
    monthCache.delete(`${props.storeId}:${month.value}`);
    await load({ force: true });
  } catch (error) {
    emit('error', error.response?.data?.message || '영업일 상태를 변경하지 못했습니다.');
  } finally {
    saving.value = false;
  }
}

// 행사 저장은 확인 후 실행하고 성공한 경우에만 현재 월 데이터를 다시 조회합니다.
async function saveEvent() {
  saving.value = true;

  try {
    await window.axios.post('/tillwhite/api/production-management/events', {
      store_id: props.storeId,
      ...eventForm,
    });

    confirmEvent.value = false;
    eventOpen.value = false;
    emit('success', '캘린더 일정을 저장했습니다.');
    monthCache.delete(`${props.storeId}:${month.value}`);
    await load({ force: true });
  } catch (error) {
    emit('error', error.response?.data?.message || '행사를 저장하지 못했습니다.');
  } finally {
    saving.value = false;
  }
}
</script>
<style scoped>
.calendar-page {
    min-height:420px;
}
.calendar-toolbar {
    padding:4px 0 16px;
}
.calendar-month-copy {
    min-width:180px;
    text-align:center;
}
.calendar-month-copy strong,.calendar-month-copy span {
    display:block;
}
.calendar-month-copy strong {
    font-size:1rem;
    font-weight:650;
}
.calendar-month-copy span {
    margin-top:2px;
    font-size:.68rem;
    color:rgba(var(--v-theme-on-surface),.54);
}
.calendar-status-guide {
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:14px;
    font-size:.68rem;
}
.calendar-status-guide span {
    display:flex;
    align-items:center;
    gap:5px;
}
.status-dot {
    width:7px;
    height:7px;
    border-radius:50%;
    background:rgba(var(--v-theme-on-surface),.3);
}
.today-dot {
    background:rgb(var(--v-theme-primary));
}
.open-dot {
    border:1px solid rgba(var(--v-theme-on-surface),.45);
    background:transparent;
}
.closed-dot {
    background:rgba(var(--v-theme-success),.7);
}
.off-dot {
    background:rgba(var(--v-theme-on-surface),.18);
}
.calendar-grid {
    display:grid;
    grid-template-columns:repeat(7,minmax(0,1fr));
    gap:6px;
}
.calendar-week {
    margin-bottom:6px;
    text-align:center;
    color:rgba(var(--v-theme-on-surface),.5);
    font-size:.68rem;
    font-weight:550;
}
.calendar-cell {
    position:relative;
    min-height:112px;
    padding:8px;
    border:1px solid rgba(var(--v-border-color),.65);
    border-radius:10px;
    background:rgb(var(--v-theme-surface));
    color:inherit;
    text-align:left;
    transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease;
}
button.calendar-cell {
    cursor:pointer;
}
button.calendar-cell:hover {
    transform:translateY(-1px);
    border-color:rgba(var(--v-theme-primary),.38);
    box-shadow:0 3px 10px rgba(0,0,0,.06);
}
.calendar-cell.blank {
    border:0;
    background:none;
}
.calendar-cell.today {
    border-color:rgba(var(--v-theme-primary),.65);
    box-shadow:inset 0 0 0 1px rgba(var(--v-theme-primary),.16);
}
.calendar-cell.sun .day-number {
    color:rgb(var(--v-theme-error));
}
.calendar-cell.sat .day-number {
    color:rgb(var(--v-theme-primary));
}
.calendar-cell.closed {
    background:rgba(var(--v-theme-on-surface),.025);
}
.calendar-cell.incomplete .day-state {
    font-weight:600;
}
.calendar-cell-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
}
.day-number {
    font-size:.75rem;
    font-weight:600;
}
.event-dot {
    width:6px;
    height:6px;
    border-radius:50%;
    background:rgba(var(--v-theme-primary),.72);
}
.day-summary {
    display:flex;
    flex-direction:column;
    gap:1px;
    margin-top:9px;
    font-size:.64rem;
    color:rgba(var(--v-theme-on-surface),.58);
}
.day-summary span {
    display:flex;
    justify-content:space-between;
    gap:6px;
}
.day-summary b {
    color:rgba(var(--v-theme-on-surface),.82);
    font-weight:600;
    font-variant-numeric:tabular-nums;
}
.calendar-production b {
    color:rgb(46,125,50);
}
.calendar-waste b {
    color:rgb(198,40,40);
}
.day-state {
    display:block;
    margin-top:7px;
    font-size:.62rem;
    color:rgba(var(--v-theme-on-surface),.5);
}
.calendar-cell.closed .day-state {
    margin-top:28px;
    text-align:center;
}
.calendar-actions {
    display:flex;
    justify-content:flex-end;
    margin-top:14px;
}
.dialog-intro {
    margin-bottom:14px;
    font-size:.72rem;
    line-height:1.5;
    color:rgba(var(--v-theme-on-surface),.58);
}
.event-date-fields {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px;
}
.field-label {
    margin-bottom:7px;
    font-size:.72rem;
    font-weight:600;
}
.event-target-toggle {
    width:100%;
    margin-bottom:16px;
}
.event-target-toggle :deep(.v-btn) {
    flex:1;
}
.event-note {
    margin:-4px 0 16px;
    padding:9px 10px;
    border-radius:8px;
    background:rgba(var(--v-theme-on-surface),.045);
    font-size:.7rem;
    color:rgba(var(--v-theme-on-surface),.62);
}
.skeleton-cell {
    pointer-events:none;
    opacity:.55;
    overflow:hidden;
}
.skeleton-cell::after {
    content:'';
    display:block;
    width:75%;
    height:7px;
    margin-top:16px;
    border-radius:8px;
    background:rgba(var(--v-theme-on-surface),.08);
    box-shadow:0 13px 0 rgba(var(--v-theme-on-surface),.06),0 26px 0 rgba(var(--v-theme-on-surface),.05);
}
.day-dialog-status {
    display:flex;
    flex-direction:column;
    gap:2px;
}
.day-dialog-status strong {
    font-size:.86rem;
    font-weight:650;
}
.day-dialog-status span {
    font-size:.72rem;
    color:rgba(var(--v-theme-on-surface),.58);
}
.day-record-loading {
    margin-top:14px;
    padding:12px;
    border-radius:9px;
    background:rgba(var(--v-theme-on-surface),.04);
    font-size:.68rem;
    color:rgba(var(--v-theme-on-surface),.58);
}
.day-filter {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    width:100%;
    margin-top:14px;
}
.day-filter :deep(.v-btn) {
    min-width:0;
    font-size:.66rem;
}
.day-product-list {
    margin-top:8px;
    max-height:280px;
    overflow:auto;
}
.day-product-row {
    padding:9px 2px;
    border-bottom:1px solid rgba(var(--v-border-color),.45);
}
.day-product-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}
.day-product-head strong {
    font-size:.72rem;
}
.day-product-head span {
    font-size:.62rem;
    color:rgba(var(--v-theme-on-surface),.55);
}
.day-product-values {
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:3px;
    margin-top:6px;
}
.day-product-values span {
    text-align:center;
    font-size:.58rem;
    color:rgba(var(--v-theme-on-surface),.55);
}
.day-product-values b {
    display:block;
    margin-top:1px;
    font-size:.67rem;
    color:rgb(var(--v-theme-on-surface));
}
.day-detail {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:8px;
}
.day-detail>div {
    padding:10px;
    border-radius:9px;
    background:rgba(var(--v-theme-on-surface),.035);
    text-align:center;
}
.day-detail span,.day-detail strong {
    display:block;
}
.day-detail span {
    font-size:.66rem;
    color:rgba(var(--v-theme-on-surface),.56);
}
.day-detail strong {
    margin-top:2px;
    font-size:.9rem;
    font-weight:600;
    font-variant-numeric:tabular-nums;
}
.calendar-empty-state {
    display:flex;
    align-items:flex-start;
    gap:9px;
    margin-bottom:12px;
    padding:11px;
    border-radius:10px;
    background:rgba(var(--v-theme-on-surface),.04);
}
.calendar-empty-state strong,.calendar-empty-state span {
    display:block;
}
.calendar-empty-state strong {
    font-size:.75rem;
    font-weight:600;
}
.calendar-empty-state span {
    margin-top:2px;
    font-size:.67rem;
    color:rgba(var(--v-theme-on-surface),.58);
}
.calendar-analysis {
    margin-top:12px;
    padding:12px;
    border-top:1px solid rgba(var(--v-border-color),.5);
    background:rgba(var(--v-theme-on-surface),.025);
    border-radius:9px;
}
.calendar-analysis-title {
    margin-bottom:5px;
    font-size:.65rem;
    color:rgba(var(--v-theme-on-surface),.5);
}
.calendar-analysis strong,.calendar-analysis span {
    display:block;
}
.calendar-analysis strong {
    font-size:.78rem;
}
.calendar-analysis span {
    margin-top:3px;
    font-size:.68rem;
    line-height:1.45;
    color:rgba(var(--v-theme-on-surface),.58);
}
@media(max-width:760px) {
  .calendar-grid {
      gap:3px;
  }
  .calendar-cell {
      min-height:72px;
      padding:5px;
      border-radius:7px;
  }
  .calendar-month-copy span,.calendar-status-guide {
      display:none;
  }
  .day-summary {
      margin-top:6px;
      font-size:.58rem;
  }
  .day-state {
      margin-top:4px;
      font-size:.56rem;
      overflow:hidden;
      text-overflow:ellipsis;
      white-space:nowrap;
  }
  .calendar-cell.closed .day-state {
      margin-top:18px;
  }
  .day-detail {
      grid-template-columns:repeat(3,1fr);
      gap:5px;
  }
  .day-detail>div {
      padding:8px 4px;
  }
  .event-date-fields {
      grid-template-columns:1fr;
      gap:0;
  }
}
</style>
