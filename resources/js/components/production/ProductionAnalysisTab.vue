<template>
<div>
  <div class="d-flex align-center mb-3">
    <div>
      <div class="text-h6">확인이 필요한 제품</div>
      <div class="app-supporting-text text-medium-emphasis">원인을 단정하지 않고 실제 실적과 관련 조건을 함께 확인합니다.</div>
    </div>
    <v-spacer/>
    <v-btn variant="text" prepend-icon="mdi-refresh" @click="load">새로고침</v-btn>
  </div>
  <v-card v-for="item in sortedItems" :key="item.product_id" variant="outlined" rounded="lg" class="mb-3">
    <v-card-title>{{ item.product_name }}</v-card-title>
    <v-card-text>
      <div v-if="item.flags.length" class="mb-3">
        <v-alert v-for="flag in item.flags" :key="flag" type="warning" variant="tonal" density="compact" class="mb-2 app-supporting-alert">{{ flag }}</v-alert>
      </div>
      <div class="recommendation">
        <div>
          <span>권장 생산량</span>
          <strong>{{ recommendationText(item.recommendation) }}</strong>
        </div>
        <div>
          <span>신뢰도</span>
          <strong>{{ confidenceText(item.recommendation.confidence) }}</strong>
        </div>
      </div>
      <div class="app-supporting-text mt-3">
        <div v-for="reason in item.recommendation.reasons" :key="reason">· {{ reason }}</div>
      </div>
    </v-card-text>
  </v-card>
  <v-empty-state v-if="!items.length" title="분석할 데이터가 없습니다." icon="mdi-chart-box-outline" />
</div>
</template>
<script setup>
import {
  computed, ref, watch
}  from 'vue';
const props=defineProps({
  storeId:Number,workDate:String
});
const emit=defineEmits(['error']);
const items=ref([]);
const sortedItems=computed(()=>[...items.value].sort((a,b)=>b.flags.length-a.flags.length));
watch([()=>props.storeId,()=>props.workDate],load,{
  immediate:true
});
/** 현재 점포만 사용한 제품별 분석과 추천을 조회합니다. */
async function load(){
  if(!props.storeId)return;
  try{
    const{
      data
    }=await window.axios.get('/tillwhite/api/production-management/analysis',{
      params:{
        store_id:props.storeId,date:props.workDate
      }
    });
    items.value=data.items||[];
  } catch(error){
    emit('error',error.response?.data?.message||'분석 데이터를 불러오지 못했습니다.');
  }
}
/** 추천 데이터가 부족하면 숫자를 만들지 않고 상태를 그대로 표시합니다. */
function recommendationText(r){
  return r?.min==null?'데이터 부족':`${r.min}~${r.max}개`;
}
/** 내부 신뢰도 코드를 사용자가 이해하기 쉬운 문구로 변환합니다. */
function confidenceText(value){
  return{
    insufficient:'데이터 부족',low:'낮음',normal:'보통',high:'높음'
  }[value]||'-';
}
</script><style scoped>
.recommendation {
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:8px
}
.recommendation>div {
  padding:12px;
  border:1px solid rgba(var(--v-border-color),var(--v-border-opacity));
  border-radius:10px
}
.recommendation span,.recommendation strong {
  display:block
}
.recommendation span {
  font-size:.76rem;
  color:rgba(var(--v-theme-on-surface),.62)
}
.recommendation strong {
  font-size:1.15rem;
  margin-top:4px
}
</style>
