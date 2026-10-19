<template>
  <header
    class="app-dialog-header d-flex align-center px-4 py-3"
  >
    <!--
      헤더는 제목과 부제목 표시만 담당합니다.
      닫기·취소 등의 사용자 작업은 공통 푸터에서 처리합니다.

      제목이나 설명이 길어져도 320px 모바일 화면에서
      가로 넘침이 발생하지 않도록 유연한 Flex 구조를 사용합니다.
    -->
    <div
      class="app-dialog-header-content d-flex flex-column flex-grow-1 ga-1"
    >
      <div
        v-if="showTitle && title"
        class="text-subtitle-1 font-weight-bold"
      >
        {{ title }}
      </div>

      <!--
        부제목은 제목보다 작은 글꼴을 사용합니다.
        긴 설명은 생략하지 않고 자연스럽게 줄바꿈합니다.
      -->
      <div
        v-if="subtitle"
        class="text-caption text-medium-emphasis"
      >
        {{ subtitle }}
      </div>

      <!--
        기존 헤더 슬롯은 호환성을 위해 유지합니다.
        추가 안내 내용이 필요한 경우에만 사용합니다.
      -->
      <slot />
    </div>
  </header>
</template>

<script setup>
defineProps({
  showTitle: {
    type: Boolean,
    default: true,
  },

  title: {
    type: String,
    default: '',
  },

  subtitle: {
    type: String,
    default: '',
  },
});
</script>