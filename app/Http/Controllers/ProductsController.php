<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Cache;

class ProductsController extends Controller
{
       public function select_all_products(){
        //confirm if cache has data, if yes, pull form cache direct, else, pull from db and store in cache
        if (Cache::has('products')){
            $products  = Cache::get('products');
         } else {
            Cache::rememberForever('products', function(){
                $sql = "SELECT product.product_code,product_name,stock_in_shop,stock_in_store,unit_price,sell_on_retail,short_name,IFNULL(min_qty_measure,0)AS min_qty_measure, IFNULL(max_qty_measure,0)AS max_qty_measure,IFNULL(wholesale_qty_measure,0)AS wholesale_qty_measure,
                 IFNULL(wholesale_markup_price,0)AS wholesale_markup_price,IFNULL(markup_price,0)AS markup_price, IFNULL(min_uom_measure,'')AS min_uom_measure, IFNULL(max_uom_measure,'')AS max_uom_measure FROM product LEFT JOIN (SELECT id,short_name FROM uom)UOMT ON product.unit_id=UOMT.id
                 LEFT JOIN (SELECT  product_code, min_qty_measure, max_qty_measure, markup_price, min_uom_measure, max_uom_measure,wholesale_qty_measure,wholesale_markup_price FROM retail_package_setting)RPT ON product.product_code=RPT.product_code  WHERE DLT_BY IS NULL AND DLT_ON IS NULL";

                return DB::select(DB::raw($sql));   
            });
            
            $products  = Cache::get('products');
        }
 
        if(!empty($products)){
            $response = [
                'status'=>true,
                'message'=>'success',
                'data'=>$products
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
    
    //update products prices
    public function update_products_price(Request $request){
        $product_code = $request->post('product_code');
        $unit_price = $request->post('unit_price');
        $markupprice = $request->post('markup_price');
        $wholesaleMarkupprice = $request->post('wholesale_markup_price');
        $username = $request->post('username');
        
        try{

            DB::update("UPDATE product SET unit_price='$unit_price',update_by='$username' WHERE product_code='$product_code'");

            if($markupprice !=0){
                DB::update("UPDATE retail_package_setting SET markup_price='$markupprice',wholesale_markup_price='$wholesaleMarkupprice' WHERE product_code='$product_code'");
            }
            //reset cache when data is changed
            Cache::forget('products');

            $response = [
                'status'=>1,
                'message'=>'success',
                'long_message'=>'Price updated succesfully'
            ];

            return response()->json($response);
            
        
        } catch (\Exception $e) {
            DB::rollback(); //rollback transaction

            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to update price'.$e
            ];

            return response()->json($response);
        }   
    }

    //update products prices
    public function update_products_packages(Request $request){
        $product_code = $request->post('product_code');
        $unit_id = $request->post('unit_id');
        $username = $request->post('username');
        
        try{

            DB::update("UPDATE product SET unit_id='$unit_id',update_by='$username' WHERE product_code='$product_code'");

            //reset cache when data is changed
            Cache::forget('products');

            $response = [
                'status'=>1,
                'message'=>'success',
                'long_message'=>'Packages updated succesfully'
            ];

            return response()->json($response);
            
        
        } catch (\Exception $e) {
            DB::rollback(); //rollback transaction

            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to update product packages'.$e
            ];

            return response()->json($response);
        }   
    }

    //update stock
    public function update_products_stocks(Request $request){
        $product_code = $request->post('product_code');
        $product_name = $request->post('product_name');
        $unit_price = $request->post('unit_price');
        $stock_in_shop = $request->post('stock_in_shop');
        $stock_in_store = $request->post('stock_in_store');
        
        try{
            DB::update("UPDATE IGNORE product SET stock_in_shop='$stock_in_shop',stock_in_store='$stock_in_store' WHERE product_code='$product_code'");

            //reset cache when data is changed
            Cache::forget('products');

            $response = [
                'status'=>1,
                'message'=>'success',
                'long_message'=>'Stocks updated succesfully'
            ];

            return response()->json($response);            
        
        } catch (\Exception $e) {
            DB::rollback(); //rollback transaction

            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to update stocks'.$e
            ];

            return response()->json($response);
        }   
    }

    public function create_new_product(Request $request){
        $product_code = $request->post('product_code');
        $product_name = $request->post('product_name');
        $stock_in_shop = $request->post('stock_in_shop');
        $unit_price = $request->post('unit_price');
        $stock_in_store = $request->post('stock_in_store');
        $unit_id = $request->post('unit_id');
        $sell_on_retail= $request->post('sell_on_retail');

        try{
            if($this->productExist($product_code)){
                $response = [
                    'status'=>3,
                    'message'=>'Exists',
                    'long_message'=>'Oops!!!Product Already Exists'
                ];
            }else{
                DB::table('product')->insert([
                    'product_code' => $product_code,
                    'product_name'=>$product_name,
                    'unit_price'=>$unit_price,
                    'stock_in_shop'=>$stock_in_shop,
                    'stock_in_store'=>$stock_in_store,
                    'unit_id'=>$unit_id,
                    'sell_on_retail'=>$sell_on_retail
                ]);

                DB::commit();

                $response = [
                    'status'=>1,
                    'message'=>'New Product',
                    'long_message'=>'Products has been Uploaded online succesfully'
                ];
            }   

            return response()->json($response);           

        } catch (\Exception $e) {
            DB::rollback(); //rollback transaction
               
            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to create new product'.$e
            ];

            return response()->json($response);
        }   
    }

    //Function to check if product exist in previous order or not
    public function productExist($prod_code){
        //Existing order
        $existingProduct = DB::table("product")->get()->where("product_code",$prod_code);

        //Pull the value from array
        foreach($existingProduct as $key => $record){
            if(!empty($existingProduct)){
                //Yes item exist
                $itemCode  = $existingProduct[$key]->product_code;
                return true;
            }else{
                //No item does not exist
                return false;
            }
        }        
    }

    //create new uom or update uom
    public function create_uom_measure(Request $request){
        $id = $request->post('id');
        $short_name = $request->post('short_name');
        $full_name = $request->post('full_name');
        $uom_type = $request->post('uom_type');

        try{
            if($this->uomExist($id)){
                $response = [
                    'status'=>0,
                    'message'=>'Exists',
                    'long_message'=>'Oops!!!U.O.M Already Exists'
                ];
            }else{
                DB::table('uom')->insert([
                    'id' => $id,
                    'short_name'=>$short_name,
                    'full_name'=>$full_name,
                    'uom_type'=>$uom_type,
                    "uom_userid"=>4
                ]);

                DB::commit();

                $response = [
                    'status'=>1,
                    'message'=>'New U.O.M',
                    'long_message'=>'Uom has been Uploaded online succesfully'
                ];
            }   

            return response()->json($response);           

        } catch (\Exception $e) {
            DB::rollback(); //rollback transaction
               
            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to create new Uom'.$e
            ];

            return response()->json($response);
        } 
    }

    //Function to check if UoM exist or not
    public function uomExist($uomId){
        //Existing order
        $existingUom = DB::table("uom")->get()->where("id",$uomId);

        //Pull the value from array
        foreach($existingUom as $key => $record){
            if(!empty($existingUom)){
                //Yes item exist
                $uom_id  = $existingUom[$key]->short_name;
                return true;
            }else{
                //No item does not exist
                return false;
            }
        }        
    }
        
    //update uom online
    public function update_uom_online(Request $request){
        $id = $request->post('id');
        $short_name = $request->post('short_name');
        $full_name = $request->post('full_name');
        $uom_type = $request->post('uom_type');
        
        try{
            DB::update("UPDATE IGNORE uom SET short_name='$short_name',full_name='$full_name',uom_type='$uom_type' WHERE id='$id'");

            //reset cache when data is changed
            Cache::forget('products');

            $response = [
                'status'=>1,
                'message'=>'success',
                'long_message'=>'U.O.M updated succesfully'
            ];

            return response()->json($response);            
        
        } catch (\Exception $e) {
            DB::rollback(); //rollback transaction

            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to update U.O.M'.$e
            ];

            return response()->json($response);
        }   
    }

    //create new uom or update uom
    public function create_retail_package(Request $request){
        $product_code = $request->post('product_code');
        $max_qty_measure = $request->post('max_qty_measure');
        $markup_price = $request->post('markup_price');
        $wholesale_qty_measure = $request->post('wholesale_qty_measure');
        $wholesale_markup_price = $request->post('wholesale_markup_price');
        $min_uom_measure = $request->post('min_uom_measure');
        $max_uom_measure = $request->post('max_uom_measure');

        try{
            if($this->markUpExist($product_code)){
                $response = [
                    'status'=>2,
                    'message'=>'Exists',
                    'long_message'=>'Oops!!!Package(s) Already Exists'
                ];
            }else{
                DB::table('retail_package_setting')->insert([
                    'product_code' => $product_code,
                    'max_qty_measure'=>$max_qty_measure,
                    'markup_price'=>$markup_price,
                    'wholesale_qty_measure'=>$wholesale_qty_measure,
                    'wholesale_markup_price'=>$wholesale_markup_price,
                    "min_uom_measure"=>$min_uom_measure,
                    "max_uom_measure"=>$max_uom_measure
                ]);

                //Set sell_on_retail =1;
                DB::update("UPDATE IGNORE product SET sell_on_retail=1 WHERE product_code='$product_code'");

                DB::commit();

                $response = [
                    'status'=>1,
                    'message'=>'New Retail Package',
                    'long_message'=>'Retail Package(s) has been Uploaded online succesfully'
                ];
            }   

            return response()->json($response);           

        } catch (\Exception $e) {
            DB::rollback(); //rollback transaction
               
            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to create new Retail Package'.$e
            ];

            return response()->json($response);
        } 
    }

    //Function to check if UoM exist or not
    public function markUpExist($productCode){
        //Existing order
        $existingPackage = DB::table("retail_package_setting")->get()->where("product_code",$productCode);

        //Pull the value from array
        foreach($existingPackage as $key => $record){
            if(!empty($existingPackage)){
                //Yes item exist
                $item_code  = $existingPackage[$key]->product_code;
                return true;
            }else{
                //No item does not exist
                return false;
            }
        }        
    }

       //update uom online
       public function update_retail_package_online(Request $request){
        $product_code = $request->post('product_code');
        $max_qty_measure = $request->post('max_qty_measure');
        $markup_price = $request->post('markup_price');
        $wholesale_qty_measure = $request->post('wholesale_qty_measure');
        $wholesale_markup_price = $request->post('wholesale_markup_price');
        $min_uom_measure = $request->post('min_uom_measure');
        $max_uom_measure = $request->post('max_uom_measure');
        
        try{
            DB::update("UPDATE IGNORE retail_package_setting SET min_qty_measure='$min_qty_measure',max_qty_measure='$max_qty_measure',markup_price='$markup_price', wholesale_qty_measure='$wholesale_qty_measure',wholesale_markup_price='$wholesale_markup_price',min_uom_measure='$min_uom_measure',max_uom_measure='$max_uom_measure' WHERE product_code='$product_code'");

            //Set sell_on_retail =1;
            DB::update("UPDATE IGNORE product SET sell_on_retail=1 WHERE product_code='$product_code'");

            //reset cache when data is changed
            Cache::forget('products');

            $response = [
                'status'=>1,
                'message'=>'success',
                'long_message'=>'Retail Packages Updated succesfully'
            ];

            return response()->json($response);            
        
        } catch (\Exception $e) {
            DB::rollback(); //rollback transaction

            $response = [
                'status'=>0,
                'message'=>'error',
                'long_message'=>'Failed to update Packages'.$e
            ];

            return response()->json($response);
        }   
    }
}
