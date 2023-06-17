<?php

namespace Concrete\Package\CommunityStoreBulkProductUpdating\Controller\SinglePage\Dashboard\Store\Products;

use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Database\Query\LikeBuilder;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\CommunityStore\Src\CommunityStore\Product\Product;
use Concrete\Package\CommunityStoreBulkProductUpdating\Cursor;
use Concrete\Package\CommunityStoreBulkProductUpdating\SubjectFactory;
use Concrete\Package\CommunityStoreBulkProductUpdating\UI;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;

defined('C5_EXECUTE') or die('Access Denied.');

class BulkUpdate extends DashboardPageController
{
    public function view()
    {
        $this->requireAsset('javascript', 'vue');
        $this->addHeaderItem(
            <<<'EOT'
<style>
[v-cloak] {
    display: none;
}
</style>
EOT
        );
        $this->set('subjects', $this->app->make(SubjectFactory::class)->getRegisteredSubjects());
        $this->set('ui', $this->app->make(UI::class));
        $this->set('pageSize', $this->getPageSize());
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function search()
    {
        if (!$this->token->validate('community_store_bulk_product_updating')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $subject = $this->app->make(SubjectFactory::class)->getRegisteredSubject($this->request->request->get('subject'));
        if ($subject === null) {
            throw new UserMessageException(t('Invalid parameters received.'));
        }
        $searchText = $this->request->request->get('searchText');
        $searchText = is_string($searchText) ? trim($searchText) : '';
        $enabled = $this->request->request->get('enabled');
        if ($enabled === '*') {
            $enabled = null;
        } elseif ($enabled === 'y') {
            $enabled = true;
        } elseif ($enabled === 'n') {
            $enabled = false;
        } else {
            throw new UserMessageException(t('Invalid parameters received.'));
        }
        $nextPageAt = $this->unserializeNextPageAt();
        $records = [];
        $pageSize = $this->getPageSize();
        for (;;) {
            $qb = $this->createSearchQuery($searchText, $enabled, $nextPageAt);
            $qb = $subject->patchSearchQuery($qb);
            $rows = $qb->getQuery()->getArrayResult();
            if ($rows === []) {
                $nextPageAt = null;
                break;
            }
            $productIDs = [];
            foreach ($rows as $row) {
                $productIDs[] = (int) array_shift($row);
            }
            $qb = $this->createResultsQuery($productIDs);
            $qb = $subject->patchResultsQuery($qb);
            $query = $qb->getQuery();
            $lastCursor = null;
            foreach ($query->execute() as $product) {
                /** @var \Concrete\Package\CommunityStore\Src\CommunityStore\Product\Product $product */
                $subject->processProductForSearch($product, $records, $nextPageAt, $lastCursor);
                if (count($records) > $pageSize) {
                    $nextPageAt = $records[$pageSize]['_cursor'];
                    array_splice($records, $pageSize);
                    break 2;
                }
            }
            $nextPageAt = $lastCursor === null ? null : $lastCursor->next();
            if ($nextPageAt === null || count($records) === $pageSize) {
                break;
            }
        }
        array_walk(
            $records,
            static function (array &$record) {
                unset($record['_cursor']);
            }
        );

        return $this->app->make(ResponseFactoryInterface::class)->json([
            'records' => $records,
            'nextPageAt' => $nextPageAt === null ? '' : json_encode($nextPageAt),
        ]);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function saveItem()
    {
        if (!$this->token->validate('community_store_bulk_product_updating')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $subjectHandle = $this->request->request->get('subject');
        $subject = $this->app->make(SubjectFactory::class)->getRegisteredSubject($subjectHandle);
        if ($subject === null) {
            throw new UserMessageException(t('Invalid parameters received.'));
        }
        $item = $subject->getItemByKey($this->request->request->get('itemKey'));
        if ($item === null) {
            throw new UserMessageException(t('Invalid parameters received.'));
        }
        $oldData = null;
        $newData = null;
        foreach ($this->request->request->all() as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            switch ($key) {
                case 'oldData':
                    $oldData = $subject->normalizeReceivedSubjectData($item, $value);
                    break;
                case 'newData':
                    $newData = $subject->normalizeReceivedSubjectData($item, $value);
                    break;
            }
        }
        if ($oldData === null || $newData === null) {
            throw new UserMessageException(t('Invalid parameters received.'));
        }
        $currentData = $subject->getItemData($item);
        if ($currentData !== $oldData) {
            throw new UserMessageException(
                t("The data stored in the database doesn't match the data you see in the page, maybe because a new order has been submitted.")
                . "\n" .
                t('You should reload the page in order to see the up-to-date data.')
            );
        }
        $subject->updateItemData($item, $newData);
        $responseData = [
            'subjectData' => $subject->getItemData($item),
        ];
        $this->app->make(EntityManagerInterface::class)->flush();

        return $this->app->make(ResponseFactoryInterface::class)->json($responseData);
    }

    /**
     * @param string $searchText
     * @param bool|null $enabled
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function createSearchQuery($searchText, $enabled, Cursor $startingAt = null)
    {
        $likeBuilder = $this->app->make(LikeBuilder::class);
        $em = $this->app->make(EntityManagerInterface::class);
        $qb = $em->createQueryBuilder();
        $expr = $qb->expr();
        if ($enabled === true) {
            $whereVariations = ' AND (pv.pvDisabled IS NULL OR pv.pvDisabled = 0)';
            $qb
                ->andWhere('p.pActive = 1')
                ->andWhere('p.pDateAvailableStart IS NULL OR p.pDateAvailableStart < :now')
                ->andWhere('p.pDateAvailableEnd IS NULL OR p.pDateAvailableEnd > :now')
                ->setParameter('now', date($em->getConnection()->getDatabasePlatform()->getDateTimeFormatString()))
             ;
        } elseif ($enabled === false) {
            $whereVariations = ' AND (pv.pvDisabled IS NOT NULL AND pv.pvDisabled = 1)';
            $qb
                ->andWhere($qb->expr()->orX(
                    'p.pActive IS NULL OR p.pActive = 0',
                    'p.pDateAvailableStart IS NOT NULL AND p.pDateAvailableStart >= :now',
                    'p.pDateAvailableEnd IS NOT NULL AND p.pDateAvailableEnd <= :now'
                ))
                ->setParameter('now', date($em->getConnection()->getDatabasePlatform()->getDateTimeFormatString()))
            ;
        } else {
            $whereVariations = '';
        }
        $qb
            ->from(Product::class, 'p')
            ->select('DISTINCT p.pID, p.pName')
            ->leftJoin('p.options', 'po')
            ->leftJoin('p.variations', 'pv', Expr\Join::WITH, 'p.pVariations = 1' . $whereVariations)
            ->addOrderBy('p.pName')
            ->addOrderBy('p.pID')
            ->setMaxResults($this->getPageSize() + 1)
        ;
        $searchChunks = $likeBuilder->splitKeywordsForLike($searchText);
        if ($searchChunks !== null) {
            $and = [];
            foreach ($likeBuilder->splitKeywordsForLike($searchText) as $index => $paramValue) {
                $paramName = "keyword{$index}";
                $qb->setParameter($paramName, $paramValue, TYPE::STRING);
                $and[] = $expr->orX(
                    $expr->like('p.pName', ":{$paramName}"),
                    $expr->like('p.pSKU', ":{$paramName}"),
                    $expr->like('p.pBarcode', ":{$paramName}"),
                    $expr->like('p.pDesc', ":{$paramName}"),
                    $expr->like('pv.pvSKU', ":{$paramName}"),
                    $expr->like('pv.pvBarcode', ":{$paramName}")
                );
            }
            $qb->andWhere($expr->andX()->addMultiple($and));
        }
        if ($startingAt !== null) {
            $qb
                ->setParameter('startingAtPName', $startingAt->pName)
                ->setParameter('startingAtPID', $startingAt->pID)
                ->andWhere(
                    $expr->orX(
                        'p.pName > :startingAtPName',
                        'p.pName = :startingAtPName AND p.pID >= :startingAtPID'
                    )
                )
            ;
        }

        return $qb;
    }

    /**
     * @param int[] $productIDs
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function createResultsQuery(array $productIDs)
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $qb = $em->createQueryBuilder();
        $cn = $em->getConnection();
        $qb
            ->from(Product::class, 'p')
            ->select('p, po, pv, pvo, pvoo')
            ->leftJoin('p.options', 'po')
            ->leftJoin('p.variations', 'pv')
            ->leftJoin('pv.options', 'pvo')
            ->leftJoin('pvo.option', 'pvoo')
            ->andWhere('p.pID IN (:productIDs)')->setParameter('productIDs', $productIDs, $cn::PARAM_INT_ARRAY)
            ->addOrderBy('p.pName')
            ->addOrderBy('p.pID')
        ;

        return $qb;
    }

    /**
     * @return \Concrete\Package\CommunityStoreBulkProductUpdating\Cursor|null
     */
    protected function unserializeNextPageAt()
    {
        return Cursor::unserialize($this->request->request->get('nextPageAt'));
    }

    /**
     * @return int
     */
    protected function getPageSize()
    {
        $pageSize = (int) $this->app->make(Repository::class)->get('community_store_bulk_product_updating::options.pageSize', 0);

        return $pageSize > 0 ? $pageSize : 20;
    }
}
