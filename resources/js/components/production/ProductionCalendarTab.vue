<template>
<div>
  <div class="d-flex align-center justify-center ga-2 mb-4">
    <v-btn icon="mdi-chevron-left" variant="text" @click="moveMonth(-1)" />
    <strong>{{ monthLabel }}</strong>
    <v-btn icon="mdi-chevron-right" variant="text" @click="moveMonth(1)" />
  </div>
  <div class="calendar-legend app-supporting-text mb-2">
    <span>생산</span>
    <span>이월</span>
    <span>로스</span>
    <span>폐기</span>
    <span>휴점/미입력은 별도 상태</span>
  </div>
  <div class="calendar-grid calendar-week">
    <div v-for="name in weekNames" :key="name">{{ name }}</div>
  </div>
  <div class="calendar-grid">
    <div v-for="blank in leadingBlanks" :key="`blank-${blank}`" class="calendar-cell blank" />
    <button v-for="day in days" :key="day.date" type="button" class="calendar-cell" :class="dayClass(day)" :style="dayStyle(day)" @click="openDay(day)">
      <span class="day-number">{{ Number(day.date.slice(-2)) }}</span>
      <span v-if="day.events?.length" class="event-dot" />
      <span v-if="day.status==='store_closed'" class="state-label">휴점</span>
      <span v-else-if="day.required_count && day.complete_count < day.required_count" class="state-label">확인 필요</span>
    </button>
  </div>
  <div class="d-flex justify-end mt-4">
    <v-btn variant="outlined" prepend-icon="mdi-calendar-plus" @click="eventOpen=true">행사 등록</v-btn>
  </div>
  <v-dialog v-model="dayOpen" max-width="640">
    <v-card rounded="lg">
      <v-card-title class="d-flex justify-space-between">
        <span>{{ selectedDay?.date || '-' }}</span>
        <v-btn icon="mdi-close" size="small" variant="text" @click="dayOpen=false" />
      </v-card-title>
      <v-card-text>
        <div v-if="selectedDay" class="day-detail">
          <div>생산 <strong>{{ selectedDay.totals.production }}개</strong>
          </div>
          <div>판매 <strong>{{ selectedDay.totals.sale }}개</strong>
          </div>
          <div>이월 <strong>{{ selectedDay.totals.carryover }}개</strong>
          </div>
          <div>로스 <strong>{{ selectedDay.totals.loss }}개</strong>
          </div>
          <div>폐기 <strong>{{ selectedDay.totals.waste }}개</strong>
          </div>
          <div>폐기율 <strong>{{ selectedDay.totals.waste_rate == null ? '-' : `${selectedDay.totals.waste_rate}%` }}</strong>
          </div>
        </div>
        <div v-if="selectedDay?.events?.length" class="mt-4">
          <v-chip v-for="event in selectedDay.events" :key="event.id" class="mr-1 mb-1" size="small">{{ event.title }}</v-chip>
        </div>
      </v-card-text>
      <v-card-actions>
        <v-btn variant="text" @click="dayOpen=false">닫기</v-btn>
        <v-spacer/>
        <v-btn variant="text" @click="confirmDayStatus=true">{{ selectedDay?.status==='store_closed' ? '휴점 해제' : '휴점 설정' }}</v-btn>
        <v-btn variant="text" @click="emit('jump-date',selectedDay.date)">목록에서 보기</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog v-model="eventOpen" max-width="620">
    <v-card rounded="lg">
      <v-card-title>행사 등록</v-card-title>
      <v-card-text>
        <div class="app-supporting-text text-medium-emphasis mb-3">임시 행사·할인·단체주문을 날짜 조건으로 남겨 분석에 활용합니다.</div>
        <v-select v-model="eventForm.event_type" :items="eventTypes" item-title="title" item-value="value" label="행사 종류" variant="outlined"/>
        <v-text-field v-model="eventForm.title" label="행사명" variant="outlined"/>
        <div class="d-flex ga-2">
          <v-text-field v-model="eventForm.start_date" type="date" label="시작일" variant="outlined"/>
          <v-text-field v-model="eventForm.end_date" type="date" label="종료일" variant="outlined"/>
        </div>
        <v-select v-model="eventForm.product_ids" :items="products" item-title="name" item-value="id" label="대상 제품 · 비우면 전체 제품" variant="outlined" multiple chips clearable/>
        <div class="d-flex ga-2">
          <v-select v-model="eventForm.discount_type" :items="discountTypes" item-title="title" item-value="value" label="할인 방식" variant="outlined" clearable/>
          <v-number-input v-model="eventForm.discount_value" label="할인 값" variant="outlined" :min="0"/>
        </div>
        <v-number-input v-if="eventForm.event_type==='group_order'" v-model="eventForm.order_quantity" label="단체주문 수량" variant="outlined" :min="0"/>
        <v-textarea v-model="eventForm.memo" label="메모" variant="outlined" rows="2"/>
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn variant="text" @click="eventOpen=false">취소</v-btn>
        <v-spacer/>
        <v-btn variant="flat" @click="confirmEvent=true">저장</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <ConfirmDialog v-model="confirmDayStatus" title="영업일 상태 변경" :message="selectedDay?.status==='store_closed' ? '이 날짜의 휴점을 해제하시겠습니까?' : '이 날짜를 휴점일로 설정하시겠습니까? 기존 업무 기록이 있으면 설정할 수 없습니다.'" :loading="saving" @confirm="saveDayStatus" />
  <ConfirmDialog v-model="confirmEvent" title="행사 저장" message="입력한 일정과 조건으로 행사를 저장하시겠습니까?" :loading="saving" @confirm="saveEvent" />
</div>
</template>
<script setup>
import {
  computed, reactive, ref, watch
}  from 'vue';
import ConfirmDialog from '../common/ConfirmDialog.vue';
const props=defineProps({
  storeId:Number,workDate:String,products:{
    type:Array,default:()=>[]
  }
});
const emit=defineEmits(['error','success','jump-date']);
const month=ref(props.workDate.slice(0,7));
const days=ref([]);
const dayOpen=ref(false);
const confirmDayStatus=ref(false);
const selectedDay=ref(null);
const eventOpen=ref(false);
const confirmEvent=ref(false);
const saving=ref(false);
const weekNames=['일','월','화','수','목','금','토'];
const discountTypes=[{
  value:'percent',title:'퍼센트 할인'
},{
  value:'amount',title:'금액 할인'
},{
  value:'one_plus_one',title:'1+1'
}];
const eventTypes=[{
  value:'holiday',title:'공휴일'
},{
  value:'department_event',title:'백화점 행사'
},{
  value:'nearby_event',title:'주변 행사'
},{
  value:'promotion',title:'프로모션'
},{
  value:'group_order',title:'단체주문'
},{
  value:'hours_change',title:'영업시간 변경'
},{
  value:'other',title:'기타'
}];
const eventForm=reactive({
  event_type:'promotion',title:'',start_date:props.workDate,end_date:props.workDate,discount_type:null,discount_value:null,order_quantity:null,product_ids:[],memo:''
});
const monthLabel=computed(()=>{
  const [y,m]=month.value.split('-');return `${y}년 ${Number(m)}월`;
});
const leadingBlanks=computed(()=>{
  const [y,m]=month.value.split('-').map(Number);return new Date(y,m-1,1).getDay();
});
watch(()=>props.storeId,load,{
  immediate:true
});
/** 선택 월의 날짜별 요약을 서버에서 조회합니다. */
async function load(){
  if(!props.storeId)return;
  try{
    const {
      data
    }=await window.axios.get('/tillwhite/api/production-management/calendar',{
      params:{
        store_id:props.storeId,month:month.value
      }
    });
    days.value=data.days||[];
  } catch(error){
    emit('error',error.response?.data?.message||'캘린더를 불러오지 못했습니다.');
  }
}
/** 이전 또는 다음 달로 이동한 뒤 새 월 데이터를 조회합니다. */
function moveMonth(amount){
  const [y,m]=month.value.split('-').map(Number);
  const d=new Date(y,m-1+amount,1);
  month.value=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
  load();
}
/** 날짜별 생산·이월·로스·폐기 비율을 배경 강도로 표현하되 폐기 위험을 우선합니다. */
function dayStyle(day){
  const t=day.totals||{
  };
  const total=Math.max(1,(t.production||0)+(t.carryover||0)+(t.loss||0)+(t.waste||0));
  const risk=((t.waste||0)*2+(t.loss||0))/total;
  if(day.status==='store_closed')return{
  };
  return{
    '--day-risk':Math.min(.28,risk*.8),'--day-activity':Math.min(.14,(t.production||0)/Math.max(1,50)*.12)
  };
}
/** 주말과 업무 상태에 맞는 날짜 셀 클래스를 반환합니다. */
function dayClass(day){
  const [y,m,d]=day.date.split('-').map(Number);
  const dow=new Date(y,m-1,d).getDay();
  const holiday=day.events?.some((event)=>event.event_type==='holiday');
  return{
    sun:dow===0||holiday,sat:dow===6&&!holiday,closed:day.status==='store_closed',incomplete:day.required_count&&day.complete_count<day.required_count
  };
}
/** 날짜 상세 다이얼로그를 엽니다. */
function openDay(day){
  selectedDay.value=day;
  dayOpen.value=true;
}
/** 휴점 설정·해제는 중요 상태 변경이므로 확인창 뒤 서버 검증을 거쳐 저장합니다. */
async function saveDayStatus(){
  if(!selectedDay.value)return;
  saving.value=true;
  try{
    const nextStatus=selectedDay.value.status==='store_closed'?'open':'closed';
    await window.axios.put('/tillwhite/api/production-management/day-status',{
      store_id:props.storeId,work_date:selectedDay.value.date,status:nextStatus
    });
    confirmDayStatus.value=false;
    dayOpen.value=false;
    emit('success',nextStatus==='closed'?'휴점일로 설정했습니다.':'휴점을 해제했습니다.');
    await load();
  } catch(error){
    emit('error',error.response?.data?.message||'영업일 상태를 변경하지 못했습니다.');
  }
  finally{
    saving.value=false;
  }
}
/** 중요 일정 저장은 확인창을 거친 뒤 서버 성공 시에만 완료 처리합니다. */
async function saveEvent(){
  saving.value=true;
  try{
    await window.axios.post('/tillwhite/api/production-management/events',{
      store_id:props.storeId,...eventForm
    });
    confirmEvent.value=false;
    eventOpen.value=false;
    emit('success','캘린더 일정을 저장했습니다.');
    await load();
  } catch(error){
    emit('error',error.response?.data?.message||'행사를 저장하지 못했습니다.');
  } finally{
    saving.value=false;
  }
}
</script>
<style scoped>
.calendar-legend {
  display:flex;
  flex-wrap:wrap;
  gap:12px
}
.calendar-grid {
  display:grid;
  grid-template-columns:repeat(7,minmax(0,1fr));
  gap:6px
}
.calendar-week {
  text-align:center;
  font-size:.76rem;
  margin-bottom:6px
}
.calendar-cell {
  position:relative;
  min-height:78px;
  padding:8px;
  border:1px solid rgba(var(--v-border-color),var(--v-border-opacity));
  border-radius:10px;
  background:linear-gradient(rgba(244,67,54,var(--day-risk,0)),rgba(76,175,80,var(--day-activity,0))),rgb(var(--v-theme-surface));
  color:inherit;
  text-align:left
}
.calendar-cell.blank {
  border:0;
  background:none
}
.calendar-cell.sun .day-number {
  color:#d32f2f
}
.calendar-cell.sat .day-number {
  color:#1976d2
}
.calendar-cell.closed {
  opacity:.5;
  background:rgba(var(--v-theme-on-surface),.06)
}
.calendar-cell.incomplete {
  border-style:dashed
}
.event-dot {
  position:absolute;
  top:8px;
  right:8px;
  width:7px;
  height:7px;
  border-radius:50%;
  background:currentColor
}
.state-label {
  display:block;
  margin-top:22px;
  font-size:.68rem
}
.day-detail {
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:10px
}
.day-detail>div {
  padding:12px;
  border:1px solid rgba(var(--v-border-color),var(--v-border-opacity));
  border-radius:10px
}
.day-detail strong {
  display:block;
  margin-top:3px
}
@media(max-width:600px) {
  .calendar-cell {
    min-height:62px;
    padding:6px
  }
  .state-label {
    margin-top:14px
  }
  .day-detail {
    grid-template-columns:repeat(2,1fr)
  }
}
</style>
