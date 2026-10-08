<?php

declare(strict_types=1);

namespace Belkajo\ProductBadge\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;


class AddBadgeStoreLabels implements DataPatchInterface
{

    private const STORE_LABELS = [
        'en' => [
            'New' => 'New',
            'Best Seller' => 'Best Seller',
            'Sale' => 'Sale',
        ],
        'fr' => [
            'New' => 'Nouveau',
            'Best Seller' => 'Meilleure vente',
            'Sale' => 'Promo',
        ],
        'de' => [
            'New' => 'Neu',
            'Best Seller' => 'Bestseller',
            'Sale' => 'Angebot',
        ],
    ];

    public function __construct(
        private ModuleDataSetupInterface $moduleDataSetup,
        private EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $attributeId = (int)$eavSetup->getAttributeId(Product::ENTITY, AddBadgeAttribute::ATTRIBUTE_CODE);

        if (!$attributeId) {
            throw new LocalizedException(
                __('The "%1" attribute is missing, cannot add store view labels.', AddBadgeAttribute::ATTRIBUTE_CODE)
            );
        }

        $storeIds = $this->getStoreIdsByCode();
        $options = $this->getOptions($attributeId);

        $values = [];
        $order = [];
        foreach (self::STORE_LABELS as $storeCode => $labels) {
            foreach ($labels as $defaultLabel => $localizedLabel) {
                if (!isset($options[$defaultLabel])) {
                    throw new LocalizedException(
                        __('The "%1" badge option is missing, cannot translate it.', $defaultLabel)
                    );
                }

                $optionId = $options[$defaultLabel]['option_id'];
                $values[$optionId][0] = $defaultLabel;
                $values[$optionId][$storeIds[$storeCode]] = $localizedLabel;
                $order[$optionId] = $options[$defaultLabel]['sort_order'];
            }
        }

        $eavSetup->addAttributeOption([
            'attribute_id' => $attributeId,
            'value' => $values,
            'order' => $order,
        ]);

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    private function getStoreIdsByCode(): array
    {
        $connection = $this->moduleDataSetup->getConnection();
        $select = $connection->select()
            ->from($this->moduleDataSetup->getTable('store'), ['code', 'store_id'])
            ->where('code IN (?)', array_keys(self::STORE_LABELS));

        $storeIds = [];
        foreach ($connection->fetchPairs($select) as $code => $storeId) {
            $storeIds[$code] = (int)$storeId;
        }

        $missing = array_diff(array_keys(self::STORE_LABELS), array_keys($storeIds));
        if ($missing) {
            throw new LocalizedException(
                __('Create the "%1" store view(s) before running this patch.', implode('", "', $missing))
            );
        }

        return $storeIds;
    }

    private function getOptions(int $attributeId): array
    {
        $connection = $this->moduleDataSetup->getConnection();
        $select = $connection->select()
            ->from(['o' => $this->moduleDataSetup->getTable('eav_attribute_option')], ['option_id', 'sort_order'])
            ->join(
                ['v' => $this->moduleDataSetup->getTable('eav_attribute_option_value')],
                'o.option_id = v.option_id',
                ['value']
            )
            ->where('o.attribute_id = ?', $attributeId)
            ->where('v.store_id = ?', 0);

        $options = [];
        foreach ($connection->fetchAll($select) as $row) {
            $options[$row['value']] = [
                'option_id' => (int)$row['option_id'],
                'sort_order' => (int)$row['sort_order'],
            ];
        }

        return $options;
    }

    public static function getDependencies()
    {
        return [
            AddBadgeAttribute::class,
        ];
    }

    public function getAliases()
    {
        return [];
    }
}
