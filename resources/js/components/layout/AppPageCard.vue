<template>
  <!--
    Till White 공통 페이지 카드

    로그인 화면의 카드 디자인을 기준으로
    주요 업무 화면의 전체적인 디자인을 통일한다.

    공통으로 관리하는 항목:
    - 카드 중앙 정렬
    - 카드 너비
    - 최대 너비 400px
    - 시스템 제목
    - 페이지 부제목
    - 본문 배경

    각 페이지에서는 카드 디자인을 직접 반복해서 작성하지 않고
    AppPageCard를 사용하여 동일한 화면 구조를 유지한다.
  -->
  <v-card
    class="mx-auto"
    width="100%"
    max-width="400"
  >
    <!--
      카드 상단 영역

      기본 상태에서는
      Till White 시스템 이름과 subtitle을 표시한다.

      header 슬롯이 전달되면 기본 헤더 대신
      슬롯으로 전달된 헤더를 표시한다.

      로그인 이후 업무 화면에서는
      AppHeader를 header 슬롯으로 전달하여
      메뉴 버튼이 포함된 공통 헤더를 사용할 수 있다.
    -->
    <slot name="header">
      <!-- 시스템 이름 -->
      <v-card-title class="font-weight-black">
        Till White
      </v-card-title>

      <!-- 현재 페이지 이름 또는 기본 시스템 설명 -->
      <v-card-subtitle class="mb-3">
        {{ subtitle }}
      </v-card-subtitle>
    </slot>

    <!--
      카드 본문 영역

      로그인 화면과 동일하게
      bg-surface-light 배경을 사용한다.

      각 페이지의 실제 화면 내용은
      기본 슬롯을 통해 이 영역에 표시한다.
    -->
    <v-card-text class="bg-surface-light">
      <slot />
    </v-card-text>
  </v-card>
</template>

<script setup>
/**
 * 공통 페이지 카드 속성
 *
 * subtitle:
 * - 카드 상단에 표시할 부제목
 * - 업무 화면에서는 현재 페이지 이름을 전달할 수 있다.
 * - 별도로 전달하지 않으면 로그인 화면과 동일하게
 *   '베이커리 관리 시스템'을 표시한다.
 *
 * header 슬롯을 사용하는 경우에는
 * 슬롯의 내용이 기본 헤더를 대체하므로
 * 이 subtitle이 화면에 직접 표시되지 않을 수도 있다.
 */
defineProps({
  subtitle: {
    type: String,
    default: '베이커리 관리 시스템',
  },
});
</script>