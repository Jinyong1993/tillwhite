<template>
  <!--
    로그인 사용자 정보

    현재 로그인한 사용자의 이름과
    소속 점포, 부서 및 직급 정보를 표시한다.

    사용자 정보의 화면 디자인을 한 곳에서 관리하여
    다른 페이지에서도 동일한 형태로 재사용할 수 있도록 한다.
  -->
  <div class="user-info">
    <!--
      사용자 정보 상단 영역

      현재 로그인한 사용자의 이름을 강조하여 표시하고
      오른쪽에는 사용자 아이콘을 표시한다.
    -->
    <div class="user-info-header">
      <div>
        <div class="text-h6 font-weight-bold">
          {{ user.name }}
        </div>

        <div class="text-body-2 text-medium-emphasis">
          로그인 사용자
        </div>
      </div>

      <v-icon
        icon="mdi-account-circle-outline"
        size="36"
      />
    </div>

    <v-divider class="my-4" />

    <!--
      점포 정보

      현재 사용자가 소속된 점포를 표시한다.
    -->
    <div class="user-info-row">
      <div class="user-info-label">
        <v-icon
          icon="mdi-store-outline"
          size="20"
        />

        <span>
          점포
        </span>
      </div>

      <div class="font-weight-medium">
        {{ user.store.name }}
      </div>
    </div>

    <!--
      부서 정보

      DB에 저장된 부서 코드를
      사용자가 이해하기 쉬운 한글 이름으로 표시한다.
    -->
    <div class="user-info-row">
      <div class="user-info-label">
        <v-icon
          icon="mdi-account-group-outline"
          size="20"
        />

        <span>
          부서
        </span>
      </div>

      <div class="font-weight-medium">
        {{ departmentName }}
      </div>
    </div>

    <!--
      직급 정보

      현재 사용자에게 지정된 역할 이름을
      화면에서는 직급으로 표시한다.
    -->
    <div class="user-info-row">
      <div class="user-info-label">
        <v-icon
          icon="mdi-badge-account-outline"
          size="20"
        />

        <span>
          직급
        </span>
      </div>

      <div class="font-weight-medium">
        {{ user.role.name }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

/**
 * 사용자 정보 컴포넌트 속성
 *
 * user:
 * Laravel에서 전달받은 현재 로그인 사용자의
 * 이름, 점포, 부서 및 역할 정보를 전달받는다.
 */
const props = defineProps({
  user: {
    type: Object,
    required: true,
  },
});

/**
 * 부서 코드별 화면 표시 이름
 *
 * DB에는 변경하기 쉬운 영문 코드를 저장하고
 * 사용자 화면에서는 이해하기 쉬운 한글 이름으로 표시한다.
 */
const departmentNames = {
  kitchen: '주방',
  hall: '홀',
  operations: '운영진',
};

/**
 * 사용자 부서 표시 이름
 *
 * 등록된 부서 코드가 존재하면 한글 이름으로 변환한다.
 *
 * 예상하지 못한 부서 코드가 전달된 경우에는
 * 원래 부서 코드를 그대로 표시한다.
 */
const departmentName = computed(() => {
  return departmentNames[props.user.department] ?? props.user.department;
});
</script>

<style scoped>
/**
 * 사용자 정보 전체 영역
 *
 * 부모 카드의 전체 너비를 사용하여
 * 사용자 정보를 정리하여 표시한다.
 */
.user-info {
  width: 100%;
}

/**
 * 사용자 정보 상단 영역
 *
 * 사용자 이름과 아이콘을 양쪽에 배치하여
 * 현재 로그인 사용자를 쉽게 확인할 수 있도록 한다.
 */
.user-info-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

/**
 * 사용자 정보 한 줄
 *
 * 항목 이름은 왼쪽에 표시하고
 * 실제 값은 오른쪽에 표시한다.
 */
.user-info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 44px;
}

/**
 * 사용자 정보 항목 이름
 *
 * 아이콘과 항목 이름을 한 줄에 배치하고
 * 일정한 간격을 유지한다.
 */
.user-info-label {
  display: flex;
  align-items: center;
  gap: 8px;
  color: rgba(var(--v-theme-on-surface), 0.7);
}
</style>