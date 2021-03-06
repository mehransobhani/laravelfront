<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Classes\DiscountCalculator;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use PhpParser\JsonDecoder;
use stdClass;

class CartController extends Controller
{
    //@route: /api/user-cart <--> @middleware: ApiAuthenticationMiddleware
     public function userCart(Request $request){
        $userId = $request->userId;
        $userCart = DB::select("SELECT products FROM shoppingCarts WHERE user_id = $userId AND active = 1");
        if(count($userCart) === 0){
            echo json_encode(array('status' => 'done', 'message' => 'cart is empty', 'cart' => '{}'));
            exit();
        }
        // expected to be string, object given... this is the error that I have to deal with it it first
        $userCart = $userCart[0];
        $userCart = json_decode($userCart->products);
        $cartProducts = [];
        foreach($userCart as $key => $value){
            $product = DB::select(
                "SELECT P.id, PP.id AS packId, P.prodName_fa, P.prodID, P.url, P.prodStatus, P.prodUnite, PL.stock AS productStock, PL.pack_stock AS packStock, PP.status, PP.price, PP.base_price, PP.label, PP.count, PC.category 
                FROM products P
                INNER JOIN products_location PL ON PL.product_id = P.id INNER JOIN product_pack PP ON PL.pack_id = PP.id INNER JOIN product_category PC ON P.id = PC.product_id 
                WHERE PL.pack_id = $key AND PP.status = 1 AND P.prodStatus = 1 AND PL.stock > 0 AND PL.pack_stock > 0 AND $value->count <= PL.pack_stock 
                LIMIT 1 ");
            if(count($product) !== 0){
                $product = $product[0];
                $productObject = new stdClass();
                $productObject->productId = $product->id;
                $productObject->productPackId = $product->packId;
                $productObject->productName = $product->prodName_fa;
                $productObject->prodID = $product->prodID;
                $productObject->categoryId = $product->category;
                $productObject->productPrice = $product->price;
                $productObject->productUrl = $product->url;
                $productObject->productBasePrice = $product->base_price;
                $productObject->productCount = $value->count;
                $productObject->productUnitCount = $product->count;
                $productObject->productUnitName = $product->prodUnite;
                $productObject->productLabel = $product->label;
                array_push($cartProducts, $productObject);
            }else{
                $product = DB::select(
                    "SELECT P.id, $key AS packId, P.prodName_fa, P.prodID, P.url, P.prodUnite, '' AS label, 0 AS `count`, PC.category 
                    FROM products P 
                    INNER JOIN product_pack PP ON P.id = PP.product_id INNER JOIN product_category PC ON P.id = PC.product_id 
                    WHERE PP.id = $key AND P.prodStatus = 1 
                    LIMIT 1");
                if(count($product) !== 0){
                    $product = $product[0];
                    $productObject = new stdClass();
                    $productObject->productId = $product->id;
                    $productObject->productPackId = $product->packId;
                    $productObject->productName = $product->prodName_fa;
                    $productObject->prodID = $product->prodID;
                    $productObject->categoryId = $product->category;
                    $productObject->productPrice = 0;
                    $productObject->productUrl = $product->url;
                    $productObject->productBasePrice = 0;
                    $productObject->productCount = $value->count;
                    $productObject->productUnitCount = 0;
                    $productObject->productUnitName = $product->prodUnite;
                    $productObject->productLabel = $product->label;
                    array_push($cartProducts, $productObject);
                }
            }
        }
        $cartProducts = DiscountCalculator::calculateProductsDiscount($cartProducts);
        echo json_encode(array('status' => 'done', 'message' => 'cart is received', 'cart' => $cartProducts));
        exit();
    }

    //@route: /api/user-special-cart <--> @middleware: ApiAuthenticationMiddleware
    public function userSpecialCart(Request $request){
        /*
        $userId = $request->userId;
        $user = DB::select("SELECT * FROM users WHERE id = $userId");
        $user = $user[0];
        $userCart = DB::select("SELECT products FROM shoppingCarts WHERE user_id = $userId AND active = 1");
        if(count($userCart) === 0){
            echo json_encode(array('status' => 'done', 'message' => 'cart is empty', 'cart' => '{}'));
            exit();
        }
        $userCart = $userCart[0];
        $userCart = json_decode($userCart->products);
        $cartProducts = [];
        if($user->address === '' || $user->address === NULL){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'user does not have address', 'umessage' => '?????????? ???????? ???????? ????????????'));
            exit();
        }
        $addressPack = json_decode($user->address)->addressPack;
        if($addressPack->province == -1){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'user does not have address', 'umessage' => '?????????? ???????? ???????? ????????????'));
            exit();
        }
        $provinceId = DB::select("SELECT id FROM provinces WHERE name = '$addressPack->province'");
        if(count($provinceId) == 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'province could not be found', 'umessage' => '?????????? ?????????? ???????? ??????'));
            exit();
        }
        $provinceId = $provinceId[0];
        $provinceId = $provinceId->id;
        foreach($userCart as $key => $value){
            $product = DB::select(
                "SELECT P.id, PP.id AS packId, P.prodName_fa, P.prodID, P.url, P.prodStatus, P.prodUnite, P.stock AS productStock, PP.stock AS packStock, PP.status, PP.price, PP.base_price, PP.label, PP.count, PC.category 
                FROM products P
                INNER JOIN product_pack PP ON P.id = PP.product_id INNER JOIN product_category PC ON P.id = PC.product_id 
                WHERE PP.id = $key AND PP.status = 1 AND P.prodStatus = 1 AND PP.stock > 0 AND P.stock > 0 AND (PP.stock * PP.count <= P.stock)");
            if(count($product) !== 0){
                $product = $product[0];
                $productStatus = -1;
                if($product->prodStatus == 1 && $product->status === 1 && $product->packStock !==0 && $product->productStock !== 0 && ($product->count * $product->packStock <= $product->productStock) ){
                    $productStatus = 1;
                }
                $productObject = new stdClass();
                $productObject->productId = $product->id;
                $productObject->productPackId = $product->packId;
                $productObject->productName = $product->prodName_fa;
                $productObject->prodID = $product->prodID;
                $productObject->categoryId = $product->category;
                $productObject->productPrice = $product->price;
                $productObject->productUrl = $product->url;
                $productObject->productBasePrice = $product->base_price;
                $productObject->productCount = $value->count;
                $productObject->productUnitCount = $product->count;
                $productObject->productUnitName = $product->prodUnite;
                $productObject->productLabel = $product->label;
                array_push($cartProducts, $productObject);
            }
        }
        
        $cartProducts = DiscountCalculator::calculateSpecialProductsDiscount($cartProducts, $userId, $provinceId);
        echo json_encode(array('status' => 'done', 'message' => 'cart is received', 'cart' => $cartProducts));
        exit();
        */
    }

    //@route: /api/user-cart-raw <--> @middleware: ApiAuthenticationMiddleware
    public function userCartRaw(Request $request){
        /*
        $userId = $request->userId;
        $userCart = DB::select("SELECT products FROM shoppingCarts WHERE user_id = $userId AND active = 1");
        if(count($userCart) == 0){
            echo json_encode(array('status' => 'done', 'found' => false, 'message' => 'user does not have any active shipping cart yet'));
            exit();
        }
        $userCart = $userCart[0];
        if($userCart == NULL || $userCart == ''){
            echo json_encode(array('status' => 'done', 'found' => 'false', 'message' => 'user cart is empty'));
            exit();
        }
        $userCart = json_decode($userCart->products);
        $responseArray = array();
        foreach($userCart as $key => $value){
            $singleCartOrderObject = new stdClass();
            $singleCartOrderObject->id = $key;
            $singleCartOrderObject->count = $value->count;
            array_push($responseArray, $singleCartOrderObject);
        }
        echo json_encode(array('status' => 'done', 'found' => true, 'cart' => $responseArray, 'message' => 'user cart successfully found'));
        */
    }

    /*### without api route ###*/
    public function getUserShoppingCart(Request $request){
        /*
        if(Auth::check()){
            $user = Auth::user();
            if($user == null){
                echo json_encode(array('status' => 'failed', 'message' => 'user is not authenticated'));
                exit();
            }
            $shoppingCart = DB::select(
                "SELECT products, timestamp 
                FROM shoppingCarts 
                WHERE active = 1 AND user_id = $user->id
                ORDER BY timestamp DESC
                LIMIT 1"
            );
            if(count($shoppingCart) == 0){
                echo json_encode(array('status' => 'done', 'found' => false, 'message' => 'shopping cart is empty'));
                exit();
            }
            $cartItems = array();
            foreach($shoppingCart[0]->products as $key => $value){
                $item = new stdClass();
                $item->productId = $key;
                $item->count = $value->count;
                $product = DB::select(
                    "SELECT P.id, PP.id AS packId, P.prodName_fa, P.prodStatus, P.prodID, P.prodPicture, P.stock AS prodStockPC.category, PP.label, PP.count, PP.base_price, PP.price, PP.stock, PP.status
                    FROM products P INNER JOIN products_pack PP ON P.id = PP.product_id INNER JOIN product_category PC ON P.id = PC.product_id 
                    WHERE PP.id = $key 
                    LIMIT 1"
                );
                $product = $product[0];
                if(count($product) !== 0){
                    $item->productId = $product->id;
                    $item->productPackId = $product->packId;
                    $item->poductName = $product->prodName_fa;
                    $item->packCount = $product->count;
                    $item->prodID = $product->prodID;
                    $item->prodPicture = $product->prodPicture;
                    $item->status = 0;
                    if(
                        $product->stock > 0 &&
                        $product->prodStock > 0 &&
                        $product->status == 1 &&
                        $product->prodStatus == 1 &&
                        (($product->count * $product->stock) <= $product->prodStock)
                    ){
                        $item->status = 1;
                    }
                    if($item->status === 1){
                        $item->basePrice = $product->base_price;
                        $item->price = $product->price;
                    }else{
                        $item->basePrice = 0;
                        $item->price = 0;
                    }
                }
                // now I have to check for the discounts.
                $item = DiscountCalculator::calculateProductDiscount($item);
                array_push($cartItems, $item);
            }
        }else{
            echo json_encode(array('status' => 'failed', 'message' => 'user is not authenticated'));
        }
        */
    }

    public function addProductToCart(Request $request){
        /*
        $user = DB::select("SELECT * FROM users WHERE id = $request->userId LIMIT 1");
        $user = $user[0];
        if(!isset($request->productPackId) || !isset($request->productCount)){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'reason' => 'missingBody', 'message' => 'not enough parmeter'));
            exit();
        }
        if($request->productCount <= 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'reason' => 'wrongBody', 'message' => 'wrong product count input number'));
            exit();
        }
        $productPackId = $request->productPackId;
        $productCount = $request->productCount;
        
        $product = DB::select(
            "SELECT P.prodStatus, P.stock AS productStock, PP.count, PP.stock, PP.status
            FROM products P INNER JOIN products_pack PP ON P.id = PP.product_id 
            WHERE PP.id = $productPackId 
            LIMIT 1"
        );
        if(count($product) == 0){
            echo json_encode(array('status' => 'failed', 'message' => 'product does not exist'));
            exit();
        }
        $product = $product[0];
        if($productCount > $product->stock){
            echo json_encode(array('status' => 'failed', 'message' => 'this number of product does not exist'));
            exit();
        }
        if(
            $product->status != 1 || 
            $product->prodStatus != 1 ||
            $product->stock <= 0 ||
            $product->productStock <= 0 ||
            ($product->count * $product->stock > $product->productStock)
        ){
            echo json_encode(array('status' => 'failed', 'message' => 'product is out of order'));
            exit();
        }
        $currentUserCart = DB::select(
            "SELECT products 
            FROM shoppingCarts
            WHERE user_id = $user->id AND status = 1
            ORDER BY timestamp DESC
            LIMIT 1"
        );
        $allowAddToCart = false;
        $action = 'update';
        if(count($currentUserCart) == 0){
            $allowAddToCart = true;
            $action = 'insert';
        }else{
            $currentUserCart = $currentUserCart[0];
            if($currentUserCart->products == ''){
                $allowAddToCart = true;
                $action = 'update';
            }else{
                $productsArray = json_decode($currentUserCart->products);
                $exists = false;
                foreach($productsArray as $key => $value){
                    if($key == $productPackId){
                        $exists = true;
                        break;
                    }
                }
                $allowAddToCart = !$exists;
                $action = 'update';
            }
        }
        if(!$allowAddToCart){
            echo json_encode(array('status' => 'failed', 'message' => 'product exists in users shopping cart'));
            exit();
        }
        if($allowAddToCart && $action === 'insert'){
            $time = time();
            $result = DB::insert("INSERT INTO shoppingCarts (user_id, products, timestamp, status, active) VALUES ($user->id, hi, $time, 0, 1)");
            if(!$result){
                echo json_encode(array('status' => 'failed', 'message' => 'an error occured while adding the productd to users cart'));
                exit();
            }else{
                echo json_encode(array('status' => 'done', 'message' => 'product successfully added to users cart'));
            }
        }else if($allowAddToCart && $action === 'update'){
            $time = time();
            if($currentUserCart->products === ''){
                // I should update it and set a totally new value to it.
            }else{
                // I should add the product and its count to current cart whcih contains some products too.
            }
            // DB::update("UPDATE shoppingCarts set votes = 100 where name = ?", ['John']);
        }
        */
    }

    //@route: /api/user-increase-cart-by-one <--> @middleware: ApiAuthenticationMiddleware
    public function increaseCartByOne(Request $request){
        $userId = $request->userId;

        $validator = Validator::make($request->all(), [
            'productPackId' => 'required|numeric',
        ]);
        if($validator->fails()){
            echo json_encode(array('status' => 'failed', 'source' => 'v', 'message' => 'argument validation failed', 'umessage' => '???????????? ?????????? ???????? ????????'));
            exit();
        }

        if(!isset($request->productPackId)){
            echo json_encode(array('status' => 'failed', 'source' => 'c' ,'message' => 'not enough parameter', 'umessage' => '?????????? ???????? ????????'));
            exit();
        }
        $productPackId = $request->productPackId;
        
        $productLocation = DB::select(
            "SELECT * FROM products_location WHERE pack_id = $productPackId AND stock > 0 AND pack_stock > 0 AND pack_stock <= stock LIMIT 1 "
        );
        if(count($productLocation) === 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product is finished', 'umessage' => '?????????? ?????????????? ?????? ??????'));
            exit();
        }

        $productLocation = $productLocation[0];
        /*$product = DB::select(
            "SELECT P.prodStatus, P.stock AS productStock, PP.count, PP.stock, PP.status
            FROM products P INNER JOIN product_pack PP ON P.id = PP.product_id 
            WHERE PP.id = $productPackId AND PP.status = 1 AND P.prodStatus = 1
            LIMIT 1"
        );
        if(count($product) == 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product did not found', 'umessage' => '?????????? ???????? ??????'));
            exit();
        }
        $product = $product[0];
        if(
            $product->status != 1 || 
            $product->prodStatus != 1 ||
            $product->stock <= 0 ||
            $product->productStock <= 0 ||
            ($product->count * $product->stock > $product->productStock)
        ){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product is out of order', 'umessage' => '?????????? ?????????????? ?????? ??????'));
            exit();
        }
        */

        $userCart = DB::select(
            "SELECT id, products 
            FROM shoppingCarts
            WHERE user_id = $userId AND active = 1
            ORDER BY timestamp DESC
            LIMIT 1"
        );
        if(count($userCart) == 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'cart is empty', 'umessage' => '?????? ???????? ???????? ??????'));
            exit();
        }
        $userCart = $userCart[0];
        $cartProducts = json_decode($userCart->products);
        $newUserCartProducts = new stdClass();
        $infoObject = new stdClass();
        $found = false;
        $newProductCount = 0;
        foreach($cartProducts as $key => $value){
            if($key == $productPackId){
                if($value->count + 1 > $productLocation->pack_stock){
                    echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'it is the heighest number', 'umessage' => '?????????? ???????????????? ?????????? ???? ?????????? ?????????? ??????'));
                    exit();
                }
                $infoObject = new stdClass();
                $infoObject->count = $value->count + 1;
                $newProductCount = $value->count + 1;
                $newUserCartProducts->$key = $infoObject;
                $found = true;
            }else{
                $infoObject = new stdClass();
                $infoObject->count = $value->count;
                $newUserCartProducts->$key = $infoObject;
            }
        }
        if($found == false){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product is not in the cart', 'umessage' => '?????????? ???? ?????? ???????? ?????????? ??????????????'));
            exit();
        }
        $newUserCartProductsString = json_encode($newUserCartProducts);
        $updateQueryResult = DB::update("UPDATE shoppingCarts SET products = '" . $newUserCartProductsString . "' WHERE id = $userCart->id");
        if($updateQueryResult){
            echo json_encode(array('status' => 'done', 'newProductCount' => $newProductCount, 'message' => 'cart successfully updated'));
            exit();
        }else{
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'query error', 'umessage' => '?????? ???? ?????????? ???????? ??????????????'));
            exit();
        }
    }

    //@route: /api/user-decrease-cart-by-one <--> @middleware: ApiAuthenticationMiddleware
    public function decreaseCartByOne(Request $request){
        $userId = $request->userId;

        $validator = Validator::make($request->all(), [
            'productPackId' => 'required|numeric',
        ]);
        if($validator->fails()){
            echo json_encode(array('status' => 'failed', 'source' => 'v', 'message' => 'argument validation failed', 'umessage' => '???????????? ?????????? ???????? ????????'));
            exit();
        }

        if(!isset($request->productPackId)){
            echo json_encode(array('status' => 'failed', 'source' => 'c' ,'message' => 'not enough parameter', 'umessage' => '?????????????? ?????????? ???????? ??????????????'));
            exit();
        }
        $productPackId = $request->productPackId;

        $productLocation = DB::select(
            "SELECT * FROM products_location WHERE pack_id = $productPackId AND stock > 0 AND pack_stock > 0 AND pack_stock <= stock LIMIT 1"
        );

        if(count($productLocation) === 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product is finished', 'umessage' => '?????????? ?????????????? ?????? ??????'));
            exit();
        }

        $productLocation = $productLocation[0];
        /*
        $product = DB::select(
                        "SELECT P.prodStatus, P.stock AS productStock, PP.count, PP.stock, PP.status
                        FROM products P INNER JOIN product_pack PP ON P.id = PP.product_id 
                        WHERE PP.id = $productPackId AND PP.status = 1 AND P.prodStatus = 1
                        LIMIT 1"
        );
        if(count($product) == 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product did not found', 'umessage' => '?????????? ???????? ??????'));
            exit();
        }
        $product = $product[0];
        if(
            $product->status != 1 || 
            $product->prodStatus != 1 ||
            $product->stock <= 0 ||
            $product->productStock <= 0 ||
            ($product->count * $product->stock > $product->productStock)
        ){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product is out of order', 'umessage' => '?????????? ?????????????? ?????? ??????'));
            exit();
        }
        */
        $userCart = DB::select(
            "SELECT id, products 
            FROM shoppingCarts
            WHERE user_id = $userId AND active = 1
            ORDER BY timestamp DESC
            LIMIT 1"
        );
        if(count($userCart) == 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'cart is empty', 'umessage' => '?????? ???????? ???????? ??????'));
            exit();
        }
        $userCart = $userCart[0];
        $cartProducts = json_decode($userCart->products);
        $newUserCartProducts = new stdClass();
        $infoObject = new stdClass();
        $found = false;
        $newProductCount = 0;
        foreach($cartProducts as $key => $value){
            if($key == $productPackId){
                if($value->count - 1 == 0){
                    echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'it is the lowest number', '?????????? ?????????? ???? ???????????? ???????? ?????? '));
                    exit();
                }
                $infoObject = new stdClass();
                $infoObject->count = $value->count - 1;
                $newProductCount = $value->count - 1;
                $newUserCartProducts->$key = $infoObject;
                $found = true;
            }else{
                $infoObject = new stdClass();
                $infoObject->count = $value->count;
                $newUserCartProducts->$key = $infoObject;
            }
        }
        if($found == false){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product is not in the cart', '?????????? ???? ?????????????? ?????????? ??????????????'));
            exit();
        }
        $newUserCartProductsString = json_encode($newUserCartProducts);
        $updateQueryResult = DB::update("UPDATE shoppingCarts SET products = '" . $newUserCartProductsString . "' WHERE id = $userCart->id");
        if($updateQueryResult){
            echo json_encode(array('status' => 'done', 'newProductCount' => $newProductCount, 'message' => 'cart successfully updated'));
            exit();
        }else{
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'query error', 'umessage' => '?????? ???? ????????????????????? ??????????????'));
            exit();
        }
    }

    //@route: /api/user-remove-from-cart <--> @middleware: ApiAuthenticationMiddleware
    public function removeFromCart(Request $request){
        $userId = $request->userId;
        $validator = Validator::make($request->all(), [
            'productPackId' => 'required|numeric',
        ]);
        if($validator->fails()){
            echo json_encode(array('status' => 'failed', 'source' => 'v', 'message' => 'argument validation failed', 'umessage' => '???????????? ?????????? ???????? ????????'));
            exit();
        }
        if(!isset($request->productPackId)){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'not enough parameter', 'umessage' => '?????????? ???????????? ??????'));
            exit();
        }
        $productPackId = $request->productPackId; 
        $userCart = DB::select( 
            "SELECT id, products 
            FROM shoppingCarts 
            WHERE user_id = $userId AND active = 1 
            ORDER BY timestamp DESC 
            LIMIT 1 "
        );
        if(count($userCart) == 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'cart is empty', '?????? ???????? ?????????? ???????? ??????????'));
            exit();
        }
        $userCart = $userCart[0];
        $cartProducts = json_decode($userCart->products);
        $found = false;
        $newProducts = new stdClass();
        foreach($cartProducts as $key => $value){
            if($key == $productPackId){
                $found = true;
            }else{
                $info = new stdClass();
                $info->count = $value->count;
                $newProducts->$key = $info;
            }
        }
        if($found === true){
            //$newProducts = json_encode($newProducts);
            $updateQueryResult = DB::update("UPDATE shoppingCarts SET products = '" . json_encode($newProducts) . "' WHERE id = $userCart->id");
            if($updateQueryResult){
                echo json_encode(array('status' => 'done', 'message' => 'cart successfully updated', 'umessage' => '?????????? ???? ???????????? ?????? ????'));
                exit();
            }else{
                echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'query error', 'umessage' => '?????? ???? ?????????? ???????? ??????????????'));
                exit();
            }
        }else{
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product does not exist', 'umessage' => '?????????? ???????????? ??????'));
            exit();
        }
    }

    //@route: /api/user-wipe-cart <--> @middleware: ApiAuthenticationMiddleware
    public function wipeCart(Request $request){
        $userId = $request->userId;
        $currentCart = DB::select(
            "SELECT id, products
            FROM shoppingCarts 
            WHERE user_id = $userId AND active = 1 LIMIT 1"
        );
        if(count($currentCart) == 0){
            echo json_encode(array('status' => 'failed', 'message' => 'cart is not initialized', 'umessage' => '?????? ???????? ?????????? ???????? ??????????'));
            exit();
        }
        $currentCart = $currentCart[0];
        if($currentCart->products == '{}'){
            echo json_encode(array('status' => 'failed', 'message' => 'cart is empty', 'umessage' => '?????????????? ???????? ??????'));
            exit();
        }
        $updateQueryResult = DB::update("UPDATE shoppingCarts SET products = '{}' WHERE id = $currentCart->id");
        if($updateQueryResult){
            echo json_encode(array('status' => 'done', 'message' => 'cart is successfully wiped', 'umessage' => '?????? ???????? ???? ???????????? ???????? ????'));
            exit();
        }else{
            echo json_encode(array('status' => 'failed', 'message' => 'query error', 'umessage' => '?????????? ???? ?????????????????? ??????????????? ???? ???????? ??????'));
            exit();
        }
    }

    //@route: /api/guest-cart <--> @middleware: -----
    public function guestCart(Request $request){
        $validator = Validator::make($request->all(), [
            'cart' => 'required|string',
        ]);
        if($validator->fails()){
            echo json_encode(array('status' => 'failed', 'source' => 'v', 'message' => 'argument validation failed', 'umessage' => '???????????? ?????????? ???????? ????????'));
            exit();
        }

        if(!isset($request->cart)){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'not enough information', 'umessage' => '?????????????? ???????? ?????????? ???????? ??????'));
            exit();
        }
        $cart = json_decode($request->cart);
        $cartProducts = [];
        foreach($cart as $cartItem){
            $product = DB::select(
                "SELECT P.id, PP.id AS packId, P.prodName_fa, P.prodID, P.url, P.prodStatus, P.prodUnite, PL.stock AS productStock, PL.pack_stock AS packStock, PP.status, PP.price, PP.base_price, PP.label, PP.count, PC.category 
                FROM products P
                INNER JOIN products_location PL ON PL.product_id = P.id INNER JOIN product_pack PP ON PP.id = PL.pack_id INNER JOIN product_category PC ON P.id = PC.product_id 
                WHERE PL.stock >0 AND PL.pack_stock > 0 AND PL.pack_stock <= PL.stock AND  PP.status = 1 AND P.prodStatus = 1 AND PP.id = $cartItem->id 
                LIMIT 1 ");
            if(count($product) !== 0){
                $product = $product[0];
                $productStatus = -1;
                if($product->prodStatus == 1 && $product->status == 1 && $product->packStock != 0 && $product->productStock != 0 ){
                    $productStatus = 1;
                }
                $productObject = new stdClass();
                $productObject->productId = $product->id;
                $productObject->productPackId = $product->packId;
                $productObject->productName = $product->prodName_fa;
                $productObject->prodID = $product->prodID;
                $productObject->categoryId = $product->category;
                $productObject->productPrice = $product->price;
                $productObject->productBasePrice = $product->base_price;
                $productObject->productCount = $cartItem->count;
                $productObject->productUnitName = $product->prodUnite;
                $productObject->productUnitCount = 0;
                $productObject->productUrl = $product->url;
                $productObject->productLabel = $product->label;
                if($productStatus === -1)
                {
                    $productObject->productPrice = 0;
                    $productObject->productBasePrice = 0;
                }
                array_push($cartProducts, $productObject);
            }else{
                $product = DB::select(
                    "SELECT P.id, 0 AS packId, P.prodName_fa, P.prodID, P.url, P.prodStatus, P.prodUnite, 0 AS productStock, 0 AS packStock, 0 AS `status`, 0 AS price, 0 AS base_price, '' AS label, 0 AS `count`, PC.category 
                    FROM products P
                    INNER JOIN product_pack PP ON P.id = PP.product_id INNER JOIN product_category PC ON P.id = PC.product_id 
                    WHERE P.prodStatus = 1 AND PP.id = $cartItem->id 
                    LIMIT 1 ");
                if(count($product) !== 0){
                    $product = $product[0];
                    $productStatus = -1;
                    if($product->prodStatus == 1 && $product->status == 1 && $product->packStock != 0 && $product->productStock != 0 && ($product->count * $product->packStock <= $product->productStock)){
                        $productStatus = 1;
                    }
                    $productObject = new stdClass();
                    $productObject->productId = $product->id;
                    $productObject->productPackId = $product->packId;
                    $productObject->productName = $product->prodName_fa;
                    $productObject->prodID = $product->prodID;
                    $productObject->categoryId = $product->category;
                    $productObject->productPrice = $product->price;
                    $productObject->productBasePrice = $product->base_price;
                    $productObject->productCount = $cartItem->count;
                    $productObject->productUnitName = $product->prodUnite;
                    $productObject->productUnitCount = 0;
                    $productObject->productUrl = $product->url;
                    $productObject->productLabel = $product->label;
                    if($productStatus === -1)
                    {
                        $productObject->productPrice = 0;
                        $productObject->productBasePrice = 0;
                    }
                    array_push($cartProducts, $productObject);
                }
            }
        }
        $cartProducts = DiscountCalculator::calculateProductsDiscount($cartProducts);

        echo json_encode(array('status' => 'done', 'message' => 'user cart found successfully', 'umessage' => '?????????????? ?????? ???????? ???? ???????????? ???????????? ????', 'cart' => $cartProducts));
    }

    //@route: /api/guest-check-cart-changes <--> @middleware: -----
    public function checkGuestCartChanges(Request $request){
        $validator = Validator::make($request->all(), [
            'productPackId' => 'required|numeric',
        ]);
        if($validator->fails()){
            echo json_encode(array('status' => 'failed', 'source' => 'v', 'message' => 'argument validation failed', 'umessage' => '???????????? ?????????? ???????? ????????'));
            exit();
        }

        if(!isset($request->productPackId) || !isset($request->count)){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'not enough parameter', 'umessage' => '?????????? ???????? ??????'));
            exit();
        }
        $productPackId = $request->productPackId;
        $count = $request->count;
        if($count <= 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'wrong input', 'umessage' => '?????????? ???????????? ??????'));
            exit();
        }

        $productLocation = DB::select(
            "SELECT * FROM products_location WHERE pack_id = $productPackId AND stock > 0 AND pack_stock > 0 AND pack_stock <= stock LIMIT 1 "
        );
        if(count($productLocation) === 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product is finished', 'umessage' => '?????????? ?????????????? ???? ?????????? ?????????? ??????'));
            exit();
        }
        
        $productLocation = $productLocation[0];

        /*$product = DB::select(
            "SELECT P.id, PP.id AS packId, P.prodName_fa, P.prodID, P.id, P.prodStatus, P.stock AS productStock, PP.stock AS packStock, PP.status, PP.price, PP.base_price, PP.label, PP.count, PC.category 
            FROM products P
            INNER JOIN product_pack PP ON P.id = PP.product_id INNER JOIN product_category PC ON P.id = PC.product_id 
            WHERE PP.id = $productPackId AND PP.status = 1 AND P.prodStatus = 1");
        if(count($product) == 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product not found', 'umessage' => '?????????? ???????? ?????? ???????? ??????'));
            exit();
        }
        $product = $product[0];
        $productOutOfOrder = true;
        if($product->prodStatus == 1 && $product->status === 1 && $product->packStock !==0 && $product->productStock !== 0 && ($product->count * $product->packStock <= $product->productStock) ){
            $productOutOfOrder = false;
        }
        if($productOutOfOrder == true){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product is out of order', 'umessage' => '???????????? ?????????? ???? ?????????? ???????????????????'));
            exit();
        }*/

        if($count > $productLocation->pack_stock){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'that number is not available', 'umessage' => '?????????? ???????????????? ?????????? ???? ?????????? ?????????? ??????'));
            exit();
        }else{
            echo json_encode(array('status' => 'done', 'messsage' => 'this number is available', 'umessage' => '?????? ?????????? ???? ???????? ?????????? ??????', 'count' => $count ));
            exit();
        }
    }

    //@route: /api/user-add-to-cart <--> @middleware: ApiAuthenticationMiddleware
    public function addToCart(Request $request){
        $validator = Validator::make($request->all(), [
            'productPackId' => 'required|numeric', 
            'productPackCount' => 'required|numeric', 
        ]);
        if($validator->fails()){
            echo json_encode(array('status' => 'failed', 'source' => 'v', 'message' => 'argument validation failed', 'umessage' => '???????????? ?????????? ???????? ????????'));
            exit();
        }

        if(!isset($request->productPackId) || !isset($request->productPackCount)){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'not enough parameter', 'umessage' => '?????????? ???????? ????????'));
            exit();
        }
        $userId = $request->userId;
        $productPackId = $request->productPackId;
        $productPackCount = $request->productPackCount;

        $productLocation = DB::select(
            "SELECT * FROM products_location WHERE pack_id = $productPackId LIMIT 1 "
        );
        
        if(count($productLocation) === 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product is not available', 'umessage' => '?????????? ?????????????? ???????? ???????? ????????'));
            exit();
        }

        $productLocation = $productLocation[0];

        if($productPackCount > $productLocation->pack_stock){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'this amount of number is not available', 'umessage' => '?????? ?????????? ?????????? ???????? ??????????'));
            exit();
        }

        $product = DB::select(
            "SELECT P.id, PP.id AS packId, P.prodName_fa, P.prodID, P.url, P.prodStatus, P.prodUnite, P.stock AS productStock, PP.stock AS packStock, PP.status, PP.price, PP.base_price, PP.label, PP.count, PC.category 
            FROM products P
            INNER JOIN product_pack PP ON P.id = PP.product_id INNER JOIN product_category PC ON P.id = PC.product_id 
            WHERE PP.id = $productLocation->pack_id AND PP.status = 1 AND P.prodStatus = 1"
        );
        if(count($product) === 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product not found', 'umessage' => '?????????? ?????????????? ???????? ??????'));
            exit();
        }
        $product = $product[0];
        /*
        if($productPackCount > $product->packStock){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'wrong input', 'umessage' => '?????????? ???????????????? ???????????? ??????'));
            exit();
        }
        */
        
        $productObject = new stdClass();
        $productObject->productId = $product->id;
        $productObject->productPackId = $product->packId;
        $productObject->productName = $product->prodName_fa;
        $productObject->prodID = $product->prodID;
        $productObject->categoryId = $product->category;
        $productObject->productPrice = $product->price;
        $productObject->productUrl = $product->url;
        $productObject->productBasePrice = $product->base_price;
        $productObject->productCount = $productPackCount;
        $productObject->productUnitCount = $product->count;
        $productObject->productUnitName = $product->prodUnite;
        $productObject->productLabel = $product->label;
        $productObject = DiscountCalculator::calculateProductDiscount($productObject);

        $lastActiveCart = DB::select("SELECT * FROM shoppingCarts WHERE user_id = $userId AND active = 1");
        if(count($lastActiveCart) !== 0){
            $lastActiveCart = $lastActiveCart[0];
            $products = json_decode($lastActiveCart->products);
            $found = false;
            $newKey = 0;
            $i = 0;
            foreach($products as $key => $value){
                $i++;
                if($key === $productPackId){
                    $found = true;
                    $newKey = $key;
                }
            }
            if($i === 0){
                $p = new stdClass();
                $p->$productPackId = new stdClass();
                $p->$productPackId->count = $productPackCount;
                $pString = json_encode($p);
                $updateQuery = DB::update("UPDATE shoppingCarts SET products = '$pString' WHERE id = $lastActiveCart->id");
                if($updateQuery){
                    echo json_encode(array('status' => 'done', 'message' => 'cart successfully updated', 'umessage' => '?????? ???????? ???? ???????????? ???????????? ????', 'information' => $productObject));
                    exit();
                }else{
                    echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'query error', 'umessage' => '???????? ?????? ?????????? ?????????? ???????? ??????????????'));
                    exit();
                }
            }
            if($found){
                echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product already exists', 'umessage' => '?????????? ???? ?????????????? ?????????? ??????'));
                exit();
            }else{
                $products->$productPackId = new stdClass();
                $products->$productPackId->count = $productPackCount;
                $productString = json_encode($products);
                $updateQueryResult = DB::update("UPDATE shoppingCarts SET products = '$productString' WHERE id = $lastActiveCart->id");
                if($updateQueryResult){
                    echo json_encode(array('status' => 'done', 'message' => 'cart successfully updated', 'umessage' => '?????????? ???? ???????????? ???? ?????? ???????? ?????????? ????', 'information' => $productObject));
                    exit();
                }else{
                    echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'update query error', 'umessage' => '???????? ?????? ?????????? ?????????? ???????? ??????????????'));
                    exit();
                }
            }
        }else{
            $time = time();
            $p = new stdClass();
            $p->$productPackId = new stdClass();
            $p->$productPackId->count = $productPackCount;
            $productString = json_encode($p);
            $insertQueryResult = DB::insert("INSERT INTO shoppingCarts (user, user_id, products, bundles, timestamp, status, active) VALUES ('', $userId, '$productString', '', $time, 0, 1)");
            if($insertQueryResult){
                echo json_encode(array('status' => 'done', 'message' => 'product successfully added to product', 'umessage' => '?????????? ???? ???????????? ???? ?????? ???????? ?????????? ????', 'information' => $productObject));
                exit();
            }else{
                echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'inserting query error', 'umessage' => '?????? ?????????? ?????????? ???????? ??????????????'));
                exit();
            }
        }
    }

    //@route: /api/guest-add-to-cart <--> @middleware: -----
    public function guestAddToCart(Request $request){
        $validator = Validator::make($request->all(), [
            'productPackId' => 'required|numeric', 
            'productPackCount' => 'required|numeric', 
        ]);
        if($validator->fails()){
            echo json_encode(array('status' => 'failed', 'source' => 'v', 'message' => 'argument validation failed', 'umessage' => '???????????? ?????????? ???????? ????????'));
            exit();
        }

        if(!isset($request->productPackId) || !isset($request->productPackCount)){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'not enough parameter', 'umessage' => '?????????? ???????? ????????'));
            exit();
        }
        $productPackId = $request->productPackId;
        $productPackCount = $request->productPackCount;

        $productLocation = DB::select(
            "SELECT * FROM products_location WHERE pack_id = $productPackId LIMIT 1 "
        );
        
        if(count($productLocation) === 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product is not available', 'umessage' => '?????????? ?????????????? ???????? ???????? ????????'));
            exit();
        }

        $productLocation = $productLocation[0];

        if($productPackCount > $productLocation->pack_stock){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'this amount of number is not available', 'umessage' => '?????? ?????????? ?????????? ???????? ??????????'));
            exit();
        }

        $product = DB::select(
            "SELECT P.id, PP.id AS packId, P.prodName_fa, P.prodID, P.url, P.prodStatus, P.prodUnite, P.stock AS productStock, PP.stock AS packStock, PP.status, PP.price, PP.base_price, PP.label, PP.count, PC.category 
            FROM products P
            INNER JOIN product_pack PP ON P.id = PP.product_id INNER JOIN product_category PC ON P.id = PC.product_id 
            WHERE PP.id = $productLocation->pack_id "
        );
        if(count($product) === 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product not found', 'umessage' => '?????????? ?????????????? ???????? ??????'));
            exit();
        }
        $product = $product[0];
        /*
        if($productPackCount > $product->packStock){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'wrong input', 'umessage' => '?????????? ???????????????? ???????????? ??????'));
            exit();
        }
        */
        $productObject = new stdClass();
        $productObject->productId = $product->id;
        $productObject->productPackId = $product->packId;
        $productObject->productName = $product->prodName_fa;
        $productObject->prodID = $product->prodID;
        $productObject->categoryId = $product->category;
        $productObject->productPrice = $product->price;
        $productObject->productUrl = $product->url;
        $productObject->productBasePrice = $product->base_price;
        $productObject->productCount = $productPackCount;
        $productObject->productUnitCount = $product->count;
        $productObject->productUnitName = $product->prodUnite;
        $productObject->productLabel = $product->label;

        echo json_encode(array('status' => 'done', 'message' => 'adding product is allowed', 'information' => $productObject));
    }

    public function cartFinalInformation(Request $request){
        $userId = $request->userId;
        $user = DB::select("SELECT * FROM users WHERE id = $userId");
        $user = $user[0];
        $cart = DB::select( 
            "SELECT products 
            FROM shoppingCarts 
            WHERE user_id = $userId AND active = 1 
            ORDER BY timestamp DESC 
            LIMIT 1"
        );
        if(count($cart) === 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'cart not found', 'umessage' => '?????? ???????? ???????? ??????'));
            exit();
        }
        $cart = json_decode($cart[0]->products);
        $cartProducts = [];
        foreach($cart as $key => $value){
            $product = DB::select(
                "SELECT P.id, PP.id AS packId, P.prodName_fa, P.prodID, P.prodWeight, P.url, P.prodStatus, P.prodUnite, P.stock AS productStock, PP.stock AS packStock, PP.status, PP.price, PP.base_price, PP.label, PP.count, PC.category 
                FROM products P 
                INNER JOIN products_location PL ON PL.product_id = P.id INNER JOIN product_pack PP ON PL.pack_id = PP.id INNER JOIN product_category PC ON P.id = PC.product_id 
                WHERE PP.status = 1 AND P.prodStatus = 1 AND PL.pack_id = $key AND PL.stock > 0 AND PL.pack_stock > 0 AND PL.pack_stock <= PL.stock  LIMIT 1 "
            );
            if(count($product) !== 0){
                $product = $product[0];
                $productObject = new stdClass();
                $productObject->productId = $product->id;
                $productObject->productPackId = $product->packId;
                $productObject->productName = $product->prodName_fa;
                $productObject->prodID = $product->prodID;
                $productObject->categoryId = $product->category;
                $productObject->productPrice = $product->price;
                $productObject->productUrl = $product->url;
                $productObject->productBasePrice = $product->base_price;
                $productObject->productCount = $value->count;
                $productObject->productUnitCount = $product->count;
                $productObject->productUnitName = $product->prodUnite;
                $productObject->productLabel = $product->label;
                $productObject->productWeight = $product->prodWeight;
                array_push($cartProducts, $productObject);
            }
        }
        
        /*if($user->address === '' || $user->address === NULL){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'user does not have address', 'umessage' => '?????????? ???????? ???????? ????????????'));
            exit();
        }
        $addressPack = json_decode($user->address)->addressPack;
        if($addressPack->province == -1){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'user does not have address', 'umessage' => '?????????? ???????? ???????? ????????????'));
            exit();
        }
        $provinceId = DB::select("SELECT id FROM provinces WHERE name = '$addressPack->province'");
        if(count($provinceId) == 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'province could not be found', 'umessage' => '?????????? ?????????? ???????? ??????'));
            exit();
        }
        $provinceId = $provinceId[0];
        $provinceId = $provinceId->id;
        $cityId = DB::select("SELECT id FROM cities WHERE city = '$addressPack->city'");
        if(count($cityId) == 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'city could not be found', 'umessage' => '?????? ?????????? ???????? ??????'));
            exit();
        }
        $cityId = $cityId[0];
        $cityId = $cityId->id;*/

        $provinceId = 0;
        $cityId = 0;

        $result = UserController::getProvinceId($user);
        if($result->successful){
            $provinceId = $result->provinceId;
        }else{
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => $result->message, 'umessage' => $result->umessage));
            exit();
        }

        $result = UserController::getCityId($user);
        if($result->successful){
            $cityId = $result->cityId;
        }else{
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => $result->message, 'umessage' => $result->umessage));
            exit();
        }

        $info = DiscountCalculator::totalDiscount($cartProducts, $user, $provinceId, $cityId);
        $info->userStock = $user->user_stock;
        echo json_encode(array('status' => 'done', 'message' => 'discounts successfully found', 'information' => $info));
    }

    public function userChangeCart(Request $request){
        if(!isset($request->cart)){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'not enough parameter', 'umessage' => '?????????????? ?????????????? ???????? ????????'));
            exit();
        }
        $userId = $request->userId;
        $cart = $request->cart;
        $responseCartArray = [];
        $cartObject = new stdClass();
        foreach($cart as $c){
            $packId = $c['id'];
            $count = $c['count'];
            $productInformation = DB::select( 
                "SELECT P.id, PP.id AS packId, P.prodName_fa, P.prodID, P.url, P.prodStatus, P.prodUnite, P.stock AS productStock, PP.stock AS packStock, PP.status, PP.price, PP.base_price, PP.label, PP.count, PC.category 
                FROM products P 
                INNER JOIN products_location PL ON P.id = PL.product_id INNER JOIN product_pack PP ON PP.id = PL.pack_id INNER JOIN product_category PC ON P.id = PC.product_id 
                WHERE PL.stock > 0 AND PL.pack_stock > 0 AND (PL.pack_stock * PP.count <= PL.stock ) AND PL.pack_id = $packId AND PP.status = 1 AND P.prodStatus = 1 AND PP.stock > 0 AND P.stock > 0 AND (PP.count * PP.stock <= P.stock)"
            );
            if(count($productInformation) === 0){
                continue; //If product was not available, do not add it to users cart
            }

            $productInformation = $productInformation[0];
            $cartObject->$packId = new stdClass();
            $cartObject->$packId->count = $count;
            if($count > $productInformation->packStock){
                $cartObject->$packId->count = $productInformation->packStock;
            }
            $productObject = new stdClass();
            $productObject->productId = $productInformation->id;
            $productObject->productPackId = $productInformation->packId;
            $productObject->productName = $productInformation->prodName_fa;
            $productObject->prodID = $productInformation->prodID;
            $productObject->categoryId = $productInformation->category;
            $productObject->productPrice = $productInformation->price;
            $productObject->productUrl = $productInformation->url;
            $productObject->productBasePrice = $productInformation->base_price;
            $productObject->productCount = $cartObject->$packId->count;
            $productObject->productUnitCount = $productInformation->count;
            $productObject->productUnitName = $productInformation->prodUnite;
            $productObject->productLabel = $productInformation->label;
            $productObject = DiscountCalculator::calculateProductDiscount($productObject);
            array_push($responseCartArray, $productObject);
        }
        $cartString = json_encode($cartObject);

        // If there was not any available products, then do nothing
        if($cartString === '{}'){
            echo json_encode(array('status' => 'done', 'message' => 'user cart did not change'));
            exit();
        }
        $userPreviousShippingCart = DB::select(
            "SELECT id, products 
            FROM shoppingCarts 
            WHERE user_id = $userId AND active = 1 
            ORDER BY id DESC 
            LIMIT 1"
        );
        $time = time();
        if(count($userPreviousShippingCart) === 0){
            DB::insert(
                "INSERT INTO shoppingCarts (
                    user, user_id, products, bundles, timestamp, status, active
                ) VALUES (
                    '', $userId, '$cartString', '', $time, 1, 1
                )"
            );
            echo json_encode(array('status' => 'done', 'message' => 'a new user cart was created', 'cart' => $responseCartArray));
        }else{
            $userPreviousShippingCart = $userPreviousShippingCart[0];
            DB::update(
                "UPDATE shoppingCarts 
                SET products = '$cartString', timestamp = $time 
                WHERE id = $userPreviousShippingCart->id"
            );
            echo json_encode(array('status' => 'done', 'message' => 'user cart successfully got updated', 'cart' => $responseCartArray));
        }
    }
}
/*
$product = DB::select(
                    "SELECT P.id, PP.id AS packId, P.prodName_fa, P.prodID, P.url, P.prodStatus, P.prodUnite, P.stock AS productStock, PP.stock AS packStock, PP.status, PP.price, PP.base_price, PP.label, PP.count, PC.category 
                    FROM products P
                    INNER JOIN product_pack PP ON P.id = PP.product_id INNER JOIN product_category PC ON P.id = PC.product_id 
                    WHERE PP.id = $productPackId AND PP.status = 1 AND P.prodStatus = 1"
                );
        if(count($product) === 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'product not found', 'umessage' => '?????????? ?????????????? ???????? ??????'));
            exit();
        }
        $product = $product[0];
        if($productPackCount > $product->packStock){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'wrong input', 'umessage' => '?????????? ???????????????? ???????????? ??????'));
            exit();
        }
        $productObject = new stdClass();
        $productObject->productId = $product->id;
        $productObject->productPackId = $product->packId;
        $productObject->productName = $product->prodName_fa;
        $productObject->prodID = $product->prodID;
        $productObject->categoryId = $product->category;
        $productObject->productPrice = $product->price;
        $productObject->productUrl = $product->url;
        $productObject->productBasePrice = $product->base_price;
        $productObject->productCount = $productPackCount;
        $productObject->productUnitCount = $product->count;
        $productObject->productUnitName = $product->prodUnite;
        $productObject->productLabel = $product->label;
        $productObject = DiscountCalculator::calculateProductDiscount($productObject);
*/
