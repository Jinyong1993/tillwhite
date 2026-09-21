<?php
namespace Database\Seeders;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductionRecord;
use App\Models\Sale;
use App\Models\Store;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkCode;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;
class DemoDataSeeder extends Seeder {
 /** 데모 화면에서 즉시 확인할 수 있는 업무 샘플 데이터를 생성한다. */
 public function run(): void {
  foreach(Store::where('status','active')->get() as $store){
   foreach([['베이커리',10],['디저트',20],['음료',30]] as [$name,$sort]) ProductCategory::updateOrCreate(['store_id'=>$store->id,'name'=>$name],['sort_order'=>$sort,'is_active'=>true]);
   $bakery=ProductCategory::where('store_id',$store->id)->where('name','베이커리')->first();
   $dessert=ProductCategory::where('store_id',$store->id)->where('name','디저트')->first();
   $products=[['소금빵',$bakery,'kitchen',3500],['크루아상',$bakery,'kitchen',4200],['식빵',$bakery,'kitchen',5800],['바스크 치즈케이크',$dessert,'kitchen',6900]];
   foreach($products as $i=>[$name,$cat,$dept,$price]){$p=Product::updateOrCreate(['store_id'=>$store->id,'name'=>$name],['product_category_id'=>$cat->id,'production_department'=>$dept,'management_department'=>$dept,'sort_order'=>($i+1)*10,'is_active'=>true]);ProductPrice::updateOrCreate(['product_id'=>$p->id,'effective_from'=>now()->startOfYear()->toDateString()],['price'=>$price,'created_by'=>User::where('store_id',$store->id)->first()->id]);}
   foreach([['A','오픈',7,30,16,30],['B','미들',10,0,19,0],['C','마감',12,30,21,30]] as [$code,$name,$sh,$sm,$eh,$em]) WorkCode::updateOrCreate(['store_id'=>$store->id,'department'=>'kitchen','code'=>$code],['name'=>$name,'start_time'=>sprintf('%02d:%02d',$sh,$sm),'end_time'=>sprintf('%02d:%02d',$eh,$em),'break_minutes'=>60,'sort_order'=>ord($code),'is_active'=>true]);
   $workers=User::where('store_id',$store->id)->where('department','kitchen')->get();$productsQ=Product::where('store_id',$store->id)->get();
   foreach($workers as $wi=>$worker){$wc=WorkCode::where('store_id',$store->id)->where('department','kitchen')->orderBy('sort_order')->first();WorkSchedule::updateOrCreate(['user_id'=>$worker->id,'work_date'=>now()->toDateString()],['store_id'=>$store->id,'department'=>'kitchen','work_code_id'=>$wc?->id,'scheduled_start_time'=>'07:30','scheduled_end_time'=>'16:30','scheduled_break_minutes'=>60,'status'=>'work','source'=>'manual','created_by'=>$worker->id]);}
   foreach($productsQ as $i=>$p){$worker=$workers->first();if($worker)ProductionRecord::updateOrCreate(['work_date'=>now()->toDateString(),'store_id'=>$store->id,'product_id'=>$p->id,'worker_id'=>$worker->id],['department'=>'kitchen','production_quantity'=>30+$i*5,'waste_quantity'=>$i%2,'loss_quantity'=>$i===2?1:0,'waste_reason'=>$i%2?'판매 잔량':null,'loss_reason'=>$i===2?'성형 불량':null,'created_by'=>$worker->id]);}
  }
  SystemSetting::updateOrCreate(['key'=>'app.name'],['value'=>'Till White','type'=>'string','description'=>'시스템 표시 이름']);
  SystemSetting::updateOrCreate(['key'=>'day_off.monthly_limit'],['value'=>'10','type'=>'integer','description'=>'월 희망휴무 최대 신청일']);
 }
}
