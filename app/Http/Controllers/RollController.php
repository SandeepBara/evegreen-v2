<?php

namespace App\Http\Controllers;

use App\Exports\ExportRoll;
use App\Imports\RollDetailsImport;
use App\Models\BagType;
use App\Models\BagTypeMaster;
use App\Models\ClientDetail;
use App\Models\ClientDetailMaster;
use App\Models\ColorMaster;
use App\Models\CuttingScheduleDetail;
use App\Models\MachineMater;
use App\Models\OrderPunchDetail;
use App\Models\OrderRollBagType;
use App\Models\PendingOrderBagType;
use App\Models\PrintingMachine;
use App\Models\PrintingScheduleDetail;
use App\Models\RollColorMaster;
use App\Models\RollDetail;
use App\Models\RollPrintColor;
use App\Models\RollTransit;
use App\Models\VendorDetail;
use App\Models\VendorDetailMaster;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Excel as ExcelExcel;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Yajra\DataTables\Facades\DataTables;

class RollController extends Controller
{
    private $_M_RollDetail;
    private $_M_VendorDetail;
    private $_M_PrintingScheduleDetail;
    private $_M_ClientDetails;
    private $_M_BagType;
    private $_M_Machine;

    private $_M_RollTransit;
    private $_M_RollColor;
    private $_M_CuttingScheduleDetail;
    private $_M_Color;
    private $_M_OrderPunches;
    private $_M_OrderRollBagType;
    private $_M_PendingOrderBagType;
    function __construct()
    {
        
        $this->_M_RollTransit = new RollTransit();
        $this->_M_RollColor = new RollColorMaster();
        $this->_M_VendorDetail= new VendorDetailMaster();
        $this->_M_ClientDetails = new ClientDetailMaster();
        $this->_M_BagType = new BagTypeMaster();
        $this->_M_RollDetail = new RollDetail();
        $this->_M_PrintingScheduleDetail = new PrintingScheduleDetail();
        $this->_M_Machine = new MachineMater();
        $this->_M_CuttingScheduleDetail = new CuttingScheduleDetail();
        $this->_M_Color = new ColorMaster();
        $this->_M_OrderPunches = new OrderPunchDetail();
        $this->_M_OrderRollBagType = new OrderRollBagType();
        $this->_M_PendingOrderBagType = new PendingOrderBagType();
    }

    #================ Roll Transit =====================

    public function addTransitRoll(Request $request){
        try{
            
            if($request->getMethod()=="POST"){ 
                $rule = [
                    "purchaseDate"=>"nullable|",
                    "venderId"=>"required|exists:".$this->_M_VendorDetail->getTable().",id,lock_status,false",
                    "size"=>"required|numeric|min:0.1",
                    "gsm"=>"required|numeric|min:0.01",
                    "rollColor"=>"required|exists:".$this->_M_RollColor->getTable().",color,lock_status,false",
                    "length"=>"required|numeric|min:0.1",
                    "netWeight"=>"required|numeric|min:0.1",
                    "grossWeight"=>"required|numeric|min:0.1",
                    "forClientId"=>"nullable".($request->forClientId?"|exists:".$this->_M_ClientDetails->getTable().",id":""),
                    "estimatedDespatchDate"=>"required_with:forClientId",
                ];
                $validate = Validator::make($request->all(),$rule);
                if($validate->fails()){
                    return validationError($validate);
                }    
                $request->merge([
                    "rollType"=>"NW",
                    "estimateDeliveryDate"=>$request->estimatedDespatchDate,
                    "bagTypeId"=>$request->bookingBagTypeId,
                    "bagUnit"=>$request->bookingBagUnits,
                    "printingColor"=>$request->bookingPrintingColor,
                ]);  
                DB::beginTransaction();   
                $id = $this->_M_RollTransit->store($request);                
                $roll = $this->_M_RollTransit->find($id);
                if($roll->client_detail_id){
                    $newRequest = new Request($roll->toArray());
                    $orderId = $this->_M_OrderPunches->store($request);
                    $newRequest->merge(["order_id"=>$orderId,"roll_id"=>$roll->id]);
                    $this->_M_OrderRollBagType->store($newRequest);
                }
                DB::commit();
                flashToast("message","New Roll Add");
                return responseMsgs(true,"New Roll Added",["rollDtl"=>$roll]);
            }
        }catch(Exception $e){
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    public function transitList(Request $request){
        // $sl = 1;
        // $padded = str_pad((string)$sl, 4, "0", STR_PAD_LEFT);
        // dd($padded);
        $flag= $request->flag;
        $user_type = Auth()->user()->user_type_id??"";
        $data["flag"]=$flag;
        $data["items"] = $this->_M_RollTransit
                        ->select("roll_transits.vender_id","roll_transits.purchase_date",
                            "vendor_detail_masters.vendor_name",
                            DB::raw("count(roll_transits.id) as total_count,
                                    count( CASE WHEN roll_transits.client_detail_id IS NOT NULL THEN roll_transits.id END ) as total_book,
                                TO_CHAR(roll_transits.purchase_date, 'DD-MM-YYYY') as purchase_date 
                            ")
                        )
                        ->join("vendor_detail_masters","vendor_detail_masters.id","roll_transits.vender_id")
                        ->where("roll_transits.lock_status",false)
                        ->groupBy("roll_transits.vender_id","roll_transits.purchase_date","vendor_detail_masters.vendor_name")
                        ->orderBy("roll_transits.purchase_date")
                        ->get(); 
        return view("Roll/transit",$data);
    }

    public function transitDtl($vendor_id,Request $request){
        $user_type = Auth()->user()->user_type_id;        
        $data =[];
        $data["user_type"] = $user_type;
        $data["addToRollInStock"] = false;
        if(in_array($user_type,[1,2])){
            $data["addToRollInStock"] = true;
        }
        if($request->ajax()){                            
                $data = $this->_M_RollTransit->select("roll_transits.*","vendor_detail_masters.vendor_name",
                            "client_detail_masters.client_name","bag_type_masters.bag_type",
                            DB::raw("
                                    roll_transits.gsm_variation * 100 as gsm_variation,
                                    TO_CHAR(roll_transits.purchase_date, 'DD-MM-YYYY') as purchase_date ,
                                    TO_CHAR(roll_transits.estimate_delivery_date, 'DD-MM-YYYY') as estimate_delivery_date ,
                                    TO_CHAR(roll_transits.delivery_date, 'DD-MM-YYYY') as delivery_date ,
                                    TO_CHAR(roll_transits.printing_date, 'DD-MM-YYYY') as printing_date ,
                                    TO_CHAR(roll_transits.cutting_date, 'DD-MM-YYYY') as cutting_date                                     
                                    ")
                            )
                        ->join("vendor_detail_masters","vendor_detail_masters.id","roll_transits.vender_id")
                        ->leftJoin("client_detail_masters","client_detail_masters.id","roll_transits.client_detail_id")
                        ->leftJoin("bag_type_masters","bag_type_masters.id","roll_transits.bag_type_id")
                        ->where("roll_transits.lock_status",false)
                        ->orderBy("roll_transits.id","DESC");
                if($request->purchase_date){
                    $data->where("roll_transits.purchase_date",$request->purchase_date);
                }
                if($vendor_id){
                    $data->where("roll_transits.vender_id",$vendor_id);
                } 
                               
                if ($request->has('export')) {
                    // Skip pagination when exporting
                    $data = $data->get();
                    return Excel::download(new ExportRoll($data), 'roll.xlsx');
                }

                // Handling search
                if ($request->has('search')) {
                    $search = $request->search['value'];  // search term from DataTables
                    
                    $data = $data->where(function ($query) use ($search) {

                        $query->where("roll_transits.roll_no","LIKE", "%$search%")
                            ->orWhere("roll_transits.purchase_date","LIKE", "%$search%")
                            ->orWhere("roll_transits.size","LIKE", "%$search%")
                            ->orWhere("roll_transits.gsm","LIKE", "%$search%")
                            ->orWhere("roll_transits.roll_color","LIKE", "%$search%")
                            ->orWhere("roll_transits.length","LIKE", "%$search%")
                            ->orWhere("roll_transits.net_weight","LIKE", "%$search%")
                            ->orWhere("roll_transits.gross_weight","LIKE", "%$search%")
                            ->orWhere('vendor_detail_masters.vendor_name', 'LIKE', "%$search%")
                            ->orWhere('client_detail_masters.client_name', 'LIKE', "%$search%")
                            ->orWhere('bag_type_masters.bag_type', 'LIKE', "%$search%");  // Assuming ststop is a field to search
                    });
                }
                $list = DataTables::of($data)
                    ->addIndexColumn()
                    ->addColumn("check",function ($val) {
                        return "<input type='checkbox' name='transitId[]' value='" . $val->id . "' /> " ;
                    })
                    ->addColumn('row_color', function ($val) {
                        $color = "";
                        $gsmVariationPer = $val->gsm_variation;
                        if(!is_between($gsmVariationPer,-4,4)){
                            $color="tr-gsm_variation";
                        }
                        
                        return $color;
                    })
                    ->addColumn('gsm_variation', function ($val) {                        
                        return roundFigure($val->gsm_variation)."%";
                    })
                    ->addColumn('color', function ($val) {
                        return collect(json_decode($val->printing_color,true))->implode(",");
                    })
                    ->addColumn('action', function ($val) {                    
                        $button = "";
                        if($val->is_roll_cut){
                            return $button;
                        }
                        if(!$val->client_detail_id){
                            $button .= '<button class="btn btn-sm btn-warning" onClick="openModelBookingModel('.$val->id.')" >Book</button>';
                        }if($val->client_detail_id && !$val->is_printed){
                            $button .= '<button class="btn btn-sm btn-danger" onClick="openModelAlterBookingModel('.$val->id.')" >Alter Booking</button>';
                        }
                        return $button;
                    })
                    ->rawColumns(['action','color',"check"])
                    ->make(true);
                    // dd($list);
                return $list;

        }
        return view("Roll/transitDtl",$data);
    }


    
    function rollTransitBook(Request $request){
        try{
            $rule=[
                "rollId"=>"required|exists:".$this->_M_RollTransit->getTable().",id,lock_status,false",
                "bookingForClientId"=>"required|exists:".$this->_M_ClientDetails->getTable().",id,lock_status,false",
                "bookingEstimatedDespatchDate"=>"required|date",
                "bookingBagUnits"=>"required|in:Kg,Pice",
                "bookingBagTypeId"=>"required|exists:".$this->_M_BagType->getTable().",id",
                "bookingPrintingColor"=>"required|array",
                "bookingPrintingColor.*"=>"required",
            ];
            $validate = Validator::make($request->all(),$rule);
            if($validate->fails()){
                return validationError($validate);
            }

            $request->merge([
                "clientDetailId"=>$request->bookingForClientId,
                "estimateDeliveryDate"=>$request->bookingEstimatedDespatchDate,
            ]);

            $roll = $this->_M_RollTransit->find($request->rollId);
            $roll->client_detail_id = $request->bookingForClientId;
            $roll->estimate_delivery_date = $request->bookingEstimatedDespatchDate;
            $roll->bag_type_id = $request->bookingBagTypeId;
            $roll->bag_unit = $request->bookingBagUnits;
            $roll->w = $request->w;
            $roll->l = $request->l;
            $roll->g = $request->g;
            $roll->printing_color = $request->bookingPrintingColor; 
            $roll->loop_color = $request->looColor;
                      
            $newRequest = new Request($roll->toArray());
            
            DB::beginTransaction();
            $roll->update();
            $orderId = $this->_M_OrderPunches->store($request);
            $newRequest->merge(["order_id"=>$orderId,"roll_id"=>$roll->id]);
            $this->_M_OrderRollBagType->store($newRequest);
            DB::commit();
            return responseMsgs(true,"Roll No. ".$roll->roll_no." is Booked","");
        }catch(Exception $e){
            DB::rollBack();
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    function rollTransitRollStock(Request $request){
        try{

            foreach($request->items as $val){
                $rollTransit = $this->_M_RollTransit->find($val);
    
                if($rollTransit){
                    DB::beginTransaction();
                    $rollDtl =$rollTransit->replicate();
                    $rollDtl->setTable($this->_M_RollDetail->getTable());
                    $rollDtl->id =  $rollTransit->id;
                    $rollDtl->save();
                    $rollTransit->delete();
                    DB::commit();
                }
            }
            return responseMsgs(true,"Rolls Added On Stock","");
        }catch(Exception $e){
            return responseMsgs(false,$e->getMessage(),"");
        }

    }

    #=========== end Roll Transit ======================
    public function addRoll(Request $request){
        try{
            if($request->getMethod()=="POST"){
                $rule = [
                    "rollNo"=>"required|unique:".$this->_M_RollDetail->getTable().",roll_no",
                    "purchaseDate"=>"nullable|",
                    "vendorId"=>"required|exists:".$this->_M_VendorDetail->getTable().",id,lock_status,false",
                    "rollSize"=>"required|numeric|min:0.1",
                    "rollGsm"=>"required|numeric|min:0.01",
                    "rollColor"=>"required",
                    "rollLength"=>"required|numeric|min:0.1",
                    "netWeight"=>"required|numeric|min:0.1",
                    "grossWeight"=>"required|numeric|min:0.1",
                    "forClientId"=>"nullable".($request->forClientId?"|exists:".$this->_M_ClientDetails->getTable().",id":""),
                    "estimatedDespatchDate"=>"required_with:forClientId",
                ];
                $validate = Validator::make($request->all(),$rule);
                if($validate->fails()){
                    return validationError($validate);
                }         
                $id = $this->_M_RollDetail->store($request);
                if($request->forClientId){
                    foreach($request->printingColor as $val){
                        $newRequest = new Request(["roll_id"=>$id,"color"=>$val]);
                        $this->_M_RollPrintColor->store($newRequest);
                    }
                }
                $roll = $this->_M_RollDetail->find($id);
                flashToast("message","New Roll Add");
                return responseMsgs(true,"New Roll Added",["rollDtl"=>$roll]);
            }
        }catch(Exception $e){
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    public function importRoll(Request $request){
        try{
            $validate = Validator::make($request->all(),["csvFile"=>"required|mimes:csv,xlsx"]);
            if($validate->fails()){
                return validationError($validate);
            }
            $file = $request->file('csvFile');
            $headings = (new HeadingRowImport())->toArray($file)[0][0];
            $expectedHeadings = ['vendor_name', 'purchase_date', 'roll_size',"roll_type","hardness","roll_gsm","bopp","roll_color","roll_length","net_weight","gross_weight"];
            if (array_diff($expectedHeadings, $headings)) {
                return responseMsgs(false,"data in invalid Formate","");;
            }

            $rows = Excel::toArray([], $file);

            // Validate rows
            $validationErrors = [];
            foreach ($rows[0] as $index => $row) {
                // Skip the header row
                if ($index == 0) continue;
                // Validate each row
                $rowData = array_combine($headings, $row);
                $validator = Validator::make($rowData, [
                    'vendor_name' => 'required|exists:'.$this->_M_VendorDetail->getTable().",vendor_name",
                    'purchase_date' => 'required|date',
                    'roll_size' => 'required',
                    'roll_type' => 'nullable|in:NW,BOPP',
                    "hardness" => "nullable",
                    'roll_gsm' => 'required',
                    'bopp' => 'required_if:roll_type,BOPP',
                    'roll_color' => 'required|exists:'.$this->_M_RollColor->getTable().",color",
                    'roll_length' => 'required|int',
                    'net_weight' => 'required',
                    'gross_weight' => 'required',
                ]);

                if ($validator->fails()) {
                    $validationErrors[$index] = $validator->errors()->all();
                }
            }

            if (!empty($validationErrors)) {
                return responseMsgs(false,"data is invalid",$validationErrors);
            }

            // Import the CSV file using the RollDetailsImport class
            DB::beginTransaction();
            Excel::import(new RollDetailsImport, $file);
            DB::commit();
            return responseMsgs(true,"data import","");

        }catch(Exception $e){
            dd($e);
            DB::rollBack();
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    function rollBook(Request $request){
        try{

            $rule=[
                "rollId"=>"required|exists:".$this->_M_RollDetail->getTable().",id,lock_status,false",
                "bookingForClientId"=>"required|exists:".$this->_M_ClientDetails->getTable().",id,lock_status,false",
                "bookingEstimatedDespatchDate"=>"required|date",
                "bookingBagUnits"=>"required|in:Kg,Pice",
                "bookingBagTypeId"=>"required|exists:".$this->_M_BagType->getTable().",id",
                "bookingPrintingColor"=>"required|array",
                "bookingPrintingColor.*"=>"required",
            ];
            $validate = Validator::make($request->all(),$rule);
            if($validate->fails()){
                return validationError($validate);
            }

            $request->merge([
                "clientDetailId"=>$request->bookingForClientId,
                "estimateDeliveryDate"=>$request->bookingEstimatedDespatchDate,
            ]);

            $roll = $this->_M_RollDetail->find($request->rollId);
            $roll->client_detail_id = $request->bookingForClientId;
            $roll->estimate_delivery_date = $request->bookingEstimatedDespatchDate;
            $roll->bag_type_id = $request->bookingBagTypeId;
            $roll->bag_unit = $request->bookingBagUnits;
            $roll->w = $request->w;
            $roll->l = $request->l;
            $roll->g = $request->g;
            $roll->printing_color = $request->bookingPrintingColor; 
            $roll->loop_color = $request->looColor;

            $newRequest = new Request($roll->toArray());

            DB::beginTransaction();
            $roll->update();

            $orderId = $this->_M_OrderPunches->store($request);
            $newRequest->merge(["order_id"=>$orderId,"roll_id"=>$roll->id]);
            $this->_M_OrderRollBagType->store($newRequest);

            DB::commit();
            return responseMsgs(true,"Roll No. ".$roll->roll_no." is Booked","");
        }catch(Exception $e){
            DB::rollBack();
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    public function rollList(Request $request){
        $flag= $request->flag;
        $user_type = Auth()->user()->user_type_id??"";
        if($request->ajax()){
            // dd($request->ajax());
            $data = $this->_M_RollDetail->select("roll_details.*","vendor_detail_masters.vendor_name",
                                "client_detail_masters.client_name",
                                "bag_type_masters.bag_type",
                                DB::raw("
                                    roll_details.gsm_variation * 100 as gsm_variation,
                                    TO_CHAR(roll_details.purchase_date, 'DD-MM-YYYY') as purchase_date ,
                                    TO_CHAR(roll_details.estimate_delivery_date, 'DD-MM-YYYY') as estimate_delivery_date ,
                                    TO_CHAR(roll_details.delivery_date, 'DD-MM-YYYY') as delivery_date ,
                                    TO_CHAR(roll_details.printing_date, 'DD-MM-YYYY') as printing_date ,
                                    TO_CHAR(roll_details.cutting_date, 'DD-MM-YYYY') as cutting_date ,    
                                    TO_CHAR(printing_schedule_details.printing_date, 'DD-MM-YYYY') as schedule_date_for_print ,
                                    TO_CHAR(cutting_schedule_details.cutting_date , 'DD-MM-YYYY') as schedule_date_for_cutting                                 
                                    ")
                                )
                    ->join("vendor_detail_masters","vendor_detail_masters.id","roll_details.vender_id")
                    ->leftJoin("client_detail_masters","client_detail_masters.id","roll_details.client_detail_id")
                    ->leftJoin("bag_type_masters","bag_type_masters.id","roll_details.bag_type_id")
                    ->leftJoin("printing_schedule_details",function($join){
                        $join->on("printing_schedule_details.roll_id","=","roll_details.id")
                        ->where("printing_schedule_details.lock_status",false);
                    })
                    ->leftJoin("cutting_schedule_details",function($join){
                        $join->on("cutting_schedule_details.roll_id","=","roll_details.id")
                        ->where("cutting_schedule_details.lock_status",false);
                    })
                    ->where("roll_details.lock_status",false);                    
            if($flag!="history"){
                $data->where("roll_details.is_cut",false);
            }
            if($flag=="history" ){
                $fromDate = $request->fromDate;
                $uptoDate = $request->uptoDate;
                if($fromDate && $uptoDate){              
                    $data->whereBetween("purchase_date",[$fromDate,$uptoDate]);
                }
                elseif($fromDate){
                    $data->where("purchase_date",">=",$fromDate);
                }
                elseif($uptoDate){
                    $data->where("purchase_date","<=",$uptoDate);
                }
            }

            if($flag=="schedule-printing"){
                if(!in_array($user_type,[11,12])){
                    $data->whereNotNull("roll_details.client_detail_id");
                }
                $data->where("roll_details.is_printed",false)
                    ->where(function($where){
                        $where->where("roll_details.is_schedule_for_print",false)
                        ->orWhere("roll_details.schedule_date_for_print","<",Carbon::now()->format("Y-m-d"));
                    })
                    ->orderBy("roll_details.despatch_date","ASC");
            }elseif($flag=="print"){
                $data->where("roll_details.is_printed",false)
                ->where("roll_details.is_schedule_for_print",true)
                ->orderBy("roll_details.schedule_date_for_print","ASC");
            }elseif($flag=="schedule-cutting"){
                if(!in_array($user_type,[11,12])){
                    $data->whereNotNull("roll_details.client_detail_id");
                }
                $data->where("roll_details.is_cut",false)
                    ->where(function($where){
                        $where->where("roll_details.is_schedule_for_cutting",false)
                        ->orWhere("roll_details.schedule_date_for_cutting","<",Carbon::now()->format("Y-m-d"));
                    })
                    ->orderBy("roll_details.schedule_date_for_cutting","ASC");
            }elseif($flag=="cutting"){
                $data->where("roll_details.is_cut",false)
                ->where("roll_details.is_schedule_for_cutting",true)
                ->orderBy("roll_details.schedule_date_for_cutting","ASC");
            }
            else{
                $data->orderBy("roll_details.id","DESC");
            }
            // if($flag=="booking"){
            //     $data->whereNull("roll_details.for_client_id");
            // }
            if ($request->has('export')) {
                // Skip pagination when exporting
                $data = $data->get();
                return Excel::download(new ExportRoll($data), 'roll.xlsx');
            }
            // Handling search
            if ($request->has('search')) {
                $search = $request->search['value'];  // search term from DataTables
                
                $data = $data->where(function ($query) use ($search) {

                    $query->where("roll_details.roll_no","LIKE", "%$search%")
                        ->orWhere("roll_details.purchase_date","LIKE", "%$search%")
                        ->orWhere("roll_details.size","LIKE", "%$search%")
                        ->orWhere("roll_details.gsm","LIKE", "%$search%")
                        ->orWhere("roll_details.roll_color","LIKE", "%$search%")
                        ->orWhere("roll_details.length","LIKE", "%$search%")
                        ->orWhere("roll_details.net_weight","LIKE", "%$search%")
                        ->orWhere("roll_details.gross_weight","LIKE", "%$search%")
                        ->orWhere('vendor_detail_masters.vendor_name', 'LIKE', "%$search%")
                        ->orWhere('client_detail_masters.client_name', 'LIKE', "%$search%")
                        ->orWhere('bag_type_masters.bag_type', 'LIKE', "%$search%");  // Assuming ststop is a field to search
                });
            }
            // DB::enableQueryLog();
            $list = DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('row_color', function ($val) use($flag) {
                    $color = "";
                    $gsmVariationPer = $val->gsm_variation;
                    if(!is_between($gsmVariationPer,-4,4)){
                        $color="tr-gsm_variation";
                    }
                    if($val->for_client_id && $val->is_printed){
                        $color="tr-client-printed";
                    }elseif($val->is_printed){
                        $color="tr-printed";
                    }
                    elseif($val->for_client_id){
                        $color="tr-client";
                    }
                    if($flag=="schedule-printing"){
                        $color="";
                        if($flag=="schedule" && $val->estimated_despatch_date ){
                            $dayDiff = Carbon::now()->diffInDays(Carbon::parse($val->estimated_despatch_date),false);
                            // dd($dayDiff,$val->despatch_date);
                            if($dayDiff<3){
                                $color="tr-primary-print";
                            }
                            if($dayDiff<2){
                                $color="tr-argent-print";
                            }
                            if($dayDiff<0){
                                $color="tr-expiry-print blink";
                            }
                        }
                    }
                    if($flag=="schedule-cutting"){
                        $color="";
                        if($flag=="schedule" && $val->estimated_despatch_date ){
                            $dayDiff = Carbon::now()->diffInDays(Carbon::parse($val->estimated_despatch_date),false);
                            // dd($dayDiff,$val->despatch_date);
                            if($dayDiff<3){
                                $color="tr-primary-print";
                            }
                            if($dayDiff<2){
                                $color="tr-argent-print";
                            }
                            if($dayDiff<0){
                                $color="tr-expiry-print blink";
                            }
                        }
                    }
                    
                    return $color;
                })
                ->addColumn('gsm_variation', function ($val) {                        
                    return roundFigure($val->gsm_variation)."%";
                })
                ->addColumn('print_color', function ($val) {                    
                    return collect(json_decode($val->printing_color,true))->implode(",");
                })
                ->addColumn('action', function ($val) use($flag,$user_type) {                    
                    $button = "";
                    if($val->is_roll_cut){
                        return $button;
                    }
                    if(in_array($user_type,[1,2]) && !$val->client_detail_id){
                        $button .= '<button class="btn btn-sm btn-warning" onClick="openModelBookingModel('.$val->id.')" >Book</button>';
                    }if(in_array($user_type,[1,2]) && $val->client_detail_id && !$val->is_printed){
                        $button .= '<button class="btn btn-sm btn-danger" onClick="openModelAlterBookingModel('.$val->id.')" >Alter Booking</button>';
                    }
                    if($flag=="schedule-printing"){
                        $button='<button class="btn btn-sm btn-warning" onClick="openPrintingScheduleModel('.$val->id.')" >Schedule For Print</button>';
                        if($val->is_schedule_for_print){
                            $button='<button class="btn btn-sm btn-warning" onClick="openPrintingScheduleModel('.$val->id.')" >Re-Schedule For Print</button>';
                        }
                    }
                    if($flag=="print"){
                        $button='<button class="btn btn-sm btn-info" onClick="openPrintingModel('.$val->id.')" >Update Print</button>';
                    }
                    if($flag=="schedule-cutting"){
                        $button='<button class="btn btn-sm btn-warning" onClick="openCuttingScheduleModel('.$val->id.')" >Schedule For Cut</button>';
                        if($val->is_schedule_for_cutting){
                            $button='<button class="btn btn-sm btn-warning" onClick="openCuttingScheduleModel('.$val->id.')" >Re-Schedule For Cut</button>';
                        }
                    }
                    if($flag=="cutting"){
                        $button='<button class="btn btn-sm btn-info" onClick="openCuttingModel('.$val->id.')" >Update Cutting</button>';
                    }
                    return $button;
                })
                ->rawColumns(['row_color', 'action'])
                ->make(true);
                // dd(DB::getQueryLog());
            return $list;

        }
        $data["flag"]=$flag;
        return view("Roll/list",$data);
    }

    public function rollDtl($id,Request $request){
        try{
            $data = $this->_M_RollDetail->find($id);
            if($request->flag=="cutting"){
                $data->schedule_date = $data->getCuttingSchedule()->first();
            }elseif($request->flag=="printing"){
                $data->schedule_date = $data->getPrintingSchedule()->first();
            }
            return responseMsgs(true,"roll dtl fetched",$data);
        }catch(Exception $e){
            return responseMsgs(false,$e->getMessage(),"");
        }
    }
    /*
    public function rollPrintingSchedule(Request $request){
        try{            
            $roll = $this->_M_RollDetail->find($request->printingScheduleRollId);
            $request->merge([
                "roll_id"=>$roll->id,
                "printing_date"=>$request->printingScheduleDate,
                "machine_id"=>$request->printingMachineId
            ]);
            if($roll->is_cut){
                throw new Exception("Roll Already Cute");
            }
            if($roll->is_printed){
                throw new Exception("Roll Already Printed");
            }
            DB::beginTransaction();
            $this->_M_PrintingScheduleDetail->where("roll_id",$request->printingScheduleRollId)->update(["lock_status"=>true]);
            $this->_M_PrintingScheduleDetail->store($request);
            DB::commit();
            return responseMsgs(true,"Roll No-".$roll->roll_no." Is Scheduled","");
        }catch(Exception $e){
            DB::rollBack();
            return responseMsgs(false,$e->getMessage(),"");
        }
    }
    

    public function rollCuttingSchedule(Request $request){
        try{
            $roll = $this->_M_RollDetail->find($request->cuttingScheduleRollId);
            $request->merge([
                "roll_id"=>$roll->id,
                "cutting_date"=>$request->cuttingScheduleDate,
                "machine_id"=>$request->cuttingMachineId
            ]);
            if($roll->is_roll_cut){
                throw new Exception("Roll Already Cute");
            }
            DB::beginTransaction();
            $this->_M_CuttingScheduleDetail->where("roll_id",$request->cuttingScheduleRollId)->update(["lock_status"=>true]);
            $this->_M_CuttingScheduleDetail->store($request);
            DB::commit();
            return responseMsgs(true,"Roll No-".$roll->roll_no." Is Scheduled","");
        }catch(Exception $e){
            DB::rollBack();
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    */


    #====== roll register =========================
    public function rollRegister(Request $request){
        $flag= $request->flag;
        $user_type = Auth()->user()->user_type_id??"";
        list($from,$upto)=explode("-",getFY());
        $data["fromDate"] = $from."-04-01";
        $data["uptoDate"] = Carbon::now()->format("Y-m-d");
        if($request->ajax()){
            // dd($request->ajax());
            $fromDate = $request->fromDate;
            $uptoDate = $request->uptoDate;
            $data = $this->_M_RollDetail->select("roll_details.*","vendor_detail_masters.vendor_name",
                                "client_detail_masters.client_name",
                                "bag_type_masters.bag_type",
                                DB::raw("printing_schedule_details.printing_date AS schedule_date_for_print , 
                                cutting_schedule_details.cutting_date AS schedule_date_for_cutting")
                                )
                    ->join("vendor_detail_masters","vendor_detail_masters.id","roll_details.vender_id")
                    ->leftJoin("client_detail_masters","client_detail_masters.id","roll_details.client_detail_id")
                    ->leftJoin("bag_type_masters","bag_type_masters.id","roll_details.bag_type_id")
                    ->leftJoin("printing_schedule_details",function($join){
                        $join->on("printing_schedule_details.roll_id","=","roll_details.id")
                        ->where("printing_schedule_details.lock_status",false);
                    })
                    ->leftJoin("cutting_schedule_details",function($join){
                        $join->on("cutting_schedule_details.roll_id","=","roll_details.id")
                        ->where("cutting_schedule_details.lock_status",false);
                    })
                    ->where("roll_details.lock_status",false)
                    ->orderBy("roll_details.id","DESC"); 

            if($fromDate && $uptoDate){              
                $data->whereBetween("purchase_date",[$fromDate,$uptoDate]);
            }
            elseif($fromDate){
                $data->where("purchase_date",">=",$fromDate);
            }
            elseif($uptoDate){
                $data->where("purchase_date","<=",$uptoDate);
            } 
            // DB::enableQueryLog();
            $list = DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('row_color', function ($val) use($flag) {
                    $color = "";
                    if($val->for_client_id && $val->is_printed){
                        $color="tr-client-printed";
                    }elseif($val->is_printed){
                        $color="tr-printed";
                    }
                    elseif($val->for_client_id){
                        $color="tr-client";
                    }                    
                    return $color;
                })
                ->addColumn('print_color', function ($val) {                    
                    return collect(json_decode($val->printing_color,true))->implode(",");
                })
                ->addColumn("loop_color",function($val){
                    return"";
                })
                ->rawColumns(['row_color', 'action'])
                ->make(true);
                // dd(DB::getQueryLog());
            return $list;

        }
        $data["flag"]=$flag;
        return view("Roll/register",$data);
    }

    public function rollRegisterPrinting(Request $request){
        $flag= $request->flag;
        $machineId = $request->machineId;
        $user_type = Auth()->user()->user_type_id??"";
        list($from,$upto)=explode("-",getFY());
        $data["fromDate"] = $from."-04-01";
        $data["uptoDate"] = Carbon::now()->format("Y-m-d");
        if($request->ajax()){
            // dd($request->ajax());
            $fromDate = $request->fromDate;
            $uptoDate = $request->uptoDate;
            $data = $this->_M_RollDetail->select("roll_details.*","vendor_detail_masters.vendor_name",
                                "client_detail_masters.client_name",
                                "bag_type_masters.bag_type",
                                DB::raw("
                                    TO_CHAR(roll_details.purchase_date, 'DD-MM-YYYY') as purchase_date ,
                                    TO_CHAR(roll_details.estimate_delivery_date, 'DD-MM-YYYY') as estimate_delivery_date ,
                                    TO_CHAR(roll_details.delivery_date, 'DD-MM-YYYY') as delivery_date ,
                                    TO_CHAR(roll_details.printing_date, 'DD-MM-YYYY') as printing_date ,
                                    TO_CHAR(roll_details.cutting_date, 'DD-MM-YYYY') as cutting_date                                     
                                    ")
                                )
                    ->join("vendor_detail_masters","vendor_detail_masters.id","roll_details.vender_id")
                    ->leftJoin("client_detail_masters","client_detail_masters.id","roll_details.client_detail_id")
                    ->leftJoin("bag_type_masters","bag_type_masters.id","roll_details.bag_type_id")                    
                    ->where("roll_details.lock_status",false)
                    ->where("roll_details.printing_machine_id",$machineId)
                    ->orderBy("roll_details.id","DESC"); 

            if($fromDate && $uptoDate){              
                $data->whereBetween("printing_date",[$fromDate,$uptoDate]);
            }
            elseif($fromDate){
                $data->where("printing_date",">=",$fromDate);
            }
            elseif($uptoDate){
                $data->where("printing_date","<=",$uptoDate);
            } 
            DB::enableQueryLog();
            // $data->get();
            // dd(DB::getQueryLog());
            $list = DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('row_color', function ($val) use($flag) {
                    $color = "";
                    if($val->for_client_id && $val->is_printed){
                        $color="tr-client-printed";
                    }elseif($val->is_printed){
                        $color="tr-printed";
                    }
                    elseif($val->for_client_id){
                        $color="tr-client";
                    }                    
                    return $color;
                })
                ->addColumn('print_color', function ($val) {                    
                    return collect(json_decode($val->printing_color,true))->implode(",");
                })
                ->addColumn("loop_color",function($val){
                    return"";
                })
                ->rawColumns(['row_color', 'action'])
                ->make(true);
                // dd(DB::getQueryLog());
            return $list;

        }
        $data=[];
        $data["machine"] = $this->_M_Machine->find($machineId);
        return view("Roll/rollRegisterPrinting",$data);
    }

    public function rollRegisterCutting(Request $request){
        $flag= $request->flag;
        $machineId = $request->machineId;
        $user_type = Auth()->user()->user_type_id??"";
        list($from,$upto)=explode("-",getFY());
        $data["fromDate"] = $from."-04-01";
        $data["uptoDate"] = Carbon::now()->format("Y-m-d");
        if($request->ajax()){
            // dd($request->ajax());
            $fromDate = $request->fromDate;
            $uptoDate = $request->uptoDate;
            $data = $this->_M_RollDetail->select("roll_details.*","vendor_detail_masters.vendor_name",
                                "client_detail_masters.client_name",
                                "bag_type_masters.bag_type",
                                DB::raw("
                                    TO_CHAR(roll_details.purchase_date, 'DD-MM-YYYY') as purchase_date ,
                                    TO_CHAR(roll_details.estimate_delivery_date, 'DD-MM-YYYY') as estimate_delivery_date ,
                                    TO_CHAR(roll_details.delivery_date, 'DD-MM-YYYY') as delivery_date ,
                                    TO_CHAR(roll_details.printing_date, 'DD-MM-YYYY') as printing_date ,
                                    TO_CHAR(roll_details.cutting_date, 'DD-MM-YYYY') as cutting_date                                     
                                    ")
                                )
                    ->join("vendor_detail_masters","vendor_detail_masters.id","roll_details.vender_id")
                    ->leftJoin("client_detail_masters","client_detail_masters.id","roll_details.client_detail_id")
                    ->leftJoin("bag_type_masters","bag_type_masters.id","roll_details.bag_type_id")                    
                    ->where("roll_details.lock_status",false)
                    ->where("roll_details.cutting_machine_id",$machineId)
                    ->orderBy("roll_details.id","DESC"); 

            if($fromDate && $uptoDate){              
                $data->whereBetween("cutting_date",[$fromDate,$uptoDate]);
            }
            elseif($fromDate){
                $data->where("cutting_date",">=",$fromDate);
            }
            elseif($uptoDate){
                $data->where("cutting_date","<=",$uptoDate);
            } 
            DB::enableQueryLog();
            // $data->get();
            // dd(DB::getQueryLog());
            $list = DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('row_color', function ($val) use($flag) {
                    $color = "";
                    if($val->for_client_id && $val->is_printed){
                        $color="tr-client-printed";
                    }elseif($val->is_printed){
                        $color="tr-printed";
                    }
                    elseif($val->for_client_id){
                        $color="tr-client";
                    }                    
                    return $color;
                })
                ->addColumn('print_color', function ($val) {                    
                    return collect(json_decode($val->printing_color,true))->implode(",");
                })
                ->addColumn("loop_color",function($val){
                    return"";
                })
                ->rawColumns(['row_color', 'action'])
                ->make(true);
                // dd(DB::getQueryLog());
            return $list;

        }
        $data=[];
        $data["machine"] = $this->_M_Machine->find($machineId);
        return view("Roll/rollRegisterCutting",$data);
    }
    

    #===== roll schedule =========================

    public function rollSchedule(Request $request){
        try{

            $flag= $request->flag;
            $user_type = Auth()->user()->user_type_id??"";
            $currentDate = Carbon::now()->format("Y-m-d");
            
            if($request->ajax()){
                $data = $this->_M_RollDetail->select("roll_details.*","vendor_detail_masters.vendor_name",
                                    "client_detail_masters.client_name",
                                    "bag_type_masters.bag_type",
                                    DB::raw("
                                    TO_CHAR(roll_details.purchase_date, 'DD-MM-YYYY') as purchase_date ,
                                    TO_CHAR(roll_details.estimate_delivery_date, 'DD-MM-YYYY') as estimate_delivery_date ,
                                    TO_CHAR(roll_details.delivery_date, 'DD-MM-YYYY') as delivery_date ,
                                    TO_CHAR(roll_details.printing_date, 'DD-MM-YYYY') as printing_date ,
                                    TO_CHAR(printing_schedule_details.printing_date, 'DD-MM-YYYY') as schedule_date_for_print ,
                                    TO_CHAR(cutting_schedule_details.cutting_date, 'DD-MM-YYYY') as schedule_date_for_cutting                                      
                                    ")
                                    )
                        ->join("vendor_detail_masters","vendor_detail_masters.id","roll_details.vender_id")
                        ->Join("client_detail_masters","client_detail_masters.id","roll_details.client_detail_id")
                        ->leftJoin("bag_type_masters","bag_type_masters.id","roll_details.bag_type_id")
                        ->leftJoin("printing_schedule_details",function($join){
                            $join->on("printing_schedule_details.roll_id","=","roll_details.id")
                            ->where("printing_schedule_details.lock_status",false);
                        })
                        ->leftJoin("cutting_schedule_details",function($join){
                            $join->on("cutting_schedule_details.roll_id","=","roll_details.id")
                            ->where("cutting_schedule_details.lock_status",false);
                        })
                        ->where("roll_details.lock_status",false);
                if($flag=="printing"){
                    $data->where("roll_details.is_printed",false)
                    ->whereNotNull(DB::raw("json_array_length(roll_details.printing_color)"))
                    ->where(function($where)use($currentDate){
                        $where->where("printing_schedule_details.printing_date","<",$currentDate)
                        ->orWhereNull("printing_schedule_details.id");

                    });
                } 

                if($flag=="cutting"){
                    $data->where("roll_details.is_cut",false)
                    ->where(function($where)use($currentDate){
                        $where->where("cutting_schedule_details.cutting_date","<",$currentDate)
                        ->orWhereNull("cutting_schedule_details.id");

                    })
                    ->where(function($where){
                        $where->whereNull("printing_schedule_details.id")
                            ->orWhere(DB::raw(" CASE WHEN printing_schedule_details.id IS NOT NULL AND roll_details.is_printed = TRUE THEN TRUE ELSE FALSE END"),true);
                    })
                    ->whereNotNull("roll_details.client_detail_id");
                }

                $data->orderBy("roll_details.estimate_delivery_date","ASC");
                // DB::enableQueryLog();
                // $data->get();
                // dd(DB::getQueryLog());
                
                $list = DataTables::of($data)
                    ->addIndexColumn()
                    ->addColumn('row_color', function ($val) use($flag) {
                        $color = "";
                        if($val->for_client_id && $val->is_printed){
                            $color="tr-client-printed";
                        }elseif($val->is_printed){
                            $color="tr-printed";
                        }
                        elseif($val->for_client_id){
                            $color="tr-client";
                        }                    
                        return $color;
                    })
                    ->addColumn('action', function ($val)use($flag) {                    
                        $button = "";
                        if($val->is_roll_cut){
                            return $button;
                        }
                        if($flag=="printing"){
                            if(!$val->schedule_date_for_print){
                                $button .= '<button class="btn btn-sm btn-warning" onClick="openPrintingScheduleModel('.$val->id.')" >Schedule</button>';
                            }elseif($val->schedule_date_for_print){
                                $button .= '<button class="btn btn-sm btn-danger" onClick="openPrintingScheduleModel('.$val->id.')" >Re-Schedule</button>';
                            }
                        }elseif($flag=="cutting"){
                            if(!$val->schedule_date_for_cutting){
                                $button .= '<button class="btn btn-sm btn-warning" onClick="openCuttingScheduleModel('.$val->id.')" >Schedule</button>';
                            }elseif($val->schedule_date_for_cutting){
                                $button .= '<button class="btn btn-sm btn-danger" onClick="openCuttingScheduleModel('.$val->id.')" >Re-Schedule</button>';
                            }
                        }
                        return $button;
                    })
                    ->addColumn('print_color', function ($val) {                    
                        return collect(json_decode($val->printing_color,true))->implode(",");
                    })
                    ->addColumn("loop_color",function($val){
                        return"";
                    })
                    ->rawColumns(['row_color', 'action'])
                    ->make(true);
                    // dd(DB::getQueryLog());
                return $list;
    
            }
            $data["flag"]=$flag;
            return view("Roll/schedule",$data);
        }catch(Exception $e){

        }
    }

    public function rollScheduleSet(Request $request){
        try{
            $flag =  $request->flag;
            $rules = [
                "scheduleDate" => "required|date|after_or_equal:" . Carbon::now()->format("Y-m-d"),
                "rolls" => "required|array",
                "rolls.*.id" => "required|exists:" . $this->_M_RollDetail->getTable() . ",id",
                "rolls.*.position" => "required|integer", // Use `integer` for clarity instead of `int`
            ];
            $validate = Validator::make($request->all(),$rules);
            if($validate->fails()){
                return validationError($validate);
            }
            DB::beginTransaction();
            foreach($request->rolls as $roll){
                $newRequest = new Request($roll);
                $newRequest->merge([
                    "roll_id"=>$roll["id"],
                    "cutting_date"=>$request->scheduleDate,
                    "printing_date"=>$request->scheduleDate,
                    "sl"=> $roll["position"],
                ]);
                if($flag=="printing"){
                    $this->_M_PrintingScheduleDetail->where("roll_id",$newRequest->roll_id)->update(["lock_status"=>true]);
                    $this->_M_PrintingScheduleDetail->store($newRequest);
                }elseif($flag=="cutting"){
                    $this->_M_CuttingScheduleDetail->where("roll_id",$newRequest->roll_id)->update(["lock_status"=>true]);
                    $this->_M_CuttingScheduleDetail->store($newRequest);
                }
            }
            DB::commit();
            return responseMsgs(true,"Roll Schedule for ".$flag,"");

        }catch(Exception $e){
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    public function rollProduction(Request $request){
        $user_type = Auth()->user()->user_type_id??"";
        $machineId = $request->machineId;
        if($request->ajax())
        {
            // dd($request->ajax());
            $fromDate = $request->fromDate;
            $uptoDate = $request->uptoDate;
            if(!$fromDate){
                $fromDate = Carbon::now()->format("Y-m-d");
            }
            if(!$uptoDate){
                $uptoDate = Carbon::now()->format("Y-m-d");
            }
            $data = $this->_M_RollDetail->select(
                                "roll_details.*","vendor_detail_masters.vendor_name",
                                "client_detail_masters.client_name",
                                "bag_type_masters.bag_type",
                                DB::raw("
                                    TO_CHAR(roll_details.purchase_date, 'DD-MM-YYYY') as purchase_date ,
                                    TO_CHAR(roll_details.estimate_delivery_date, 'DD-MM-YYYY') as estimate_delivery_date ,
                                    TO_CHAR(roll_details.delivery_date, 'DD-MM-YYYY') as delivery_date ,
                                    TO_CHAR(roll_details.printing_date, 'DD-MM-YYYY') as printing_date ,
                                    TO_CHAR(printing_schedule_details.printing_date, 'DD-MM-YYYY') as schedule_date_for_print                                      
                                    ")
                    )
                    ->join("vendor_detail_masters","vendor_detail_masters.id","roll_details.vender_id")
                    ->leftJoin("client_detail_masters","client_detail_masters.id","roll_details.client_detail_id")
                    ->leftJoin("bag_type_masters","bag_type_masters.id","roll_details.bag_type_id")
                    ->Join("printing_schedule_details",function($join){
                        $join->on("printing_schedule_details.roll_id","=","roll_details.id")
                        ->where("printing_schedule_details.lock_status",false);
                    })
                    ->where("roll_details.lock_status",false)
                    ->where("roll_details.is_printed",false)
                    ->orderBy("printing_schedule_details.sl","ASC");
            if($machineId==1){                
                $data->where(function($where){
                    $where->where(DB::raw("json_array_length(roll_details.printing_color)"),"<=",2)
                        ->orWhereNull("roll_details.printing_color");
                });
            }                     

            if($fromDate && $uptoDate){             
                $data->whereBetween("printing_schedule_details.printing_date",[$fromDate,$uptoDate]);
            }

            elseif($fromDate){
                $data->where("printing_schedule_details.printing_date",">=",$fromDate);
            }
            elseif($uptoDate){
                $data->where("printing_schedule_details.printing_date","<=",$uptoDate);
            } 
            $list = DataTables::of($data)
                ->addIndexColumn()                
                ->addColumn('print_color', function ($val) {                    
                    return collect(json_decode($val->printing_color,true))->implode(",");
                })
                ->addColumn("loop_color",function($val){
                    return"";
                })
                ->addColumn('action', function ($val){                    
                    $button = "";
                    if(!$val->is_cut){
                        $button .= '<button class="btn btn-sm btn-warning" onClick="openPrintingUpdateModel('.$val->id.')" >Update</button>';
                    }                    
                    return $button;
                })
                ->rawColumns(['row_color', 'action'])
                ->make(true);
            return $list;

        }
        $data=[];
        $data["machine"] = $this->_M_Machine->find($machineId);
        return view("Roll/rollProduction",$data);
    }

    public function rollProductionCutting(Request $request){
        $user_type = Auth()->user()->user_type_id??"";
        $machineId = $request->machineId;
        if($request->ajax())
        {
            // dd($request->ajax());
            $fromDate = $request->fromDate;
            $uptoDate = $request->uptoDate;
            $data = $this->_M_RollDetail->select("roll_details.*","vendor_detail_masters.vendor_name",
                                "client_detail_masters.client_name",
                                "bag_type_masters.bag_type",
                                DB::raw("
                                    TO_CHAR(roll_details.purchase_date, 'DD-MM-YYYY') as purchase_date ,
                                    TO_CHAR(roll_details.estimate_delivery_date, 'DD-MM-YYYY') as estimate_delivery_date ,
                                    TO_CHAR(roll_details.delivery_date, 'DD-MM-YYYY') as delivery_date ,
                                    TO_CHAR(roll_details.printing_date, 'DD-MM-YYYY') as printing_date ,
                                    TO_CHAR(cutting_schedule_details.cutting_date, 'DD-MM-YYYY') as schedule_date_for_cutting                                      
                                    ")
                                )
                    ->join("vendor_detail_masters","vendor_detail_masters.id","roll_details.vender_id")
                    ->leftJoin("client_detail_masters","client_detail_masters.id","roll_details.client_detail_id")
                    ->leftJoin("bag_type_masters","bag_type_masters.id","roll_details.bag_type_id")                    
                    ->Join("cutting_schedule_details",function($join){
                        $join->on("cutting_schedule_details.roll_id","=","roll_details.id")
                        ->where("cutting_schedule_details.lock_status",false);
                    })
                    ->where("roll_details.lock_status",false)
                    ->where("roll_details.is_cut",false)
                    ->orderBy("cutting_schedule_details.sl","ASC");                     

            if($fromDate && $uptoDate){             
                $data->whereBetween("cutting_schedule_details.cutting_date",[$fromDate,$uptoDate]);
            }

            elseif($fromDate){
                $data->where("cutting_schedule_details.cutting_date",">=",$fromDate);
            }
            elseif($uptoDate){
                $data->where("cutting_schedule_details.cutting_date","<=",$uptoDate);
            } 
            $list = DataTables::of($data)
                ->addIndexColumn()                
                ->addColumn('print_color', function ($val) {                    
                    return collect(json_decode($val->printing_color,true))->implode(",");
                })
                ->addColumn("loop_color",function($val){
                    return"";
                })
                ->addColumn('action', function ($val){                    
                    $button = "";
                    if(!$val->is_cut){
                        $button .= '<button class="btn btn-sm btn-warning" onClick="openCuttingUpdateModel('.$val->id.')" >Update</button>';
                    }                    
                    return $button;
                })
                ->rawColumns(['row_color', 'action'])
                ->make(true);
            return $list;

        }
        $data=[];
        $data["machine"] = $this->_M_Machine->find($machineId);
        return view("Roll/rollProductionCutting",$data);
    }

    public function rollSearchPrinting(Request $request){
        try{
            $data = $this->_M_RollDetail
                    ->select("roll_details.*",
                        "client_detail_masters.client_name",
                        DB::raw("
                                    TO_CHAR(roll_details.purchase_date, 'DD-MM-YYYY') as purchase_date ,
                                    TO_CHAR(roll_details.estimate_delivery_date, 'DD-MM-YYYY') as estimate_delivery_date ,
                                    TO_CHAR(roll_details.delivery_date, 'DD-MM-YYYY') as delivery_date
                                "
                                )
                    )
                    ->leftJoin("client_detail_masters","client_detail_masters.id","roll_details.client_detail_id")
                    ->where("roll_details.roll_no",$request->rollNo)
                    ->where("roll_details.is_printed",false)
                    ->where("roll_details.lock_status",false)
                    ->first();
            if($data){
                $data->printing_color = collect(json_decode($data->printing_color,true))->implode(",");
            }
            return responseMsgs(true,"Data Fetch",$data);
        }catch(Exception $e){
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    public function rollSearchCutting(Request $request){
        try{
            DB::enableQueryLog();
            $data = $this->_M_RollDetail
                    ->select("roll_details.*",
                        "client_detail_masters.client_name",
                        DB::raw("
                                    TO_CHAR(roll_details.purchase_date, 'DD-MM-YYYY') as purchase_date ,
                                    TO_CHAR(roll_details.estimate_delivery_date, 'DD-MM-YYYY') as estimate_delivery_date ,
                                    TO_CHAR(roll_details.delivery_date, 'DD-MM-YYYY') as delivery_date
                                "
                                )
                    )
                    ->leftJoin("client_detail_masters","client_detail_masters.id","roll_details.client_detail_id")
                    ->where("roll_details.roll_no",$request->rollNo)
                    ->where(function ($query) {
                        $query->where(function($subQuery){
                                    $subQuery->where('roll_details.is_printed', false)
                                    ->whereNull(DB::raw('json_array_length(roll_details.printing_color)'));
                                }                            
                            )
                            ->orWhere(function ($subQuery) {
                                  $subQuery->whereNotNull(DB::raw('json_array_length(roll_details.printing_color)'))
                                           ->where('roll_details.is_printed', true);
                              });
                    })                   
                    ->where("roll_details.is_cut",false)
                    ->whereNotNull("roll_details.client_detail_id")
                    ->where("roll_details.lock_status",false)
                    ->first();
            if($data){
                $data->printing_color = collect(json_decode($data->printing_color,true))->implode(",");
            }
            return responseMsgs(true,"Data Fetch",$data);
        }catch(Exception $e){
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    public function rollPrintingUpdate(Request $request){        
        try{
           
            $rule = [
                "id" => "required|exists:" . $this->_M_Machine->getTable() . ",id,is_printing,true",
                "printingUpdate" => "required|date|date_format:Y-m-d|before_or_equal:" . Carbon::now()->format("Y-m-d"),
                "roll" => "required|array",
                "roll.*.id"=>"required|exists:".$this->_M_RollDetail->getTable().",id,lock_status,false,is_printed,false,is_cut,false",
            ];
            $validate = Validator::make($request->all(),$rule);
            if($validate->fails()){
                return validationError($validate);
            }
            DB::beginTransaction();
            foreach($request->roll as $index=>$val){
                $roll = $this->_M_RollDetail->find($val['id']);
                $roll->is_printed = true;
                $roll->printing_date = $request->printingUpdate;
                $roll->weight_after_print = $val["printingWeight"];
                $roll->printing_machine_id = $request->id;
                $roll->update();
            }
            DB::commit();
            return responseMsgs(true,"Roll No ".$roll->roll_no." Printed","");

        }catch(Exception $e){
            DB::rollBack();
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    public function rollCuttingUpdate(Request $request){        
        try{
           
            $rule = [
                "id" => "required|exists:" . $this->_M_Machine->getTable() . ",id,is_cutting,true",
                "cuttingUpdate" => "required|date|date_format:Y-m-d|before_or_equal:" . Carbon::now()->format("Y-m-d"),
                "roll" => "required|array",
                "roll.*.id"=>"required|exists:".$this->_M_RollDetail->getTable().",id,lock_status,false,is_cut,false",
            ];
            $validate = Validator::make($request->all(),$rule);
            if($validate->fails()){
                return validationError($validate);
            }
            DB::beginTransaction();
            foreach($request->roll as $index=>$val){
                $roll = $this->_M_RollDetail->find($val['id']);
                $roll->is_cut = true;
                $roll->cutting_date = $request->cuttingUpdate;
                $roll->weight_after_cutting = $val["cuttingWeight"];
                $roll->cutting_machine_id = $request->id;
                $roll->update();
            }
            DB::commit();
            return responseMsgs(true,"Roll No ".$roll->roll_no." Printed","");

        }catch(Exception $e){
            DB::rollBack();
            return responseMsgs(false,$e->getMessage(),"");
        }
    }


    public function orderPunches(Request $request){
        $data["clientList"] = $this->_M_ClientDetails->getClientListOrm()->get();
        $data["bagType"] = $this->_M_BagType->getBagListOrm()->get();
        $data["color"] = $this->_M_Color->getColorListOrm()->get();
        return view("Roll/orderPunches",$data);
    }

    public function oldOrderOfClient(Request $request){
        try{
            $roll = $this->_M_RollDetail
                    ->select(DB::raw("roll_details.gsm,roll_details.roll_color,roll_details.length,roll_details.size,
                                      roll_details.net_weight, roll_details.roll_type, roll_details.hardness, roll_details.bag_type_id,
                                      roll_details.bag_unit, roll_details.w, roll_details.l, roll_details.g, roll_details.printing_color::text,
                                      bag_type_masters.bag_type
                                      ")
                    )
                    ->join("bag_type_masters","bag_type_masters.id","roll_details.bag_type_id")
                    ->where("roll_details.client_detail_id",$request->clientId)
                    ->where("roll_details.lock_status",false)
                    ->groupBy(DB::raw("roll_details.gsm,roll_details.roll_color,roll_details.length,roll_details.size,
                                      roll_details.net_weight, roll_details.roll_type, roll_details.hardness, roll_details.bag_type_id,
                                      roll_details.bag_unit, roll_details.w, roll_details.l, roll_details.g, roll_details.printing_color::text,
                                      bag_type_masters.bag_type
                                      "))
                    ->orderBy("roll_details.bag_type_id")
                    ->get();
            return responseMsgs(true,"old history",$roll);
            

        }catch(ExcelExcel $e){
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    public function orderSuggestionClient(Request $request){
        try{
            $roll=$this->_M_RollDetail->select("*",DB::raw("'stock' as stock"))
                    ->whereNull("client_detail_id")
                    ->where("lock_status",false)
                    ->get();
            $transit = $this->_M_RollTransit->select("*",DB::raw("'transit' as stock"))
                        ->whereNull("client_detail_id")
                        ->where("lock_status",false)
                        ->get();
            $data["roll"]=$roll;
            $data["rollTransit"]= $transit;
            return responseMsgs(true,"data Fetched",$data);
        }catch(ExcelExcel $e){
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    public function orderPunchesSave(Request $request){
        try{
            $request->merge([
                "clientDetailId"=>$request->bookingForClientId,
                "estimateDeliveryDate"=>$request->bookingEstimatedDespatchDate,
            ]);
            DB::beginTransaction();
            $orderId = $this->_M_OrderPunches->store($request);
            $type ="Pending";
            if($request->roll){
                foreach($request->roll as $val){
                    $type ="Booked";
                    $roll = $this->_M_RollDetail->find($val["id"]);
                    if(!$roll){
                        $roll = $this->_M_RollTransit->find($val["id"]);
                    }
                    if($roll->client_detail_id){
                        throw new Exception($roll->roll_no." Roll Already Assign");
                    }
                    $roll->client_detail_id = $request->bookingForClientId;
                    $roll->estimate_delivery_date = $request->bookingEstimatedDespatchDate;
                    $roll->bag_type_id = $request->bookingBagTypeId;
                    $roll->bag_unit = $request->bookingBagUnits;
                    $roll->loop_color = $request->looColor;
                    $roll->w = $request->w;
                    $roll->l = $request->l;
                    $roll->g = $request->g;
                    $roll->printing_color = $request->bookingPrintingColor;
                    $roll->update();
                    $newRequest = new Request($roll->toArray());
                    $newRequest->merge(["order_id"=>$orderId,"roll_id"=>$roll->id]);
                    $this->_M_OrderRollBagType->store($newRequest);
                }

            }else{
                $request->merge(
                    [
                        "bag_type_id" => $request->bookingBagTypeId,
                        "bag_unit" => $request->bookingBagUnits,
                        "loop_color" => $request->looColor,
                        "w" => $request->w,
                        "l" => $request->l,
                        "g" => $request->g,
                        "printing_color" => $request->bookingPrintingColor,
                        "order_id"=>$orderId,
                    ]
                );
                $this->_M_PendingOrderBagType->store($request);
            }
            DB::commit();
            return responseMsgs(true,"Order Place On $type","");
        }catch(Exception $e){
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    public function bookedOrder(Request $request){
        
        if($request->ajax())
        {
            // dd($request->ajax());
            $fromDate = $request->fromDate;
            $uptoDate = $request->uptoDate;
            $orderNo = $request->orderNo;            
            $data = $this->_M_OrderPunches
                    ->select(
                                "order_punch_details.*","order_roll_bag_types.*",
                                "client_detail_masters.client_name",
                                
                    )
                    ->join(
                        DB::raw("(
                            SELECT *
                            FROM(
                                    (
                                        SELECT order_roll_bag_types.order_id, STRING_AGG(roll_details.roll_no,' , ') as roll_no , 
                                            STRING_AGG(bag_type_masters.bag_type,' , ') as bag_type, 
                                            STRING_AGG(roll_details.bag_unit,' , ') as bag_unit, 
                                            STRING_AGG(roll_details.printing_color,' , ') AS printing_color
                                        FROM order_roll_bag_types
                                        JOIN (
                                            SELECT 
                                                roll_details.id, roll_details.roll_no, roll_details.bag_type_id, roll_details.bag_unit,
                                                '(' || STRING_AGG(jsonb_element.value, ', ') || ')' AS printing_color
                                            FROM roll_details
                                            LEFT JOIN LATERAL jsonb_array_elements_text(roll_details.printing_color::jsonb) AS jsonb_element(value) ON TRUE
                                            GROUP BY roll_details.id
                                        ) as roll_details on roll_details.id = order_roll_bag_types.roll_id
                                        JOIN bag_type_masters on bag_type_masters.id = roll_details.bag_type_id
                                        GROUP BY order_roll_bag_types.order_id
                                    )
                                    UNION ALL(
                                        SELECT order_roll_bag_types.order_id, STRING_AGG(roll_transits.roll_no,' , ') as roll_no , 
                                            STRING_AGG(bag_type_masters.bag_type,' , ') as bag_type, 
                                            STRING_AGG(roll_transits.bag_unit,' , ') as bag_unit, 
                                            STRING_AGG(roll_transits.printing_color,' , ') AS printing_color
                                        FROM order_roll_bag_types
                                        JOIN (
                                            SELECT 
                                                roll_transits.id, roll_transits.roll_no, roll_transits.bag_type_id, roll_transits.bag_unit,
                                                '(' || STRING_AGG(jsonb_element.value, ', ') || ')' AS printing_color
                                            FROM roll_transits
                                            LEFT JOIN LATERAL jsonb_array_elements_text(roll_transits.printing_color::jsonb) AS jsonb_element(value) ON TRUE
                                            GROUP BY roll_transits.id
                                        ) as roll_transits on roll_transits.id = order_roll_bag_types.roll_id
                                        JOIN bag_type_masters on bag_type_masters.id = roll_transits.bag_type_id
                                        GROUP BY order_roll_bag_types.order_id
                                    )
                            )
                        ) AS order_roll_bag_types"),
                        "order_roll_bag_types.order_id","order_punch_details.id"
                    )                
                    ->leftJoin("client_detail_masters","client_detail_masters.id","order_punch_details.client_detail_id")                   
                    ->where("order_punch_details.lock_status",false)
                    ->orderBy("order_punch_details.created_at","ASC");                               

            if($fromDate && $uptoDate){             
                $data->whereBetween(DB::raw("order_punch_details.created_at::date"),[$fromDate,$uptoDate]);
            }

            elseif($fromDate){
                $data->where(DB::raw("order_punch_details.created_at::date"),">=",$fromDate);
            }
            elseif($uptoDate){
                $data->where(DB::raw("order_punch_details.created_at::date"),"<=",$uptoDate);
            } 

            if($orderNo){
                $data->where("order_punch_details.order_no",$orderNo);
            }
            // DB::enableQueryLog();
            // $data->get();
            // dd(DB::getQueryLog());            
            $list = DataTables::of($data)
                ->addIndexColumn()                
                ->addColumn('is_delivered', function ($val) {                    
                    return $val->is_delivered ? "YES" : "NO";
                })
                ->addColumn('created_at', function ($val) {                    
                    return $val->created_at ? Carbon::parse($val->created_at)->format("d-m-Y") : "";                    
                })
                ->addColumn('estimate_delivery_date', function ($val) {                    
                    return $val->estimate_delivery_date ? Carbon::parse($val->estimate_delivery_date)->format("d-m-Y") : "";                    
                })
                ->addColumn('delivery_date', function ($val) {                    
                    return $val->delivery_date ? Carbon::parse($val->delivery_date)->format("d-m-Y") : "";                    
                })
                ->make(true);
            return $list;

        }
        return view("Roll/bookedOrder");
    }

    public function unBookedOrder(Request $request){
        
        if($request->ajax())
        {
            // dd($request->ajax());
            $fromDate = $request->fromDate;
            $uptoDate = $request->uptoDate;
            $orderNo = $request->orderNo;            
            $data = $this->_M_OrderPunches
                    ->select(
                                "order_punch_details.*","pending_order_bag_types.*",
                                "client_detail_masters.client_name",
                                
                    )
                    ->join(
                        DB::raw("(
                            SELECT pending_order_bag_types.order_id, 
                                STRING_AGG( DISTINCT(bag_type_masters.bag_type),' , ') as bag_type, 
		                        STRING_AGG( DISTINCT(pending_order_bag_types.bag_unit),' , ') as bag_unit, 
                                '(' || STRING_AGG(jsonb_element.value, ', ') || ')' AS printing_color
                            FROM pending_order_bag_types
                            JOIN bag_type_masters on bag_type_masters.id = pending_order_bag_types.bag_type_id
                            LEFT JOIN LATERAL jsonb_array_elements_text(pending_order_bag_types.printing_color::jsonb) AS jsonb_element(value) ON TRUE
                            GROUP BY pending_order_bag_types.order_id
                        ) AS pending_order_bag_types")
                        ,"pending_order_bag_types.order_id","order_punch_details.id")
                    ->leftJoin("order_roll_bag_types", "order_roll_bag_types.order_id","order_punch_details.id")                
                    ->leftJoin("client_detail_masters","client_detail_masters.id","order_punch_details.client_detail_id")                    
                    ->where("order_punch_details.lock_status",false)
                    ->whereNull("order_roll_bag_types.order_id")
                    ->orderBy("order_punch_details.created_at","ASC");                               

            if($fromDate && $uptoDate){             
                $data->whereBetween(DB::raw("order_punch_details.created_at::date"),[$fromDate,$uptoDate]);
            }

            elseif($fromDate){
                $data->where(DB::raw("order_punch_details.created_at::date"),">=",$fromDate);
            }
            elseif($uptoDate){
                $data->where(DB::raw("order_punch_details.created_at::date"),"<=",$uptoDate);
            } 

            if($orderNo){
                $data->where("order_punch_details.order_no",$orderNo);
            }
            $list = DataTables::of($data)
                ->addIndexColumn()                
                ->addColumn('is_delivered', function ($val) {                    
                    return $val->is_delivered ? "YES" : "NO";
                })
                ->addColumn('created_at', function ($val) {                    
                    return $val->created_at ? Carbon::parse($val->created_at)->format("d-m-Y") : "";                    
                })
                ->addColumn('estimate_delivery_date', function ($val) {                    
                    return $val->estimate_delivery_date ? Carbon::parse($val->estimate_delivery_date)->format("d-m-Y") : "";                    
                })
                ->addColumn("roll_no",function($val){
                    return "";
                })
                ->addColumn('delivery_date', function ($val) {                    
                    return $val->delivery_date ? Carbon::parse($val->delivery_date)->format("d-m-Y") : "";                    
                })
                ->make(true);
            return $list;

        }
        return view("Roll/unBookedOrder");
    }

}
