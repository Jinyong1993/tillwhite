<template>
<div>
  <div class="d-flex flex-wrap ga-2 mb-4">
    <v-text-field v-model="from" type="date" label="시작일" variant="outlined" density="compact" hide-details/>
    <v-text-field v-model="to" type="date" label="종료일" variant="outlined" density="compact" hide-details/>
    <v-btn variant="outlined" @click="load">조회</v-btn>
  </div>
  <div class="stat-cards mb-4">
    <div v-for="item in cards" :key="item.title">
      <span>{{ item.title }}</span>
      <strong>{{ item.value }}개</strong>
    </div>
  </div>
  <v-card variant="outlined" rounded="lg">
    <v-card-title>일별 흐름</v-card-title>
    <v-table density="compact">
      <thead>
        <tr>
          <th>날짜</th>
          <th>생산</th>
          <th>판매</th>
          <th>이월</th>
          <th>로스</th>
          <th>폐기</th>
          <th>폐기율</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in series" :key="row.date">
          <td>{{ row.date }}</td>
          <td>{{ row.production }}</td>
          <td>{{ row.sale }}</td>
          <td>{{ row.carryover }}</td>
          <td>{{ row.loss }}</td>
          <td>{{ row.waste }}</td>
          <td>{{ row.waste_rate==null?'-':`${row.waste_rate}%` }}</td>
        </tr>
      </tbody>
    </v-table>
  </v-card>
</div>
</template>
<script setup>
import {
  computed, ref, watch
}  from 'vue';
import {
  addLocalDays
}  from '../../utils/localDate';
const props=defineProps({
  storeId:Number,workDate:String
});
const emit=defineEmits(['error']);
const from=ref(addLocalDays(props.workDate,-9));
const to=ref(props.workDate);
const series=ref([]);
const totals=ref({
});
const cards=computed(()=>[{
  title:'생산',value:totals.value.production||0
},{
  title:'판매',value:totals.value.sale||0
},{
  title:'이월',value:totals.value.carryover||0
},{
  title:'로스',value:totals.value.loss||0
},{
  title:'폐기',value:totals.value.waste||0
}]);
watch(()=>props.storeId,load,{
  immediate:true
});
/** 선택 기간의 객관적인 일별 통계를 서버에서 조회합니다. */
async function load(){
  if(!props.storeId)return;
  try{
    const{
      data
    }=await window.axios.get('/tillwhite/api/production-management/statistics',{
      params:{
        store_id:props.storeId,from:from.value,to:to.value
      }
    });
    series.value=data.series||[];
    totals.value=data.totals||{
    };
  } catch(error){
    emit('error',error.response?.data?.message||'통계를 불러오지 못했습니다.');
  }
}
</script><style scoped>
.stat-cards {
  display:grid;
  grid-template-columns:repeat(5,minmax(0,1fr));
  gap:8px
}
.stat-cards>div {
  padding:12px;
  border:1px solid rgba(var(--v-border-color),var(--v-border-opacity));
  border-radius:10px
}
.stat-cards span,.stat-cards strong {
  display:block
}
.stat-cards span {
  font-size:.76rem;
  color:rgba(var(--v-theme-on-surface),.62)
}
.stat-cards strong {
  font-size:1.1rem;
  margin-top:4px
}
@media(max-width:700px) {
  .stat-cards {
    grid-template-columns:repeat(2,minmax(0,1fr))
  }
}
</style>
