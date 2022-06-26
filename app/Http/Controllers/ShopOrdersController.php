<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Cache;

class ShopOrdersController extends Controller
{    
    //Create a shop order
    public function create_new_shop_order(Request $request){
        //Route order masters
        $customer_name = $request->post('customer_name');
        $org_id = $request->post('org_id'); 
        $user_id = $request->post('user_id'); 
        $username = $request->post('username');
        
        try{ 
            DB::beginTransaction();
            //Select order      
            $new_order_no =0;
            $order_no = 0;
            //Select order
            $var = DB::table("order_masters")
            ->orderBy('order_num', 'desc')->first();

            //Pull the value from array
            if(!empty($var)){
                $order_no  = $var->order_num;
            }else{
                $order_no=0;
            }                   

            //Cast value to int and add 1
            $new_order_no = (int)$order_no + 1;
                        
            if(!empty($new_order_no)){               
                //New Order, new customer
                DB::table('order_masters')->insert([
                    'order_num' =>$new_order_no,
                    'customer_name'=>$customer_name,
                    'userid_ref'=>$user_id,
                    'orgid_ref'=>$org_id
                ]);

                $copyItems = DB::table("order_items")->get()->where("username",$username);                
                
                foreach($copyItems as $record){
                    DB::table('order_products')->insert([
                        'order_num'=>$new_order_no,
                        'product_code' =>$record->product_code,
                        'quantity'=>$record->qty,
                        'uom'=>$record->uom,
                        'selling_price' =>$record->unit_price,            
                        'product_vat'=>$record->product_vat,
                        'amount'=>$record->amount
                    ]);
                }                  
                                
                DB::commit();
                
                DB::delete("delete from order_items where username='$username'");

                Cache::forget("shopcustomers");

                $response = [
                    'status'=>1,
                    'message'=>'success',
                    'long_message'=>'Order saved succesfully'
                ];                

                return response()->json($response);
            }
        
        } catch (\Exception $e) {
            DB::rollback(); //rollback transaction

            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to save order, '.$e
            ];

            return response()->json(["response"=>$response]);
        }   
    }

    //Edit a shop order
    public function edit_shop_order(Request $request){
        //Route order masters
        $order_no = $request->post('order_num'); 
        $username = $request->post('username'); 
        
        try{  
            DB::beginTransaction();
                        
            if(!empty($order_no)){               

                $copyItems = DB::table("order_items")->get()->where("username",$username);                
                
                foreach($copyItems as $record){
                    //Check if item exist
                    if($this->ItemExist($order_no,$record->product_code)){
                        //Select the existing product details
                        $extQty = DB::table("order_products")->get()->where("order_num",$order_no)
                        ->where("product_code",$record->product_code)->first();

                        $newUpdateQty = $extQty->quantity + $record->qty;
                        $newUpdateVat = $extQty->product_vat + $record->product_vat;
                        $newUpdateAmt = $extQty->amount + $record->amount;
                        //Update item qty, vat and amount
                        DB::update("UPDATE order_products SET quantity='$newUpdateQty',product_vat='$newUpdateVat',amount='$newUpdateAmt' 
                        WHERE  order_num ='$order_no' AND product_code='$record->product_code'");
                    }
                    else{
                        DB::table('order_products')->insert([
                            'order_num'=>$order_no,
                            'product_code' =>$record->product_code,
                            'quantity'=>$record->qty,
                            'uom'=>$record->uom,
                            'selling_price' =>$record->unit_price,            
                            'product_vat'=>$record->product_vat,
                            'amount'=>$record->amount
                        ]);
                    }                   
                }                  
                                
                DB::commit();
                
                Cache::forget("shopcustomers");

                DB::delete("delete from order_items where username='$username'");

                $response = [
                    'status'=>1,
                    'message'=>'success',
                    'long_message'=>'Order Updated succesfully'
                ];                

                return response()->json($response);
            }
        
        } catch (\Exception $e) {
            DB::rollback(); //rollback transaction

            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to update order, '.$e
            ];

            return response()->json(["response"=>$response]);
        }   
    }

    //Function to check if product exist in previous order or not
    public function ItemExist($order_no,$prod_code){
        //Existing order
        $existingItems = DB::table("order_products")->get()->where("order_num",$order_no)
                                                                ->where("product_code",$prod_code);

        //Pull the value from array
        foreach($existingItems as $key => $record){
            if(!empty($existingItems)){
                //Yes item exist
                $itemCode  = $existingItems[$key]->product_code;
                return true;
            }else{
                //No item does not exist
                return false;
            }
        }        
    } 

    //select recent sales summary
    public function select_weekly_data(Request $request){
        $userID = $request->post('user_id');

        $sql = "SELECT concat(DAY(create_time),'-',MONTH(create_time)) as create_date,SUM(amount) AS total_amount FROM route_order_details LEFT JOIN (SELECT order_num,`user_id` FROM route_master WHERE `user_id`='$userID')MT on MT.order_num = route_order_details.order_no 
        WHERE DATE(create_time) BETWEEN date_sub(DATE(now()),INTERVAL 1 WEEK) AND DATE(NOW()) AND MT.user_id='$userID' GROUP BY create_date";

        $weeklySummary=DB::select(DB::raw($sql));         

        if(!empty($weeklySummary)){
            $response = [
                'status'=>true,
                'message'=>'success',
                'data'=>$weeklySummary
            ];
        }else{
            $response = [
                'status'=>false,
                'message'=>'error',
                'long_message'=>'Failed to select weekly sales'
                ];
            }
        header('Content-Type: application/json');            
        
        return json_encode($response);
    }

    //select recent sales summary
    public function select_monthly_data(Request $request){
        $userID = $request->post('user_id');

        $sql = "SELECT concat(DAY(create_time),'-',MONTH(create_time))as create_date,SUM(amount) AS total_amount FROM route_order_details LEFT JOIN (SELECT order_num,`user_id` FROM route_master WHERE `user_id`='$userID')MT on MT.order_num = route_order_details.order_no 
        WHERE DATE(create_time) BETWEEN date_sub(DATE(now()),INTERVAL 1 MONTH) AND DATE(NOW()) AND MT.user_id='$userID' GROUP BY create_date";

        $monthlySummary=DB::select(DB::raw($sql));     

        if(!empty($monthlySummary)){
            $response = [
                'status'=>true,
                'message'=>'success',
                'data'=>$monthlySummary
            ];
        }else{
            $response = [
                'status'=>false,
                'message'=>'error',
                'long_message'=>'Failed to select monthly sales'
                ];
            }
        header('Content-Type: application/json');            

        return json_encode($response);
    }

    public function select_recent_shop_orders(Request $request){
        $userID = $request->post('user_id');

        $sql = "SELECT MT.order_num,customer_name,ROUND(SUM(amount),0)AS orderTotals,createdBy FROM order_products LEFT JOIN(SELECT order_num,customer_name,userid_ref FROM order_masters WHERE userid_ref='$userID'
        AND DATE(order_masters.create_time)=DATE(NOW()))MT ON order_products.order_num=MT.order_num  LEFT JOIN (SELECT id,username AS createdBy FROM user WHERE id='$userID')UT ON UT.id=MT.userid_ref WHERE MT.userid_ref='$userID' AND DATE(order_products.create_time)=DATE(NOW()) GROUP BY MT.order_num ORDER BY MT.order_num DESC";

        $recentSummary=DB::select(DB::raw($sql));     

        if(!empty($recentSummary)){
            $response = [
                'status'=>true,
                'message'=>'success',
                'data'=>$recentSummary
            ];
        }else{
            $response = [
                'status'=>false,
                'message'=>'error',
                'long_message'=>'Failed to select recent sales'
                ];
        }
        header('Content-Type: application/json');
        

        return json_encode($response);
    }

    //select order items
    public function select_shop_order_items(Request $request){
        $order_num = $request->post('order_num');
            
        $sql = "SELECT OPT.product_code,product_name,customer_name, order_masters.order_num, CONCAT(quantity,' ',IFNULL(uom,''))as quantity,
            selling_price, amount,(SELECT ROUND(SUM(amount),2) FROM order_products  WHERE order_num=1)as orderTotals,createdBy FROM order_masters
            LEFT JOIN(SELECT product_code, order_num, quantity, uom, selling_price, product_vat, amount FROM order_products)OPT ON order_masters.order_num = OPT.order_num 
            LEFT JOIN (SELECT id,username AS createdBy FROM user)UT ON order_masters.userid_ref=UT.id INNER JOIN(SELECT product_code,product_name FROM product)PT ON OPT.product_code=PT.product_code WHERE order_masters.order_num='$order_num'";


        $orderItems=DB::select(DB::raw($sql));     

        if(!empty($orderItems)){
            $response = [
                'status'=>true,
                'message'=>'success',
                'data'=>$orderItems
            ];
        }else{
            $response = [
                'status'=>false,
                'message'=>'error',
                'long_message'=>'Failed to select order items'
                ];
            }
            header('Content-Type: application/json');

            return json_encode($response);
    }

    //Delete whole orders
    public function delete_shop_order(Request $request){
        try{
            $order_no = $request->post("order_num");
            
            DB::beginTransaction();
            
            //Delete from order products
            DB::delete("delete from order_products where order_num = '$order_no'");
            
            //Delete from order masters
            DB::delete("delete from order_masters where order_num = '$order_no'");
            
            DB::commit();
            
            $response = [
                'status'=>true,
                'message'=>'success',
                'long_message'=>'Order Deleted Successfully'
                ];
                
            header('Content-Type: application/json');
    
            return json_encode($response);
        
        } catch (\Exception $e) {
            DB::rollback();//roll back transcation
            
                $response = [
                'status'=>false,
                'message'=>'error',
                'long_message'=>'Failed to delete order'.$e
                ];
                
            header('Content-Type: application/json');
    
            return json_encode($response);
        }
    }
    
    //Select all shop customers
    public function select_shop_customers(){
        //confirm if cache has data, if yes, pull form cache direct, else, pull from db and store in cache
        if (Cache::has('shopcustomers')){
            $customers  = Cache::get('shopcustomers');
        } else {
            Cache::rememberForever('shopcustomers', function(){
                $sql = "SELECT order_num,customer_name,(SELECT ROUND(SUM(IFNULL(amount,0)),0) FROM order_products WHERE order_products.order_num=order_masters.order_num)AS order_total FROM order_masters";
        
                return DB::select(DB::raw($sql));   
            });
            $customers  = Cache::get('shopcustomers');
        }

        if(!empty($customers)){
            $response = [
                'status'=>true,
                'message'=>'success',
                'data'=>$customers
            ];
        }else{
            $response = [
                'status'=>false,
                'message'=>'error',
                'long_message'=>'Failed to select customers'
            ];
        }
        header('Content-Type: application/json');

        return json_encode($response);
  }
}
