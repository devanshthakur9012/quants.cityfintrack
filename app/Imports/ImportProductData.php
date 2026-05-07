<?php


namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Jobs\ProductImportJob;

use App\Models\Category;
use App\Models\ProductSku;
use App\Models\Product;
use App\Models\CategoryProduct;
use App\Models\SellerProduct;
use App\Models\SellerProductSKU;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Events\ImportFailed;
use App\Jobs\InsertProductData;
use Maatwebsite\Excel\Concerns\Importable;

class ImportProductData implements ToCollection, WithChunkReading,WithHeadingRow,WithBatchInserts,ShouldQueue
{
  	use Importable;

    public function __construct(){
      set_time_limit(0);
    }

    public function collection(Collection $rows)
    {
        $categorys = 0;
        $subcategorys = 0;
        foreach ($rows as $row) {
          try {
            \DB::beginTransaction();
            //add category
            if (isset($row['category']) && $row['category'] != null) {
              $checkCat = Category::select('id')->where('name', $row['category'])->first();
              if (!$checkCat) {
                $slug = \Str::slug($row['category']);
                $hasSlug = Category::where('slug', $slug)->count();
                if ($hasSlug) {
                  $slug = $slug . '-' . $hasSlug + 1;
                }
                $category = new Category();
                $category->name = $row['category'];
                $category->slug = $slug;
                $category->parent_id = 0;
                $category->searchable = 1;
                $category->status = 1;
                $category->depth_level = 1;
                $category->save();
                $parentCat = $category->id;
              } else {
                $parentCat = $checkCat->id;
              }
              $categorys = $parentCat;
            }
            //insert sub categories
            if (isset($row['sub_category']) && $row['sub_category'] != null) {
              $checkCat = Category::select('id')->where('name', $row['sub_category'])->where('parent_id', '!=', 0)->first();
              if (!$checkCat) {
                $slug = \Str::slug($row['sub_category']);
                $hasSlug = Category::where('slug', $slug)->count();
                if ($hasSlug) {
                  $slug = $slug . '-' . $hasSlug + 1;
                }
                $findSubCategory = new Category();
                $findSubCategory->name = $row['sub_category'];
                $findSubCategory->slug = $slug;
                $findSubCategory->parent_id = $parentCat;
                $findSubCategory->searchable = 1;
                $findSubCategory->status = 1;
                $findSubCategory->depth_level = 2;
                $findSubCategory->save();
                $subcategorys = $findSubCategory->id;
              }
            }
            //insert sub categories

            //**********************************INSERT PRODUCT******************************************//

            $productSkuData = ProductSku::select('product_id', 'id')->where('sku', $row['id'])->first();
            if (!$productSkuData) {
              $slugP = \Str::slug($row['name']);
              $hasSlug = Product::where('slug', $slugP)->count();
              if ($hasSlug) {
                $slugP = $slugP . '-' . $hasSlug + 1;
              }
              $productId = Product::insertGetId([
                'product_name' => $row['name'] ?? NULL,
                'discount_type' => 1,
                'minimum_order_qty' => 1,
                'slug' => $slugP,
                'max_order_qty' => 1,
                'product_type' => 1,
                'discount' => isset($row['discount_percent']) ? $row['discount_percent'] : 0,
                'manufacturers' => $row['manufacturers'] ?? NULL,
                'packaging' => $row['packaging'] ?? NULL,
                'package' => $row['package'] ?? NULL,
                'product_form' => $row['product_form'] ?? NULL,
                'description' => $row['description'] ?? NULL,
                'primary_use' => $row['primary_use'] ?? NULL,
                'storage' => $row['storage'] ?? NULL,
                'introduction' => $row['introduction'] ?? NULL,
                'use_of' => $row['use_of'] ?? NULL,
                'benefits' => $row['benefits'] ?? NULL,
                'how_to_use' => $row['how_to_use'] ?? NULL,
                'safety_advise' => $row['safety_advise'] ?? NULL,
                'if_miss' => $row['if_miss'] ?? NULL,
                'is_physical' => 1,
                'is_approved' => 1,
                'prescription_required' => $row['prescription_required'] ?? NULL,
                'label' => $row['label'] ?? NULL,
                'salt_composition' => $row['salt_composition'] ?? NULL,
                'common_side_effect' => $row['common_side_effect'] ?? NULL,
                'alcoholInteraction' => $row['alcoholInteraction'] ?? NULL,
                'pregnancyInteraction' => $row['pregnancyInteraction'] ?? NULL,
                'lactationInteraction' => $row['lactationInteraction'] ?? NULL,
                'drivingInteraction' => $row['drivingInteraction'] ?? NULL,
                'kidneyInteraction' => $row['kidneyInteraction'] ?? NULL,
                'liverInteraction' => $row['liverInteraction'] ?? NULL,
                'q_a' => $row['Q_A'] ?? NULL,
                'salt_synonmys' => $row['salt_synonmys'] ?? NULL,
                'side_effect' => $row['side_effect'] ?? NULL,
                'how_works' => $row['how_works'] ?? NULL,
                'ingredients' => $row['ingredients'] ?? NULL,
                'rating' => $row['rating'] ?? NULL,
                'reviews' => $row['reviews'] ?? NULL,
                'star' => $row['star'] ?? NULL,
                'alternate_brand' => $row['alternate_brand'] ?? NULL,
                'manufacturer_address' => $row['manufacturer_address'] ?? NULL,
                'country_of_origin' => $row['country_of_origin'] ?? NULL,
                'thumbnail_image_source' => "productImg/" . $row['id'] . "_1.jpg" ?? NULL,
                'how_to_use' => $row['how_to_use'] ?? NULL
              ]);
              
              $productSkuId = ProductSku::insertGetId([
                'sku' => $row['id'],
                'product_id' => $productId,
                'selling_price' => $row['mrp'] ?? 0,
                'purchase_price' => $row['mrp'] ?? 0,
                'product_stock' => (isset($row['for_sale']) && $row['for_sale']  == "ADD TO CART") ? 1 : 0,
                'status' => 1,
                'additional_shipping' => $row['additional_shipping'] ?? 0,
              ]);



              $sellerProductId = SellerProduct::insertGetId([
                'user_id' => 1,
                'product_id' => $productId,
                'stock_manage' => 0,
                'tax' => $row['tax'] ?? 0,
                'tax_type' => $row['tax_type'] ?? NULL,
                'discount' => $row['discount'] ?? 0,
                'discount_type' => $row['discount_type'] ?? NULL,
                'product_name' => $row['product_name'] ?? NULL,
                'slug' => $slugP,
                'status' => 1,
                'is_approved' => $row['is_approved'] ?? 1,
                'min_sell_price' => $row['mrp'] ?? 0,
                'max_sell_price' => $row['mrp'] ?? 0,
                'thum_img' => "productImg/" . $row['id'] . "_1.jpg" ?? NULL,
              ]);

              $SellerProductSKU = SellerProductSKU::insert([
                'user_id' => 1,
                'product_id' => $sellerProductId,
                'product_sku_id' => $productSkuId,
                'selling_price' => $row['mrp'] ?? 0,
                'status' => 1,
                'product_stock' => (isset($row['for_sale']) && $row['for_sale']  == "ADD TO CART") ? 1 : 0,
              ]);

              if ($subcategorys > 0) {
                $deleteCatgeory = CategoryProduct::where('product_id', $productId)->where('category_id', $subcategorys)->first();
                if($deleteCatgeory){
                  CategoryProduct::insert([
                  	'product_id'=>$productId,
                    'category_id'=>$subcategorys,
                    'created_at'=>date("Y-m-d H:i:s"),
                    'updated_at'=>date("Y-m-d H:i:s"),
                  ]);
                }
              }

              if ($categorys > 0) {
                $deleteCatgeory = CategoryProduct::where('product_id', $productId)->where('category_id', $categorys)->first();
                if(!$deleteCatgeory){
                  CategoryProduct::insert([
                  	'product_id'=>$productId,
                    'category_id'=>$categorys,
                    'created_at'=>date("Y-m-d H:i:s"),
                    'updated_at'=>date("Y-m-d H:i:s"),
                  ]);
                }
              }

              
            } else {
              $productId = $productSkuData->product_id;

              Product::where('id', $productId)->update([
                'product_name' => $row['name'] ?? NULL,
                'discount_type' => 1,
                'minimum_order_qty' => 1,
                'max_order_qty' => 1,
                'product_type' => 1,
                'discount' => isset($row['discount_percent']) ? $row['discount_percent'] : 0,
                'manufacturers' => $row['manufacturers'] ?? NULL,
                'packaging' => $row['packaging'] ?? NULL,
                'package' => $row['package'] ?? NULL,
                'product_form' => $row['product_form'] ?? NULL,
                'description' => $row['description'] ?? NULL,
                'primary_use' => $row['primary_use'] ?? NULL,
                'storage' => $row['storage'] ?? NULL,
                'introduction' => $row['introduction'] ?? NULL,
                'use_of' => $row['use_of'] ?? NULL,
                'benefits' => $row['benefits'] ?? NULL,
                'how_to_use' => $row['how_to_use'] ?? NULL,
                'safety_advise' => $row['safety_advise'] ?? NULL,
                'if_miss' => $row['if_miss'] ?? NULL,
                'is_physical' => 1,
                'is_approved' => 1,
                'prescription_required' => $row['prescription_required'] ?? NULL,
                'label' => $row['label'] ?? NULL,
                'salt_composition' => $row['salt_composition'] ?? NULL,
                'common_side_effect' => $row['common_side_effect'] ?? NULL,
                'alcoholInteraction' => $row['alcoholInteraction'] ?? NULL,
                'pregnancyInteraction' => $row['pregnancyInteraction'] ?? NULL,
                'lactationInteraction' => $row['lactationInteraction'] ?? NULL,
                'drivingInteraction' => $row['drivingInteraction'] ?? NULL,
                'kidneyInteraction' => $row['kidneyInteraction'] ?? NULL,
                'liverInteraction' => $row['liverInteraction'] ?? NULL,
                'q_a' => $row['Q_A'] ?? NULL,
                'salt_synonmys' => $row['salt_synonmys'] ?? NULL,
                'side_effect' => $row['side_effect'] ?? NULL,
                'how_works' => $row['how_works'] ?? NULL,
                'ingredients' => $row['ingredients'] ?? NULL,
                'rating' => $row['rating'] ?? NULL,
                'reviews' => $row['reviews'] ?? NULL,
                'star' => $row['star'] ?? NULL,
                'alternate_brand' => $row['alternate_brand'] ?? NULL,
                'manufacturer_address' => $row['manufacturer_address'] ?? NULL,
                'country_of_origin' => $row['country_of_origin'] ?? NULL,
                'thumbnail_image_source' => "productImg/" . $row['id'] . "_1.jpg" ?? NULL,
                'how_to_use' => $row['how_to_use'] ?? NULL
              ]);

              ProductSku::where('product_id', $productId)->update([
                'sku' => $row['id'],
                'selling_price' => $row['mrp'] ?? 0,
                'purchase_price' => $row['mrp'] ?? 0,
                'product_stock' => (isset($row['for_sale']) && $row['for_sale']  == "ADD TO CART") ? 1 : 0,
                'status' => 1,
                'additional_shipping' => $row['additional_shipping'] ?? 0,
              ]);



              $sellerProductId = SellerProduct::where('product_id', $productId)->update([
                'user_id' => 1,
                'stock_manage' => 0,
                'tax' => $row['tax'] ?? 0,
                'tax_type' => $row['tax_type'] ?? NULL,
                'discount' => $row['discount'] ?? 0,
                'discount_type' => $row['discount_type'] ?? NULL,
                'product_name' => $row['product_name'] ?? NULL,
                'status' => 1,
                'is_approved' => $row['is_approved'] ?? 1,
                'min_sell_price' => $row['mrp'] ?? 0,
                'max_sell_price' => $row['mrp'] ?? 0,
                'thum_img' => "productImg/" . $row['id'] . "_1.jpg" ?? NULL,
              ]);

              $SellerProductSKU = SellerProductSKU::where('product_sku_id', $productSkuData->id)->update([
                'user_id' => 1,
                'selling_price' => $row['mrp'] ?? 0,
                'status' => 1,
                'product_stock' => (isset($row['for_sale']) && $row['for_sale']  == "ADD TO CART") ? 1 : 0,
              ]);

              if ($subcategorys > 0) {
                $deleteCatgeory = CategoryProduct::where('product_id', $productId)->where('category_id', $subcategorys)->first();
                if(!$deleteCatgeory){
                  CategoryProduct::insert([
                  	'product_id'=>$productId,
                    'category_id'=>$subcategorys,
                    'created_at'=>date("Y-m-d H:i:s"),
                    'updated_at'=>date("Y-m-d H:i:s"),
                  ]);
                }
              }

              if ($categorys > 0) {
                $deleteCatgeory = CategoryProduct::where('product_id', $productId)->where('category_id', $categorys)->first();
                if(!$deleteCatgeory){
                  CategoryProduct::insert([
                  	'product_id'=>$productId,
                    'category_id'=>$categorys,
                    'created_at'=>date("Y-m-d H:i:s"),
                    'updated_at'=>date("Y-m-d H:i:s"),
                  ]);
                }
              }
            }

            //**********************************INSERT PRODUCT******************************************//
            \DB::commit();
          } catch (\Exception $e) {
            \DB::rollback();

            \DB::table('tags')->insert(['name' => $e->getMessage()]);
          }
        }
    }
   	
  
    public function chunkSize(): int
    {
       return 1000;
    }

    public function batchSize(): int
    {
        return 1000;
    }
  
     
}