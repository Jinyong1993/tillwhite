<?php
namespace App\Http\Controllers;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\Recipe;
use App\Services\AccessService;
use App\Services\AuditService;
use Illuminate\Http\Request;
class ProductController extends Controller {
 public function __construct(private AccessService $access,private AuditService $audit){}
 /** 제품, 카테고리, 가격, 레시피를 한 화면에서 사용할 수 있도록 반환한다. */
 public function index(Request $r){$u=$r->user();$this->access->requirePermission($u,'product.view');$q=$this->access->scopeStore(Product::query(),$u)->with(['store:id,name','category:id,name','prices'=>fn($q)=>$q->orderByDesc('effective_from'),'recipes.ingredients','recipes.steps'])->orderBy('sort_order');return response()->json(['products'=>$q->get(),'categories'=>$this->access->scopeStore(ProductCategory::query(),$u)->orderBy('sort_order')->get()]);}
 /** 제품 등록 */
 public function store(Request $r){$u=$r->user();$this->access->requirePermission($u,'product.manage');$v=$r->validate(['store_id'=>['required','integer','exists:stores,id'],'product_category_id'=>['required','integer','exists:product_categories,id'],'name'=>['required','string','max:255'],'production_department'=>['required','in:kitchen,hall'],'management_department'=>['required','in:kitchen,hall'],'sort_order'=>['nullable','integer','min:0'],'price'=>['nullable','integer','min:0']]);$this->access->assertStoreDepartment($u,(int)$v['store_id'],$v['management_department']);$price=$v['price']??null;unset($v['price']);$p=Product::create([...$v,'sort_order'=>$v['sort_order']??0,'is_active'=>true]);if($price!==null)ProductPrice::create(['product_id'=>$p->id,'price'=>$price,'effective_from'=>now()->toDateString(),'created_by'=>$u->id]);$this->audit->log($u,'product','create',Product::class,$p->id,null,$p->toArray(),'제품 등록');return response()->json(['message'=>'제품이 등록되었습니다.'],201);}
 /** 제품 사용 상태 변경 */
 public function toggle(Request $r,Product $product){$u=$r->user();$this->access->requirePermission($u,'product.manage');$this->access->assertStoreDepartment($u,$product->store_id,$product->management_department);$old=$product->toArray();$product->update(['is_active'=>!$product->is_active]);$this->audit->log($u,'product','update',Product::class,$product->id,$old,$product->toArray(),'제품 사용 상태 변경');return response()->json(['message'=>'제품 상태가 변경되었습니다.']);}
 /** 레시피 등록 */
 public function recipe(Request $r,Product $product){$u=$r->user();$this->access->requirePermission($u,'recipe.manage');$this->access->assertStoreDepartment($u,$product->store_id,$product->management_department);$v=$r->validate(['name'=>['required','string','max:255'],'description'=>['nullable','string'],'ingredients'=>['array'],'ingredients.*.name'=>['required','string'],'ingredients.*.quantity'=>['required','numeric','min:0'],'ingredients.*.unit'=>['required','string'],'steps'=>['array'],'steps.*.description'=>['required','string']]);$recipe=Recipe::create(['store_id'=>$product->store_id,'product_id'=>$product->id,'department'=>$product->management_department,'name'=>$v['name'],'description'=>$v['description']??null,'is_active'=>true,'created_by'=>$u->id]);foreach($v['ingredients']??[] as $i=>$row)$recipe->ingredients()->create([...$row,'sort_order'=>$i]);foreach($v['steps']??[] as $i=>$row)$recipe->steps()->create([...$row,'sort_order'=>$i]);$this->audit->log($u,'recipe','create',Recipe::class,$recipe->id,null,$recipe->toArray(),'레시피 등록');return response()->json(['message'=>'레시피가 등록되었습니다.'],201);}
}
