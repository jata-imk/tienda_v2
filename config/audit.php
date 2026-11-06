<?php

use App\Models;

return [
    // Activación por módulo para despliegues por etapas. Nunca se controla desde HTTP.
    'modules' => array_filter(explode(',', env('AUDIT_MODULES', 'security,users,inventory,orders,products,catalogs,configuration,documents'))),
    'models' => [
        Models\User::class => ['user', 'id_user_type,first_name,last_name,user_name,email,status'],
        Models\UserType::class => ['user_type', 'name,code,status'],
        Models\UserSession::class => ['user_session', 'id_user,expires_at,revoked_at'],
        Models\Product::class => ['product', 'id_main_category,id_size_group,key,name,description,code_bar,image,image_thumb,price,cost,stock_control,discount,type_iva,rate_iva,quota_iva,isr,imp_esp,status'],
        Models\ProductVariant::class => ['product_variant', 'id_product,id_size,id_color,sku,code_bar,stock,status'],
        Models\ProductImage::class => ['product_image', 'id_product,id_color,path,path_thumb'],
        Models\Category::class => ['category', 'key,name,description,status'],
        Models\SizeGroup::class => ['size_group', 'key,name,description,status'],
        Models\Size::class => ['size', 'id_size_group,name,sort_order,status'],
        Models\Color::class => ['color', 'name,hex_color,status'],
        Models\Currency::class => ['currency', 'name,code,symbol,exchange_rate,status'],
        Models\CompanyInfo::class => ['company_info', 'name,rfc,legal_name,tax_regime,id_currency,logo,street,external_number,cross_street_one,cross_street_two,postal_code,neighborhood,city,stock_control,quantity_integers,quantity_decimals,grid_settings,status'],
        Models\InventoryMovement::class => ['inventory_movement', 'id_product_variant,movement_type,quantity,previous_stock,new_stock,reference_type,reference_id,notes,id_user'],
        Models\Order::class => ['order', 'key,folio,status,id_currency,exchange_rate,total_items,total_amount,supplier_name,supplier_details,shipping_address,notes,date_order,date_expected,date_received,id_user'],
        Models\OrderDetail::class => ['order_detail', 'id_order,id_product_variant,quantity_ordered,quantity_received,unit_cost,subtotal,notes'],
        Models\OrderStatusHistory::class => ['order_status_history', 'id_order,previous_status,new_status,id_user,notes'],
    ],
];
