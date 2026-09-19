import { createRouter, createWebHistory } from 'vue-router';
import LoginPage from '../pages/Auth/LoginPage.vue';
import MainPage from '../pages/MainPage.vue';

const routes = [
  {
    path: '/tillwhite',
    redirect: '/tillwhite/login',
  },
  {
    path: '/tillwhite/login',
    name: 'login',
    component: LoginPage,
  },
  {
    path: '/tillwhite/main',
    name: 'main',
    component: MainPage,
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;