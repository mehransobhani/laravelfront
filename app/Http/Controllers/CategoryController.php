<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Classes\DiscountCalculator;
use Illuminate\Support\Facades\Validator;
use stdClass;

class CategoryController extends Controller
{
    public function subCategories(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric',
        ]);
        if($validator->fails()){
            echo json_encode(array('status' => 'failed', 'source' => 'v', 'message' => 'argument validation failed', 'umessage' => 'مقادیر ورودی صحیح نیست'));
            exit();
        }

        if($request->id){
            $categories = Category::where('parentID', $request->id)->where('hide', 0);
            if($categories->count() !== 0){
                $categories = $categories->get();
                $subCategories = [];
                foreach($categories as $category){
                    array_push($subCategories, array('name' => $category->name, 'url' => $category->url, 'image' => $category->info->ads_image));
                }
                echo json_encode(array('status' => 'done', 'found' => true, 'message' => 'subcategories successfully were found', 'subcategories' => $subCategories));
            }else{
                echo json_encode(array('status' => 'done', 'found' => false, 'message' => 'this category does not have any category'));
            }
        }else{
            echo json_encode(array('status' => 'failed', 'message' => 'not enough parameter'));
        }
    }

    public function rootCategorySixNewProducts(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric',
        ]);
        if($validator->fails()){
            echo json_encode(array('status' => 'failed', 'source' => 'v', 'message' => 'argument validation failed', 'umessage' => 'مقادیر ورودی صحیح نیست'));
            exit();
        }

        if($request->id){
            $category = Category::where('id', $request->id);
            if($category->count() !== 0){
                $category = $category->first();
                $products = Product::where('url', 'LIKE', 'shop/product/category/' . $category->url . '%')->where(function($query){
                    return $query->where('prodStatus', 1)->orWhere('prodStatus', 2);
                })->where('stock', '>', 0)->orderBy('prodDate', 'DESC');
                if($products->count() !== 0){
                    $products = $products->get();
                    $response = [];
                    $itemsSelected = 0;
                    foreach($products as $product){
                        if($itemsSelected < 6){
                            $productCategory = $product->productCategory;
                            $category = Category::where('id', $productCategory->category)->first();
                            if($product->pack->status === 1 && $product->pack->stock > 0 && (($product->pack->count * $product->pack->stock) <= $product->stock)){
                                array_push($response, array('name' => $product->prodName_fa, 'category' => $category->name, 'url' => $product->url, 'prodID' => $product->prodID, 'price' => $product->pack->price));
                                $itemsSelected++;
                            }
                        }else{
                            break;
                        }
                    }
                    echo json_encode(array('status' => 'done', 'found' => true, 'message' => 'new products of the category successfully were found', 'products' => $response));
                }else{
                    echo json_encode(array('status' => 'done', 'found' => false, 'message' => 'this category does not have any product'));
                }
            }else{
                echo json_encode(array('status' => 'failed', 'message' => 'category not found'));
            }
        }else{
            echo json_encode(array('status' => 'failed', 'message' => 'not enough parameter'));
        }
    }

    public function getSubCategories($categoryId){
        $response = $categoryId . '';
        $scs = DB::select("SELECT * FROM category WHERE parentID = $categoryId ");
        foreach($scs as $category){
            $response = $response . ', ' . $this->getSubCategories($category->id);
        }
        return $response;
    }

    public function filterPaginatedCategoryProducts (Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric', 
            'order' => 'required|string'
        ]);
        if($validator->fails()){
            echo json_encode(array('status' => 'failed', 'source' => 'v', 'message' => 'argument validation failed', 'umessage' => 'مقادیر ورودی صحیح نیست'));
            exit();
        }

        if(isset($request->id) && isset($request->order)){
            $category = Category::where('id', $request->id);
            if($category->count() !== 0){
		//### START OF NEW PART
		/*
		$category = $category->first();
                $order = $request->order;
                $onlyAvailableProducts = $request->onlyAvailableProducts;
		        $onlyDiscounts = $request->onlyDiscounts;
                $name = '';
                $minPrice = '';
                $maxPrice = '';
		        $discount = '';
                $time = time();
                if($onlyDiscounts == 1){
                    $discount = " AND PL.product_id IN (SELECT DD.dependency_id 
                    FROM discounts D INNER JOIN discount_dependencies DD ON DD.discount_id = D.id 
                    WHERE D.type_id = 5 AND D.status = 1 AND (D.start_date IS NULL OR D.start_date <= $time) AND (D.finish_date IS NULL OR D.finish_date >= $time) AND (D.expiration_date IS NULL OR D.expiration_date >= $time) AND DD.type_id = 1 AND (DD.final_stock = 0 OR DD.final_stock < PL.pack_stock)
                    ) ";
                }
                if($request->minPrice != 0 && $request->minPrice != ''){
                    $minPrice = " AND PP.price >= $request->minPrice ";
                }
                if($request->maxPrice != 0 && $request->maxPrice != ''){
                    $maxPrice = " AND PP.price <= $request->maxPrice ";
                }
                if($request->searchInput != ''){
                    $name = " AND P.prodName_fa LIKE '%" . $request->searchInput . "%'";
                }
                
                $order = " ORDER BY P.prodDate DESC";
		        $finishedOrder = $order;
                if($request->order == 'old'){
                    $order = " ORDER BY P.prodDate ASC";
                }else if($request->order == 'cheap'){
                    $order = " ORDER BY PP.price ASC, P.prodDate DESC";
		        $finishedOrder = " ORDER BY P.prodDate DESC ";
                }else if($request->order == 'expensive'){
                    $order = " ORDER BY PP.price DESC, P.prodDate DESC";
		        $finishedOrder = " ORDER BY P.prodDate DESC ";
                }
                
                $response = [];

		        $categoryUrl = $category->url . '%';


		        $subCategories = $this->getSubCategories($category->id); 


                $havingProductIds = DB::select("SELECT DISTINCT PC.product_id FROM product_category PC INNER JOIN products_location PL INNER JOIN products P ON PC.product_id = P.id INNER JOIN product_pack PP ON PL.pack_id = PP.id WHERE PC.category IN ($subCategories) AND PC.kind = 'primary' AND PL.stock > 0 AND PL.pack_stock > 0 AND PL.anbar_id = 1 AND PP.status = 1 AND P.prodStatus = 1 $discount");
                $finishedProductIds = [];

                if($onlyDiscounts != 1 && $onlyAvailableProducts != 1){
                    $finishedProductIds = DB::select("SELECT DISTINCT PC.product_id FROM product_category PC INNER JOIN products_location PL INNER JOIN products P ON PC.product_id = P.id INNER JOIN product_pack PP ON PL.pack_id = PP.id WHERE PC.category IN ($subCategories) AND PC.kind = 'primary' AND PL.anbar_id = 1 AND PP.status = 1 AND P.prodStatus = 1 ");
                }

                $hpis = [];
                $fpis = [];

                foreach($havingProductIds as $hpi){
                    array_push($hpis, $hpi->product_id);
                }

                foreach($finishedProductIds as $fpi){  
                    if(array_search($fpi->product_id, $fpis) == false){
                        array_push($fpis, $fpi->product_id); 
                    }
                }

                $filters = $request->filters;
                $distinctFiltersCount = 0;
                $distinctFiltersArray = [];
                foreach($filters as $filter){
                    $found = false;
                    foreach($distinctFiltersArray as $dfa){
                        if($dfa['en_name'] == $filter['en_name']){
                            $found = true;
                            break;
                        }
                    }
                    if($found === false){
                        array_push($distinctFiltersArray, $filter);
                    }
                }
                $distinctFiltersCount = count($distinctFiltersArray);

                $fhpis = [];
                $ffpis = [];
                $totalCount = 0;

                foreach($hpis as $hpi){
                    if(count($filters) !== 0){
                        $i = 0;
                        $productMetas = DB::select("SELECT * FROM products_meta WHERE product_id = $hpi");
                        if(count($productMetas) !== 0){
                            foreach($filters as $filter){
                                foreach($productMetas as $pm){
                                    if($pm->key === '__' . $filter['en_name'] && $pm->value == $filter['value']){
                                        $i++;
                                    }
                                }
                            }
                        }
                        if($i === $distinctFiltersCount){
                            array_push($fhpis, $hpi);
                            $totalCount++;
                        }
                    }else{
                        array_push($fhpis, $hpi);
                        $totalCount++;
                    }
                }

                foreach($fpis as $fpi){
                    if(count($filters) !== 0){
                        $i = 0;
                        $productMetas = DB::select("SELECT * FROM products_meta WHERE product_id = $fpi");
                        if(count($productMetas) !== 0){
                            foreach($filters as $filter){
                                foreach($productMetas as $pm){
                                    if($pm->key === '__' . $filter['en_name'] && $pm->value == $filter['value']){
                                        $i++;
                                    }
                                }
                            }
                        }
                        if($i === $distinctFiltersCount){
                            array_push($ffpis, $fpi);
                            $totalCount++;
                        }
                    }else{
                        array_push($ffpis, $fpi);
                        $totalCount++;
                    }
                }


                $havingProductIdsCondition = ' P.id <> 0 ';
                $finishedProductIdsCondition = ' P.id <> 0 ';
                if(count($fhpis) != 0){
                    $havingProductIdsCondition = 'P.id IN (' .  implode(", ", $fhpis) . ') ';
                }
                if(count($ffpis) != 0){
                    $finishedProductIdsCondition = 'P.id IN (' .  implode(", ", $ffpis) . ') ';
                }

                $queryHaving = "SELECT P.id, PP.id AS packId, 
                        P.prodName_fa, P.prodID, P.url, 
                        P.prodStatus, P.prodUnite, P.stock AS productStock, 
                        PP.stock AS packStock, PP.status, PP.price, 
                        PP.base_price, PP.label, PP.count, PPC.category 
                    FROM products P 
                    INNER JOIN products_location PL ON P.id = PL.product_id 
                    INNER JOIN product_pack PP ON PL.pack_id = PP.id 
                    INNER JOIN product_category PPC ON PPC.product_id = P.id 
                    WHERE PPC.category IN ($subCategories) AND  $havingProductIdsCondition 
                        AND PP.status = 1 AND PPC.kind = 'primary'"  . $name . $minPrice . $maxPrice . $order . " " ;
                
                $queryFinished = "SELECT P.id, 0 AS packId, P.prodName_fa, 
                        P.prodID, P.url, P.prodStatus, P.prodUnite, 0 AS productStock, 
                        0 AS packStock, 0 AS `status`, -1 AS price, 0 AS base_price, '' AS label, 
                        0 AS `count`, PPC.category 
                    FROM products P 
                        INNER JOIN product_category PPC ON PPC.product_id = P.id 
                        INNER JOIN products_location PL ON P.id = PL.product_id 
                    WHERE PPC.category IN ($subCategories) AND $finishedProductIdsCondition 
                        AND PPC.kind = 'primary' " . $name . $minPrice . $maxPrice . $finishedOrder . " " ;

                $allResponses = [];

                $havingProducts = DB::select($queryHaving);
                $finishedProducts = [];
                if($onlyDiscounts != 1){
                    if($onlyAvailableProducts == 0){
                        $finishedProducts = DB::select($queryFinished);
                    }
                }

                $products = $havingProducts;
                foreach($finishedProducts as $finishedProduct){
                    array_push($products, $finishedProduct);
                }

                if(count($products) != 0){
                    $i=0;
                    for($i=($request->page - 1)*24; $i < count($products) && $i < (($request->page - 1)*24) + 24; $i++){
                        $productObject = new stdClass();
                        $productObject->productId = $products[$i]->id;
                        $productObject->productPackId = $products[$i]->packId;
                        $productObject->productName = $products[$i]->prodName_fa;
                        $productObject->prodID = $products[$i]->prodID;
                        $productObject->categoryId = $products[$i]->category;
                        $productObject->productPrice = $products[$i]->price;
                        $productObject->productUrl = $products[$i]->url;
                        $productObject->productBasePrice = $products[$i]->base_price;
                        $productObject->productUnitCount = $products[$i]->count;
                        $productObject->productUnitName = $products[$i]->prodUnite;
                        $productObject->productLabel = $products[$i]->label;
                        array_push($allResponses, $productObject);
                    }
                    //$r = array_slice($allResponses, ($request->page - 1)*24, 24);
                    $allResponses = DiscountCalculator::calculateProductsDiscount($allResponses);
                    echo json_encode(array('status' => 'done', 'found' => true, 'categoryName' => $category->name, 'count' => count($products), 'products' => $allResponses, 'message' => 'products are successfully found')); 
                    exit();
                }else{
                    echo json_encode(array('status' => 'done',  'found' => false, 'message' => 'there is not any products available to show', 'categoryName' => $category->name, 'count' => 0, 'products' => []));
                }
		*/
		//###END OF NEW PART
                
		$category = $category->first();
                $order = $request->order;
                $onlyAvailableProducts = $request->onlyAvailableProducts;
		$onlyDiscounts = $request->onlyDiscounts;
                $name = '';
                $minPrice = '';
                $maxPrice = '';
		$discount = '';
                $time = time();
                if($onlyDiscounts == 1){
                    $discount = " AND PL.product_id IN (SELECT DD.dependency_id 
                    FROM discounts D INNER JOIN discount_dependencies DD ON DD.discount_id = D.id 
                    WHERE D.type_id = 5 AND D.status = 1 AND (D.start_date IS NULL OR D.start_date <= $time) AND (D.finish_date IS NULL OR D.finish_date >= $time) AND (D.expiration_date IS NULL OR D.expiration_date >= $time) AND DD.type_id = 1 AND (DD.final_stock = 0 OR DD.final_stock < PL.pack_stock)
                    ) ";
                }
                if($request->minPrice != 0 && $request->minPrice != ''){
                    $minPrice = " AND PP.price >= $request->minPrice ";
                }
                if($request->maxPrice != 0 && $request->maxPrice != ''){
                    $maxPrice = " AND PP.price <= $request->maxPrice ";
                }
                if($request->searchInput != ''){
                    $name = " AND P.prodName_fa LIKE '%" . $request->searchInput . "%'";
                }
                
                $order = " ORDER BY P.prodDate DESC";
		$finishedOrder = $order;
                if($request->order == 'old'){
                    $order = " ORDER BY P.prodDate ASC";
                }else if($request->order == 'cheap'){
                    $order = " ORDER BY PP.price ASC, P.prodDate DESC";
		    $finishedOrder = " ORDER BY P.prodDate DESC ";
                }else if($request->order == 'expensive'){
                    $order = " ORDER BY PP.price DESC, P.prodDate DESC";
		    $finishedOrder = " ORDER BY P.prodDate DESC ";
                }
                
                $response = [];

		$categoryUrl = $category->url . '%';

		$subCategories = $this->getSubCategories($category->id);

		$having = " AND P.prodStatus = 1 AND PL.stock > 0 AND  PL.pack_stock > 0 AND PP.status = 1 AND (PP.count * PL.pack_stock <= PL.stock) ";
                $finished = " AND P.prodStatus = 1 AND ((P.id NOT IN (SELECT DISTINCT PLL.product_id FROM products_location PLL )) OR PL.stock = 0 OR PL.pack_stock = 0 ) "; //"AND (P.stock = 0 OR PP.stock = 0 OR  PP.status = 0 OR (PP.count * PP.stock > P.stock))";

		$having = " $discount AND P.prodStatus = 1 AND PL.stock > 0 AND  PL.pack_stock > 0 AND PP.status = 1 AND PL.anbar_id = 1";
                $finished = " AND P.prodStatus = 1 AND (PL.stock <=  0 OR PL.pack_stock <= 0 ) AND PL.anbar_id = 1"; 

                $queryHaving = "SELECT  P.id, PP.id AS packId, P.prodName_fa, P.prodID, P.url, P.prodStatus, P.prodUnite, P.stock AS productStock, PP.stock AS packStock, PP.status, PP.price, PP.base_price, PP.label, PP.count, PPC.category FROM products P INNER JOIN products_location PL ON P.id = PL.product_id INNER JOIN product_pack PP ON PL.pack_id = PP.id INNER JOIN product_category PPC ON PPC.product_id = P.id WHERE P.id IN (SELECT DISTINCT PC.product_id FROM product_category PC INNER JOIN category C ON PC.category = C.id
                    WHERE C.id IN ($subCategories)) AND PP.status = 1 AND PPC.kind = 'primary'" . $having . $name . $minPrice . $maxPrice . $order . "";
                
                $queryFinished = "SELECT  P.id, 0 AS packId, P.prodName_fa, P.prodID, P.url, P.prodStatus, P.prodUnite, 0 AS productStock, 0 AS packStock, 0 AS `status`, -1 AS price, 0 AS base_price, '' AS label, 0 AS `count`, PPC.category FROM products P INNER JOIN product_category PPC ON PPC.product_id = P.id INNER JOIN products_location PL ON P.id = PL.product_id WHERE P.id IN (SELECT DISTINCT PC.product_id FROM product_category PC INNER JOIN category C ON PC.category = C.id
                    WHERE C.id IN ($subCategories)) and PPC.kind = 'primary' " . $finished . $name . $minPrice . $maxPrice . $finishedOrder . "";

		$havingProducts = DB::select($queryHaving);
                
		$finishedProducts = [];
                if($onlyDiscounts != 1){
                    if($onlyAvailableProducts === 0){
                        $finishedProducts = DB::select($queryFinished);
                    }
                }
		$selectedProducts = [];
                $products = [];
                foreach($havingProducts as $hp){
                    if($hp->status == 1 && array_search($hp->id, $selectedProducts) === false){
                        array_push($products, $hp);
			array_push($selectedProducts, $hp->id);
                    }
                }
                foreach($finishedProducts as $fp){
		    if(array_search($fp->id, $selectedProducts) === false){
			array_push($products, $fp);
			array_push($selectedProducts, $fp->id);
		    }
                }
                
                if(count($products) !== 0){
                    $allResponses = [];
                    $filters = $request->filters;
                    $distinctFiltersCount = 0;
                    $distinctFiltersArray = [];
                    foreach($filters as $filter){
                        $found = false;
                        foreach($distinctFiltersArray as $dfa){
                            if($dfa['en_name'] == $filter['en_name']){
                                $found = true;
                                break;
                            }
                        }
                        if($found === false){
                            array_push($distinctFiltersArray, $filter);
                        }
                    }
                    $distinctFiltersCount = count($distinctFiltersArray);
                    if(count($filters) !== 0){
                        foreach($products as $p){
                            $i = 0;
                            $productMetas = DB::select("SELECT * FROM products_meta WHERE product_id = $p->id");
                            if(count($productMetas) !== 0){
                                foreach($filters as $filter){
                                    foreach($productMetas as $pm){
                                        if($pm->key === '__' . $filter['en_name'] && $pm->value == $filter['value']){
                                            $i++;
                                        }
                                    }
                                }
                            }
                            if($i === $distinctFiltersCount){
                                $productObject = new stdClass();
                                $productObject->productId = $p->id;
                                $productObject->productPackId = $p->packId;
                                $productObject->productName = $p->prodName_fa;
                                $productObject->prodID = $p->prodID;
                                $productObject->categoryId = $p->category;
                                $productObject->productPrice = $p->price;
                                $productObject->productUrl = $p->url;
                                $productObject->productBasePrice = $p->base_price;
                                //$productObject->productCount = $value->count;
                                $productObject->productUnitCount = $p->count;
                                $productObject->productUnitName = $p->prodUnite;
                                $productObject->productLabel = $p->label;
                                array_push($allResponses, $productObject);
                            }
                        }
                        $r = array_slice($allResponses, ($request->page - 1)*24, 24);
                        $response = DiscountCalculator::calculateProductsDiscount($r);
                        echo json_encode(array('status' => 'done', 'found' => true, 'categoryName' => $category->name, 'count' => count($allResponses), 'products' => $response, 'message' => 'products are successfully found'));
                    }else{
                        foreach($products as $pr){
                            $productObject = new stdClass();
                            $productObject->productId = $pr->id;
                            $productObject->productPackId = $pr->packId;
                            $productObject->productName = $pr->prodName_fa;
                            $productObject->prodID = $pr->prodID;
                            $productObject->categoryId = $pr->category;
                            $productObject->productPrice = $pr->price;
                            $productObject->productUrl = $pr->url;
                            $productObject->productBasePrice = $pr->base_price;
                            //$productObject->productCount = $value->count;
                            $productObject->productUnitCount = $pr->count;
                            $productObject->productUnitName = $pr->prodUnite;
                            $productObject->productLabel = $pr->label;
                            array_push($allResponses, $productObject);
                        }
                        $r = array_slice($allResponses, ($request->page - 1)*24, 24);
                        $response = DiscountCalculator::calculateProductsDiscount($r);
                        echo json_encode(array('status' => 'done', 'found' => true, 'categoryName' => $category->name, 'count' => count($allResponses), 'products' => $response, 'message' => 'products are successfully found'));
                    }
                }else{
                    echo json_encode(array('status' => 'done',  'found' => false, 'message' => 'there is not any products available to show', 'categoryName' => $category->name, 'count' => 0, 'products' => []));
                }
		
            }else{
                echo json_encode((array('status' => 'failed', 'message' => 'category not found')));
            }
        }else{
            echo json_encode(array('status' => 'failed', 'message' => 'not enough parameter'));
        }
    }

    public function categoryFilters(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric',
        ]);
        if($validator->fails()){
            echo json_encode(array('status' => 'failed', 'source' => 'v', 'message' => 'argument validation failed', 'umessage' => 'مقادیر ورودی صحیح نیست'));
            exit();
        }

        if(isset($request->id)){
            $category = DB::select("SELECT feature_group_id FROM category WHERE id = $request->id LIMIT 1");
            if(count($category) !== 0){
                $category = $category[0];
                if($category->feature_group_id !== 0){
                    $featureGroupId = $category->feature_group_id;
                    $filterGroup = DB::select("SELECT * FROM product_feature_groups WHERE id = $featureGroupId");
                    if(count($filterGroup) !== 0){
                        $filterGroup = $filterGroup[0];
                        $featureIds = explode(',', $filterGroup->feature_ids);
                        $response = [];
                        foreach($featureIds as $featureId){
                            $feature = DB::select("SELECT * FROM product_features WHERE id = $featureId AND show_in_filter = 1");
                            if(count($feature) !== 0){
                                $feature = $feature[0];
                                array_push($response, array('id' => $feature->id, 'name' => $feature->name, 'type' => $feature->type, 'enName' => $feature->en_name, 'options' => $feature->options));
                            }
                        }
                        echo json_encode(array('status' => 'done', 'found' => true, 'message' => 'category filters is found', 'filters' => $response));
                    }else{
                        echo json_encode(array('status' => 'done', 'found' => false, 'message' => 'specified filter did not found'));
                    }
                }else{
                    echo json_encode(array('status' => 'done', 'found' => false, 'message' => 'this category does not have any filter'));
                }
            }else{
                echo json_encode(array('status' => 'failed', 'message' => 'category not found'));
            }
        }else{
            echo json_encode(array('status' => 'failed', 'message' => 'not enough parameter'));
        }
        /*##### I wrote this API but did not test it #####*/
    }

    public function categoryBreadCrumb(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric',
        ]);
        if($validator->fails()){
            echo json_encode(array('status' => 'failed', 'source' => 'v', 'message' => 'argument validation failed', 'umessage' => 'مقادیر ورودی صحیح نیست'));
            exit();
        }

        if(isset($request->id)){
            $categoryId = $request->id;
            $c = Category::where('id', $categoryId);
            if($c->count() !== 0){
                //$category = $category->first();
                //$categoryId = $category->cid;
                $categories = [];
                do{
                    $category = Category::where('id', $categoryId)->first();
                    array_push($categories, array('name' => $category->name, 'url' => $category->url));
                    $categoryId = $category->parentID;

                }while($categoryId !== 0);
                echo json_encode(array('status' => 'done', 'message' => 'categories successfully found', 'categories' => array_reverse($categories)));
            }else{
                echo json_encode(array('status' => 'failed', 'message' => 'category not found'));
            }
        }else{
            echo json_encode(array('status' => 'failed', 'message' => 'not enough parameter'));
        }
    }

    public function topSixBestSellerCategories(Request $request){
        $categories = DB::select(
            "SELECT COUNT(RESULT.categoryId) AS `count`, 
                RESULT.categoryId, 
                RESULT.categoryName, 
                RESULT.categoryUrl 
            FROM (
                SELECT  
                    C.id AS categoryId,
                    C.name AS categoryName, 
                    C.url AS categoryUrl 
                FROM product_stock PS 
                INNER JOIN product_category PC ON PS.product_id = PC.product_id 
                INNER JOIN category C ON PC.category = C.id 
                WHERE PS.kind IN (5, 6) 
                ORDER BY PS.id DESC   
                LIMIT 50 
                ) AS RESULT  
            GROUP BY RESULT.categoryId, RESULT.categoryName, RESULT.categoryUrl
            ORDER BY COUNT(RESULT.categoryId) DESC 
            LIMIT 6"
        );
        if(count($categories) === 0){
            echo json_encode(array('status' => 'done', 'found' => false, 'message' => 'top categories not found', 'categories' => []));
            exit();
        }
        foreach($categories as $category){
            $product = DB::select(
                "SELECT P.prodID 
                FROM products P 
                INNER JOIN product_category PC ON P.id = PC.product_id 
                WHERE P.stock > 0 AND 
                    P.prodStatus = 1 AND 
                    PC.category = $category->categoryId 
                ORDER BY P.id DESC 
                LIMIT 1"
            );
            if(count($product) === 0){
                $category->categoryImage = '';
            }else{
                $product = $product[0];
                $category->categoryImage = 'https://honari.com/image/resizeTest/shop/_200x/thumb_' . $product->prodID . '.jpg';
            }
        }
        echo json_encode(array('status' => 'done', 'found' => true, 'message' => 'categories successfully found', 'categories' => $categories));
    }
}
