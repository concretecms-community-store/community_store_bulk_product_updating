<?php

namespace CommunityStore\BulkProductUpdating\Subject;

use CommunityStore\BulkProductUpdating\Subject;
use CommunityStore\BulkProductUpdating\UI;
use Concrete\Core\Database\Query\LikeBuilder;
use Concrete\Package\CommunityStore\Src\CommunityStore\Product\Product;
use Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductVariation\ProductVariation;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;

defined('C5_EXECUTE') or die('Access Denied.');

final class StockLevels extends Subject
{
    /**
     * {@inheritdoc}
     *
     * @see \CommunityStore\BulkProductUpdating\Subject::getHandle()
     */
    public function getHandle()
    {
        return 'stock-levels';
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityStore\BulkProductUpdating\Subject::getName()
     */
    public function getName()
    {
        return t('Stock Levels');
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityStore\BulkProductUpdating\Subject::createVueComponentHtml()
     */
    public function createVueComponentHtml(UI $ui)
    {
        return <<<EOT
<script type="text/x-template" id="cs-bpu-template-stock-levels">
    <div class="input-group input-group-sm" style="max-width: 400px">
        <input type="number" class="form-control" ref="input" v-bind:step="quantitySteps" min="0" v-bind:readonly="disabled" v-on:input="updateDisplayQuantity" v-on:keyup.enter.prevent="save" />
        <div class="input-group-btn" v-if="quantitySteps === Math.round(quantitySteps)">
            <button class="btn {$ui->defaultButton}" v-bind:disabled="disabled || displayQuantity === null" v-on:click.prevent="delta(-quantitySteps)">
                -{{ quantitySteps }}
            </button>
            <button class="btn {$ui->defaultButton}" v-bind:disabled="disabled || displayQuantity === null" v-on:click.prevent="delta(quantitySteps)">
                +{{ quantitySteps }}
            </button>
        </div>
        <span class="input-group-addon" v-if="quantityLabel !== ''">{{ quantityLabel }}</span>
        <div class="input-group-btn" v-bind:style="{visibility: canSave ? 'visible' : 'hidden'}">
            <button class="btn btn-primary" v-bind:disabled="!canSave" v-on:click.prevent="save">
                <i class="{$ui->faSave}" aria-hidden="true"></i>
            </button>
            <button class="btn btn-danger" v-bind:disabled="!canSave" v-on:click.prevent="revert">
                <i class="{$ui->faCancel}" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</script>
EOT
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityStore\BulkProductUpdating\Subject::createVueComponentJavascript()
     */
    public function createVueComponentJavascript()
    {
        return <<<'EOT'
Vue.component('csBpuComponentStockLevels', {
    template: '#cs-bpu-template-stock-levels',
    data() {
        return {
            beforeUnloadHook: null,
            displayQuantity: null,
        };
    },
    props: {
        disabled: {
            type: Boolean,
            required: true,
        },
        quantity: {
            type: Number,
            required: true,
        },
        quantitySteps: {
            type: Number,
            required: true,
        },
        quantityLabel: {
            type: String,
            required: true,
        },
    },
    mounted() {
        this.$nextTick(() => {
            this.$refs.input.value = this.quantity;
            this.updateDisplayQuantity();
        });
    },
    unmounted() {
        this.updateBeforeUnloadHook(false);
    },
    watch: {
        quantity() {
            this.$nextTick(() => {
                this.$refs.input.value = this.quantity;
                this.updateDisplayQuantity()
            });
        },
    },
    computed: {
        canSave() {
            return !this.disabled && this.displayQuantity !== null && this.displayQuantity !== this.quantity;
        }
    },
    methods: {
        updateDisplayQuantity() {
            this.displayQuantity = parseFloat(this.$refs.input.value);
            if (isNaN(this.displayQuantity) || this.displayQuantity < 0) {
                this.displayQuantity = null;
            }
            this.updateBeforeUnloadHook();
        },
        delta(amount) {
            if (this.disabled || this.displayQuantity === null) {
                return;
            }
            this.$refs.input.value = Math.max(0, this.displayQuantity + amount);
            this.updateDisplayQuantity();
        },
        revert() {
            if (this.disabled) {
                return;
            }
            this.$refs.input.value = this.quantity;
            this.updateDisplayQuantity();
        },
        save() {
            this.updateDisplayQuantity();
            if (this.disabled || this.displayQuantity === null || this.displayQuantity === this.quantity) {
                return;
            }
            this.$emit('change', {quantity: this.displayQuantity});
        },
        updateBeforeUnloadHook(force) {
            let enableHook;
            if (force === true || force === false) {
                enableHook = force;
            } else {
                enableHook = this.displayQuantity !== null && this.displayQuantity !== this.quantity;
            }
            if (enableHook) {
                if (this.beforeUnloadHook === null) {
                    this.beforeUnloadHook = (e) => {
                        e.preventDefault();
                        return e.returnValue = 'confirm';
                    };
                    window.addEventListener('beforeunload', this.beforeUnloadHook, {capture: true});
                }
            } else {
                if (this.beforeUnloadHook !== null) {
                    window.removeEventListener('beforeunload', this.beforeUnloadHook, {capture: true});
                    this.beforeUnloadHook = null;
                }
            }
        },
    },
});

EOT
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityStore\BulkProductUpdating\Subject::insertVueComponentElement()
     */
    public function insertVueComponentElement()
    {
        return <<<'EOT'
<cs-bpu-component-stock-levels
    v-bind:disabled="record.busy"
    v-bind:quantity="record.subjectData.quantity"
    v-bind:quantity-steps="record.quantitySteps"
    v-bind:quantity-label="record.quantityLabel"
    v-on:change="updateRecord(record, $event)"
/>
EOT
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityStore\BulkProductUpdating\Subject::createSearchQuery()
     */
    public function createSearchQuery($searchText)
    {
        $likeBuilder = $this->app->make(LikeBuilder::class);
        $em = $this->app->make(EntityManagerInterface::class);
        $qb = $em->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->setParameter('true', true, Type::BOOLEAN)
            ->setParameter('false', false, Type::BOOLEAN)
            ->setParameter('now', date($em->getConnection()->getDatabasePlatform()->getDateTimeFormatString()))
            ->select('p, po, pv, pvoi, pvoio')
            ->from(Product::class, 'p')
            ->leftJoin('p.options', 'po')
            ->leftJoin('p.variations', 'pv')
            ->leftJoin('pv.options', 'pvoi')
            ->leftJoin('pvoi.option', 'pvoio')
            ->leftJoin(
                'p.variations',
                'pvWhere',
                Expr\Join::WITH,
                'p.pVariations = :true AND (pvWhere.pvDisabled IS NULL OR pvWhere.pvDisabled = :false)'
            )
            ->andWhere('p.pActive = :true')
            ->andWhere('p.pDateAvailableStart IS NULL OR p.pDateAvailableStart < :now')
            ->andWhere('p.pDateAvailableEnd IS NULL OR p.pDateAvailableEnd > :now')
            ->addOrderBy('p.pName')
            ->addOrderBy('p.pSKU')
        ;
        $and = [];
        foreach ($likeBuilder->splitKeywordsForLike($searchText) as $index => $paramValue) {
            $paramName = "keyword{$index}";
            $qb->setParameter($paramName, $paramValue, TYPE::STRING);
            $and[] = $expr->orX(
                $expr->like('p.pName', ":{$paramName}"),
                $expr->like('p.pSKU', ":{$paramName}"),
                $expr->like('p.pBarcode', ":{$paramName}"),
                $expr->like('p.pDesc', ":{$paramName}"),
                $expr->like('pvWhere.pvSKU', ":{$paramName}"),
                $expr->like('pvWhere.pvBarcode', ":{$paramName}")
            );
        }
        $qb->andWhere($expr->andX()->addMultiple($and));

        return $qb->getQuery();
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityStore\BulkProductUpdating\Subject::normalizeReceivedSubjectData()
     */
    public function normalizeReceivedSubjectData($item, array $rawData)
    {
        if (!isset($rawData['quantity']) || !is_numeric($rawData['quantity'])) {
            return null;
        }

        return [
            'quantity' => (float) $rawData['quantity'],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityStore\BulkProductUpdating\Subject::shouldReturnProductForSearch()
     */
    protected function shouldReturnProductForSearch(Product $product)
    {
        if ($product->isUnlimited(true)) {
            return false;
        }
        foreach ($product->getOptions() as $option) {
            /** @var \Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductOption\ProductOption $option */
            if ($option->getRequired() && $option->getIncludeVariations()) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityStore\BulkProductUpdating\Subject::shouldReturnProductVariationForSearch()
     */
    protected function shouldReturnProductVariationForSearch(ProductVariation $productVariation)
    {
        return !$productVariation->getVariationDisabled() && !$productVariation->getVariationQtyUnlim();
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityStore\BulkProductUpdating\Subject::serializeProductData()
     */
    protected function serializeProductData(Product $product)
    {
        return [
            'quantity' => (float) $product->getStockLevel(),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityStore\BulkProductUpdating\Subject::serializeProductVariationData()
     */
    protected function serializeProductVariationData(ProductVariation $productVariation)
    {
        return [
            'quantity' => (float) $productVariation->getStockLevel(),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityStore\BulkProductUpdating\Subject::updateProductData()
     */
    protected function updateProductData(Product $product, array $newData)
    {
        $product->setQty($newData['quantity']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityStore\BulkProductUpdating\Subject::updateProductVariationData()
     */
    protected function updateProductVariationData(ProductVariation $productVariation, array $newData)
    {
        $productVariation->setVariationStockLevel($newData['quantity']);
    }
}
