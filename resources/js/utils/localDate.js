/**
 * 브라우저의 로컬 시간대를 기준으로 YYYY-MM-DD 문자열을 만듭니다.
 * UTC 기반 toISOString()을 사용하지 않아 한국시간 자정 부근 날짜 오류를 방지합니다.
 */
export function toLocalDateString(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

// YYYY-MM-DD 날짜에 일수를 더하고 로컬 날짜 문자열로 반환합니다.
export function addLocalDays(value, amount) {
  const [year, month, day] = value.split('-').map(Number);
  const date = new Date(year, month - 1, day);
  date.setDate(date.getDate() + amount);
  return toLocalDateString(date);
}

// YYYY-MM-DD 날짜를 사용자가 읽기 쉬운 한국어 날짜로 표시합니다.
export function formatKoreanDate(value) {
  const [year, month, day] = value.split('-').map(Number);
  return new Intl.DateTimeFormat('ko-KR', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    weekday: 'short',
  }).format(new Date(year, month - 1, day));
}
