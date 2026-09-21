<?php
namespace App\Http\Controllers;
use App\Models\ProductionRecord;
use App\Models\Product;
use App\Models\User;
use App\Services\AccessService;
use App\Services\AuditService;
use App\Services\Scopes\ProductionRecordScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ProductionRecordController extends Controller {
    public function __construct(private ProductionRecordScope $scope, private AccessService $access, private AuditService $audit) {}
    /** 권한 범위 내 생산·폐기·로스 기록 목록 */
    public function index(Request $request) {
        $this->access->requirePermission($request->user(),'production.view');
        $q=$this->scope->apply($request->user())->with(['store:id,name','product:id,name','worker:id,name'])->orderByDesc('work_date')->orderByDesc('id');
        if($request->filled('date')) $q->whereDate('work_date',$request->date);
        return response()->json(['records'=>$q->limit(200)->get()]);
    }
    /** 입력 화면에 필요한 제품과 작업자 목록 */
    public function options(Request $request) {
        $u=$request->user(); $this->access->requirePermission($u,'production.view');
        $products=$this->access->scopeStore(Product::query(),$u)->where('is_active',true)->orderBy('sort_order')->get(['id','store_id','name','production_department']);
        $users=User::query()->where('is_active',true)->where('employment_status','active');
        if(!$u->isHeadOffice() && $u->role?->code!=='super_admin') $users->where('store_id',$u->store_id)->where('department',$u->department);
        return response()->json(['products'=>$products,'workers'=>$users->orderBy('name')->get(['id','name','store_id','department'])]);
    }
    /** 생산·폐기·로스 기록 등록 */
    public function store(Request $request) {
        $u=$request->user(); $this->access->requirePermission($u,'production.create');
        $v=$this->validated($request); $worker=User::findOrFail($v['worker_id']); $product=Product::findOrFail($v['product_id']);
        $this->access->assertStoreDepartment($u,$worker->store_id,$worker->department);
        abort_unless($product->store_id===$worker->store_id,422,'제품과 작업자의 점포가 일치하지 않습니다.');
        $record=ProductionRecord::create([...$v,'store_id'=>$worker->store_id,'department'=>$worker->department,'created_by'=>$u->id]);
        $this->audit->log($u,'production','create',ProductionRecord::class,$record->id,null,$record->toArray(),'생산·폐기·로스 기록 등록');
        return response()->json(['message'=>'기록이 등록되었습니다.','record'=>$record],201);
    }
    /** 생산·폐기·로스 기록 수정 */
    public function update(Request $request, ProductionRecord $productionRecord) {
        $u=$request->user(); $this->access->requirePermission($u,'production.update'); $this->access->assertStoreDepartment($u,$productionRecord->store_id,$productionRecord->department);
        $old=$productionRecord->toArray(); $v=$this->validated($request); unset($v['worker_id'],$v['product_id']); $productionRecord->update([...$v,'updated_by'=>$u->id]);
        $this->audit->log($u,'production','update',ProductionRecord::class,$productionRecord->id,$old,$productionRecord->fresh()->toArray(),'생산·폐기·로스 기록 수정');
        return response()->json(['message'=>'기록이 수정되었습니다.']);
    }
    /** 생산·폐기·로스 기록 Soft Delete */
    public function destroy(Request $request, ProductionRecord $productionRecord) {
        $u=$request->user(); $this->access->requirePermission($u,'production.delete'); $this->access->assertStoreDepartment($u,$productionRecord->store_id,$productionRecord->department);
        $old=$productionRecord->toArray(); $productionRecord->delete(); $this->audit->log($u,'production','delete',ProductionRecord::class,$productionRecord->id,$old,null,'생산·폐기·로스 기록 삭제');
        return response()->json(['message'=>'기록이 삭제되었습니다.']);
    }
    /** 공통 입력값 검증 */
    private function validated(Request $request): array {
        $v=$request->validate(['work_date'=>['required','date'],'product_id'=>['required','integer','exists:products,id'],'worker_id'=>['required','integer','exists:users,id'],'production_quantity'=>['required','integer','min:0'],'waste_quantity'=>['required','integer','min:0'],'loss_quantity'=>['required','integer','min:0'],'waste_reason'=>['nullable','string','max:255'],'waste_note'=>['nullable','string','max:2000'],'loss_reason'=>['nullable','string','max:255'],'loss_note'=>['nullable','string','max:2000'],'note'=>['nullable','string','max:2000']]);
        if(($v['production_quantity']+$v['waste_quantity']+$v['loss_quantity'])===0) abort(422,'생산량, 폐기량, 로스량 중 하나 이상 입력해주세요.');
        if($v['waste_quantity']>0 && empty($v['waste_reason'])) abort(422,'폐기량이 있으면 폐기 사유를 입력해주세요.');
        if($v['loss_quantity']>0 && empty($v['loss_reason'])) abort(422,'로스량이 있으면 로스 사유를 입력해주세요.');
        return $v;
    }
}
