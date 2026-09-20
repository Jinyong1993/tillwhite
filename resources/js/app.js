import './bootstrap';

import { createApp } from 'vue';
import { createVuetify } from 'vuetify';

import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';

import App from './App.vue';
import router from './router';

import '@mdi/font/css/materialdesignicons.css';

const vuetify = createVuetify();

createApp(App)
    .use(vuetify)
    .use(router)
    .mount('#app');