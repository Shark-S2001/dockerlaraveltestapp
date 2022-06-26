<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Cache;
use AfricasTalking\SDK\AfricasTalking;

class CustomerController extends Controller
{
    //Select all cutsomers
    public function select_all_customers(){
        //confirm if cache has data, if yes, pull form cache direct, else, pull from db and store in cache
      if (Cache::has('customers')){
          $customers  = Cache::get('customers');
      } else {
          Cache::rememberForever('customers', function(){
              $sql = "SELECT customer_num, customer_name, phone_number,IFNULL(alt_phone_number,'')AS alt_phone_number, town, route_name,createdBy FROM customer LEFT JOIN(SELECT ID,username as createdBy FROM user)UT
              ON UT.ID=customer.user_id LEFT JOIN (SELECT id,route_name FROM routes)RST ON RST.id=customer.route_id;";
     
              return DB::select(DB::raw($sql));   
          });
          $customers  = Cache::get('customers');
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
    
        //Select all routes
    public function select_all_routes(){
        if (Cache::has('routes')){
            $routes  = Cache::get('routes');
         } else {
            Cache::rememberForever('routes', function(){
                $sql = "SELECT * FROM `routes`";

                return DB::select(DB::raw($sql));  
            });            
            $routes=Cache::get('routes');     
         }

        if(!empty($routes)){
            $response = [
                'status'=>true,
                'message'=>'success',
                'data'=>$routes
            ];
        }else{
            $response = [
                'status'=>false,
                'message'=>'error',
                'long_message'=>'Failed to select routes'
            ];
        }
        header('Content-Type: application/json');

        return json_encode($response);
    }
    
    //Register new customer
    public function add_new_customer(Request $request){
        $customer_name = $request->post('customer_name');
        $phone_number = $request->post('phone_number');
        $alt_phone_number = $request->post('alt_phone_number');
        $town = $request->post('town');       
        $route_id = $request->post('route_id');
        $user_id = $request->post('user_id');
        $org_id = $request->post('org_id');

        try{
             DB::beginTransaction();
             
             DB::table('customer')->insert([
                    'customer_name' =>$customer_name,
                    'phone_number'=>$phone_number,
                    'alt_phone_number'=>$alt_phone_number,
                    'town' => $town,            
                    'route_id'=>$route_id,
                    'user_id'=>$user_id,
                    'org_id'=>$org_id
                ]);
                
            DB::commit();
           
            Cache::forget('customers');
            
            $message = "Dear customer, a new account under your Name: ".$request->post('customer_name')." has been created on Moon Investment Ltd,your account will be used to track and update you on any ongoings regarding your orders. Welcome! We look forward to serving you.";
            //Strip first character 0 from phone number
            $phoneNum = substr($request->post('phone_number'), 1);

            //Send Message By calling the sms Function
            $this->sendMessage($phoneNum,$message);

            $response = [
                'status'=>1,
                'message'=>'success',
                'long_message'=>'Customer added succesfully'
            ];

            return response()->json($response);
        

        } catch (\Exception $e) {
            DB::rollback(); //rollback transaction
            
            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to add new customer, '.$e
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

    //update products prices
    public function update_customers_info(Request $request){
        $customer_num = $request->post('customer_num');
        $customer_name = $request->post('customer_name');
        $phone_number = $request->post('phone_number');
        $alt_phone_number = $request->post('alt_phone_number');
        $town = $request->post('town');       
        $route_id = $request->post('route_id');
        $user_id = $request->post('user_id');
        
        try{
            DB::update("UPDATE customer SET customer_name='$customer_name',phone_number='$phone_number',alt_phone_number='$alt_phone_number',town='$town',route_id='$route_id',user_id='$user_id' WHERE customer_num='$customer_num'");

            //reset cache when data is changed
            Cache::forget('customers');

            $response = [
                'status'=>1,
                'message'=>'success',
                'long_message'=>'Customer updated succesfully'
            ];

            return response()->json($response);            
        
        } catch (\Exception $e) {
            DB::rollback(); //rollback transaction

            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to update customer'.$e
            ];

            return response()->json($response);
        }   
    }
}
