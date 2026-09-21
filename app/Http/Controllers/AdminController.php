<?php
namespace App\Http\Controllers;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Store;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AccessService;
use App\Services\AuditService;
use Illuminate\Http\Request;
class AdminController extends Controller {
 public function __construct(private AccessService $access,private AuditService $audit){}
 /** 직원 목록 */
 public function employees(Request $r){$u=$r->user();$this->access->requirePermission($u,'employee.view');$q=User::with(['store:id,name','position:id,name','role:id,code,name'])->orderBy('name');if(!$u->isHeadOffice()&&$u->role?->code!=='super_admin')$q->where('store_id',$u->store_id);return response()->json(['employees'=>$q->get(),'stores'=>Store::where('status','active')->orderBy('sort_order')->get(['id','name']),'positions'=>Position::where('is_active',true)->orderBy('sort_order')->get(['id','name']),'roles'=>Role::where('is_active',true)->get(['id','code','name'])]);}
 /** 직원 등록 */
 public function employeeStore(Request $r){$u=$r->user();$this->access->requirePermission($u,'employee.manage');$v=$r->validate(['employee_code'=>['required','string','max:255','unique:users,employee_code'],'name'=>['required','string','max:255'],'password'=>['required','string','min:4'],'store_id'=>['nullable','exists:stores,id'],'department'=>['required','in:kitchen,hall,head_office'],'position_id'=>['nullable','exists:positions,id'],'role_id'=>['required','exists:roles,id']]);if($v['department']==='head_office')$v['store_id']=null;else abort_if($v['store_id']===null,422,'점포 직원은 점포를 선택해야 합니다.');$user=User::create([...$v,'employment_status'=>'active','is_active'=>true]);$this->audit->log($u,'employee','create',User::class,$user->id,null,$user->toArray(),'직원 등록');return response()->json(['message'=>'직원이 등록되었습니다.'],201);}
 /** 직원 재직/계정 상태 변경 */
 public function employeeStatus(Request $r,User $user){$actor=$r->user();$this->access->requirePermission($actor,'employee.manage');$v=$r->validate(['employment_status'=>['required','in:active,leave,resigned'],'is_active'=>['required','boolean']]);$old=$user->toArray();$user->update([...$v,'resigned_at'=>$v['employment_status']==='resigned'?now()->toDateString():null]);$this->audit->log($actor,'employee','update',User::class,$user->id,$old,$user->toArray(),'직원 상태 변경');return response()->json(['message'=>'직원 상태가 변경되었습니다.']);}
 /** 점포 목록 */
 public function stores(Request $r){$this->access->requirePermission($r->user(),'store.view');return response()->json(['stores'=>Store::orderBy('sort_order')->get()]);}
 /** 점포 등록 */
 public function storeStore(Request $r){$u=$r->user();$this->access->requirePermission($u,'store.manage');$v=$r->validate(['store_code'=>['required','string','unique:stores,store_code'],'name'=>['required','string','unique:stores,name'],'phone'=>['nullable','string'],'address'=>['nullable','string'],'attendance_radius_meters'=>['required','integer','min:10'],'status'=>['required','in:active,inactive,closed']]);$s=Store::create($v);$this->audit->log($u,'store','create',Store::class,$s->id,null,$s->toArray(),'점포 등록');return response()->json(['message'=>'점포가 등록되었습니다.'],201);}
 /** 시스템 설정, 역할, 권한, 직급 */
 public function system(Request $r){$this->access->requirePermission($r->user(),'system.view');return response()->json(['settings'=>SystemSetting::orderBy('key')->get(),'roles'=>Role::with('permissions:id,code,name')->get(),'permissions'=>Permission::where('is_active',true)->orderBy('code')->get(),'positions'=>Position::orderBy('sort_order')->get()]);}
 /** 시스템 설정 저장 */
 public function setting(Request $r){$u=$r->user();$this->access->requirePermission($u,'system.manage');$v=$r->validate(['key'=>['required','string','max:255'],'value'=>['required'],'type'=>['required','in:string,integer,boolean,json'],'description'=>['nullable','string']]);$s=SystemSetting::updateOrCreate(['key'=>$v['key']],[...$v,'value'=>is_array($v['value'])?json_encode($v['value'],JSON_UNESCAPED_UNICODE):(string)$v['value'],'updated_by'=>$u->id]);$this->audit->log($u,'system','save',SystemSetting::class,$s->id,null,$s->toArray(),'시스템 설정 저장');return response()->json(['message'=>'설정이 저장되었습니다.']);}
 /** 역할 권한 동기화 */
 public function rolePermissions(Request $r,Role $role){$u=$r->user();$this->access->requirePermission($u,'system.manage');$v=$r->validate(['permissions'=>['required','array'],'permissions.*'=>['string','exists:permissions,code']]);$ids=Permission::whereIn('code',$v['permissions'])->pluck('id');$role->permissions()->sync($ids);$this->audit->log($u,'system','permissions',Role::class,$role->id,null,['permissions'=>$v['permissions']],'역할 권한 변경');return response()->json(['message'=>'역할 권한이 저장되었습니다.']);}
 /** 감사 로그 조회 */
 public function audits(Request $r){$u=$r->user();$this->access->requirePermission($u,'audit.view');$q=AuditLog::with('user:id,name,store_id')->orderByDesc('id');if(!$u->isHeadOffice()&&$u->role?->code!=='super_admin'){$ids=User::where('store_id',$u->store_id)->pluck('id');$q->whereIn('user_id',$ids);}return response()->json(['logs'=>$q->limit(300)->get()]);}
}
