import { createRouter, createWebHistory } from 'vue-router';
import LoginPage from '../pages/Auth/LoginPage.vue';

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
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;