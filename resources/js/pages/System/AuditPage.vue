<template>
  <AppShell :title="pageTitle">
    <template #default>
      <!-- 감사 로그 목록 -->
      <v-list lines="three">
        <v-list-item
          v-for="log in logs"
          :key="log.id"
          :title="`${log.domain} · ${log.action}`"
          :subtitle="`${log.user?.name ?? '시스템'} · ${log.description ?? ''} · ${log.created_at}`"
        />
      </v-list>

      <!-- 조회된 감사 로그가 없는 경우 표시 -->
      <v-empty-state
        v-if="!logs.length"
        title="감사 로그가 없습니다."
        icon="mdi-history"
      />
    </template>
  </AppShell>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import AppShell from '../../components/layout/AppShell.vue';

// 현재 페이지 제목
const pageTitle = '감사 로그';

// 서버에서 조회한 감사 로그 목록
const logs = ref([]);

/**
 * 화면이 처음 열릴 때 감사 로그를 서버에서 조회합니다.
 *
 * 서버에서는 현재 로그인한 사용자의 권한과 범위에 맞는
 * 감사 로그만 반환하도록 처리합니다.
 */
onMounted(async () => {
  const response = await window.axios.get(
    '/tillwhite/api/audits',
  );

  logs.value = response.data.logs;
});
</script>