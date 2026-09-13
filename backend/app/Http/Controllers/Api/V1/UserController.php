<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
class UserController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}
    public function index(Request $request): JsonResponse { $users=User::with('roles')->when($request->filled('search'),fn($q)=>$q->where(fn($q)=>$q->where('name','like','%'.$request->string('search').'%')->orWhere('email','like','%'.$request->string('search').'%')))->when($request->filled('status')&&$request->string('status')!=='all',fn($q)=>$q->where('status',$request->string('status')))->latest()->paginate($request->integer('per_page',20)); $users->through(fn(User $u)=>$this->resource($u)); return response()->json(['data'=>$users]); }
    public function store(StoreUserRequest $request): JsonResponse { $data=$request->validated(); $roles=$data['role_ids']??[]; unset($data['role_ids']); $user=DB::transaction(function()use($request,$data,$roles){$u=User::create($data);$u->roles()->sync($roles);$this->auditLogger->record($request,'user.created',$u,[],[]);return $u->fresh('roles');}); return response()->json(['data'=>$this->resource($user)],Response::HTTP_CREATED); }
    public function show(string $publicId): JsonResponse { return response()->json(['data'=>$this->resource(User::with('roles')->where('public_id',$publicId)->firstOrFail())]); }
    public function update(UpdateUserRequest $request,string $publicId): JsonResponse { $u=User::where('public_id',$publicId)->firstOrFail();$data=$request->validated();$rolesProvided=array_key_exists('role_ids',$data);$roles=$data['role_ids']??[];unset($data['role_ids']);$old=$this->resource($u->load('roles'));$u->update($data);if($rolesProvided)$u->roles()->sync($roles);$u=$u->fresh('roles');$this->auditLogger->record($request,'user.updated',$u,$old,$this->resource($u));return response()->json(['data'=>$this->resource($u)]); }
    public function destroy(Request $request,string $publicId): JsonResponse { $u=User::where('public_id',$publicId)->firstOrFail();$u->update(['status'=>'inactive']);$this->auditLogger->record($request,'user.deactivated',$u,[],[]);return response()->json(['data'=>$this->resource($u->fresh('roles'))]); }
    private function resource(User $u): array { return ['public_id'=>$u->public_id,'name'=>$u->name,'email'=>$u->email,'status'=>$u->status,'roles'=>$u->roles->map(fn(Role $r)=>['id'=>$r->id,'name'=>$r->name])->values()->all(),'created_at'=>$u->created_at?->toISOString(),'updated_at'=>$u->updated_at?->toISOString()]; }
}
