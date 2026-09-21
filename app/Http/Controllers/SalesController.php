<?php
namespace App\Http\Controllers;
use App\Models\Product;
use App\Models\Sale;
use App\Services\AccessService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SalesController extends Controller {
 public function __construct(private AccessService $access,private AuditService $audit){}
 /** 권한 범위 내 매출 조회 */
 public function index(Request $r){$u=$r->user();$this->access->requirePermission($u,'sales.view');$q=$this->access->scopeStore(Sale::query(),$u)->with(['store:id,name','items.product:id,name','refunds'])->orderByDesc('sales_date')->orderByDesc('id');return response()->json(['sales'=>$q->limit(100)->get()]);}
 /** 일 매출 등록 */
 public function store(Request $r){$u=$r->user();$this->access->requirePermission($u,'sales.manage');$v=$r->validate(['store_id'=>['required','exists:stores,id'],'sales_date'=>['required','date'],'note'=>['nullable','string'],'items'=>['required','array','min:1'],'items.*.product_id'=>['required','exists:products,id'],'items.*.quantity'=>['required','integer','min:1'],'items.*.actual_unit_price'=>['required','integer','min:0']]);$this->access->assertStoreDepartment($u,(int)$v['store_id']);$sale=DB::transaction(function()use($v,$u){$sale=Sale::create(['store_id'=>$v['store_id'],'sales_date'=>$v['sales_date'],'status'=>'confirmed','confirmed_at'=>now(),'confirmed_by'=>$u->id,'note'=>$v['note']??null,'created_by'=>$u->id]);foreach($v['items'] as $row){$p=Product::findOrFail($row['product_id']);abort_unless($p->store_id===(int)$v['store_id'],422,'다른 점포 제품은 등록할 수 없습니다.');$price=(int)$row['actual_unit_price'];$qty=(int)$row['quantity'];$sale->items()->create(['product_id'=>$p->id,'sale_type'=>'regular','promotion_id'=>null,'quantity'=>$qty,'regular_unit_price'=>$price,'actual_unit_price'=>$price,'gross_amount'=>$price*$qty,'discount_amount'=>0,'net_amount'=>$price*$qty]);}return $sale;});$this->audit->log($u,'sales','create',Sale::class,$sale->id,null,$sale->load('items')->toArray(),'매출 등록');return response()->json(['message'=>'매출이 등록되었습니다.'],201);}
}
