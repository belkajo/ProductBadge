<?php

declare(strict_types=1);

namespace Belkajo\ProductBadge\Plugin\ProductNamePlugin;

use Belkajo\ProductBadge\Model\BadgeLabelResolver;
use Belkajo\ProductBadge\Setup\Patch\Data\AddBadgeAttribute;
use Magento\Catalog\Model\Product;

class RenderProductBadge
{
    public function __construct(private BadgeLabelResolver $badgeLabelResolver)
    {
    }

    /**
     * @param Product $product
     * @param string|null $result
     * @return string|null
     */
    public function afterGetName(Product $product, ?string $result): ?string
    {
        if ($result === null || $result === '') {
            return $result;
        }

        $badge = $this->badgeLabelResolver->getLabel($product->getData(AddBadgeAttribute::ATTRIBUTE_CODE));
        if ($badge === null) {
            return $result;
        }

        return sprintf('%s [%s]', $result, $badge);
    }
}
