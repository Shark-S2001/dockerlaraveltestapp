<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Carbon\Carbon; 
use AfricasTalking\SDK\AfricasTalking;

class OrdersController extends Controller
{
    //Add to order items
    public function add_new_product(Request $request){
        //Route order details
        $product_code = $request->post('product_code');
        $quantity = $request->post('qty');
        $uomMeasure= $request->post('uom');
        $unit_price = round($request->post('unit_price'),1);       
        $product_vat = round($request->post('product_vat'),1);
        $amount = round($request->post('amount'),1);
        $username = $request->post('username');

        try{
             DB::table('order_items')->insert([
                    'product_code' =>$product_code,
                    'qty'=>$quantity,
                    'uom'=>$uomMeasure,
                    'unit_price' => $unit_price,            
                    'product_vat'=>$product_vat,
                    'amount'=>$amount,
                    'username'=>$username
                ]);
                           
               
                $response = [
                    'status'=>1,
                    'message'=>'success',
                    'long_message'=>'Item added to cart succesfully'
                ];
    
                return response()->json($response);
        
        } catch (\Exception $e) {
           
            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to add item to cart, '.$e
            ];

            return response()->json($response);
        }   
    }
    
    public function select_cart_products(Request $request){
        $username = $request->post('username');
        
        $sql = "SELECT order_items.product_code,product_name,CONCAT(qty,' ',IFNULL(uom,''))as qtyDisp,qty,order_items.unit_price,amount,(SELECT sum(amount) FROM order_items  WHERE username='$username')as orderTotals FROM order_items INNER JOIN product ON product.product_code=order_items.product_code WHERE username='$username'";


        $cartItems=DB::select(DB::raw($sql));     

        if(!empty($cartItems)){
            $response = [
                'status'=>true,
                'message'=>'success',
                'data'=>$cartItems
            ];
        }else{
            $response = [
                'status'=>false,
                'message'=>'error',
                'long_message'=>'Failed to select products'
                ];
            }
            header('Content-Type: application/json');

            return json_encode($response);
    }
        
   //Add to order masters and order products
    public function create_new_order(Request $request){
        //Route order masters
        $customer_num = $request->post('customer_num');
        $order_status = $request->post('order_status');
        $org_id = $request->post('org_id'); 
        $user_id = $request->post('user_id'); 
        $username = $request->post('username');
        
        try{
            DB::beginTransaction();
            //Select order
            $exorder = DB::table("route_master")
               ->Where('customer_num', $customer_num)
               ->whereDate('create_time',date('Y-m-d'))->first();
        

            $new_order_no =0;
            $existingOrder =0;

            if(!empty($exorder)){
                $order_no  = $exorder->order_num;

                //Cast value to int and add 1
                $new_order_no = (int)$order_no;
                $existingOrder =1;
                
            }else{
                 //Select order
                $var = DB::table("route_master")
                ->orderBy('order_num', 'desc')->first();
  
                //Pull the value from array
                if(!empty($var)){
                    $order_no  = $var->order_num;
                }else{
                    $order_no=0;
                }   
                
                $existingOrder =0;
                $orderTotal =0;
                //Cast value to int and add 1
                $new_order_no = (int)$order_no + 1;
            }  
                        
            if(!empty($new_order_no)){               
                if($existingOrder ==0){
                    //New Order, new customer
                    DB::table('route_master')->insert([
                        'order_num' =>$new_order_no,
                        'customer_num'=>$customer_num,
                        'order_status'=>$order_status,
                        'org_id'=>$org_id,
                        'user_id'=>$user_id
                    ]);

                    $copyItems = DB::table("order_items")->get()->where("username",$username);                
                    
                    foreach($copyItems as $record){
                        DB::table('route_order_details')->insert([
                            'order_no'=>$new_order_no,
                            'product_code' =>$record->product_code,
                            'qty_ordered'=>$record->qty,
                            'uom'=>$record->uom,
                            'unit_price' =>round($record->unit_price,1),            
                            'product_vat'=>round($record->product_vat,1),
                            'amount'=>round($record->amount,)
                        ]);
                        $orderTotal  += round($record->amount,1); 
                    }
                    //Fetch Customer Details
                    $tel = DB::table("customer")->Where('customer_num', $customer_num)->first();

                    if(!empty($tel)){
                        $phoneNumString  = $tel->phone_number;
                        $custName = $tel -> customer_name;
                        
                        //Strip first character 0 from phone number
                        $phoneNum = substr($phoneNumString, 1);

                        $message = "Dear ".$custName.",your order to Moon Investment Ltd has been placed Successfully, Order Total is Ksh: ". number_format($orderTotal, 0, '.', ',').". Thankyou for shopping with us.";
                        //Send sms message by calling function
                        $this->sendMessage($phoneNum,$message);
                    }
                }else{                   
                    //Existing Order
                    $copyItems = DB::table("order_items")->get()->where("username",$username);               
                  
                    foreach($copyItems as $record){
                        //Check if item exist
                        if($this->ItemExist($new_order_no,$record->product_code)){
                            //Select the existing product details
                            $extQty = DB::table("route_order_details")->get()->where("order_no",$new_order_no)
                                                                ->where("product_code",$record->product_code)->first();

                            $newUpdateQty = $extQty->qty_ordered + $record->qty;
                            $newUpdateVat = round($extQty->product_vat + $record->product_vat,1);
                            $newUpdateAmt = round($extQty->amount + $record->amount,1);
                            //Update item qty, vat and amount
                            DB::update("UPDATE route_order_details SET qty_ordered='$newUpdateQty',product_vat='$newUpdateVat',amount='$newUpdateAmt' 
                                        WHERE  order_no ='$new_order_no' AND product_code='$record->product_code'");
                        }else{
                            //Insert the new Item
                             DB::table('route_order_details')->insert([
                                'order_no'=>$new_order_no,
                                'product_code' =>$record->product_code,
                                'qty_ordered'=>$record->qty,
                                'uom'=>$record->uom,
                                'unit_price' =>round($record->unit_price,1),            
                                'product_vat'=>round($record->product_vat,1),
                                'amount'=>round($record->amount,1)
                             ]);
                        }                     
                    }

                     //Fetch Customer Details
                     $tel = DB::table("customer")->Where('customer_num', $customer_num)->first();

                     //Calculate new Order total
                     $sql ="SELECT SUM(amount)AS orderTotal FROM route_order_details WHERE order_no=$new_order_no";

                     $updatedOrderTotal=DB::select(DB::raw($sql));     

                     if(!empty($tel)){
                         $phoneNumString  = $tel->phone_number;
                         $custName = $tel -> customer_name;
                         $orderTotal = round($updatedOrderTotal[0]->orderTotal,1);

                         //Strip first character 0 from phone number
                         $phoneNum = substr($phoneNumString, 1);
 
                         $message = "Dear ".$custName.",your order to Moon Investment Ltd has been updated Successfully, New Order Total is Ksh: ".  number_format($orderTotal, 0, '.', ',').". Thankyou for shopping with us.";
                         //Send sms message by calling function
                         $this->sendMessage($phoneNum,$message);
                     }
                }                  
                              
                DB::commit();
                
                DB::delete("delete from order_items where username='$username'");
                $existingOrder =0;

                $response = [
                    'status'=>1,
                    'message'=>'success',
                    'long_message'=>'Orders saved succesfully'
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
    //Function to check if product exist in previous order or not
    public function ItemExist($order_no,$prod_code){
        //Existing order
        $existingItems = DB::table("route_order_details")->get()->where("order_no",$order_no)
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
    
       //update items in cart
    public function update_item_in_cart(Request $request){
        //Route order masters
        $product_code = $request->post('product_code');
        $qty_ordered = $request->post('qty');
        $unit_price = round($request->post('unit_price'),1); 
        $product_vat = $request->post('product_vat'); 
        $amount = round($request->post('amount'),1); 
        $username = $request->post("username");

        try{

            DB::update("UPDATE order_items SET qty='$qty_ordered',unit_price='$unit_price',product_vat='$product_vat',amount='$amount' WHERE product_code='$product_code' AND username='$username'");

            $response = [
                'status'=>1,
                'message'=>'success',
                'long_message'=>'Order update succesfully'
            ];

            return response()->json($response);
            
        
        } catch (\Exception $e) {
            
            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to update order, '.$e
            ];

            return response()->json($response);
      }   
    }

    //Send Message  Using Africa's Talking Api
    public function sendMessage($phoneNumber,$custMessage){
        $fetchApiKeys = DB::table("system_settings")->get()->where("id","1");

        if(!empty($fetchApiKeys)){
            //Yes item exist
            $sms_uname  = $fetchApiKeys[0]->sms_username;
            $sms_apikey  = $fetchApiKeys[0]->sms_apiKey;

            $username = $sms_uname; 
            $apiKey   = $sms_apikey; 
            $AT       = new AfricasTalking($username, $apiKey);
    
            // Get one of the services
            $sms      = $AT->sms();
    
            // Use the service
            $result   = $sms->send([
                'to'      => '+254'.$phoneNumber,
                'message' => $custMessage
            ]);
        }
    }

    //Send message from Backoffice
       //Send Message  Using Africa's Talking Api
    public function sendReminderMessage(Request $request){
        $customer_tel = $request->post('phone_number');
        $reminderMessage = $request->post('cust_message');

        try{
            $fetchApiKeys = DB::table("system_settings")->get()->where("id","1");

            if(!empty($fetchApiKeys)){
                //Yes item exist
                $sms_uname  = $fetchApiKeys[0]->sms_username;
                $sms_apikey  = $fetchApiKeys[0]->sms_apiKey;

                $username = $sms_uname; 
                $apiKey   = $sms_apikey; 
                $AT       = new AfricasTalking($username, $apiKey);
        
                // Get one of the services
                $sms      = $AT->sms();
                
                $phoneNum = substr($customer_tel, 1);
                // Use the service
                $result   = $sms->send([
                    'to'      => '+254'.$phoneNum,
                    'message' => $reminderMessage
                ]);
            }
            $response = [
                'status'=>1,
                'message'=>'success',
                'long_message'=>'Message Sent Succesfully'
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            DB::rollback(); //rollback transaction

            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to send message'.$e
            ];

            return response()->json($response);
        }   
    }


    //Select dashboard sales summary
   public function select_dashboard_summary(Request $request){
        $userID = $request->post('user_id');
    
        $sql = "SELECT COUNT(DISTINCT order_no)AS NoofOrders,IFNULL(ROUND(SUM(product_vat),0),0) AS vatTotals,IFNULL(ROUND(SUM(amount),0),0) AS orderTotals,
        IFNULL(ROUND((SELECT SUM(amount) FROM route_order_details LEFT JOIN(SELECT order_num,order_status,`user_id` FROM route_master
        WHERE DATE(route_master.create_time)=DATE(NOW()))MT ON MT.order_num=route_order_details.order_no WHERE MT.order_status=1 and MT.user_id='$userID' LIMIT 1),0),0)as NoofPaidOrders,
        (SELECT COUNT(DISTINCT customer_num) FROM customer) AS NoofCustomers FROM route_order_details LEFT JOIN (SELECT order_num,`user_id` FROM route_master)MT ON route_order_details.order_no=MT.order_num WHERE MT.user_id='$userID' AND DATE(route_order_details.create_time)=DATE(NOW())";


        $dashboardSummary=DB::select(DB::raw($sql));     

        if(!empty($dashboardSummary)){
            $response = [
                'status'=>true,
                'message'=>'success',
                'data'=>$dashboardSummary
            ];
        }else{
            $response = [ 
                'status'=>false,
                'message'=>'error',
                'long_message'=>'Failed to select sales summary'
            ];
        }
        header('Content-Type: application/json');

    
        return json_encode($response);
    }
    
    //select recent sales summary
   public function select_recent_orders(Request $request){
        $userID = $request->post('user_id');

        $sql = "SELECT order_no,customer_name,ROUND(SUM(amount),0)AS orderTotals,status_name,createdBy FROM route_order_details LEFT JOIN(SELECT order_num,customer_num,order_status,`user_id` FROM route_master WHERE `user_id`='$userID')MT  ON route_order_details.order_no=MT.order_num LEFT JOIN(SELECT customer_num,customer_name FROM customer)CT ON MT.customer_num=CT.customer_num
        LEFT JOIN (SELECT status_code,status_name FROM route_order_status)RST ON RST.status_code=MT.order_status LEFT JOIN (SELECT ID,username AS createdBy FROM user WHERE id='$userID')UT ON UT.ID=MT.user_id WHERE MT.user_id='$userID'  GROUP BY order_no";

        \DB::statement("SET SQL_MODE=''");
        $recentSummary=DB::select(DB::raw($sql)); 
        \DB::statement("SET SQL_MODE=only_full_group_by");    

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
   public function select_orders_items(Request $request){
        $order_num = $request->post('order_num');
        
        $sql = "SELECT customer_name,ODT.product_code,product_name,CONCAT(qty_ordered,' ',IFNULL(uom,''))as qtyDisp, qty_ordered,ODT.unit_price,amount,route_name,createdBy,(SELECT sum(amount) FROM route_order_details  WHERE order_no='$order_num')as orderTotals  FROM product INNER JOIN
        (SELECT order_no,product_code,uom,qty_ordered,unit_price,product_vat,amount FROM route_order_details) ODT ON ODT.product_code=product.product_code INNER JOIN (SELECT DATE(create_time) AS createdOn,order_num,customer_num,user_id,order_status,org_id FROM route_master) MT ON ODT.order_no = MT.order_num 
        LEFT JOIN (SELECT customer_num,route_id,customer_name,town,phone_number FROM customer)CT ON MT.customer_num=CT.customer_num LEFT JOIN (SELECT ID,username AS createdBy FROM user)UT ON MT.user_id=UT.ID LEFT JOIN (SELECT ID,route_name FROM routes)RT ON CT.route_id= RT.ID
        LEFT JOIN (SELECT status_code,status_name AS orderStatus FROM route_order_status)OST ON OST.status_code=MT.order_status  WHERE order_num='$order_num'";


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

    public function delete_product(Request $request){
        try{
            $product_code = $request->post("product_code");
            $username = $request->post("username");
        
            DB::delete("delete from order_items where product_code = '$product_code' AND username='$username'");
            
            $response = [
                'status'=>true,
                'message'=>'success',
                'long_message'=>'Item Deleted Successfully'
                ];
                
            header('Content-Type: application/json');
    
            return json_encode($response);
        
        } catch (\Exception $e) {
             $response = [
                'status'=>false,
                'message'=>'error',
                'long_message'=>'Failed to delete item'
                ];
                
            header('Content-Type: application/json');
    
            return json_encode($response);
        }
    }
    
    //Delete whole orders
    public function delete_order(Request $request){
        try{
            $order_no = $request->post("order_no");
            
            DB::beginTransaction();
            
            //Delete from order details
            DB::delete("delete from route_order_details where order_no = '$order_no'");
            
            //Delete from route master
            DB::delete("delete from route_master where order_num = '$order_no'");
            
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
    
    //restore order products or item
    public function restore_product(Request $request){
        $order_num = $request->post('order_num');
        $product_code = $request->post('product_code');
        $quantity = $request->post('qty');
        $unit_price = $request->post('unit_price');      
        $reason = $request->post('reason');
        $user_id = $request->post('user_id');

        try{
             
             DB::beginTransaction();
            
             DB::table('returns')->insert([
                    'order_num'=>$order_num,
                    'product_code' =>$product_code,
                    'qty'=>$quantity,
                    'unit_price' => $unit_price,            
                    'reason'=>$reason,
                    'user_id'=>$user_id
                ]);
                           
            //Delte from order details
            DB::delete("delete from route_order_details where product_code = '$product_code' and order_no = '$order_num'");
            
            DB::commit();
              
                $response = [
                    'status'=>1,
                    'message'=>'success',
                    'long_message'=>'Return completed succesfully'
                ];
    
                return response()->json($response);
        
        } catch (\Exception $e) {
            DB::rollback();//roll back transcation
             
            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to return product, '.$e
            ];

            return response()->json($response);
        }   
    }
    
    public function restore_full_order(Request $request){
        $order_num = $request->post('order_num'); 
        $user_id = $request->post('user_id'); 
        
        try{
            DB::beginTransaction();
            
            $copyItems = DB::table("route_order_details")->get()->where("order_no",$order_num);

            foreach($copyItems as $record){
                 DB::table('returns')->insert([
                    'order_num'=>$order_num,
                    'product_code' =>$record->product_code,
                    'qty'=>$record->qty_ordered,
                    'unit_price' =>$record->unit_price,            
                    'reason'=>"Customer rejected full order",
                    'user_id'=>$user_id
                ]);
            }
           
    
            //Delete from order details
            DB::delete("delete from route_order_details where order_no = '$order_num'");
            
            //Delete from route master
            DB::delete("delete from route_master where order_num = '$order_num'");
            
            DB::commit();
            
            $response = [
                'status'=>1,
                'message'=>'success',
                'long_message'=>'Orders restored succesfully'
            ];
    
            return response()->json($response);
        
        
        } catch (\Exception $e) {
            DB::rollback(); //rollback transaction
    
            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to restore order '.$e
            ];
    
            return response()->json(["response"=>$response]);
        }   
    }

}
