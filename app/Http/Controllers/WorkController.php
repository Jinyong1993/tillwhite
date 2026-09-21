<?php
namespace App\Http\Controllers;
use App\Models\Attendance;
use App\Models\DayOffRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\WorkCode;
use App\Models\WorkSchedule;
use App\Services\AccessService;
use App\Services\AuditService;
use Illuminate\Http\Request;
class WorkController extends Controller {
 public function __construct(private AccessService $access,private AuditService $audit){}
 /** 근무 관리 대시보드 데이터 */
 public function index(Request $r){$u=$r->user();$this->access->requirePermission($u,'schedule.view');$sq=WorkSchedule::with(['user:id,name','store:id,name','workCode:id,code,name']);$this->scopeWork($sq,$u);$aq=Attendance::with(['user:id,name','store:id,name']);$this->scopeWork($aq,$u);$lq=LeaveRequest::with('user:id,name');$dq=DayOffRequest::with(['user:id,name','dates']);if(!$u->isHeadOffice()&&$u->role?->code!=='super_admin'){$ids=User::where('store_id',$u->store_id)->where('department',$u->department)->pluck('id');$lq->whereIn('user_id',$ids);$dq->whereIn('user_id',$ids);}elseif($u->role?->code!=='super_admin'){$ids=User::where('department','head_office')->pluck('id');$lq->whereIn('user_id',$ids);$dq->whereIn('user_id',$ids);}return response()->json(['schedules'=>$sq->orderByDesc('work_date')->limit(100)->get(),'attendances'=>$aq->orderByDesc('id')->limit(100)->get(),'leave_requests'=>$lq->orderByDesc('id')->limit(50)->get(),'day_off_requests'=>$dq->orderByDesc('id')->limit(50)->get(),'work_codes'=>$this->access->scopeStore(WorkCode::query(),$u)->where('is_active',true)->get()]);}
 /** 근무 스케줄 등록 */
 public function schedule(Request $r){$u=$r->user();$this->access->requirePermission($u,'schedule.manage');$v=$r->validate(['user_id'=>['required','exists:users,id'],'work_date'=>['required','date'],'work_code_id'=>['nullable','exists:work_codes,id'],'scheduled_start_time'=>['nullable'],'scheduled_end_time'=>['nullable'],'status'=>['required','in:work,day_off,leave,absence'],'note'=>['nullable','string']]);$target=User::findOrFail($v['user_id']);$this->assertWorkManage($u,$target);$s=WorkSchedule::updateOrCreate(['user_id'=>$target->id,'work_date'=>$v['work_date']],[...$v,'store_id'=>$target->store_id,'department'=>$target->department,'scheduled_break_minutes'=>0,'source'=>'manual','is_manually_modified'=>true,'created_by'=>$u->id,'updated_by'=>$u->id]);$this->audit->log($u,'schedule','save',WorkSchedule::class,$s->id,null,$s->toArray(),'근무 스케줄 저장');return response()->json(['message'=>'스케줄이 저장되었습니다.']);}
 /** 본인 휴가 신청 */
 public function leave(Request $r){$u=$r->user();$this->access->requirePermission($u,'leave.view');$v=$r->validate(['leave_type'=>['required','in:annual,half_day'],'day_unit'=>['required','in:full_day,half_day'],'start_date'=>['required','date'],'end_date'=>['required','date','after_or_equal:start_date'],'amount'=>['required','numeric','min:0.5'],'reason'=>['required','string']]);LeaveRequest::create([...$v,'user_id'=>$u->id,'status'=>'pending']);return response()->json(['message'=>'휴가 신청이 등록되었습니다.'],201);}
 /** 본인 희망휴무 신청 */
 public function dayOff(Request $r){$u=$r->user();$this->access->requirePermission($u,'leave.view');$v=$r->validate(['dates'=>['required','array','min:1','max:10'],'dates.*'=>['date'],'reason'=>['nullable','string']]);$first=collect($v['dates'])->sort()->first();$d=DayOffRequest::create(['user_id'=>$u->id,'target_year'=>(int)substr($first,0,4),'target_month'=>(int)substr($first,5,2),'reason'=>$v['reason']??null,'status'=>'pending']);foreach(array_unique($v['dates']) as $date)$d->dates()->create(['request_date'=>$date]);return response()->json(['message'=>'희망휴무 신청이 등록되었습니다.'],201);}
 /** 휴가/희망휴무 승인 또는 반려 */
 public function review(Request $r,string $type,int $id){$u=$r->user();$this->access->requirePermission($u,'leave.manage');$v=$r->validate(['status'=>['required','in:approved,rejected'],'review_note'=>['nullable','string']]);$model=$type==='leave'?LeaveRequest::findOrFail($id):DayOffRequest::findOrFail($id);$target=User::findOrFail($model->user_id);$this->assertWorkManage($u,$target);$model->update([...$v,'reviewed_by'=>$u->id,'reviewed_at'=>now()]);return response()->json(['message'=>'신청 상태가 변경되었습니다.']);}
 private function scopeWork($q,User $u): void {if($u->role?->code==='super_admin')return;if($u->isHeadOffice()){$q->whereNull('store_id');return;}$q->where('store_id',$u->store_id)->where('department',$u->department);}
 private function assertWorkManage(User $u,User $target): void {if($u->role?->code==='super_admin')return;if($u->isHeadOffice()){abort_unless($target->isHeadOffice(),403,'본사 관리자는 본사 직원의 근무만 관리할 수 있습니다.');return;}abort_unless($target->store_id===$u->store_id&&$target->department===$u->department,403,'다른 점포 또는 부서의 근무는 관리할 수 없습니다.');}
}
