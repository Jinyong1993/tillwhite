<template>
  <v-navigation-drawer v-model="drawer" location="left" width="300" temporary>
    <v-list density="comfortable" nav>
      <v-list-subheader class="font-weight-black">Till White</v-list-subheader>
      <template v-for="item in visibleItems" :key="item.title">
        <v-list-item :prepend-icon="item.icon" :title="item.title" :to="item.to" @click="drawer=false" />
      </template>
      <v-divider class="my-2" />
      <v-list-item prepend-icon="mdi-logout" title="로그아웃" :loading="isLoggingOut" :disabled="isLoggingOut" @click="logout" />
    </v-list>
  </v-navigation-drawer>
</template>
<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useSession } from '../../composables/useSession';
const props=defineProps({modelValue:{type:Boolean,default:false}});const emit=defineEmits(['update:modelValue','error','loading']);const router=useRouter();const {can,clear}=useSession();const isLoggingOut=ref(false);
const drawer=computed({get:()=>props.modelValue,set:v=>emit('update:modelValue',v)});
const items=[
 {title:'메인',icon:'mdi-home-outline',to:'/tillwhite/main'},
 {title:'생산·폐기 관리',icon:'mdi-baguette',to:'/tillwhite/production',permission:'production.view'},
 {title:'근무 관리',icon:'mdi-calendar-clock',to:'/tillwhite/work',permission:'schedule.view'},
 {title:'제품 관리',icon:'mdi-food-croissant',to:'/tillwhite/products',permission:'product.view'},
 {title:'매출 관리',icon:'mdi-cash-register',to:'/tillwhite/sales',permission:'sales.view'},
 {title:'직원 관리',icon:'mdi-account-group-outline',to:'/tillwhite/employees',permission:'employee.view'},
 {title:'점포 관리',icon:'mdi-store-outline',to:'/tillwhite/stores',permission:'store.view'},
 {title:'시스템',icon:'mdi-cog-outline',to:'/tillwhite/system',permission:'system.view'},
 {title:'감사 로그',icon:'mdi-history',to:'/tillwhite/audits',permission:'audit.view'},
];
const visibleItems=computed(()=>items.filter(i=>!i.permission||can(i.permission)));
async function logout(){isLoggingOut.value=true;drawer.value=false;emit('loading',true);try{await window.axios.post('/tillwhite/logout');clear();await router.push({name:'login'});}catch(e){emit('loading',false);emit('error',e.response?.data?.message??'로그아웃 중 오류가 발생했습니다.');isLoggingOut.value=false;}}
</script>
