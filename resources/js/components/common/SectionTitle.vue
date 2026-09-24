<template>
  <!--
    각 페이지 내부의 영역 제목을 표시하는 공통 컴포넌트입니다.
    아이콘, 제목, 우측 추가 영역을 한 줄로 배치합니다.
  -->
  <div class="d-flex align-center mb-2">
    <!-- icon 값이 있을 때만 제목 왼쪽에 아이콘 표시 -->
    <v-icon
      v-if="icon"
      :icon="icon"
      size="small"
      class="mr-2"
    />

    <!-- 영역 제목 -->
    <div class="text-subtitle-2 font-weight-bold">
      {{ title }}
    </div>

    <!-- 제목과 우측 영역 사이의 남은 공간을 채움 -->
    <v-spacer />

    <!--
      제목 오른쪽에 추가 내용을 표시하는 영역입니다.
      버튼이나 기타 기능을 필요한 페이지에서 전달할 수 있습니다.
    -->
    <slot />
  </div>
</template>

<script setup>
/**
 * SectionTitle에서 사용하는 속성입니다.
 *
 * title : 화면에 표시할 영역 제목
 * icon  : 제목 왼쪽에 표시할 Material Design Icons 아이콘
 */
defineProps({
  title: {
    type: String,
    required: true,
  },
  icon: {
    type: String,
    default: '',
  },
});
</script>