<?php

namespace CommunityStore\BulkProductUpdating;

use Concrete\Core\Config\Repository\Repository;

defined('C5_EXECUTE') or die('Access Denied.');

final class UI
{
    /**
     * Major concrete5 / ConcreteCMS version.
     *
     * @var int
     * @readonly
     */
    public $majorVersion;

    /**
     * @var string
     * @readonly
     */
    public $faSearch;

    /**
     * @var string
     * @readonly
     */
    public $faSave;

    /**
     * @var string
     * @readonly
     */
    public $faCancel;

    /**
     * @var string
     * @readonly
     */
    public $defaultButton;

    public function __construct(Repository $config)
    {
        $version = $config->get('concrete.version');
        list($majorVersion) = explode('.', $version, 2);
        $this->majorVersion = (int) $majorVersion;
        if ($this->majorVersion >= 9) {
            $this->initializeV9();
        } else {
            $this->initializeV8();
        }
    }

    /**
     * @see https://fontawesome.com/v5/search?m=free
     * @see https://getbootstrap.com/docs/5.2
     */
    private function initializeV9()
    {
        $this->faSearch = 'fas fa-search';
        $this->faSave = 'far fa-save';
        $this->faCancel = 'fas fa-times';
        $this->defaultButton = 'btn-secondary';
    }

    /**
     * @see https://fontawesome.com/v4/icons/
     * @see https://getbootstrap.com/docs/3.4/
     */
    private function initializeV8()
    {
        $this->faSearch = 'fa fa-search';
        $this->faSave = 'fa fa-floppy-o';
        $this->faCancel = 'fa fa-times';
        $this->defaultButton = 'btn-default';
    }
}
