const fallbackReasonLabels = {
    production_error: '생산 실수',
    shape_failure: '모양 불량',
    baking_failure: '굽기 불량',
    dough_issue: '재료·반죽 문제',
    damage: '파손',
    unsold: '당일 잔여',
    quality: '품질 저하',
    storage: '보관 문제',
    carryover_waste: '이월 후 폐기',
    tasting: '시식',
    service: '고객 서비스',
    gift: '무료 증정',
    staff_use: '직원 사용',
    other: '직접입력',
};

// 과거 데이터에 사유 문구가 비어 있어도 내부 코드 대신 현장용 한글 명칭을 표시합니다.
export function productionReasonLabel(item, options = []) {
    if (item?.reason_text) {
        return item.reason_text;
    }

    const option = options.find((candidate) => candidate.value === item?.reason_code);
    return option?.title || fallbackReasonLabels[item?.reason_code] || '사유 미지정';
}
