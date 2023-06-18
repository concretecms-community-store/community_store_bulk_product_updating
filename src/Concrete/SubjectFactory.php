<?php

namespace Concrete\Package\CommunityStoreBulkProductUpdating;

use Concrete\Core\Application\Application;
use Punic\Comparer;

defined('C5_EXECUTE') or die('Access Denied.');

class SubjectFactory
{
    /**
     * @var \Concrete\Core\Application\Application
     */
    protected $app;

    /**
     * @var \Concrete\Package\CommunityStoreBulkProductUpdating\Subject[]
     */
    private $subjects = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->registerDefaultSubjects();
    }

    /**
     * @return $this
     */
    public function registerDefaultSubjects()
    {
        return $this->registerSubjects([
            $this->app->make(Subject\StockLevels::class),
            $this->app->make(Subject\ProductsEnabled::class),
            $this->app->make(Subject\VariantsEnabled::class),
        ]);
    }

    /**
     * @param \Concrete\Package\CommunityStoreBulkProductUpdating\Subject[] $subjects
     *
     * @return $this
     */
    public function registerSubjects(array $subjects)
    {
        foreach ($subjects as $subject) {
            $this->registerSubject($subject);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function registerSubject(Subject $subject)
    {
        $this->subjects[$subject->getHandle()] = $subject;

        return $this;
    }

    /**
     * Get a subject given its handle.
     *
     * @param string|mixed $handle
     *
     * @return \Concrete\Package\CommunityStoreBulkProductUpdating\Subject|null
     */
    public function getRegisteredSubject($handle)
    {
        return is_string($handle) && isset($this->subjects[$handle]) ? $this->subjects[$handle] : null;
    }

    /**
     * @return \Concrete\Package\CommunityStoreBulkProductUpdating\Subject[]
     */
    public function getRegisteredSubjects()
    {
        $result = array_values($this->subjects);
        $cmp = new Comparer();
        usort(
            $result,
            static function (Subject $a, Subject $b) use ($cmp) {
                return $cmp->compare($a->getName(), $b->getName());
            }
        );

        return $result;
    }
}
