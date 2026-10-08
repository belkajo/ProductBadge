<?php

declare(strict_types=1);

namespace Belkajo\ProductBadge\Model\Resolver;

use Belkajo\ProductBadge\Model\BadgeLabelResolver;
use Belkajo\ProductBadge\Setup\Patch\Data\AddBadgeAttribute;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockStatusRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class ProductDetails implements ResolverInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private StockStatusRepositoryInterface $stockStatusRepository,
        private BadgeLabelResolver $badgeLabelResolver
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $sku = (string)($args['sku'] ?? '');
        if ($sku === '') {
            throw new GraphQlInputException(__('Required parameter "sku" is missing.'));
        }

        $store = $context->getExtensionAttributes()->getStore();

        try {
            $product = $this->productRepository->get($sku, false, (int)$store->getId());
        } catch (NoSuchEntityException $e) {
            throw new GraphQlInputException(__('The product with the "%1" SKU does not exist.', $sku));
        }

        return [
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'price' => $product->getPrice() !== null ? (float)$product->getPrice() : null,
            'currency' => $store->getCurrentCurrencyCode(),
            'stock_status' => $this->getStockStatus((int)$product->getId()),
            'badge' => $this->badgeLabelResolver->getLabel($product->getData(AddBadgeAttribute::ATTRIBUTE_CODE)),
        ];
    }

    private function getStockStatus(int $productId): string
    {
        $stockStatus = $this->stockStatusRepository->get($productId);

        return (int)$stockStatus->getStockStatus() ? (string)__('In stock') : (string)__('Out of stock');
    }
}
