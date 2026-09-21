import { createRouter, createWebHistory } from 'vue-router';
import LoginPage from '../pages/Auth/LoginPage.vue';
import MainPage from '../pages/MainPage.vue';
import ProductionPage from '../pages/Production/ProductionPage.vue';
import WorkPage from '../pages/Work/WorkPage.vue';
import ProductPage from '../pages/Product/ProductPage.vue';
import SalesPage from '../pages/Sales/SalesPage.vue';
import EmployeePage from '../pages/Employee/EmployeePage.vue';
import StorePage from '../pages/Store/StorePage.vue';
import SystemPage from '../pages/System/SystemPage.vue';
import AuditPage from '../pages/System/AuditPage.vue';

/**
 * Till White SPA 라우트
 *
 * 로그인 화면을 제외한 모든 업무 화면은 Laravel 세션 인증이 필요하다.
 */
const routes=[
 {path:'/tillwhite',redirect:'/tillwhite/login'},
 {path:'/tillwhite/login',name:'login',component:LoginPage},
 {path:'/tillwhite/main',name:'main',component:MainPage,meta:{requiresAuth:true}},
 {path:'/tillwhite/production',component:ProductionPage,meta:{requiresAuth:true}},
 {path:'/tillwhite/work',component:WorkPage,meta:{requiresAuth:true}},
 {path:'/tillwhite/products',component:ProductPage,meta:{requiresAuth:true}},
 {path:'/tillwhite/sales',component:SalesPage,meta:{requiresAuth:true}},
 {path:'/tillwhite/employees',component:EmployeePage,meta:{requiresAuth:true}},
 {path:'/tillwhite/stores',component:StorePage,meta:{requiresAuth:true}},
 {path:'/tillwhite/system',component:SystemPage,meta:{requiresAuth:true}},
 {path:'/tillwhite/audits',component:AuditPage,meta:{requiresAuth:true}},
];
const router=createRouter({history:createWebHistory(),routes});
router.beforeEach(async to=>{if(!to.meta.requiresAuth)return true;try{const r=await fetch('/tillwhite/auth/me',{headers:{Accept:'application/json'}});return r.ok?true:{name:'login'};}catch{return{name:'login'};}});
export default router;
