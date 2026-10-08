<?php

declare(strict_types=1);

namespace Belkajo\ProductBadge\Model;

use Belkajo\ProductBadge\Setup\Patch\Data\AddBadgeAttribute;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;


class BadgeLabelResolver
{

    private array $labels = [];

    public function __construct(
        private EavConfig $eavConfig,
        private CollectionFactory $optionCollectionFactory,
        private StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param int|string|null $value Raw option id stored in the badge attribute.
     * @return string|null Label of the current store view, or null when no badge is set.
     * @throws NoSuchEntityException
     */
    public function getLabel(int|string|null $value): ?string
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        return $this->getLabelsForStore((int)$this->storeManager->getStore()->getId())[(int)$value] ?? null;
    }

    /**
     * @param int $storeId
     * @return array<int, string>
     */
    private function getLabelsForStore(int $storeId): array
    {
        if (!isset($this->labels[$storeId])) {
            $this->labels[$storeId] = $this->loadLabels($storeId);
        }

        return $this->labels[$storeId];
    }

    /**
     * @param int $storeId
     * @return array<int, string>
     * @throws LocalizedException
     */
    private function loadLabels(int $storeId): array
    {
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, AddBadgeAttribute::ATTRIBUTE_CODE);
        if (!$attribute || !$attribute->getAttributeId()) {
            return [];
        }

        $options = $this->optionCollectionFactory->create()
            ->setAttributeFilter($attribute->getAttributeId())
            ->setStoreFilter($storeId)
            ->toOptionArray();

        $labels = [];
        foreach ($options as $option) {
            if ($option['value'] === '' || $option['value'] === null) {
                continue;
            }
            $labels[(int)$option['value']] = (string)$option['label'];
        }

        return $labels;
    }
}
