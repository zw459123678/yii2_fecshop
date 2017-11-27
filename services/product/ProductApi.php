<?php
/**
 * FecShop file.
 *
 * @link http://www.fecshop.com/
 * @copyright Copyright (c) 2016 FecShop Software LLC
 * @license http://www.fecshop.com/license/
 */

namespace fecshop\services\product;

use fecshop\models\mongodb\Product;
use Yii;

/**
 * Product Categoryapi Service
 * @author Terry Zhao <2358269014@qq.com>
 * @since 1.0
 * 由于产品部分的api导入操作比较复杂，因此单独一个services文件来处理产品导入。
 */
class ProductApi 
{
    protected $_error = []; // $this->_error
    protected $_param = []; // $this->_param
    
    protected $_productModelName = '\fecshop\models\mongodb\Product';
    protected $_productModel;
    
    public function __construct(){
        list($this->_productModelName,$this->_productModel) = \Yii::mapGet($this->_productModelName);  
    }
    
    /**
     * @property $post | Array , 数组
     * 检查产品数据的必填以及数据的初始化
     * 1. 产品必填数据是否填写，不填写就报错
     * 2. 产品选填信息如果没有填写，如果可以初始化，那么就初始化
     * 3. 对了列举类型，譬如产品状态，只能填写1和2，填写其他的是不允许的，对于这种情况，会忽略掉填写的值，
     *    如果可以初始化，给初始化一个默认的正确的值，如果不可以初始化，譬如颜色尺码之类的属性，则报错。
     * 4. 类型的强制转换，譬如价格，强制转换成float
     * 5. 数据结构的检查，譬如图片 custom option ，是一个大数组，需要进行严格的检查。
     */
    protected function checkPostDataRequireAndInt($post){
        $model = $this->_productModel;
        if (empty($post) || !is_array($post)) {
            $this->_error[] = 'post data is empty or is not array';
            return ;
        }
        // 产品名字：【必填】 【多语言属性】
        $name = $post['name'];
        if (!$name) {
            $this->_error[] = '[name] can not empty';
        } else {
            $this->_param['name'] = $name;
        }
        if(!Yii::$service->fecshoplang->getDefaultLangAttrVal($name, 'name')) {
            $defaultLangAttrName = Yii::$service->fecshoplang->getDefaultLangAttrName('name');
            $this->_error[] = '[name.'.$defaultLangAttrName.'] can not empty';
        }
        // 产品描述：【必填】  【多语言】
        $description = $post['description'];
        if (!$description) {
            $this->_error[] = '[description] can not empty';
        } else {
            $this->_param['description'] = $description;
        }
        if(!Yii::$service->fecshoplang->getDefaultLangAttrVal($description, 'description')) {
            $defaultLangAttrName = Yii::$service->fecshoplang->getDefaultLangAttrName('description');
            $this->_error[] = '[description.'.$defaultLangAttrName.'] can not empty';
        }
        // 产品spu：【必填】
        $spu        = $post['spu'];
        if (!$spu) {
            $this->_error[] = '[spu] can not empty';
        } else {
            $this->_param['spu'] = $spu;
        }
        // 产品sku：【必填】
        $sku        = $post['sku'];
        if (!$sku) {
            $this->_error[] = '[sku] can not empty';
        } else {
            $this->_param['sku'] = $sku;
        }
        // 产品重量Kg：【必填】强制转换成float类型
        $weight     = (float)$post['weight'];
        if (!$weight) {
            $this->_error[] = '[weight] can not empty';
        } else {
            $this->_param['weight'] = $weight;
        }
        // 产品价格：【必填】 强制转换成float类型
        $price          = (float)$post['price'];
        if (!$price) {
            $this->_error[] = '[price] can not empty';
        } else {
            $this->_param['price'] = $price;
        }
        // 产品库存：【选填】，如果不在合法范围内则强制为0
        $qty        = (int)$post['qty'];
        if (!$qty || $qty <0) {
            $qty = 0;
        } 
        $this->_param['qty'] = $qty; 
        // 选填 数组
        $category       = $post['category'];
        if ($category && !is_array($category)) {
            $this->_error[] = '[category] must be array';
        }
        if ($category) {
            $this->_param['category'] = $category;
        }
        
        // 选填, 是否下架， 如果不存在，或者不合法，则会被设置成有库存状态
        $is_in_stock     = $post['is_in_stock'];
        $allow_stock_arr = [$model::IS_IN_STOCK,$model::OUT_STOCK];
        if (!$is_in_stock || !in_array($is_in_stock,$allow_stock_arr)) {
            $is_in_stock = $model::IS_IN_STOCK;
        }
        $this->_param['is_in_stock'] = $is_in_stock;
        // 选填 特价
        $special_price  = (float)$post['special_price'];
        if (!$special_price){
            $this->_param['special_price'] = $special_price;
        }
        // 选填 特价开始时间 开始时间只要“年-月-日”部分，其他部分去除，然后取00:00:00的数据
        $special_from   = $post['special_from'];
        if(!$special_from) {
            $special_from = substr($special_from, 0, 5).' 00:00:00';
            $special_from = strtotime($special_from);
            if ($special_from) {
                $this->_param['special_from'] = $special_from;
            }
        }
        // 选填 特价结束时间 开始时间只要“年-月-日”部分，其他部分去除，然后取23:59:59的数据
        $special_to     = $post['special_to'];
        if(!$special_to) {
            $special_to = substr($special_to, 0, 5).' 23:59:59';
            $special_to = strtotime($special_to);
            if ($special_to) {
                $this->_param['special_to'] = $special_to;
            }
        }
        // 选填
        $tier_price     = $post['tier_price'];
        if (!$tier_price){
            /** 检查数据个数是否如下
             * "tier_price": [
             *      {
             *        "qty": NumberLong(2),
             *        "price": 17
             *     },
             *      {
             *        "qty": NumberLong(4),
             *        "price": 16
             *     }
             * ],
             */
            if (is_array($tier_price)) {
                $correct = 1;
                foreach ($tier_price as $one) {
                    if(
                        !isset($one['qty']) || !$one['qty'] ||
                        !isset($one['price']) || !$one['price'] 
                    ){
                        $correct = 0;
                        break;
                    }
                }
                if (!$correct){
                    $this->_error[] = '[tier_price] data format is error , you can see doc example data';
                }
                $this->_param['tier_price'] = $tier_price;
            } else {
                $this->_error[] = '[tier_price] data must be array';
            }
        }
        
        
        // 选填 开始时间只要“年-月-日”部分，其他部分去除，然后取00:00:00的数据
        $new_product_from    = $post['new_product_from'];
        if (!$new_product_from) {
            $new_product_from = substr($new_product_from, 0, 5).' 00:00:00';
            $new_product_from = strtotime($new_product_from);
            if ($new_product_from) {
                $this->_param['new_product_from'] = $new_product_from;
            }
        }
        // 选填 结束时间只要“年-月-日”部分，其他部分去除，然后取23:59:59的数据
        $new_product_to      = $post['new_product_to'];
        if (!$new_product_to) {
            $new_product_to = substr($new_product_to, 0, 5).' 23:59:59';
            $new_product_to = strtotime($new_product_to);
            if ($new_product_to) {
                $this->_param['new_product_to'] = $new_product_to;
            }
        }
        // 选填 产品的成本价
        $cost_price = (float)$post['cost_price'];
        if ($cost_price) {
            $this->_param['cost_price'] = $cost_price;
        }
        // 选填  【多语言类型】产品的简单描述
        $short_description   = $post['short_description'];
        if( !empty($short_description) && is_array($short_description)) {
            $this->_param['short_description'] = $short_description;
        }
        // 必填
        $attr_group          = $post['attr_group'];
        $customAttrGroup     = Yii::$service->product->customAttrGroup;
        if (!$attr_group) {
            $this->_error[] = '[attr_group] can not empty';
        } else if (!isset($customAttrGroup[$attr_group])){
            $this->_error[] = '[attr_group:'.$attr_group.'] is not config is Product Service Config File';
        } else {
            $this->_param['attr_group'] = $attr_group;
        }
        /**
         * 选填 当选择淘宝模式的产品时，一行数据是一个spu，各个sku的图片，库存，浮动价格，各个自定义属性的值等，就会存储到这个属性里面
         * 譬如：
         * "custom_option": {
                "red-s-s2-s3": {
                   "my_color": "red",
                   "my_size": "S",
                   "my_size2": "S2",
                   "my_size3": "S3",
                   "sku": "red-s-s2-s3",
                   "qty": NumberInt(99999),
                   "price": 0,
                   "image": "/2/01/20161024170457_10036.jpg"
                },
                "red-m-s2-s3": {
                   "my_color": "red",
                   "my_size": "M",
                   "my_size2": "S2",
                   "my_size3": "S3",
                   "sku": "red-m-s2-s3",
                   "qty": NumberInt(99999),
                   "price": 0,
                   "image": "/2/01/20161024170457_10036.jpg"
                },
                "red-l-s2-s3": {
                   "my_color": "red",
                   "my_size": "L",
                   "my_size2": "S2",
                   "my_size3": "S3",
                   "sku": "red-l-s2-s3",
                   "qty": NumberInt(99999),
                   "price": 0,
                   "image": "/2/01/20161024170457_10036.jpg"
                }
            }
         *  需要进行如下检查：
         *  1. 图片地址是否存在，不存在则报错
         *  2. sku 存在，且在当前数组中sku 重复的，将会被去除
         *  3. 通过属性组 attr_group 找到相应的custom option，查看里面的值，在这里是否都存在
         *  4. qty 是否存在，不存在，则初始化为0  ， 
         *  5. price是否存在，不存在则初始化为0，
         *  6. 数组的key，需要和sku相等，譬如 red-l-s2-s3 要等于下面的  "sku": "red-l-s2-s3"
         * 
         */
        
        $custom_option = $post['custom_option'];
        if (!empty( $custom_option) && is_array($custom_option) && isset($customAttrGroup[$attr_group]['custom_options']) && $customAttrGroup[$attr_group]['custom_options']) {
            $custom_option_arr = [];
            // 1.
            // 该属性组对应的 custom option 的数据配置结构
            $attr_group_config = $customAttrGroup[$attr_group]['custom_options'];
            foreach ( $custom_option as $key => $info) {
                // 1. 图片地址是否存在，不存在则报错
                if (!isset($info['image']) || !$info['image']){
                    $this->_error[] = 'custom option: image can not empty';
                }
                // 2.sku 存在
                if (!isset($info['sku']) || !$info['sku']){
                    $this->_error[] = 'custom option: sku can not empty';
                }
                // 4. qty 是否存在，不存在，则初始化为0 
                $info['qty'] = (int)$info['qty'];
                if (!$info['qty']) {
                    $info['qty'] = 0;
                }
                // 5. price是否存在，不存在则初始化为0，
                $info['price'] = (float)$info['price'];
                if (!$info['price']) {
                    $info['price'] = 0;
                }
                // 3. 通过属性组 attr_group 找到相应的custom option，查看里面的值，在这里是否都存在
                if (is_array($attr_group_config)) {
                    // 遍历 custom option 的数据配置结构
                    foreach ($attr_group_config as $attrKey => $custom_option_info) {
                        // 当前行数据中，是否符合 数据配置结构 
                        $val = '';
                        if (isset($info[$attrKey]) && $info[$attrKey]){
                            $val = $info[$attrKey];
                        } else {
                            $this->_error[] = '[custom option error]: (attr_group:'.$attr_group.') attr['.$attrKey.'] is exist in config file ,but current data is empty';
                            // error： 缺失 $attrKey 存在数据配置结构中，但是当前的插入数据中不存在这个属性。
                        }
                        if(isset($custom_option_info['display']['data']) && is_array($custom_option_info['display']['data'])) {
                            $attr_group_config_val_arr = $custom_option_info['display']['data'];
                            if (in_array($val,$attr_group_config_val_arr)) {
                                
                                // success
                            } else {
                                $this->_error[] = '[custom option error]: (attr_group:'.$attr_group.') attr['.$attrKey.':'.$val.'] must exist in array ['.implode(',',$attr_group_config_val_arr).'] ';
                                // error：$attrKey 这个属性在当前的插入数据中存在，但是值不合法，值必须存在于 "数据配置结构" 中对应的数据列表中
                            }
                        } else {
                            $this->_error[] = '[custom option config error]: (attr_group:'.$attr_group.') attr['.$attrKey.'] config is not correct , it must exist: [\'display\'][\'data\']';
                        }
                    }
                } else {
                    $this->_error[] = '[custom option config error]: (attr_group:'.$attr_group.') , it must be array';
                }
                $custom_option_arr[$info['sku']] = $info;
            }
            if (!empty($custom_option_arr)) {
                $this->_param['custom_option'] = $custom_option_arr;
            }
        }
        
        // 选填
        $remark = $post['remark'];
        if( !$remark ){
            $this->_param['remark'] = $remark;
        }
        // 选填
        $relation_sku = $post['relation_sku'];
        if( !$relation_sku ) {
            $this->_param['relation_sku'] = $relation_sku;
        }
        // 选填
        $buy_also_buy_sku = $post['buy_also_buy_sku'];
        if( !$buy_also_buy_sku ) {
            $this->_param['buy_also_buy_sku'] = $buy_also_buy_sku;
        }
        // 选填
        $see_also_see_sku = $post['see_also_see_sku'];
        if( !$see_also_see_sku ) {
            $this->_param['see_also_see_sku'] = $see_also_see_sku;
        }
        
        
        
        // 选填 产品状态
        $status = $post['status'];
        $allowStatus = [$model::STATUS_ENABLE,$model::STATUS_DISABLE];
        if( !$status || !in_array($status, $allowStatus) ) {
            $status = $model::STATUS_ENABLE;
        }
        $this->_param['status'] = $status;
        // 选填 产品的url key
        $url_key = $post['url_key'];
        if( !$url_key ) {
            $this->_param['url_key'] = $url_key;
        }
        
        /**
         *  选填 产品的图片
         *  图片的格式如下：
            "image": {
                "gallery": [
                    {
                        "image": "/2/01/20161024170457_13851.jpg",
                        "label": "",
                        "sort_order": ""
                    },
                    {
                        "image": "/2/01/20161024170457_21098.jpg",
                        "label": "",
                        "sort_order": ""
                    },
                    {
                        "image": "/2/01/20161101155240_26690.jpg",
                        "label": "",
                        "sort_order": ""
                    },
                    {
                        "image": "/2/01/20161101155240_56328.jpg",
                        "label": "",
                        "sort_order": ""
                    },
                    {
                        "image": "/2/01/20161101155240_94256.jpg",
                        "label": "",
                        "sort_order": ""
                    }
                ],
                "main": {
                    "image": "/2/01/20161024170457_10036.jpg",
                    "label": "",
                    "sort_order": ""
                }
            },
          */
        $image = $post['image'];
        if( !$image ) {
            $correct = 1;
            if (isset($image['main'])) {
                if (isset($image['main']['image']) && isset($image['main']['image'])) {
                    // 正确
                } else {
                    $this->_error[] = 'image[\'main\'][\'image\'] must exist ';
                }
            }
            if (isset($image['gallery'])) {
                if (is_array($image['gallery']) && !empty($image['gallery'])) {
                    foreach ($image['gallery'] as $one) {
                        if (!isset($one['image']) || !$one['image']) {
                            $this->_error[] = 'image[\'gallery\'][][\'image\'] must exist ';
                        }
                    }
                } else {
                    $this->_error[] = 'image[\'gallery\'] must be array ';
                }
            }
            $this->_param['image'] = $image;
        }
        // 选填 多语言
        $title = $post['title'];
        if( !empty($title) && is_array($title) ) {
            $this->_param['title'] = $title;
        }
        // 选填 多语言
        $meta_keywords = $post['meta_keywords'];
        if( !empty($meta_keywords) && is_array($meta_keywords) ) {
            $this->_param['meta_keywords'] = $meta_keywords;
        }
        // 选填 多语言
        $meta_description = $post['meta_description'];
        if( !empty($meta_description) && is_array($meta_description) ) {
            $this->_param['meta_description'] = $meta_description;
        }
        // 属性组属性，这里没有做强制的必填判断，有值就会加入 $this->_param 中。
        $attrInfo = Yii::$service->product->getGroupAttrInfo($attr_group);
        if (is_array($attrInfo) && !empty($attrInfo)) {
            
            foreach($attrInfo as $attrName => $info){
                if (isset($post[$attrName]) && $post[$attrName]) {
                    $attrVal = $post[$attrName];
                    if (isset($info['display']['type']) && $info['display']['type'] === 'select') {
                        $selectArr = $info['display']['data'];
                        if (!is_array($selectArr) || empty($selectArr)) {
                            $this->_error[] = 'GroutAttr:'.$attrName.' config is empty ,you must reConfig it';
                        }
                        $allowValArr = array_keys($selectArr);
                        if (is_array($allowValArr) && in_array($attrVal,$allowValArr)) {
                            $this->_param[$attrName] = $attrVal;
                        } else {
                            $this->_error[] = '['.$attrName.':'.$attrVal.'] must be in '.implode(',',$allowValArr);
                        }
                    } else {
                        $this->_param[$attrName] = $attrVal;
                    }
                }
            }
        } 
    }
    
    public function insertByPost($post = []){ 
        if (empty($post)) {
            $post = Yii::$app->request->post();
        }
        $this->checkPostDataRequireAndInt($post);
        if (!empty($this->_error)) {
            return [
                'code'    => 400,
                'message' => '',
                'error'  => $this->_error,
            ];
        }
        
        Yii::$service->product->addGroupAttrs($this->_param['attr_group']);
        $originUrlKey   = 'catalog/product/index';
        $saveData       = Yii::$service->product->save($this->_param, $originUrlKey);
        $errors         = Yii::$service->helper->errors->get();
        if (!$errors) {
            $saveData = $saveData->attributes;
            if(isset($saveData['_id'])){
                $saveData['id'] = (string)$saveData['_id'];
                unset($saveData['_id']);
            }
            return [
                'code'    => 200,
                'message' => 'add product success',
                'data'    => [
                    'addData' => $saveData,
                ]
            ];
        } else {
            return [
                'code'    => 400,
                'message' => 'save category fail',
                'data'    => [
                    'error' => $errors,
                ],
            ];
        }
    }
    
    
    
    
    
    
    
    
}