<?php

namespace Concrete\Package\CommunityStoreBulkProductUpdating;

use Concrete\Core\Database\EntityManager\Provider\ProviderInterface;
use Concrete\Core\Package\Package;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends Package implements ProviderInterface
{
    protected $pkgHandle = 'community_store_bulk_product_updating';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::$appVersionRequired
     */
    protected $appVersionRequired = '8.5';

    protected $pkgVersion = '1.0.0';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::$packageDependencies
     */
    protected $packageDependencies = [
        'community_store' => true,
    ];

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::getPackageName()
     */
    public function getPackageName()
    {
        return t('Community Store Bulk Product Updating');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::getPackageDescription()
     */
    public function getPackageDescription()
    {
        return t('Easily update details of Community Store products');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Database\EntityManager\Provider\ProviderInterface::getDrivers()
     */
    public function getDrivers()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::install()
     */
    public function install()
    {
        parent::install();
        $this->installXml();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::upgrade()
     */
    public function upgrade()
    {
        parent::upgrade();
        $this->installXml();
    }

    /**
     * Install/update data from install XML file.
     */
    private function installXml()
    {
        $this->installContentFile('install.xml');
    }
}
