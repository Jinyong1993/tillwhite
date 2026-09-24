<template>
  <!--
    로그인 사용자 정보

    현재 로그인한 사용자의 이름과
    소속 점포, 부서, 직급 정보를 표시한다.

    사용자 정보의 표시 형식을 한 곳에서 관리하여
    여러 페이지에서 동일한 형태로 재사용할 수 있도록 한다.
  -->
  <div class="user-info">
    <!--
      사용자 정보 상단 영역

      현재 로그인한 사용자의 이름을 강조하여 표시하고
      오른쪽에는 사용자 아이콘을 표시한다.
    -->
    <div class="user-info-header">
      <div>
        <!-- 사용자 이름 -->
        <div class="text-h6 font-weight-bold">
          {{ user.name }}
        </div>

        <!-- 사용자 정보 안내 -->
        <div class="text-body-2 text-medium-emphasis">
          로그인 사용자
        </div>
      </div>

      <!-- 사용자 아이콘 -->
      <v-icon
        icon="mdi-account-circle-outline"
        size="36"
      />
    </div>

    <!-- 상단 사용자 정보와 상세 소속 정보 구분선 -->
    <v-divider class="my-4" />

    <!--
      점포 정보

      현재 사용자가 소속된 점포 이름을 표시한다.

      본사 직원처럼 특정 점포에 소속되지 않은 사용자는
      store가 NULL이므로 '-'를 표시한다.

      본사 여부는 아래 부서 정보에서 별도로 표시하므로
      점포 항목에는 '본사'를 중복해서 표시하지 않는다.
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
        {{ user.store?.name ?? '-' }}
      </div>
    </div>

    <!--
      부서 정보

      DB에 저장된 부서 코드를
      사용자가 이해하기 쉬운 한글 이름으로 변환하여 표시한다.
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

      현재 사용자에게 지정된 Position의 이름을 표시한다.

      Position은 사원, 주임, 대리, 과장 등
      회사 내 직급을 의미하며 시스템 권한을 결정하는 Role과는 다르다.

      직급이 지정되지 않은 경우에는 '미지정'을 표시한다.
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
        {{ user.position?.name ?? '미지정' }}
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
 * - Laravel에서 전달받은 현재 로그인 사용자 정보
 * - 이름, 점포, 부서, 직급 등의 정보를 화면에 표시한다.
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
 * DB에서는 부서를 영문 코드로 관리하고
 * 사용자 화면에서는 이해하기 쉬운 한글 이름으로 표시한다.
 *
 * kitchen:
 * - 주방
 *
 * hall:
 * - 홀
 *
 * head_office:
 * - 본사
 */
const departmentNames = {
  kitchen: '주방',
  hall: '홀',
  head_office: '본사',
};

/**
 * 사용자 부서 표시 이름
 *
 * 등록된 부서 코드이면 한글 이름으로 변환한다.
 *
 * 예상하지 못한 새로운 부서 코드가 전달되면
 * 화면이 비어버리지 않도록 원래 코드를 그대로 표시한다.
 */
const departmentName = computed(() => {
  return departmentNames[props.user.department] ?? props.user.department;
});
</script>

<style scoped>
/**
 * 사용자 정보 전체 영역
 *
 * 부모 영역의 전체 너비를 사용한다.
 */
.user-info {
  width: 100%;
}

/**
 * 사용자 정보 상단 영역
 *
 * 사용자 이름과 사용자 아이콘을
 * 좌우로 배치한다.
 */
.user-info-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

/**
 * 사용자 정보 한 줄
 *
 * 점포, 부서, 직급의 항목 이름은 왼쪽에,
 * 실제 값은 오른쪽에 배치한다.
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
 * 일정한 간격과 보조 텍스트 색상을 적용한다.
 */
.user-info-label {
  display: flex;
  align-items: center;
  gap: 8px;
  color: rgba(var(--v-theme-on-surface), 0.7);
}
</style>