/**
 * 사용자에게 이름 목록을 보여줄 때 사용하는 공통 자연 정렬입니다.
 * 한글 → 영문 → 숫자 → 기타 순서를 우선하고, 같은 그룹에서는 숫자를 자연스럽게 비교합니다.
 */
const collator = new Intl.Collator('ko-KR', {
  numeric: true,
  sensitivity: 'base',
});

// 이름의 첫 글자를 한글·영문·숫자·기타 그룹으로 분류해 자연 정렬 우선순위를 만듭니다.
function textGroup(value) {
  const first = String(value ?? '').trim().charAt(0);

  if (/[가-힣ㄱ-ㅎㅏ-ㅣ]/.test(first)) return 0;
  if (/[A-Za-z]/.test(first)) return 1;
  if (/[0-9]/.test(first)) return 2;
  return 3;
}

export function compareDisplayName(left, right) {
  const leftText = String(left ?? '').trim();
  const rightText = String(right ?? '').trim();
  const groupDifference = textGroup(leftText) - textGroup(rightText);

  return groupDifference || collator.compare(leftText, rightText);
}

export function sortByDisplayName(items, selector = (item) => item?.name) {
  return [...items].sort((left, right) => {
    return compareDisplayName(selector(left), selector(right));
  });
}
