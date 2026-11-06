<?php

namespace App\Support;

use Illuminate\Http\Request;

final class AuditActions
{
    // null clasifica expresamente una lectura. Una ruta nueva debe añadirse aquí.
    public const CONTROLLERS = [
        'AuthController' => ['security', 'user', ['login' => 'auth.login', 'logout' => 'auth.logout']],
        'UserController' => ['users', 'user', ['index' => null, 'show' => null, 'query' => null, 'store' => 'users.created', 'update' => 'users.updated', 'destroy' => 'users.deactivated']],
        'CatalogController' => ['catalogs', null, ['index' => null]],
        'CategoryController' => ['catalogs', 'category', ['index' => null, 'show' => null, 'query' => null, 'store' => 'categories.created', 'update' => 'categories.updated', 'destroy' => 'categories.deactivated']],
        'SizeGroupController' => ['catalogs', 'size_group', ['index' => null, 'show' => null, 'query' => null, 'store' => 'size_groups.created', 'update' => 'size_groups.updated', 'destroy' => 'size_groups.deactivated']],
        'SizeController' => ['catalogs', 'size', ['index' => null, 'show' => null, 'query' => null, 'store' => 'sizes.created', 'update' => 'sizes.updated', 'destroy' => 'sizes.deactivated']],
        'ColorController' => ['catalogs', 'color', ['index' => null, 'show' => null, 'query' => null, 'store' => 'colors.created', 'update' => 'colors.updated', 'destroy' => 'colors.deactivated']],
        'CurrencyController' => ['configuration', 'currency', ['index' => null, 'show' => null, 'query' => null, 'store' => 'currencies.created', 'update' => 'currencies.updated', 'destroy' => 'currencies.deactivated']],
        'CompanyInfoController' => ['configuration', 'company_info', ['show' => null, 'store' => 'company_info.created', 'update' => 'company_info.updated', 'patch' => 'company_info.updated']],
        'ProductController' => ['products', 'product', ['index' => null, 'show' => null, 'query' => null, 'nextKey' => null, 'store' => 'products.created', 'update' => 'products.updated', 'destroy' => 'products.deactivated', 'uploadImage' => 'products.image_changed', 'deleteImage' => 'products.image_deleted']],
        'ProductVariantController' => ['products', 'product_variant', ['index' => null, 'query' => null, 'store' => 'variants.created', 'update' => 'variants.updated', 'destroy' => 'variants.deactivated']],
        'ProductColorImageController' => ['products', 'product_image', ['index' => null, 'store' => 'product_images.created', 'destroy' => 'product_images.deleted']],
        'InventoryMovementController' => ['inventory', 'inventory_movement', ['index' => null, 'query' => null, 'store' => 'inventory.movements_registered']],
        'OrderController' => ['orders', 'order', ['query' => null, 'show' => null, 'history' => null, 'store' => 'orders.created', 'update' => 'orders.updated', 'confirm' => 'orders.confirmed', 'cancel' => 'orders.cancelled', 'receive' => 'orders.received', 'pdf' => 'orders.pdf_generated']],
        'DashboardController' => ['configuration', null, ['index' => null, 'query' => null]],
        'AuditEventController' => ['security', null, ['index' => null, 'query' => null, 'show' => null, 'changes' => null]],
    ];

    public static function definition(string $handler): ?array
    {
        [$controller, $method] = array_pad(explode('@', $handler), 2, '');
        $entry = self::CONTROLLERS[class_basename($controller)] ?? null;

        return $entry && array_key_exists($method, $entry[2])
            ? ['module' => $method === 'pdf' ? 'documents' : $entry[0], 'entity' => $entry[1], 'action' => $entry[2][$method]]
            : null;
    }

    public static function forRequest(Request $request): array
    {
        $handler = $request->route()?->getActionName() ?? '';
        $definition = self::definition($handler) ?? ['module' => 'security', 'entity' => null, 'action' => null];
        if (in_array(class_basename(explode('@', $handler)[0]), ['ProductController', 'CategoryController', 'SizeGroupController'], true)
            && str_ends_with($handler, '@update') && $request->boolean('dryRun')) {
            $definition['action'] = null;
        }

        return $definition;
    }
}
