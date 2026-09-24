<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionRecord extends Model
{
    use SoftDeletes;

    /**
     * 생산·폐기·로스 기록
     *
     * 하나의 기록에서 제품의 생산량, 폐기량, 로스량을
     * 함께 관리합니다.
     *
     * Soft Delete를 사용하여 기록 삭제 시
     * 실제 데이터를 DB에서 즉시 제거하지 않고
     * deleted_at을 통해 삭제 상태로 관리합니다.
     *
     * 이를 통해 잘못 삭제된 기록의 복구,
     * 과거 데이터 추적 및 감사 로그 확인이 가능합니다.
     */

    /**
     * 대량 할당 가능한 속성
     *
     * work_date          : 실제 작업이 발생한 날짜
     * store_id           : 작업이 발생한 점포
     * department         : 작업 당시 담당 부서
     * product_id         : 대상 제품
     * worker_id          : 실제 작업자
     * production_quantity: 생산량
     * waste_quantity     : 폐기량
     * loss_quantity      : 로스량
     * waste_reason       : 폐기 사유
     * waste_note         : 폐기 상세 내용
     * loss_reason        : 로스 사유
     * loss_note          : 로스 상세 내용
     * note               : 기록 전체 메모
     * created_by         : 최초 입력자
     * updated_by         : 마지막 수정자
     *
     * 실제 입력값 검증과 데이터 접근 범위는
     * Controller, AccessService 및 Scope에서 처리합니다.
     */
    protected $fillable = [
        'work_date',
        'store_id',
        'department',
        'product_id',
        'worker_id',
        'production_quantity',
        'waste_quantity',
        'loss_quantity',
        'waste_reason',
        'waste_note',
        'loss_reason',
        'loss_note',
        'note',
        'created_by',
        'updated_by',
    ];

    /**
     * 생산 기록 컬럼의 타입 변환 설정
     *
     * work_date           : 실제 작업일을 date로 변환
     * production_quantity : 생산량을 integer로 변환
     * waste_quantity      : 폐기량을 integer로 변환
     * loss_quantity       : 로스량을 integer로 변환
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'production_quantity' => 'integer',
            'waste_quantity' => 'integer',
            'loss_quantity' => 'integer',
        ];
    }

    /**
     * 기록이 발생한 점포
     *
     * production_records.store_id를 기준으로
     * ProductionRecord와 Store를 연결합니다.
     *
     * 직원이 이후 다른 점포로 이동하더라도
     * 기록 당시의 점포 정보는 그대로 유지됩니다.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 생산·폐기·로스 대상 제품
     *
     * production_records.product_id를 기준으로
     * ProductionRecord와 Product를 연결합니다.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 실제 작업자
     *
     * production_records.worker_id를 기준으로
     * 실제 작업을 수행한 User를 연결합니다.
     *
     * 실제 작업자와 시스템 입력자는 다를 수 있습니다.
     *
     * 예:
     * 직원 A가 실제 생산 작업 수행
     * 헤드 셰프 B가 대신 시스템에 기록
     *
     * worker_id  → 직원 A
     * created_by → 헤드 셰프 B
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'worker_id'
        );
    }

    /**
     * 최초 입력자
     *
     * production_records.created_by를 기준으로
     * 해당 기록을 시스템에 최초 등록한 User를 연결합니다.
     *
     * 실제 작업자인 worker와는 별도로 관리합니다.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /**
     * 마지막 수정자
     *
     * production_records.updated_by를 기준으로
     * 해당 기록을 마지막으로 수정한 User를 연결합니다.
     *
     * 한 번도 수정되지 않은 기록은
     * updated_by가 NULL일 수 있습니다.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }
}