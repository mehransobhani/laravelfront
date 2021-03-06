<?php
namespace App\Classes;
use Illuminate\Support\Facades\DB;
use stdClass;
use App\Classes\ShippingCalculator;
use App\Http\Controllers\DeliveryServiceController;
use Dotenv\Util\Str;

class DiscountCalculator{

    public static function calculateProductDiscount($product){
        $time = time();
        $discounts = DB::select("SELECT * FROM discounts D WHERE D.status = 1 AND D.type_id IN (1, 2, 5) AND D.code IS NULL AND D.neworder = 0 AND D.user_start_date IS NULL AND D.user_finish_date IS NULL 
            AND (D.numbers_left IS NULL OR D.numbers_left > 0) AND (D.start_date IS NULL OR D.start_date <= $time) AND (D.finish_date IS NULL OR D.finish_date >= $time) AND (D.expiration_date IS NULL OR D.expiration_date >= $time)");
        $reducedPrice = 0;
        if(!isset($product->packs)){
            return $product;
        }
	if(count($product->packs) === 0){
	    return $product;
	}
        $rps = [];
        foreach($product->packs as $pack){
            array_push($rps, 0);
        }
        foreach($discounts as $discount){
            $discountUsers = DB::select("SELECT DD.dependency_id AS user_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 4");
            $discountProvinces = DB::select("SELECT DD.dependency_id AS province_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 3");
            $discountProducts = DB::select("SELECT DD.dependency_id AS product_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 1");
            $discountCategories =  DB::select("SELECT DD.dependency_id AS category_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 2");
            if(count($discountUsers) === 0 && count($discountProvinces) === 0){
                if($discount->min_price === null || $discount->min_price <= $product->productPrice){
                    $inputProductObject = new stdClass();
                    $inputCategoryObject = new stdClass();
                    $inputProductObject->product_id = $product->productId;
                    $inputCategoryObject->category_id = $product->categoryId;
                    
                    if(($discount->type_id === 1 && (count($discountProducts) === 0 || in_array($inputProductObject, $discountProducts))) ||
                        ($discount->type_id === 2 && (count($discountCategories) === 0 || in_array($inputCategoryObject, $discountCategories)))){
                        $rp = 0;
                        if($discount->price !== null){
                            $rp += $discount->price;
                            foreach($rps as $r){
                                $r += $rp;
                            }
                        }else if($discount->percent !== null){
                            $i=0;
                            for($i=0; $i<count($product->packs); $i++){
                                $temp = ($discount->percent / 100) * $product->packs[$i]->price;
                                if($discount->max_price !== null && $temp > $discount->max_price){
                                    $rps[$i] += $discount->max_price;
                                }else{
                                    $rps[$i] += $temp;
                                }
                            }
                        }
                    }else if($discount->type_id === 5 && in_array($inputProductObject, $discountProducts)){
                        $discountDependencyInformation = DB::select(
                            "SELECT * FROM discount_dependencies WHERE discount_id = $discount->id AND dependency_id = $product->productId ORDER BY id DESC LIMIT 1 "
                        );
                        if(count($discountDependencyInformation) !== 0){
                            $discountDependencyInformation = $discountDependencyInformation[0];
                            if($discountDependencyInformation->final_stock === 0){
                                $i=0;
                                for($i=0; $i<count($product->packs); $i++){
                                    $rp = 0;
                                    if($discountDependencyInformation->price !== 0 && $discountDependencyInformation->price <= $product->packs[$i]->price){
                                        $rp = $discountDependencyInformation->price;
                                    }else if($discountDependencyInformation->percent !== 0 && $discountDependencyInformation->percent <= 100){
                                        $rp = ($discountDependencyInformation->percent / 100) * $product->packs[$i]->price;
                                    }
                                    $rps[$i] += $rp;
                                }
                            }else{
                                $i=0;
                                for($i=0; $i<count($product->packs); $i++){
                                    $rp = 0;
                                    if($discountDependencyInformation->final_stock < $product->packs[$i]->stock){
                                        if($discountDependencyInformation->price !== 0 && $discountDependencyInformation->price <= $product->packs[$i]->price){
                                            $rp = $discountDependencyInformation->price;
                                        }else if($discountDependencyInformation->percent !== 0 && $discountDependencyInformation->percent <= 100){
                                            $rp = ($discountDependencyInformation->percent / 100) * $product->packs[$i]->price;
                                        }
                                        $rps[$i] += $rp;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $i=0;
        for($i=0; $i<count($product->packs); $i++){
            if($rps[$i] === 0){
                $product->packs[$i]->discountedPrice = $product->packs[$i]->price;
                $product->packs[$i]->discountPercent = 0;
            }else if($rps[$i] >= $product->packs[$i]->price){
                $product->packs[$i]->discountedPrice = 0;
                $product->packs[$i]->discountPercent = 100;
            }else{
                $product->packs[$i]->discountedPrice = $product->packs[$i]->price - ($rps[$i] - $rps[$i]%50);
                $product->packs[$i]->discountPercent = (integer)(($rps[$i]/$product->packs[$i]->price) * 100);
            }
        }
        return $product;
    }
 
    /*
    public static function calculateProductDiscount($product){
        $time = time();
        $discounts = DB::select("SELECT * FROM discounts D WHERE D.status = 1 AND D.type_id IN (1, 2, 5) AND D.code IS NULL AND D.neworder = 0 AND D.user_start_date IS NULL AND D.user_finish_date IS NULL 
            AND (D.numbers_left IS NULL OR D.numbers_left > 0) AND (D.start_date IS NULL OR D.start_date <= $time) AND (D.finish_date IS NULL OR D.finish_date >= $time) AND (D.expiration_date IS NULL OR D.expiration_date >= $time)");
        $reducedPrice = 0;
	$activePacks = 0;
        foreach($discounts as $discount){
            $discountUsers = DB::select("SELECT DD.dependency_id AS user_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 4");
            $discountProvinces = DB::select("SELECT DD.dependency_id AS province_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 3");
            $discountProducts = DB::select("SELECT DD.dependency_id AS product_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 1");
            $discountCategories =  DB::select("SELECT DD.dependency_id AS category_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 2");
            if(count($discountUsers) === 0 && count($discountProvinces) === 0){
                if($discount->min_price === null || $discount->min_price <= $product->productPrice){
                    $inputProductObject = new stdClass();
                    $inputCategoryObject = new stdClass();
                    $inputProductObject->product_id = $product->productId;
                    $inputCategoryObject->category_id = $product->categoryId;
                    
                    if(($discount->type_id === 1 && (count($discountProducts) === 0 || in_array($inputProductObject, $discountProducts))) ||
                        ($discount->type_id === 2 && (count($discountCategories) === 0 || in_array($inputCategoryObject, $discountCategories)))){
                        $rp = 0;
                        if($discount->price !== null){
                            $rp += $discount->price;
                        }else if($discount->percent !== null){
                            $rp = ($discount->percent / 100) * $product->productPrice;
                        }
                        if($discount->percent !== null && $discount->max_price !== null && $rp > $discount->max_price){
                            $rp = $discount->max_price;
                        }
                        $reducedPrice += $rp;
                        $product->rp = $reducedPrice;
                    }else if($discount->type_id === 5 && in_array($inputProductObject, $discountProducts)){
                        $discountDependencyInformation = DB::select(
                            "SELECT * FROM discount_dependencies WHERE discount_id = $discount->id AND dependency_id = $product->productId ORDER BY id DESC LIMIT 1 "
                        );
                        if(count($discountDependencyInformation) !== 0){
                            $discountDependencyInformation = $discountDependencyInformation[0];
                            if($discountDependencyInformation->final_stock === 0){
                                $rp = 0;
                                if($discountDependencyInformation->price !== 0 && $discountDependencyInformation->price <= $product->productPrice){
                                    $rp += $discountDependencyInformation->price;
                                }else if($discountDependencyInformation->percent !== 0 && $discountDependencyInformation->percent <= 100){
                                    $rp = ($discountDependencyInformation->percent / 100) * $product->productPrice;
                                }
                                $reducedPrice += $rp;
                                $product->rp = $reducedPrice;
                            }else{
                                $productStock = DB::select("SELECT pack_stock AS stock FROM products_location WHERE product_id = $product->productId ORDER BY id DESC limit 1 ");
                                if(count($productStock) !== 0){
                                    $productStock = $productStock[0];
                                    if($productStock->stock > $discountDependencyInformation->final_stock){
                                        $rp = 0;
                                        if($discountDependencyInformation->price !== 0 && $discountDependencyInformation->price <= $product->productPrice){
                                            $rp += $discountDependencyInformation->price;
                                        }else if($discountDependencyInformation->percent !== 0 && $discountDependencyInformation->percent <= 100){
                                            $rp = ($discountDependencyInformation->percent / 100) * $product->productPrice;
                                        }
                                        $reducedPrice += $rp;
                                        $product->rp = $reducedPrice;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        if($reducedPrice !== 0){
            if($reducedPrice < $product->productPrice){
                $product->discountedPrice = $product->productPrice - ($reducedPrice - $reducedPrice%50);
                $product->discountPercent = (integer)(($reducedPrice/$product->productPrice) * 100);
            }else{
                $product->discountedPrice = 0;
                $product->discountPercent = 100;
            }
        }else{
            $product->discountedPrice = $product->productPrice;
            $product->discountPercent = 0;
        }
        return $product;
    }
    */

    

    public static function calculateProductsDiscount($products){ 
        $time = time(); 
        $discounts = DB::select("SELECT * FROM discounts D WHERE D.status = 1 AND D.type_id IN (1,2, 5) AND D.code IS NULL AND D.neworder = 0 
            AND (D.numbers_left IS NULL OR D.numbers_left > 0) AND (D.start_date IS NULL OR D.start_date <= $time) AND (D.finish_date IS NULL OR D.finish_date >= $time) AND D.reusable = 1 
            AND D.user_start_date IS NULL AND D.user_finish_date IS NULL 
            AND (D.expiration_date IS NULL OR D.expiration_date >= $time)");
        if(count($discounts) !== 0){ 
            $rps = array(); 
            foreach($products as $product){ 
                array_push($rps, 0); 
            } 
            foreach($discounts as $discount){
                $discountUsers = DB::select("SELECT DD.dependency_id AS `user_id` FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 4");
                $discountProvinces = DB::select("SELECT DD.dependency_id AS province_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 3");
                $discountProducts = DB::select("SELECT DD.dependency_id AS product_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 1");
                $discountCategories =  DB::select("SELECT DD.dependency_id AS category_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 2");
                if(count($discountUsers) === 0 && count($discountProvinces) === 0){
                    $i = 0;
                    foreach($products as $product){
                        if($discount->min_price === null || $discount->min_price <= $product->productPrice){
                            $inputProductObject1 = new stdClass();
                            $inputCategoryObject1 = new stdClass();
                            $inputProductObject1->product_id = $product->productId;
                            $inputCategoryObject1->category_id = $product->categoryId;
                            if(($discount->type_id === 1 && (count($discountProducts) === 0 || in_array($inputProductObject1, $discountProducts))) ||
                                ($discount->type_id === 2 && (count($discountCategories) === 0 || in_array($inputCategoryObject1, $discountCategories)))){
                                $rp = 0;
                                if($discount->price !== null){
                                    $rp += $discount->price;
                                }else if($discount->percent !== null){
                                    $rp = ($discount->percent / 100) * $product->productPrice;
                                }
                                if($discount->max_price !== null && $rp > $discount->max_price){
                                    $rp = $discount->max_price;
                                }
                                $rps[$i] += $rp;
                            }else if($discount->type_id === 5 && in_array($inputProductObject1, $discountProducts)){
                                $discountDependencyInformation = DB::select(
                                    "SELECT * FROM discount_dependencies WHERE discount_id = $discount->id AND dependency_id = $product->productId ORDER BY id DESC LIMIT 1 "
                                );
                                if(count($discountDependencyInformation) !== 0){
                                    $discountDependencyInformation = $discountDependencyInformation[0];
                                    if($discountDependencyInformation->final_stock === 0){
                                        $rp = 0;
                                        if($discountDependencyInformation->price !== 0 && $discountDependencyInformation->price <= $product->productPrice){
                                            $rp += $discountDependencyInformation->price;
                                        }else if($discountDependencyInformation->percent !== 0 && $discountDependencyInformation->percent <= 100){
                                            $rp = ($discountDependencyInformation->percent / 100) * $product->productPrice;
                                        }
                                        $rps[$i] += $rp;
                                    }else{
                                        $productStock = DB::select("SELECT pack_stock AS stock FROM products_location WHERE product_id = $product->productId ORDER BY id DESC LIMIT 1 ");
                                        if(count($productStock) !== 0){
                                            $productStock = $productStock[0];
                                            if($discountDependencyInformation->final_stock < $productStock->stock){
                                                $rp = 0;
                                                if($discountDependencyInformation->price !== 0 && $discountDependencyInformation->price <= $product->productPrice){
                                                    $rp += $discountDependencyInformation->price;
                                                }else if($discountDependencyInformation->percent !== 0 && $discountDependencyInformation->percent <= 100){
                                                    $rp = ($discountDependencyInformation->percent / 100) * $product->productPrice;
                                                }
                                                $rps[$i] += $rp;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $i++;
                    }
                }
            }
            $i = 0;
            foreach($products as $product){
                if($rps[$i] !== 0){
                    if($rps[$i] < $products[$i]->productPrice){
                        $products[$i]->discountedPrice = $products[$i]->productPrice - ($rps[$i] - $rps[$i]%50);
                        $products[$i]->discountPercent = (integer)(($rps[$i]/$products[$i]->productPrice) * 100);
                    }else{
                        $products[$i]->discountedPrice = 0;
                        $products[$i]->discountPercent = 100;
                    }
                }else{
                    $products[$i]->discountedPrice = $products[$i]->productPrice;
                    $products[$i]->discountPercent = 0;
                }
                $i++;
            }
            return $products;
        }else{
            foreach($products as $product){
                $product->discountedPrice = $product->productPrice;
                $product->discountPercent = 0;
            }
            return $products;
        }
    }
    
    public static function calculateDeliveryDiscount($userId, $provinceId, $orderPrice, $productsInformation, $deliveryOptions){
        $now = time();
        $discountPercent = 0;
        $discountPrice = 0;
        foreach($deliveryOptions as $deliveryOption){
            $deliveryOption->discountedPrice = $deliveryOption->price;
            $deliveryOption->discountPercent = 0;
        }
        $discounts = DB::select(
                            "SELECT * 
                            FROM discounts D
                            WHERE D.type_id = 3 AND D.status = 1 AND D.code IS NULL AND 
                            ((D.start_date IS NULL AND D.finish_date IS NULL) OR (D.start_date <= $now AND $now <= D.finish_date)) AND 
                            ((D.expiration_date IS NULL) OR ($now <= D.expiration_date)) AND 
                            ((D.user_start_date IS NULL AND D.user_finish_date IS NULL) OR (D.user_start_date IS NOT NULL AND D.user_finish_date IS NOT NULL AND (SELECT COUNT(O.id) FROM orders O WHERE O.user_id = $userId AND O.date >= D.user_start_date AND O.date <= D.user_finish_date) = 0))
                            ORDER BY date DESC, id DESC"
                        );
        if(count($discounts) === 0){
            return $deliveryOptions;
        }
        foreach($discounts as $discount){
            if($discount->numbers_left !== NULL && $discount->numbers_left === 0){
                continue;
            }
            if($discount->min_price != NULL && $orderPrice < $discount->min_price ){
                continue;
            }
            $discountUserDependencies = DB::select("SELECT dependency_id FROM discount_dependencies WHERE discount_id = $discount->id AND `type_id` = 4 ");
            $discountProvinceDependencies = DB::select("SELECT dependency_id FROM discount_dependencies WHERE discount_id = $discount->id AND `type_id` = 3 ");
            $discountProductDependencies = DB::select("SELECT dependency_id FROM discount_dependencies WHERE discount_id = $discount->id AND `type_id` = 1 ");
            $discountCategoryDependencies = DB::select("SELECT dependency_id FROM discount_dependencies WHERE discount_id = $discount->id AND `type_id` = 2 ");
            if(count($discountUserDependencies) !== 0){
                $approved = false;
                foreach($discountUserDependencies as $item){
                    if($item->dependency_id == $userId){
                        $approved = true;
                        break;
                    }
                }
                if(!$approved){
                    continue;
                }
            }
            if(count($discountProvinceDependencies) !== 0){
                $approved = false;
                foreach($discountProvinceDependencies as $item){
                    if($item->dependency_id == $provinceId){
                        $approved = true;
                        break;
                    }
                }
                if(!$approved){
                   continue;
                }
            }
            if(count($discountProductDependencies) !== 0){
                $approved = false;
                foreach($discountProductDependencies as $item){
                    $dependencyId = $item->dependency_id;
                    foreach($productsInformation as $pi){
                        if($pi->productId === $dependencyId){
                            $approved = true;
                            break;
                        }
                    }
                    if($approved){
                        break;
                    }
                }
                if(!$approved){
                    continue;
                }
            }
            if(count($discountCategoryDependencies) !== 0){
                $approved = false;
                foreach($discountCategoryDependencies as $item){
                    $dependencyId = $item->dependency_id;
                    foreach($productsInformation as $pi){
                        if($pi->categoryId === $dependencyId){
                            $approved = true;
                            break;
                        }
                    }
                    if($approved){
                        break;
                    }
                }
                if(!$approved){
                    continue;
                }
            }
            if($discount->neworder !== 0){
                $userPreviousOrders = DB::select("SELECT id FROM orders WHERE user_id = $userId");
                if(count($userPreviousOrders) !== 0){
                    continue;
                }
            }
            if($discount->reusable === 0){
                $userPreviousUsedDiscounts = DB::select("SELECT id FROM discount_logs WHERE discount_id = $discount->id");
                if(count($userPreviousUsedDiscounts) !== 0){
                    continue;
                }
            }
            if($discount->percent !== NULL){
                $discountPercent += $discount->percent;
            }else if($discount->price !== NULL){
                $discountPrice += $discount->price;
            }
        }
        foreach($deliveryOptions as $deliveryOption){
            if($discountPercent !== 0){
                $deliveryOption->discountedPrice -= $deliveryOption->price * ((float)$discountPercent / 100);
            }
            if($discountPrice !== 0){
                $deliveryOption->discountedPrice -= $discountPrice;
            }
            if($deliveryOption->discountedPrice < 0){
                $deliveryOption->discountedPrice = 0;
            }
        }
        return $deliveryOptions;
    }

    public static function evaluateGiftCode($giftCode, $userId, $cartPrice, $shippingPrice, $provinceId, $productIds, $categoryIds, $has){
        $time = time();
        $responseObject = new stdClass();
        $discount = DB::select(
            "SELECT * 
            FROM discounts D
            WHERE 
                D.code = '$giftCode' AND
                D.status = 1 AND 
                (D.start_date IS NULL OR D.start_date <= $time) AND 
                (D.finish_date IS NULL OR D.finish_date >= $time) AND 
                (D.expiration_date IS NULL OR D.expiration_date >= $time) AND 
                ((D.user_start_date IS NULL AND D.user_finish_date IS NULL) OR (D.user_start_date IS NOT NULL AND D.user_finish_date IS NOT NULL AND (SELECT COUNT(O.id) FROM orders O WHERE O.user_id = $userId AND O.date >= D.user_start_date AND O.date <= D.user_finish_date) = 0)) AND 
                (D.min_price IS NULL OR D.min_price <= $cartPrice)
            LIMIT 1"
        );
        if(count($discount) === 0){
            $responseObject->allowed = false;
            $responseObject->discountPrice = 0;
            $responseObject->type = '';
            $responseObject->message = "discount not found";
            $responseObject->umessage = "???? ?????????? ?????????? ????????";
            return $responseObject;
        }
        $discount = $discount[0];
        if($has == 1 && $discount->joinable == 0){
            $responseObject->allowed = false;
            $responseObject->discountPrice = 0;
            $responseObject->type = '';
            $responseObject->message = "discount is not joinable";
            $responseObject->umessage = "?????? ???? ?????????? ???? ???????????????? ???? ???????? ???????? ?????????????? ????????";
            return $responseObject;
        }
        if($discount->numbers_left === 0){
            $responseObject->allowed = false;
            $responseObject->discountPrice = 0;
            $responseObject->type = '';
            $responseObject->message = "discounts usage number is on its limit";
            $responseObject->umessage = "?????????? ?????????? ?????????????? ???? ?????? ?????????? ???? ?????????? ?????????? ??????";
            return $responseObject;
        }
        if($discount->neworder == 1){
            $userPreviousOrders = DB::select(
                "SELECT id 
                FROM orders 
                WHERE user_id = $userId"
            );
            if(count($userPreviousOrders) != 0){
                $responseObject->allowed = false;
                $responseObject->discountPrice = 0;
                $responseObject->type = '';
                $responseObject->message = "discount is for users with frist no orders yet";
                $responseObject->umessage = "?????? ???? ?????????? ???????? ???????????????? ?????? ???? ???????????? ?????????? ?????????????????????";
                return $responseObject;
            }
        }
        if($discount->reusable == 0){
            $timesThatUserUsedThisDiscount = DB::select(
                "SELECT id 
                FROM discount_logs 
                WHERE user_id = $userId"
            );
            if(count($timesThatUserUsedThisDiscount) !== 0){
                $responseObject->allowed = false;
                $responseObject->discountPrice = 0;
                $responseObject->type = '';
                $responseObject->message = "discount has used this discount once";
                $responseObject->umessage = "?????? ???????????? ???? ?????? ?????????? ?????????????? ?????????????????";
                return $responseObject;
            }
        }
        $discountProvinceDependencies = DB::select(
            "SELECT * 
            FROM discount_dependencies 
            WHERE discount_id = $discount->id AND type_id = 3"
        );
        if(count($discountProvinceDependencies) !== 0){
            $found = false;
            foreach($discountProvinceDependencies as $dependency){
                if($dependency->dependency_id == $provinceId){
                    $found = true;
                }
            }
            if(!$found){
                $responseObject->allowed = false;
                $responseObject->discountPrice = 0;
                $responseObject->type = '';
                $responseObject->message = "discount is not available in this province";
                $responseObject->umessage = "?????? ???? ?????????? ???????? ?????????? ?????? ???????? ????????";
                return $responseObject;
            }
        }
        $discountProductDependencies = DB::select(
            "SELECT *
            FROM discount_dependencies 
            WHERE discount_id = $discount->id AND `type_id` = 1"
        );
        if(count($discountProductDependencies) !== 0){
            $found = false;
            foreach($discountProductDependencies as $dependency){
                foreach($productIds as $pid){
                    if($pid == $dependency->dependency_id){
                        $found = true;
                    }
                }
            }
            if(!$found){
                $responseObject->allowed = false;
                $responseObject->discountPrice = 0;
                $responseObject->type = '';
                $responseObject->message = "discount needs a product in your cart";
                $responseObject->umessage = "???? ?????????? ???? ???????? ???? ?????????? ?????? ???? ?????? ???????? ???????? ??????????";
                return $responseObject;
            }
        }
        $discountCategoryDependencies = DB::select(
            "SELECT * 
            FROM discount_dependencies 
            WHERE discount_id = $discount->id AND `type_id` = 2"
        );
        if(count($discountCategoryDependencies) !== 0){
            $found = false;
            foreach($discountCategoryDependencies as $dependency){
                foreach($categoryIds as $cid){
                    if($cid == $dependency->dependency_id){
                        $found = true;
                    }
                }
            }
            if(!$found){
                $responseObject->allowed = false;
                $responseObject->discountPrice = 0;
                $responseObject->type = '';
                $responseObject->message = "discount needs a product from specific category in your cart";
                $responseObject->umessage = "???? ?????????? ???? ???????? ???? ?????????? ???? ???? ??????????????????? ?????? ???? ?????? ???????? ???????? ??????????";
                return $responseObject;
            }
        }
        $discountUserDependencies = DB::select(
            "SELECT *
            FROM discount_dependencies
            WHERE discount_id = $discount->id AND `type_id` = 4"
        );
        if(count($discountUserDependencies) !== 0){
            $found = false;
            foreach($discountUserDependencies as $dependency){
                if($dependency->dependency_id == $userId){
                    $found = true;
                }
            }
            if(!$found){
                $responseObject->allowed = false;
                $responseObject->discountPrice = 0;
                $responseObject->type = '';
                $responseObject->message = "discount is not for this user";
                $responseObject->umessage = "?????? ???? ?????????? ???????? ?????? ???????? ????????";
                return $responseObject;
            }
        }
        $discountPrice = 0;
        if($discount->type_id == 4){
            if($discount->price !== NULL){
                $discountPrice = $discount->price;
            }else if($discount->percent !== NULL){
                $dp = $discount->percent * $cartPrice;
                $dp = floor($dp / 100);
                $dp -= $dp % 50;
                if($dp > $discount->max_price && $discount->max_price !== NULL){
                    $dp = $discount->max_price;
                }
                $discountPrice = $dp;
            }
            if($discountPrice > $cartPrice){
                $discountPrice = $cartPrice;
            }
            $responseObject->allowed = true;
            $responseObject->discountPrice = $discountPrice;
            $responseObject->type = 'order';
            $responseObject->joinable = $discount->joinable;
            $responseObject->message = "user is allowed to use this discount";
            $responseObject->umessage = "???? ?????????? ???? ???????????? ?????? ????";
            return $responseObject;
        }else if($discount->type_id == 3){
            if($discount->price !== NULL){
                $discountPrice = $discount->price;
            }else if($discount->percent !== NULL){
                $dp = $discount->percent * $shippingPrice;
                $dp = floor($dp / 100);
                if($dp > $discount->max_price && $discount->max_price !== NULL){
                    $dp = $discount->max_price;
                }
                $discountPrice = $dp;
            }
            if($discountPrice > $shippingPrice){
                $discountPrice = $shippingPrice;
            }
            $responseObject->allowed = true;
            $responseObject->discountPrice = $discountPrice;
            $responseObject->type = 'shipping';
            $responseObject->joinable = $discount->joinable;
            $responseObject->message = "user is allowed to use this discount";
            $responseObject->umessage = "???? ?????????? ???? ???????????? ?????? ????";
            return $responseObject;
        }
    }

    /*
    public static function calculateSpecialProductsDiscount($products, $userId, $provinceId){
        $time = time();
        $discounts = DB::select("SELECT * FROM discounts D WHERE D.status = 1 AND D.type_id IN (1. 2) AND D.code IS NULL 
            AND (D.numbers_left IS NULL OR D.numbers_left > 0) AND D.start_date IS NULL AND D.finish_date IS NULL 
            AND (D.expiration_date IS NULL OR D.expiration_date >= $time) AND 
            ((D.user_start_date IS NULL AND D.user_finish_date IS NULL) OR (D.user_start_date IS NOT NULL AND D.user_finish_date IS NOT NULL AND (SELECT COUNT(O.id) FROM orders O WHERE O.user_id = $userId AND O.date >= D.user_start_date AND O.date <= D.user_finish_date) = 0))");
        if(count($discounts) !== 0){
            $rps = array();
            foreach($products as $product){
                array_push($rps, 0);
            }
            foreach($discounts as $discount){
                $discountUsers = DB::select("SELECT DD.dependency_id AS user_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 4");
                $discountProvinces = DB::select("SELECT DD.dependency_id AS province_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 3");
                $discountProducts = DB::select("SELECT DD.dependency_id AS product_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 1");
                $discountCategories =  DB::select("SELECT DD.dependency_id AS category_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 2");
                $i = 0;
                foreach($products as $product){
                    if($discount->min_price === null || $discount->min_price <= $product->productPrice){
                        $inputProductObject1 = new stdClass();
                        $inputCategoryObject1 = new stdClass();
                        $inputUserObject1 = new stdClass();
                        $inputProvinceObject1 = new stdClass();
                        $inputProductObject1->product_id = $product->productId;
                        $inputCategoryObject1->category_id = $product->categoryId;
                        $inputUserObject1->user_id = $userId;
                        $inputProvinceObject1->province_id = $provinceId;
                        if(($discount->type_id === 1 && (count($discountProducts) === 0 || in_array($inputProductObject1, $discountProducts))) ||
                            ($discount->type_id === 2 && (count($discountCategories) === 0 || in_array($inputCategoryObject1, $discountCategories)))){
                            if(count($discountUsers) === 0 || in_array($inputUserObject1, $discountUsers)){
                                $continute = true;
                                if($discount->neworder === 1){
                                    $userPreviousOrders = DB::select(
                                        "SELECT id 
                                        FROM orders 
                                        WHERE user_id = $userId"
                                    );
                                    if(count($userPreviousOrders) !== 0){
                                        $continute = false;
                                    }
                                }
                                if($discount->reusable === 0){
                                    $thisDiscountLogsForTheUser = DB::select(
                                        "SELECT id
                                        FROM discount_logs 
                                        WHERE user_id = $userId"
                                    );
                                    if(count($thisDiscountLogsForTheUser) !== 0){
                                        $continute = false;
                                    }
                                }
                                if($continute && (count($discountProvinces) === 0 || in_array($inputProvinceObject1, $discountProvinces))){
                                    $rp = 0;
                                    if($discount->price !== null){
                                        $rp += $discount->price;
                                    }
                                    if($discount->percent !== null){
                                        $rp = ($discount->percent / 100) * $product->productPrice;
                                    }
                                    if($discount->max_price !== null && $rp > $discount->max_price){
                                        $rp = $discount->max_price;
                                    }
                                    $rps[$i] += $rp;
                                }
                            }
                        }
                    }
                    $i++;
                }
            }
            $i = 0;
            foreach($products as $product){
                if($rps[$i] !== 0){
                    if($rps[$i] < $products[$i]->productPrice){
                        $products[$i]->discountedPrice = $products[$i]->productPrice - (100 * (integer)(($rps[$i]) / 100));
                        $products[$i]->discountPercent = (integer)(($rps[$i]/$products[$i]->productPrice) * 100);
                    }else{
                        $products[$i]->discountedPrice = 0;
                        $products[$i]->discountPercent = 100;
                    }
                }else{
                    $products[$i]->discountedPrice = $products[$i]->productPrice;
                    $products[$i]->discountPercent = 0;
                }
                $i++;
            }
            return $products;
        }else{
            foreach($products as $product){
                $product->discountedPrice = $product->productPrice;
                $product->discountPercent = 0;
            }
            return $products;
        }
    }
    */

    /*public static function calculateSpecialOrderAndShippingDiscount($products, $userId, $provinceId, $cityId, $totalWeight){
        $time = time();
        $orderDiscountPrice = 0;
        $shippingDiscountPrice = 0;
        $totalOrderPrice = 0;
        $totalShippingPrice = 0;
        foreach($products as $p){
            $totalOrderPrice += ($p->productCount * $p->productPrice);
        }
        $temp = DB::select(
            "SELECT * 
            FROM delivery_service_temporary_information 
            WHERE user_id = $userId AND expiration_date >= $time LIMIT 1"
        );
        if(count($temp) === 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'selected user delivery service not found', 'umessage' => '?????????? ?????? ?? ?????? ?????????????? ?????????? ???????? ??????'));
            exit();
        }
        $temp = $temp[0];
        $serviceId = $temp->service_id;
        if($serviceId === 1){
            $totalShippingPrice = 12000;
        }else if($serviceId === 2){
            $totalShippingPrice = 15000;
        }else if($serviceId === 3){
            $pricePlans = DB::select("SELECT * FROM delivery_service_plans WHERE city_id = $cityId OR province_id = $provinceId ORDER BY min_weight ASC");
            if(count($pricePlans) == 0){
                echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'could not find delivery price', 'umessage' => '?????? ?????????? ???????????? ?????????? ??????????'));
                exit();
            }
            $found = false;
            foreach($pricePlans as $pp){
                if($pp->min_weight <= $totalWeight && $totalWeight < $pp->max_weight){
                    $found = true;
                    $totalShippingPrice = $pp->price;
                    break;
                }
            }
            if($found === false){
                $lastPricePlan = DB::select("SELECT * FROM delivery_service_plans WHERE city_id = $cityId OR province_id = $provinceId ORDER BY max_weight DESC LIMIT 1");
                if(count($lastPricePlan) == 0){
                    echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'could nto find delivery price', 'umessage' => '?????? ?????????? ???????????? ?????????? ??????????'));
                    exit();
                }
                $lastPricePlan = $lastPricePlan[0];
                $price = $lastPricePlan->price;
                for($w1 = $lastPricePlan->max_weight, $w2 = $lastPricePlan->max_weight + 1000; $w1 < $w2 ;$w1 += 1000, $w2 += 1000){
                    $price += 2500;
                    if($w1 <= $totalWeight && $totalWeight < $w2){
                        $totalShippingPrice= $price;
                        break;
                    }
                }
            }
        }
        $discounts = DB::select("SELECT * FROM discounts D WHERE D.status = 1 AND D.type_id IN (3, 4) AND D.code IS NULL 
            AND (D.numbers_left IS NULL OR D.numbers_left > 0) AND D.start_date IS NULL AND D.finish_date IS NULL 
            AND (D.expiration_date IS NULL OR D.expiration_date >= $time) AND 
            ((D.user_start_date IS NULL AND D.user_finish_date IS NULL) OR (D.user_start_date IS NOT NULL AND D.user_finish_date IS NOT NULL AND (SELECT COUNT(O.id) FROM orders O WHERE O.user_id = $userId AND O.date >= D.user_start_date AND O.date <= D.user_finish_date) = 0))");
        if(count($discounts) !== 0){
            $rps = array();
            foreach($products as $product){
                array_push($rps, 0);
            }
            foreach($discounts as $discount){
                $discountUsers = DB::select("SELECT DD.dependency_id AS user_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 4");
                $discountProvinces = DB::select("SELECT DD.dependency_id AS province_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 3");
                $discountProducts = DB::select("SELECT DD.dependency_id AS product_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 1");
                $discountCategories =  DB::select("SELECT DD.dependency_id AS category_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 2");
                $i = 0;
                if($discount->type_id == 4){
                    if($discount->min_price === NULL || $discount->min_price <= $totalOrderPrice){
                        foreach($products as $product){
                            $inputProductObject1 = new stdClass();
                            $inputCategoryObject1 = new stdClass();
                            $inputUserObject1 = new stdClass();
                            $inputProvinceObject1 = new stdClass(); 
                            $inputProductObject1->product_id = $product->productId;
                            $inputCategoryObject1->category_id = $product->categoryId;
                            $inputUserObject1->user_id = $userId;
                            $inputProvinceObject1->province_id = $provinceId;
                            if((count($discountProducts) === 0 || in_array($inputProductObject1, $discountProducts))
                                && (count($discountCategories) === 0 || in_array($inputCategoryObject1, $discountCategories))
                                && (count($discountProvinces) === 0 || in_array($inputProvinceObject1, $discountProvinces))
                                && (count($discountUsers) === 0 || in_array($inputUserObject1, $discountUsers))){
                                $continute = true;
                                if($discount->neworder === 1){
                                    $userPreviousOrders = DB::select(
                                        "SELECT id 
                                        FROM orders 
                                        WHERE user_id = $userId"
                                    );
                                    if(count($userPreviousOrders) !== 0){
                                        $continute = false;
                                    }
                                }
                                if($discount->reusable === 0){
                                    $thisDiscountLogsForTheUser = DB::select(
                                        "SELECT id 
                                        FROM discount_logs 
                                        WHERE user_id = $userId "
                                    );
                                    if(count($thisDiscountLogsForTheUser) !== 0){
                                        $continute = false;
                                    }
                                }
                                if($continute){
                                    $rp = 0;
                                    if($discount->price !== null){
                                        $rp += $discount->price;
                                    }
                                    if($discount->percent !== null){
                                        $rp = ($discount->percent / 100) * $totalOrderPrice;
                                    }
                                    if($discount->max_price !== null && $rp > $discount->max_price){
                                        $rp = $discount->max_price;
                                    }
                                    $orderDiscountPrice += $rp;
                                }
                            }
                        }
                    }
                    
                }else if($discount->type_id == 3){
                    if($discount->min_price === NULL || $discount->min_price <= $totalShippingPrice){
                        foreach($products as $product){
                            $inputProductObject1 = new stdClass();
                            $inputCategoryObject1 = new stdClass();
                            $inputUserObject1 = new stdClass();
                            $inputProvinceObject1 = new stdClass(); 
                            $inputProductObject1->product_id = $product->productId;
                            $inputCategoryObject1->category_id = $product->categoryId;
                            $inputUserObject1->user_id = $userId;
                            $inputProvinceObject1->province_id = $provinceId;
                            if((count($discountProducts) === 0 || in_array($inputProductObject1, $discountProducts))
                                && (count($discountCategories) === 0 || in_array($inputCategoryObject1, $discountCategories))
                                && (count($discountProvinces) === 0 || in_array($inputProvinceObject1, $discountProvinces))
                                && (count($discountUsers) === 0 || in_array($inputUserObject1, $discountUsers))){
                                $continute = true;
                                if($discount->neworder === 1){
                                    $userPreviousOrders = DB::select(
                                        "SELECT id 
                                        FROM orders 
                                        WHERE user_id = $userId"
                                    );
                                    if(count($userPreviousOrders) !== 0){
                                        $continute = false;
                                    }
                                }
                                if($discount->reusable === 0){
                                    $thisDiscountLogsForTheUser = DB::select(
                                        "SELECT id
                                        FROM discount_logs 
                                        WHERE user_id = $userId"
                                    );
                                    if(count($thisDiscountLogsForTheUser) !== 0){
                                        $continute = false;
                                    }
                                }
                                if($continute){
                                    $rp = 0;
                                    if($discount->price !== null){
                                        $rp += $discount->price;
                                    }
                                    if($discount->percent !== null){
                                        $rp = ($discount->percent / 100) * $totalShippingPrice;
                                    }
                                    if($discount->max_price !== null && $rp > $discount->max_price){
                                        $rp = $discount->max_price;
                                    }
                                    $shippingDiscountPrice += $rp;
                                }
                            }
                        }
                    }
                }
            }
            $orderDiscountPrice -= $orderDiscountPrice % 50;
            $shippingDiscountPrice -= $shippingDiscountPrice % 50;
            if($shippingDiscountPrice > $totalShippingPrice){
                $shippingDiscountPrice = $totalShippingPrice;
            }
            if($orderDiscountPrice > $totalOrderPrice){
                $orderDiscountPrice = $totalOrderPrice;
            }
            $result = new stdClass();
            $result->orderPrice = $totalOrderPrice;
            $result->orderDiscount = $orderDiscountPrice;
            $result->shippingPrice = $totalShippingPrice;
            $result->shippingDiscount = $shippingDiscountPrice;
            return $result;
        }else{
            foreach($products as $product){
                $product->discountedPrice = $product->productPrice;
                $product->discountPercent = 0;
            }
            return $products;
        }
    }*/

    public static function totalDiscount($products, $user, $provinceId, $cityId){
        $time = time();
        $orderDiscountPrice = 0;
        $shippingDiscountPrice = 0;
        $totalOrderPrice = 0;
        $totalShippingPrice = 0;
        $totalWeight = 0;
        $discountedProducts = [];
        $discountIds = [];
        foreach($products as $p){
            array_push($discountedProducts, 0);
            $totalOrderPrice += ($p->productCount * $p->productPrice);
            $totalWeight += $p->productWeight;
        }
        $temp = DB::select(
            "SELECT * 
            FROM delivery_service_temporary_information 
            WHERE user_id = $user->id AND expiration_date >= $time LIMIT 1"
        );
        if(count($temp) === 0){
            echo json_encode(array('status' => 'failed', 'source' => 'c', 'message' => 'selected user delivery service not found', 'umessage' => '?????????? ?????? ?? ?????? ?????????????? ?????????? ???????? ??????'));
            return NULL;
        }
        $temp = $temp[0];
        $serviceId = $temp->service_id;
        $totalShippingPrice = DeliveryServiceController::calculateDeliveryPrice($serviceId, $provinceId, $cityId, $totalWeight, 12, 12);
        //$totalShippingPrice = ShippingCalculator::shippingPriceCalculator($user, $totalWeight, $serviceId);
        
        $discounts = DB::select("SELECT * FROM discounts D WHERE D.status = 1 AND D.code IS NULL 
            AND (D.numbers_left IS NULL OR D.numbers_left > 0) AND (D.start_date IS NULL OR D.start_date <= $time) AND (D.finish_date IS NULL OR D.finish_date >= $time)  
            AND (D.expiration_date IS NULL OR D.expiration_date >= $time) AND 
            ((D.user_start_date IS NULL AND D.user_finish_date IS NULL) OR (D.user_start_date IS NOT NULL AND D.user_finish_date IS NOT NULL AND (SELECT COUNT(O.id) FROM orders O WHERE O.user_id = $user->id AND O.date >= D.user_start_date AND O.date <= D.user_finish_date) = 0))");
        if(count($discounts) !== 0){
            $rps = array();
            foreach($products as $product){
                array_push($rps, 0);
            }
            foreach($discounts as $discount){
                $discountUsers = DB::select("SELECT DD.dependency_id AS user_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 4");
                $discountProvinces = DB::select("SELECT DD.dependency_id AS province_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 3");
                $discountProducts = DB::select("SELECT DD.dependency_id AS product_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 1");
                $discountCategories =  DB::select("SELECT DD.dependency_id AS category_id FROM discount_dependencies DD WHERE DD.discount_id = $discount->id AND DD.type_id = 2");
                $inputProductObject1 = new stdClass();
                $inputCategoryObject1 = new stdClass();
                $inputUserObject1 = new stdClass();
                $inputProvinceObject1 = new stdClass();
                $inputUserObject1->user_id = $user->id;
                $inputProvinceObject1->province_id = $provinceId;
                $allow = false;
                if((count($discountUsers) === 0 || in_array($inputUserObject1, $discountUsers)) 
                    && (count($discountProvinces) === 0 || in_array($inputProvinceObject1, $discountProvinces))){
                        $allow = true;
                    }
                if($allow && $discount->neworder !== 0){
                    $userPreviousOrders = DB::select(
                        "SELECT id FROM orders WHERE user_id = $user->id LIMIT 1"
                    );
                    if(count($userPreviousOrders) !== 0){
                        $allow = false;
                    }
                }
                if($allow && $discount->reusable === 0){
                    $discountUsageLogsForUser = DB::select(
                        "SELECT id FROM discount_logs WHERE user_id = $user->id AND discount_id = $discount->id LIMIT 1"
                    );
                    if(count($discountUsageLogsForUser) !== 0){
                        $allow = false;
                    }
                }
                if(!$allow){
                    continue;
                }
                if($discount->type_id === 1){
                    if(count($discountProducts) !== 0){
                        $i = 0;
                        foreach($products as $product){
                            $inputProductObject1->product_id = $product->productId;
                            if(in_array($inputProductObject1, $discountProducts)){
                                if($discount->min_price === NULL || ($discount->min_price !== NULL && $discount->min_price <= $product->productPrice)){
                                    $added = false;
                                    if($discount->price !== NULL){
                                        $discountedProducts[$i] += $discount->price;
                                        $added = true;
                                    }else if($discount->percent !== NULL){
                                        $r = ($discount->percent / 100) * $product->productPrice;
                                        if($discount->max_price !== NULL && $r > $discount->max_price){
                                            $r = $discount->max_price;
                                        }
                                        $discountedProducts[$i] += $r;
                                        $added = true;
                                    }
                                    if($added && !in_array($discount->id, $discountIds)){
                                        array_push($discountIds, $discount->id);
                                    }   
                                }
                            }
                            $i++;
                        }
                    }else{
                        for($i=0; $i<sizeof($discountProducts); $i++){
                            if($discount->min_price === NULL || ($discount->min_price !== NULL && $discount->min_price <= $products[$i]->productPrice)){
                                $added = false;
                                if($discount->price !== NULL){
                                    $discountedProducts[$i] += $discount->price;
                                    $added = true;
                                }else if($discount->percent !== NULL){
                                    $r = ($discount->percent / 100) * $products[$i]->productPrice;
                                    if($discount->max_price !== NULL && $r > $discount->max_price){
                                        $r = $discount->max_price;
                                    }
                                    $discountedProducts[$i] += $r;
                                    $added = true;
                                }
                                if($added && !in_array($discount->id, $discountIds)){
                                    array_push($discountIds, $discount->id);
                                }
                            }
                        }
                    }
                }else if($discount->type_id === 2){
                    if(count($discountCategories) !== 0){
                        $i = 0;
                        foreach($products as $product){
                            $inputCategoryObject1->category_id = $product->categoryId;
                            if(in_array($inputCategoryObject1, $discountCategories)){
                                if($discount->min_price === NULL || ($discount->min_price !== NULL && $discount->min_price <= $product->productPrice)){
                                    $added = false;
                                    if($discount->price !== NULL){
                                        $discountedProducts[$i] += $discount->price;
                                        $added = true;
                                    }else if($discount->percent !== NULL){
                                        $r = ($discount->percent / 100) * $product->productPrice;
                                        if($discount->max_price !== NULL && $r > $discount->max_price){
                                            $r = $discount->max_price;
                                        }
                                        $discountedProducts[$i] += $r;
                                        $added = true;
                                    }  
                                    if($added && !in_array($discount->id, $discountIds)){
                                        array_push($discountIds, $discount->id);
                                    } 
                                }
                            }
                            $i++;
                        }
                    }else{
                        for($i=0; $i<sizeof($discountProducts); $i++){
                            if($discount->min_price === NULL || ($discount->min_price !== NULL && $discount->min_price <= $products[$i]->productPrice)){
                                $added = false;
                                if($discount->price !== NULL){
                                    $discountedProducts[$i] += $discount->price;
                                    $added = true;
                                }else if($discount->percent !== NULL){
                                    $r = ($discount->percent / 100) * $products[$i]->productPrice;
                                    if($discount->max_price !== NULL && $r > $discount->max_price){
                                        $r = $discount->max_price;
                                    }
                                    $discountedProducts[$i] += $r;
                                    $added = true;
                                }
                                if($added && !in_array($discount->id, $discountIds)){
                                    array_push($discountIds, $discount->id);
                                }
                            }
                        }
                    }
                }else if($discount->type_id === 4){
                    $productPermission = true;
                    $categoryPermission = true;
                    if(count($discountProducts) !== 0){
                        $found = false;
                        foreach($products as $product){
                            $inputProductObject1->product_id = $product->productId;
                            if(in_array($inputProductObject1, $discountProducts)){
                                $found = true;
                                break;
                            }
                        }
                        $productPermission = $found;
                    }
                    if(count($discountCategories) !== 0){
                        $found = false;
                        foreach($products as $product){
                            $inputCategoryObject1->category_id = $product->categoryId;
                            if(in_array($inputCategoryObject1, $discountCategories)){
                                $found = true;
                                break;
                            }
                        }
                        $categoryPermission = $found;
                    }
                    if($productPermission && $categoryPermission){
                        if($discount->min_price === NULL | ($discount->min_price !== NULL && $discount->min_price <= $totalOrderPrice)){
                            $added = false;
                            if($discount->price !== NULL){
                                $orderDiscountPrice += $discount->price;
                                $added = true;
                            }else if($discount->percent !== NULL){
                                $r = ($discount->percent / 100) * $totalOrderPrice;
                                if($discount->max_price !== NULL && $discount->max_price < $r){
                                    $r = $discount->max_price;
                                }
                                $orderDiscountPrice += $r;
                                $added = true;
                            }
                            if($added && !in_array($discount->id, $discountIds)){
                                array_push($discountIds, $discount->id);   
                            }
                        }
                    }
                }else if($discount->type_id === 5){
                    if(count($discountProducts) !== 0){
                        $i = 0;
                        foreach($products as $product){
                            $inputProductObject1->product_id = $product->productId;
                            if(in_array($inputProductObject1, $discountProducts)){
                                $discountDependencyInformation = DB::select(
                                    "SELECT * FROM discount_dependencies WHERE discount_id = $discount->id AND dependency_id = $product->productId ORDER BY id DESC LIMIT 1"
                                );
                                if(count($discountDependencyInformation) !== 0){
                                    $discountDependencyInformation = $discountDependencyInformation[0];
                                    if($discountDependencyInformation->final_stock === 0){
                                        $added = false; 
                                        if($discountDependencyInformation->price !== 0 && $discountDependencyInformation->price <= $product->productPrice ){
                                            $discountedProducts[$i] += $discountDependencyInformation->price;
                                            $added = true;
                                        }else if($discountDependencyInformation->percent !== 0 && $discountDependencyInformation->percent <= 100){
                                            $discountedProducts[$i] += ($discountDependencyInformation->percent / 100) * $product->productPrice;
                                            $added = true;
                                        }
                                        if($added){
                                            array_push($discountIds, $discount->id);
                                        }
                                    }else{
                                        $productStock = DB::select("SELECT pack_stock AS stock FROM products_location WHERE product_id = $product->productId ORDER BY id DESC LIMIT 1 ");
                                        if(count($productStock) !== 0){
                                            $productStock = $productStock[0];
                                            if($discountDependencyInformation->final_stock < $productStock->stock){
                                                $added = false; 
                                                if($discountDependencyInformation->price !== 0 && $discountDependencyInformation->price <= $product->productPrice ){
                                                    $discountedProducts[$i] += $discountDependencyInformation->price;
                                                    $added = true;
                                                }else if($discountDependencyInformation->percent !== 0 && $discountDependencyInformation->percent <= 100){
                                                    $discountedProducts[$i] += ($discountDependencyInformation->percent / 100) * $product->productPrice;
                                                    $added = true;
                                                }
                                                if($added && !in_array($discount->id, $discountIds)){
                                                    array_push($discountIds, $discount->id);
                                                }
                                            }
                                        }
                                    }
                                }
                            }   
                            $i++;                         
                        }
                    }
                }else if($discount->type_id === 3){
                    $productPermission = true;
                    $categoryPermission = true;
                    if(count($discountProducts) !== 0){
                        $found = false;
                        foreach($products as $product){
                            $inputProductObject1->product_id = $product->productId;
                            if(in_array($inputProductObject1, $discountProducts)){
                                $found = true;
                                break;
                            }
                        }
                        $productPermission = $found;
                    }
                    if(count($discountCategories) !== 0){
                        $found = false;
                        foreach($products as $product){
                            $inputCategoryObject1->category_id = $product->categoryId;
                            if(in_array($inputCategoryObject1, $discountCategories)){
                                $found = true;
                                break;
                            }
                        }
                        $categoryPermission = $found;
                    }
                    if($productPermission && $categoryPermission){
                        if($discount->min_price === NULL | ($discount->min_price !== NULL && $discount->min_price <= $totalOrderPrice)){
                            $added = false;
                            if($discount->price !== NULL){
                                $shippingDiscountPrice += $discount->price;
                                $added = true;
                            }else if($discount->percent !== NULL){
                                $r = ($discount->percent / 100) * $totalShippingPrice;
                                if($discount->max_price !== NULL && $discount->max_price < $r){
                                    $r = $discount->max_price;
                                }
                                $shippingDiscountPrice += $r;
                                $added = true;
                            }
                            if($added && !in_array($discount->id, $discountIds)){
                                array_push($discountIds, $discount->id);
                            }
                        }
                    }
                } 
            }
            for($i=0; $i < sizeof($discountedProducts); $i++){
                $dp = $discountedProducts[$i] - $discountedProducts[$i] % 50;
                //$dp = ($discountedProducts[$i] * $products[$i]->productCount) - (($discountedProducts[$i] * $products[$i]->productCount) % 50);
		if($dp >= $products[$i]->productPrice){
                    $products[$i]->discountedPrice = 0;
                    $products[$i]->discountPercent = 100;
                    $dp = $products[$i]->productPrice;
                    //$dp = $products[$i]->productPrice * $products[$i] * $products[$i]->productCount;
		}else{
                    $products[$i]->discountedPrice = $products[$i]->productPrice - $dp;
                    $products[$i]->discountPercent = ceil(($dp / $products[$i]->productPrice) * 100);
                }
                $orderDiscountPrice += $dp * $products[$i]->productCount;
		//here is where I have to write down something                
            }
            if($orderDiscountPrice < $totalOrderPrice){
                $orderDiscountedPrice = $totalOrderPrice - $orderDiscountPrice;
            }else{
                $orderDiscountedPrice = 0;
            }
            if($shippingDiscountPrice < $totalShippingPrice){
                $shippingDiscountedPrice = $totalShippingPrice - $shippingDiscountPrice;
            }else{
                $shippingDiscountedPrice = 0;
            }
            $responseObject = new stdClass();
            $responseObject->cart = $products;
            $responseObject->orderPrice = $totalOrderPrice;
            $responseObject->shippingPrice = $totalShippingPrice;
            $responseObject->orderDiscountedPrice = $orderDiscountedPrice;
            $responseObject->shippingDiscountedPrice = $shippingDiscountedPrice;
            $responseObject->discountIds = $discountIds;
            return $responseObject;
        }else{
            foreach($products as $product){
                $product->discountedPrice = $product->productPrice;
                $product->discountPercent = 0;
            }
            $responseObject = new stdClass();
            $responseObject->cart = $products;
            $responseObject->orderPrice = $totalOrderPrice;
            $responseObject->shippingPrice = $totalShippingPrice;
            $responseObject->orderDiscountedPrice = $totalOrderPrice;
            $responseObject->shippingDiscountedPrice = $totalShippingPrice;
            $responseObject->discountIds = $discountIds;
            return $responseObject;
        }
    }

    public static function validateGiftCode($products, $user, $provinceId, $code){
        $time = time();
        $discount = DB::select(
            "SELECT * 
            FROM discounts 
            WHERE code = '$code' AND 
                status = 1 AND 
                (expiration_date IS NULL OR expiration_date >= $time) AND
                ((start_date IS NULL AND finish_date IS NULL) OR (start_date <= $time AND finish_date >= $time)) AND 
                (numbers_left IS NULL OR numbers_left > 0) AND 
                (`type_id` = 4 OR `type_id` = 3) 
            ORDER BY id DESC 
            LIMIT 1"
        );
        if(count($discount) === 0){
            return NULL;
        }
        $discount = $discount[0];
        $allow = true;
        if($discount->reusable === 0){
            $userPrevisouUsagesOfThisDiscount = DB::select(
              "SELECT *
              FROM discount_logs 
              WHERE user_id = $user->id AND discount_id = $discount->id"
            );
            if(count($userPrevisouUsagesOfThisDiscount) !== 0){
                return NULL;
            }
        }
        if($discount->neworder === 1){
            $userPreviousOrders = DB::select(
                "SELECT id 
                FROM orders
                WHERE user_id = $user->id
                LIMIT 1"
            );
            if(count($userPreviousOrders) !== 0){
                return NULL;
            }
        }
        $discountProvinceDependencies = DB::select(
            "SELECT dependency_id 
            FROM discount_dependencies 
            WHERE discount_id = $discount->id AND `type_id` = 3"
        );
        $discountUserDependencies = DB::select(
            "SELECT dependency_id 
            FROM discount_dependencies 
            WHERE discount_id = $discount->id AND `type_id` = 4"
        );
        $discountProductDependencies = DB::select(
            "SELECT dependency_id 
            FROM discount_dependencies 
            WHERE discount_id = $discount->id AND `type_id` = 1"
        );
        $discountCategoryDependencies = DB::select(
            "SELECT dependency_id 
            FROM discount_dependencies 
            WHERE discount_id = $discount->id AND `type_id` = 2"
        );

        if(count($discountProvinceDependencies) !== 0){
            $obj = new stdClass();
            $obj->dependency_id = $provinceId;
            if(!in_array($obj, $discountProvinceDependencies)){
                return NULL;
            }
        }
        if(count($discountUserDependencies) !== 0){
            $obj = new stdClass();
            $obj->dependency_id = $user->id;
            if(!in_array($obj, $discountUserDependencies)){

                return NULL;
            }
        }
        if(count($discountProductDependencies) !== 0){
            $found = false;
            foreach($products as $product){
                $obj = new stdClass();
                $obj->dependency_id = $product->productId;
                if(in_array($obj, $discountProductDependencies)){
                    $found = true;
                    break;
                }
            }
            if(!$found){
                return NULL;
            }
        }
        if(count($discountCategoryDependencies) !== 0){
            $found = false;
            foreach($products as $product){
                $obj = new stdClass();
                $obj->dependency_id = $product->categoryId;
                if(in_array($obj, $discountCategoryDependencies)){
                    $found = true;
                    break;
                }
            }
            if(!$found){
                return NULL;
            }
        }
        $response = new stdClass();
        $type = '';
        if($discount->type_id === 1){
            $type = 'product';
        }else if($discount->type_id === 2){
            $type = 'category';
        }else if($discount->type_id === 3){
            $type = 'shipping';
        }else if($discount->type_id === 4){
            $type = 'order';
        }else if($discount->type_id === 5){
            $type = 'multi-product';
        }
        $response->discountId = $discount->id;
        $response->discountType = $type;
        $response->discountPrice = $discount->price;
        $response->discountPercent = $discount->percent;
        $response->discountMaxPrice = $discount->max_price;
        return $response;
    }

    public static function topSixProductsDiscountCalculator($productArray){
        $time = time();
        foreach($productArray as $product){
            $discount = DB::select(
                "SELECT * FROM discounts WHERE id = $product->discountId LIMIT 1"
            );
            if(count($discount) === 0){
                continue;
            }
            $discount = $discount[0];
            $discountPrice = 0;
            if($discount->price !== NULL){
                $discountPrice += $discount->price;
            }else if($discount->percent !== NULL){
                $dp = $discount->percent * $product->productPrice / 100;
                if($discount->max_price !== NULL && $dp > $discount->max_price){
                    $dp = $discount->max_price;
                }
                $discountPrice = $dp - $dp % 50;
            }
            $product->productDiscountedPrice = $product->productPrice - $discountPrice;
            $product->productDiscountPercent = ceil($discountPrice * 100 / $product->productPrice);
            $product->timeLeft = 0;
            if($discount->expiration_date !== NULL){
                $product->timeLeft = $discount->expiration_date - $time;
            }else if($discount->finish_date !== NULL){
                $product->timeLeft = $discount->finish_date = $time;
            }
        }
        return $productArray;
    }
}
