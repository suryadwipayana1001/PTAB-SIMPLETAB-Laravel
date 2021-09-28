<?php

namespace App\Http\Controllers\api\v1\admin;

use App\ActionApi;
use App\ActionStaff;
use App\CustomerApi;
use App\Http\Controllers\Controller;
use App\StaffApi;
use App\TicketApi;
use App\Traits\TraitModel;
use App\User;
use DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use OneSignal;
use App\Subdapertement;

class ActionsApiController extends Controller
{
    use TraitModel;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

    }

    public function actionStatusUpdate(Request $request)
    {
        try {
            // ambil data dari request simpan di dataForm

            $dataForm = json_decode($request->form);
            // data action
            $action = ActionApi::where('id', $dataForm->action_id)->with('ticket')->with('staff')->first();
            $uploadAction = false;
            $cekError = 'kosong';
            // image yang lama disimpan
            $actionImage = json_decode($action->image);
            $img_path = "/images/action";
            $basepath = str_replace("laravel-simpletab", "public_html/simpletabadmin/", \base_path());
            $dataImageName=[];


            // cek status dan upload gambar dalam pengerjaan 
            if($action->status =='pending' && $dataForm->status == 'active'){
                for ($i = 1; $i <= 2; $i++) {
                    if ($request->file('image' . $i)) {
                        $resourceImage = $request->file('image' . $i);
                        $nameImage = strtolower($action->id);
                        $file_extImage = $request->file('image' . $i)->extension();
                        $nameImage = str_replace(" ", "-", $nameImage);
    
                        $img_name = $img_path . "/" . $nameImage . "-" . $dataForm->action_id . $i . "." . $file_extImage;
    
                        $resourceImage->move($basepath . $img_path, $img_name);
    
                        $dataImageName[] = $img_name;
                    } else {
                        $responseImage = 'Image tidak di dukung';
                        break;
                    }
                }
            }else if($action->status =='active' && $dataForm->status =='active'){
                $oldImage = json_decode($action->image);
                $index = 0;
                for ($i = 1; $i <= 2; $i++) {
                    if ($request->file('image' . $i)) {
                        $resourceImage = $request->file('image' . $i);
                        $nameImage = strtolower($action->id);
                        $file_extImage = $request->file('image' . $i)->extension();
                        $nameImage = str_replace(" ", "-", $nameImage);
    
                        $img_name = $img_path . "/" . $nameImage . "-" . $dataForm->action_id . $i . "." . $file_extImage;
    
                        $resourceImage->move($basepath . $img_path, $img_name);
    
                        $dataImageName[] = $img_name;
                    } else {
                        $dataImageName[] = $oldImage[$i-1];
                        $responseImage = 'Image tidak di dukung';
                    }
                    // $index++;
                }
            }

            // foto sebelum pengerjaan 
            if($request->file('image_prework')){
                $resource_image_prework = $request->file('image_prework');
                $id_name_image_prework = strtolower($action->id);
                $file_ext_image_prework = $request->file('image_prework')->extension();
                $id_name_image_prework = str_replace(' ', '-', $id_name_image_prework);
    
                $name_image_prework = $img_path .'/'. $id_name_image_prework.'-'. $dataForm->action_id .'-pre.'. $file_ext_image_prework;
    
                $resource_image_prework->move($basepath.$img_path,$name_image_prework);
                $data_image_prework = $name_image_prework;
            }

                 // foto alat 
            if($request->file('image_tools')){
                $resource_image_tools = $request->file('image_tools');
                $id_name_image_tools = strtolower($action->id);
                $file_ext_image_tools = $request->file('image_tools')->extension();
                $id_name_image_tools = str_replace(' ', '-', $id_name_image_tools);

                $name_image_tools = $img_path .'/'. $id_name_image_tools.'-'. $dataForm->action_id .'-tools.'. $file_ext_image_tools;

                $resource_image_tools->move($basepath.$img_path,$name_image_tools);
                $data_image_tools = $name_image_tools;
            }

            for ($i = 1; $i <= 2; $i++) {
                if ($request->file('image_done' . $i)) {
                    $resourceImageDone = $request->file('image_done' . $i);
                    $nameImageDone = strtolower($action->id);
                    $file_extImageDone = $request->file('image_done' . $i)->extension();
                    $nameImageDone = str_replace(" ", "-", $nameImageDone);

                    $img_name_done = $img_path . "/" . $nameImageDone . "-" . $dataForm->action_id . $i . "-done." . $file_extImageDone;

                    $resourceImageDone->move($basepath . $img_path, $img_name_done);

                    $dataImageNameDone[] = $img_name_done;
                } else {
                    $responseImage = 'Image tidak di dukung';
                    break;
                }
            }
            $action = ActionApi::where('id', $dataForm->action_id)->with('ticket')->with('staff')->first();
            $cekAllStatus = false;
            $statusAction = $dataForm->status;

            $dateNow = date('Y-m-d H:i:s');


            // update database
            $dataNewAction = array(
                'status' => $statusAction,
                // 'image_prework' => $data_image_prework,
                // 'image_tools' => $data_image_tools,
                'end' => $statusAction == 'pending' || $statusAction == 'active' ? '' : $dateNow,
                'memo' => $dataForm->memo,
            );
            if($action->status !='close' && $dataForm->status !='close'){

                if($request->file('image_tools')){
                    $dataNewAction['image_tools'] = $data_image_tools;
                }

                if($request->file('image_prework')){
                    $dataNewAction['image_prework'] = $data_image_prework;
                }

                if($dataImageName && count($dataImageName) > 0){
                    $dataNewAction['image'] =str_replace("\/", "/", json_encode($dataImageName));
                }
                $uploadAction=true;
            }else{
                $dataNewAction['image_done'] = str_replace("\/", "/", json_encode($dataImageNameDone));
                $uploadAction=true;
            }

            if($uploadAction){
                $action->update($dataNewAction);
                //update staff
                $ids = $action->staff()->allRelatedIds();
                foreach ($ids as $sid) {
                    $action->staff()->updateExistingPivot($sid, ['status' => $dataForm->status]);
                }
                //update ticket status
                $ticket = TicketApi::find($action->ticket_id);
                $ticket->status = $statusAction;
                $ticket->save();

                //def subdap
                $dateNow = date('Y-m-d H:i:s');
                $subdapertement_def = Subdapertement::where('def', '1')->first();
                $dapertement_def_id = $subdapertement_def->dapertement_id;
                $subdapertement_def_id = $subdapertement_def->id;

                //if close send notif to user
                if ($statusAction == 'close') {
                    $customer = CustomerApi::find($ticket->customer_id);
                    $id_onesignal = $customer->_id_onesignal;
                    $message = 'Customer: Keluahan Sudah Diselesaikan  : ' . $ticket->code . $dataForm->memo;
                    if (!empty($id_onesignal)) {
                        OneSignal::sendNotificationToUser(
                            $message,
                            $id_onesignal,
                            $url = null,
                            $data = null,
                            $buttons = null,
                            $schedule = null
                        );}
                }

                //send notif to admin
                $admin_arr = User::where('dapertement_id', 0)->get();
                foreach ($admin_arr as $key => $admin) {
                    $id_onesignal = $admin->_id_onesignal;
                    $message = 'Admin: Status Pengerjaan Diupdate  : ' . $ticket->code . $dataForm->memo;
                    if (!empty($id_onesignal)) {
                        OneSignal::sendNotificationToUser(
                            $message,
                            $id_onesignal,
                            $url = null,
                            $data = null,
                            $buttons = null,
                            $schedule = null
                        );}}
                
                //send notif to humas
                $admin_arr = User::where('subdapertement_id', $subdapertement_def_id)->get();
                foreach ($admin_arr as $key => $admin) {
                    $id_onesignal = $admin->_id_onesignal;
                    $message = 'Humas: Status Pengerjaan Diupdate  : ' . $ticket->code . $dataForm->memo;
                    if (!empty($id_onesignal)) {
                        OneSignal::sendNotificationToUser(
                            $message,
                            $id_onesignal,
                            $url = null,
                            $data = null,
                            $buttons = null,
                            $schedule = null
                        );}}

                //send notif to departement terkait
                $admin_arr = User::where('dapertement_id', $ticket->dapertement_id)
                    ->where('subdapertement_id', 0)
                    ->get();
                foreach ($admin_arr as $key => $admin) {
                    $id_onesignal = $admin->_id_onesignal;
                    $message = 'Bagian: Status Pengerjaan Diupdate : ' . $ticket->code . $dataForm->memo;
                    if (!empty($id_onesignal)) {
                        OneSignal::sendNotificationToUser(
                            $message,
                            $id_onesignal,
                            $url = null,
                            $data = null,
                            $buttons = null,
                            $schedule = null
                        );}}

                //send notif to sub departement terkait
                $admin_arr = User::where('subdapertement_id', $action->subdapertement_id)
                    ->get();
                foreach ($admin_arr as $key => $admin) {
                    $id_onesignal = $admin->_id_onesignal;
                    $message = 'Sub Bagian: Status Pengerjaan Diupdate : ' . $ticket->code . $dataForm->memo;
                    if (!empty($id_onesignal)) {
                        OneSignal::sendNotificationToUser(
                            $message,
                            $id_onesignal,
                            $url = null,
                            $data = null,
                            $buttons = null,
                            $schedule = null
                        );}}

                return response()->json([
                    'message' => 'Status di ubah ' ,
                    'data' => $action,
                    'datanew' => $dataNewAction
                ]);
            }else{
                return response()->json([
                    'message' => '500',
                    'data' => $uploadAction,
                    'pesan' => $cekError,
                    'status' => $action->status
                ]);
            }
            
            
        } catch (QueryException $ex) {
            return response()->json([
                'message' => 'gagal update status ',
                'data' => $ex,
            ]);
        }
    }

    function list(Request $request) {
        $department = '';
        $subdepartment = 0;
        $staff = 0;
        if (isset($request->userid) && $request->userid != '') {
            $admin = User::with('roles')->find($request->userid);
            $role = $admin->roles[0];
            $role->load('permissions');
            $permission = json_decode($role->permissions->pluck('title'));
            if (!in_array("ticket_all_access", $permission)) {
                $department = $admin->dapertement_id;
                $subdepartment = $admin->subdapertement_id;
                $staff = $admin->staff_id;
            }
        }

        try {
            if ($subdepartment > 0 && $staff > 0) {
                $actions = ActionApi::selectRaw('DISTINCT actions.*')
                    ->join('action_staff', function ($join) use ($staff) {
                        $join->on('action_staff.action_id', '=', 'actions.id')
                            ->where('action_staff.staff_id', '=', $staff);
                    })
                    ->with('staff')
                    ->with('dapertement')
                    ->with('ticket')
                    ->where('ticket_id', $request->ticket_id)
                    ->orderBy('start', 'desc')
                    ->get();
            } else {
                $actions = ActionApi::with('staff')
                    ->with('dapertement')
                    ->with('ticket')
                    ->where('ticket_id', $request->ticket_id)
                    ->orderBy('start', 'desc')
                    ->get();
            }
            return response()->json([
                'message' => 'Data Ticket',
                'data' => $actions,
            ]);
        } catch (QueryException $ex) {
            return response()->json([
                'message' => 'Gagal Mengambil data',
                'data' => $ex,
            ]);
        }

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $dateNow = date('Y-m-d H:i:s');

        $data = $request->all();

        $rules = array(
            'description' => 'required',
            'ticket_id' => 'required',
            'dapertement_id' => 'required',
        );

        $validator = \Validator::make($data, $rules);
        if ($validator->fails()) {
            $messages = $validator->messages();
            $errors = $messages->all();
            return response()->json([
                'message' => $errors,
                'data' => $request->all(),
            ]);
        }

        $data['status'] = 'pending';
        $data['start'] = $dateNow;
        //set SPK
        $arr['dapertement_id'] = $request->dapertement_id;
        $arr['month'] = date("m");
        $arr['year'] = date("Y");
        $last_spk = $this->get_last_code('spk', $arr);
        $spk = acc_code_generate($last_spk, 21, 17, 'Y');
        $data['spk'] = $spk;

        $action = ActionApi::create($data);
        $ticket = TicketApi::find($request->ticket_id);

        //def subdap
        $dateNow = date('Y-m-d H:i:s');
        $subdapertement_def = Subdapertement::where('def', '1')->first();
        $dapertement_def_id = $subdapertement_def->dapertement_id;
        $subdapertement_def_id = $subdapertement_def->id;

        //send notif to admin
        $admin_arr = User::where('dapertement_id', 0)->get();
        foreach ($admin_arr as $key => $admin) {
            $id_onesignal = $admin->_id_onesignal;
            $message = 'Admin: Tindakan Baru Dibuat : ' . $ticket->code . $request->description;
            if (!empty($id_onesignal)) {
                OneSignal::sendNotificationToUser(
                    $message,
                    $id_onesignal,
                    $url = null,
                    $data = null,
                    $buttons = null,
                    $schedule = null
                );}}
        
        //send notif to humas
        $admin_arr = User::where('subdapertement_id', $subdapertement_def_id)
                ->where('staff_id', 0)
                ->get();
        foreach ($admin_arr as $key => $admin) {
            $id_onesignal = $admin->_id_onesignal;
            $message = 'Humas: Tindakan Baru Dibuat : ' . $ticket->code . $request->description;
            if (!empty($id_onesignal)) {
                OneSignal::sendNotificationToUser(
                    $message,
                    $id_onesignal,
                    $url = null,
                    $data = null,
                    $buttons = null,
                    $schedule = null
                );}}

        //send notif to departement terkait
        $admin_arr = User::where('dapertement_id', $action->dapertement_id)
            ->where('subdapertement_id', 0)
            ->get();
        foreach ($admin_arr as $key => $admin) {
            $id_onesignal = $admin->_id_onesignal;
            $message = 'Bagian: Tindakan Baru Dibuat : ' . $ticket->code . $request->description;
            if (!empty($id_onesignal)) {
                OneSignal::sendNotificationToUser(
                    $message,
                    $id_onesignal,
                    $url = null,
                    $data = null,
                    $buttons = null,
                    $schedule = null
                );}}

        //send notif to sub departement terkait
        $admin_arr = User::where('subdapertement_id', $action->subdapertement_id)
            ->get();
        foreach ($admin_arr as $key => $admin) {
            $id_onesignal = $admin->_id_onesignal;
            $message = 'Sub Bagian: Tindakan Baru Dibuat : ' . $ticket->code . $request->description;
            if (!empty($id_onesignal)) {
                OneSignal::sendNotificationToUser(
                    $message,
                    $id_onesignal,
                    $url = null,
                    $data = null,
                    $buttons = null,
                    $schedule = null
                );}}

        return response()->json([
            'message' => 'Data Dapertement Add Success',
            'data' => $action,
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ActionApi $action)
    {
        $rules = array(
            'description' => 'required',
            'dapertement_id' => 'required',
        );

        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->messages();
            $errors = $messages->all();
            return response()->json([
                'message' => $errors,
                'data' => $request->all(),
            ]);
        }

        $action->update($request->all());
        $ticket = TicketApi::find($action->ticket_id);

        //def subdap
        $dateNow = date('Y-m-d H:i:s');
        $subdapertement_def = Subdapertement::where('def', '1')->first();
        $dapertement_def_id = $subdapertement_def->dapertement_id;
        $subdapertement_def_id = $subdapertement_def->id;

        //send notif to admin
        $admin_arr = User::where('dapertement_id', 0)->get();
        foreach ($admin_arr as $key => $admin) {
            $id_onesignal = $admin->_id_onesignal;
            $message = 'Admin: Tindakan Baru Diupdate : ' . $ticket->code . $request->description;
            if (!empty($id_onesignal)) {
                OneSignal::sendNotificationToUser(
                    $message,
                    $id_onesignal,
                    $url = null,
                    $data = null,
                    $buttons = null,
                    $schedule = null
                );}}
        
        //send notif to humas
        $admin_arr = User::where('subdapertement_id', $subdapertement_def_id)
                ->where('staff_id', 0)
                ->get();
        foreach ($admin_arr as $key => $admin) {
            $id_onesignal = $admin->_id_onesignal;
            $message = 'Humas: Tindakan Baru Diupdate : ' . $ticket->code . $request->description;
            if (!empty($id_onesignal)) {
                OneSignal::sendNotificationToUser(
                    $message,
                    $id_onesignal,
                    $url = null,
                    $data = null,
                    $buttons = null,
                    $schedule = null
                );}}

        //send notif to departement terkait
        $admin_arr = User::where('dapertement_id', $action->dapertement_id)
            ->where('subdapertement_id', 0)
            ->get();
        foreach ($admin_arr as $key => $admin) {
            $id_onesignal = $admin->_id_onesignal;
            $message = 'Bagian: Tindakan Baru Diupdate : ' . $ticket->code . $request->description;
            if (!empty($id_onesignal)) {
                OneSignal::sendNotificationToUser(
                    $message,
                    $id_onesignal,
                    $url = null,
                    $data = null,
                    $buttons = null,
                    $schedule = null
                );}}

        //send notif to sub departement terkait
        $admin_arr = User::where('subdapertement_id', $action->subdapertement_id)
            ->get();
        foreach ($admin_arr as $key => $admin) {
            $id_onesignal = $admin->_id_onesignal;
            $message = 'Sub Bagian: Tindakan Baru Diupdate : ' . $ticket->code . $request->description;
            if (!empty($id_onesignal)) {
                OneSignal::sendNotificationToUser(
                    $message,
                    $id_onesignal,
                    $url = null,
                    $data = null,
                    $buttons = null,
                    $schedule = null
                );}}

        return response()->json([
            'message' => 'Data Dapertement Edit Success',
            'data' => $action,
        ]);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(ActionApi $action)
    {
        try {
            $actionstaff = ActionStaff::where('action_id', '=', $action->id)->first();
            if ($actionstaff === null) {

                //unlink
                $basepath = str_replace("laravel-simpletab", "public_html/simpletabadmin", \base_path());
                $img = $action->image;
                $img = str_replace('"', '', $img);
                $img = str_replace('[', '', $img);
                $img = str_replace(']', '', $img);
                $img_arr = explode(",", $img);
                foreach ($img_arr as $img_name) {
                    $file_path = $basepath . $img_name;
                    if (trim($img_name) != '' && file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                $action->delete();

                return response()->json([
                    'message' => 'Data Berhasil Di Hapus',
                    'data' => $actionstaff,
                ]);
                // user doesn't exist
            } else {
                return response()->json([
                    'message' => 'Data Masih Terkait dengan data yang lain',
                    'data' => $actionstaff,
                ]);
            }
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Data Masih Terkait dengan data yang lain',
                'data' => $e,
            ]);
        }
    }

    public function actionStaffs($action_id)
    {

        try {
            $action = ActionApi::where('id', $action_id)->with('staff')->with('dapertement')->first();
            return response()->json([
                'message' => 'sucssess',
                'data' => $action,
            ]);

        } catch (QueryException $ex) {
            return response()->json([
                'message' => 'sucssess',
                'data' => $ex,
            ]);
        }

        // $staffs = $action->staff;

    }

    public function actionStaffLists($action_id)
    {
        try {
            $action = ActionApi::findOrFail($action_id);

            $action_staffs = ActionApi::where('id', $action_id)->with('staff')->first();

            $staffs = StaffApi::where('subdapertement_id', $action->subdapertement_id)->get();

            // $staffs = Staff::where('dapertement_id', $action->dapertement_id)->with('action')->get();

            $action_staff_lists = DB::table('staffs')
                ->join('action_staff', function ($join) {
                    $join->on('action_staff.staff_id', '=', 'staffs.id')
                        ->where('action_staff.status', '!=', 'close');
                })
                ->get();

            $data = [
                'action' => $action,
                'action_staffs' => $action_staffs,
                'staffs' => $staffs,
                'action_staff_lists' => $action_staff_lists,
            ];

            return response()->json([
                'message' => 'success',
                'data' => $data,
            ]);
        } catch (QueryException $ex) {
            return response()->json([
                'message' => 'gagal ambil data',
                'data' => $ex,
            ]);
        }
    }

    public function actionStaffStore(Request $request)
    {

        try {
            $rules = array(
                'action_id' => 'required',
                'staff_id' => 'required',
            );

            $validator = \Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->messages();
                $errors = $messages->all();
                return response()->json([
                    'message' => $errors,
                    'data' => $request->all(),
                ]);
            }

            $action = ActionApi::with('ticket')->find($request->action_id);
            $staff = StaffApi::find($request->staff_id);

            if ($action) {
                $cek = $action->staff()->attach($request->staff_id, ['status' => 'pending']);

                if ($cek) {
                    $action = Action::where('id', $request->action_id)->with('staff')->first();

                    // dd($action->staff[0]->pivot->status);
                    $cekAllStatus = false;
                    $statusAction = 'close';
                    for ($status = 0; $status < count($action->staff); $status++) {
                        // dd($action->staff[$status]->pivot->status);
                        if ($action->staff[$status]->pivot->status == 'pending') {
                            $statusAction = 'pending';
                            break;
                        } else if ($action->staff[$status]->pivot->status == 'active') {

                            $statusAction = 'active';
                        }
                    }

                    $dateNow = date('Y-m-d H:i:s');

                    $action->update([
                        'status' => $statusAction,
                        'end' => $statusAction == 'pending' || $statusAction == 'active' ? '' : $dateNow,
                    ]);
                }

                //def subdap
                $dateNow = date('Y-m-d H:i:s');
                $subdapertement_def = Subdapertement::where('def', '1')->first();
                $dapertement_def_id = $subdapertement_def->dapertement_id;
                $subdapertement_def_id = $subdapertement_def->id;

                //send notif to admin
                $admin_arr = User::where('dapertement_id', 0)->get();
                foreach ($admin_arr as $key => $admin) {
                    $id_onesignal = $admin->_id_onesignal;
                    $message = 'Admin: Petugas Baru Ditugaskan : ' . $action->ticket->code . $staff->name;
                    if (!empty($id_onesignal)) {
                        OneSignal::sendNotificationToUser(
                            $message,
                            $id_onesignal,
                            $url = null,
                            $data = null,
                            $buttons = null,
                            $schedule = null
                        );}}
                
                //send notif to humas
                $admin_arr = User::where('subdapertement_id', $subdapertement_def_id)
                ->where('staff_id', 0)
                ->get();
                foreach ($admin_arr as $key => $admin) {
                    $id_onesignal = $admin->_id_onesignal;
                    $message = 'Humas: Petugas Baru Ditugaskan : ' . $action->ticket->code . $staff->name;
                    if (!empty($id_onesignal)) {
                        OneSignal::sendNotificationToUser(
                            $message,
                            $id_onesignal,
                            $url = null,
                            $data = null,
                            $buttons = null,
                            $schedule = null
                        );}}

                //send notif to departement terkait
                $admin_arr = User::where('dapertement_id', $action->dapertement_id)
                    ->where('subdapertement_id', 0)
                    ->get();
                foreach ($admin_arr as $key => $admin) {
                    $id_onesignal = $admin->_id_onesignal;
                    $message = 'Bagian: Petugas Baru Ditugaskan : ' . $action->ticket->code . $staff->name;
                    if (!empty($id_onesignal)) {
                        OneSignal::sendNotificationToUser(
                            $message,
                            $id_onesignal,
                            $url = null,
                            $data = null,
                            $buttons = null,
                            $schedule = null
                        );}}

                //send notif to sub departement terkait
                $admin_arr = User::where('subdapertement_id', $action->subdapertement_id)
                    ->get();
                foreach ($admin_arr as $key => $admin) {
                    $id_onesignal = $admin->_id_onesignal;
                    $message = 'Sub Bagian: Petugas Baru Ditugaskan : ' . $action->ticket->code . $staff->name;
                    if (!empty($id_onesignal)) {
                        OneSignal::sendNotificationToUser(
                            $message,
                            $id_onesignal,
                            $url = null,
                            $data = null,
                            $buttons = null,
                            $schedule = null
                        );}}

                return response()->json([
                    'message' => 'staff Berhasil di tambahkan ',
                    'data' => $action,
                ]);
            }

        } catch (QueryException $ex) {
            return response()->json([
                'message' => 'gagal tambah staff ',
                'data' => $ex,
            ]);
        }

    }

    public function actionStaffUpdate(Request $request)
    {
        try {

            $rules = array(
                'action_id' => 'required',
                'staff_id' => 'required',
                'status' => 'required',
            );

            $validator = \Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->messages();
                $errors = $messages->all();
                return response()->json([
                    'message' => $errors,
                    'data' => $request->all(),
                ]);
            }

            $action = ActionApi::where('id', $request->action_id)->with('ticket')->with('staff')->first();
            $staff = StaffApi::find($request->staff_id);
            $idStaff = $request->staff_id;
            if ($action) {
                $cek = $action->staff()->updateExistingPivot($request->staff_id, ['status' => $request->status]);
                //    $cek =  $action->staff()->sync([$idStaff => [ 'status' => $request->status] ], false);
            }

            if ($cek) {
                $action = ActionApi::where('id', $request->action_id)->with('ticket')->with('staff')->first();

                //     // dd($action->staff[0]->pivot->status);
                $cekAllStatus = false;
                $statusAction = 'close';
                for ($status = 0; $status < count($action->staff); $status++) {
                    // dd($action->staff[$status]->pivot->status);
                    if ($action->staff[$status]->pivot->status == 'pending') {
                        $statusAction = 'pending';
                        break;
                    } else if ($action->staff[$status]->pivot->status == 'active') {

                        $statusAction = 'active';
                    }
                }

                $dateNow = date('Y-m-d H:i:s');

                $action->update([
                    'status' => $statusAction,
                    'end' => $statusAction == 'pending' || $statusAction == 'active' ? '' : $dateNow,
                ]);

                // update ticket
                $statusTicket = 'close';
                if ($action) {
                    $actionStatusAll = ActionApi::where('ticket_id', $action->ticket_id)->with('ticket')->get();

                    for ($i = 0; $i < count($actionStatusAll); $i++) {
                        if ($actionStatusAll[$i]->status == 'pending') {
                            $statusTicket = 'pending';
                            break;
                        } else if ($actionStatusAll[$i]->status == 'active') {
                            $statusTicket = 'active';
                        }
                    }

                    $ticket = TicketApi::findOrFail($action->ticket_id);

                    $ticket->update([
                        'status' => $statusTicket,
                    ]);
                    // $actionStatusAll->update([
                    //     'status' => $statusTicket,
                    // ]);

                    // dd($statusTicket);
                }

                //def subdap
                $dateNow = date('Y-m-d H:i:s');
                $subdapertement_def = Subdapertement::where('def', '1')->first();
                $dapertement_def_id = $subdapertement_def->dapertement_id;
                $subdapertement_def_id = $subdapertement_def->id;

                //send notif to admin
                $admin_arr = User::where('dapertement_id', 0)->get();
                foreach ($admin_arr as $key => $admin) {
                    $id_onesignal = $admin->_id_onesignal;
                    $message = 'Admin: Petugas Baru Diupdate : ' . $action->ticket->code . $staff->name;
                    if (!empty($id_onesignal)) {
                        OneSignal::sendNotificationToUser(
                            $message,
                            $id_onesignal,
                            $url = null,
                            $data = null,
                            $buttons = null,
                            $schedule = null
                        );}}
                
                //send notif to humas
                $admin_arr = User::where('subdapertement_id', $subdapertement_def_id)
                ->where('staff_id', 0)
                ->get();
                foreach ($admin_arr as $key => $admin) {
                    $id_onesignal = $admin->_id_onesignal;
                    $message = 'Humas: Petugas Baru Diupdate : ' . $action->ticket->code . $staff->name;
                    if (!empty($id_onesignal)) {
                        OneSignal::sendNotificationToUser(
                            $message,
                            $id_onesignal,
                            $url = null,
                            $data = null,
                            $buttons = null,
                            $schedule = null
                        );}}

                //send notif to departement terkait
                $admin_arr = User::where('dapertement_id', $action->dapertement_id)
                    ->where('subdapertement_id', 0)
                    ->get();
                foreach ($admin_arr as $key => $admin) {
                    $id_onesignal = $admin->_id_onesignal;
                    $message = 'Bagian: Petugas Baru Diupdate : ' . $action->ticket->code . $staff->name;
                    if (!empty($id_onesignal)) {
                        OneSignal::sendNotificationToUser(
                            $message,
                            $id_onesignal,
                            $url = null,
                            $data = null,
                            $buttons = null,
                            $schedule = null
                        );}}

                //send notif to sub departement terkait
                $admin_arr = User::where('subdapertement_id', $action->subdapertement_id)
                    ->get();
                foreach ($admin_arr as $key => $admin) {
                    $id_onesignal = $admin->_id_onesignal;
                    $message = 'Sub Bagian: Petugas Baru Diupdate : ' . $action->ticket->code . $staff->name;
                    if (!empty($id_onesignal)) {
                        OneSignal::sendNotificationToUser(
                            $message,
                            $id_onesignal,
                            $url = null,
                            $data = null,
                            $buttons = null,
                            $schedule = null
                        );}}

                return response()->json([
                    'message' => 'Status di ubah ',
                    'data' => $action,
                ]);
            } else {
                return $request->all();
            }
        } catch (QueryException $ex) {
            return response()->json([
                'message' => 'gagal tambah staff ',
                'data' => $ex,
            ]);
        }
    }

    public function actionStaffDestroy($action_id, $staff_id)
    {
        // abort_unless(\Gate::allows('action_staff_delete'), 403);

        try {
            $action = ActionApi::findOrFail($action_id);

            if ($action) {
                $cek = $action->staff()->detach($staff_id);

                if ($cek) {
                    $action = ActionApi::where('id', $action_id)->with('staff')->first();

                    // dd($action->staff[0]->pivot->status);
                    $cekAllStatus = false;
                    $statusAction = 'close';
                    for ($status = 0; $status < count($action->staff); $status++) {
                        // dd($action->staff[$status]->pivot->status);
                        if ($action->staff[$status]->pivot->status == 'pending') {
                            $statusAction = 'pending';
                            break;
                        } else if ($action->staff[$status]->pivot->status == 'active') {

                            $statusAction = 'active';
                        }
                    }

                    $dateNow = date('Y-m-d H:i:s');

                    $action->update([
                        'status' => $statusAction,
                        'end' => $statusAction == 'pending' || $statusAction == 'active' ? '' : $dateNow,
                    ]);

                    return response()->json([
                        'message' => 'Berhasil di hapus ',
                        'data' => $action,
                    ]);
                }
            }
        } catch (QueryException $th) {
            return response()->json([
                'message' => 'gagal tambah staff ',
                'data' => $ex,
            ]);
        }
    }
}
