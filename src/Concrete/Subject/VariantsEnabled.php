<?php

namespace Concrete\Package\CommunityStoreBulkProductUpdating\Subject;

use Concrete\Package\CommunityStore\Src\CommunityStore\Product\Product;
use Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductVariation\ProductVariation;
use Concrete\Package\CommunityStoreBulkProductUpdating\Subject;
use Concrete\Package\CommunityStoreBulkProductUpdating\UI;
use RuntimeException;

defined('C5_EXECUTE') or die('Access Denied.');

final class VariantsEnabled extends Subject
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Package\CommunityStoreBulkProductUpdating\Subject::getHandle()
     */
    public function getHandle()
    {
        return 'enabled-variants';
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Package\CommunityStoreBulkProductUpdating\Subject::getName()
     */
    public function getName()
    {
        return t('Enabled/Disabled variants');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Package\CommunityStoreBulkProductUpdating\Subject::createVueComponentHtml()
     */
    public function createVueComponentHtml(UI $ui)
    {
        $enabledText = t('Enabled');
        $disabledText = t('Disabled');
        
        return <<<EOT
<script type="text/x-template" id="cs-bpu-template-enabled-variants">
    <a href="#" v-on:click.prevent="toggle">
        <span v-if="enabled" class="badge text-bg-success">{$enabledText}</span>
        <span v-else class="badge text-bg-danger">{$disabledText}</span>
    </a>
</script>
EOT
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Package\CommunityStoreBulkProductUpdating\Subject::createVueComponentJavascript()
     */
    public function createVueComponentJavascript()
    {
        return <<<'EOT'
Vue.component('csBpuComponentEnabledVariants', {
    template: '#cs-bpu-template-enabled-variants',
    data() {
        return {
            displayEnabled: null,
        };
    },
    props: {
        disabled: {
            type: Boolean,
            required: true,
        },
        enabled: {
            type: Boolean,
            required: true,
        },
    },
    mounted() {
        this.$nextTick(() => {
            this.displayEnabled = this.enabled;
        });
    },
    watch: {
        enabled() {
            this.$nextTick(() => {
                this.displayEnabled = this.enabled;
            });
        },
    },
    methods: {
        toggle() {
            if (this.disabled) {
                return;
            }
            this.$emit('change', {enabled: !this.displayEnabled});
        },
    },
});

EOT
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Package\CommunityStoreBulkProductUpdating\Subject::insertVueComponentElement()
     */
    public function insertVueComponentElement()
    {
        return <<<'EOT'
<cs-bpu-component-enabled-variants
    v-bind:disabled="record.busy"
    v-bind:enabled="record.subjectData.enabled"
    v-on:change="updateRecord(record, $event)"
/>
EOT
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Package\CommunityStoreBulkProductUpdating\Subject::normalizeReceivedSubjectData()
     */
    public function normalizeReceivedSubjectData($item, array $rawData)
    {
        if (!isset($rawData['enabled'])) {
            return null;
        }
        if ($rawData['enabled'] === true || $rawData['enabled'] === 'true') {
            $enabled = true;
        } elseif ($rawData['enabled'] === false || $rawData['enabled'] === 'false') {
            $enabled = false;
        } else {
            return null;
        }

        return [
            'enabled' => $enabled,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Package\CommunityStoreBulkProductUpdating\Subject::shouldReturnProductForSearch()
     */
    protected function shouldReturnProductForSearch(Product $product)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Package\CommunityStoreBulkProductUpdating\Subject::shouldReturnProductVariationForSearch()
     */
    protected function shouldReturnProductVariationForSearch(ProductVariation $productVariation)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Package\CommunityStoreBulkProductUpdating\Subject::serializeProductData()
     */
    protected function serializeProductData(Product $product)
    {
        throw new RuntimeException('This method should NOT be called');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Package\CommunityStoreBulkProductUpdating\Subject::serializeProductVariationData()
     */
    protected function serializeProductVariationData(ProductVariation $productVariation)
    {
        return [
            'enabled' => !$productVariation->getVariationDisabled(),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Package\CommunityStoreBulkProductUpdating\Subject::updateProductData()
     */
    protected function updateProductData(Product $product, array $newData)
    {
        throw new RuntimeException('This method should NOT be called');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Package\CommunityStoreBulkProductUpdating\Subject::updateProductVariationData()
     */
    protected function updateProductVariationData(ProductVariation $productVariation, array $newData)
    {
        $productVariation->setVariationDisabled(!$newData['enabled']);
    }
}
