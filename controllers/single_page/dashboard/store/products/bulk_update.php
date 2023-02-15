<?php

namespace Concrete\Package\CommunityStoreBulkProductUpdating\Controller\SinglePage\Dashboard\Store\Products;

use CommunityStore\BulkProductUpdating\SubjectFactory;
use CommunityStore\BulkProductUpdating\UI;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Doctrine\ORM\EntityManagerInterface;

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
        if ($searchText === '') {
            throw new UserMessageException(t('Please specify the search text.'));
        }
        $query = $subject->createSearchQuery($searchText);
        $result = [
            'records' => [],
        ];
        foreach ($query->execute() as $product) {
            $subject->processProductForSearch($product, $result);
        }

        return $this->app->make(ResponseFactoryInterface::class)->json($result);
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
}
