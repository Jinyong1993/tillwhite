<template>
  <!--
    공통 페이지 카드

    Till White의 모든 주요 화면에서 사용하는
    공통 카드 디자인을 관리한다.

    로그인 화면을 불변의 기준 디자인으로 사용하며
    카드의 너비, 정렬, 제목 및 본문 배경을
    모든 페이지에서 동일하게 유지한다.

    페이지별 부제목은 subtitle 속성을 통해
    각 화면에서 필요한 이름을 전달받아 표시한다.
  -->
  <v-card
    class="mx-auto"
    width="100%"
    max-width="400"
  >
    <!--
      카드 상단 영역

      기본적으로 Till White 시스템 이름과
      현재 페이지의 부제목을 표시한다.

      header 슬롯이 전달된 경우에는
      해당 페이지에서 지정한 공통 헤더를 대신 표시한다.
    -->
    <slot name="header">
      <v-card-title
        class="font-weight-black"
      >
        Till White
      </v-card-title>

      <v-card-subtitle
        class="mb-3"
      >
        {{ subtitle }}
      </v-card-subtitle>
    </slot>

    <!--
      카드 본문 영역

      로그인 화면과 동일한 bg-surface-light 배경을 사용한다.

      각 페이지에서 전달한 실제 화면 내용은
      기본 슬롯을 통해 이 영역에 표시한다.
    -->
    <v-card-text
      class="bg-surface-light"
    >
      <slot />
    </v-card-text>
  </v-card>
</template>

<script setup>
/**
 * 공통 페이지 카드 속성
 *
 * subtitle:
 * 카드 상단에 표시할 현재 페이지의 이름이다.
 *
 * 별도로 전달하지 않은 경우에는 로그인 화면의
 * 기본 부제목인 '베이커리 관리 시스템'을 표시한다.
 */
defineProps({
  subtitle: {
    type: String,
    default: '베이커리 관리 시스템',
  },
});
</script>