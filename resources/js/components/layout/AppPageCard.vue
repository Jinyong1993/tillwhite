<template>
  <!--
    공통 페이지 카드

    Till White의 모든 주요 화면에서 사용하는
    공통 카드 디자인을 관리한다.

    로그인 화면을 불변의 기준 디자인으로 사용하며
    카드의 너비, 정렬, 제목, 부제목 및 본문 배경을
    모든 페이지에서 동일하게 유지한다.
  -->
  <v-card
    class="mx-auto"
    width="100%"
    max-width="400"
  >
    <!--
      카드 상단 영역

      기본적으로 로그인 화면과 동일하게
      Till White 시스템 이름과 부제목을 표시한다.

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
        베이커리 관리 시스템
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