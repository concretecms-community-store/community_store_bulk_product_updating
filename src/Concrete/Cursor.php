<?php

namespace Concrete\Package\CommunityStoreBulkProductUpdating;

use JsonSerializable;

// readonly
class Cursor implements JsonSerializable
{
    /**
     * @var string
     */
    public $pName;

    /**
     * @var int
     */
    public $pID;

    /**
     * @var int|null
     */
    public $pvSort;

    /**
     * @var int|null
     */
    public $pvID;

    /**
     * @param string $pName
     * @param int $pID
     * @param int|null $pvSort
     * @param int|null $pvID
     */
    public function __construct($pName, $pID, $pvSort = null, $pvID = null)
    {
        $this->pName = (string) $pName;
        $this->pID = (int) $pID;
        $this->pvSort = $pvSort === null ? null : (int) $pvSort;
        $this->pvID = $pvID === null ? null : (int) $pvID;
    }

    /**
     * @param string|mixed $str
     *
     * @return static|null
     */
    public static function unserialize($str)
    {
        if (!is_string($str) || $str === '' || $str === 'null') {
            return null;
        }
        $arr = @json_decode($str, true);
        if (!is_array($arr)) {
            return null;
        }
        if (!is_string($pName = array_get($arr, 'pName', null))) {
            return null;
        }
        if (!is_int($pID = array_get($arr, 'pID', null))) {
            return null;
        }
        if (isset($arr['pvSort'])) {
            if (!is_int($pvSort = array_get($arr, 'pvSort', null))) {
                return null;
            }
        } else {
            $pvSort = null;
        }
        if (isset($arr['pvID'])) {
            if (!is_int($pvID = array_get($arr, 'pvID', null))) {
                return null;
            }
        } else {
            $pvID = null;
        }

        return new static($pName, $pID, $pvSort, $pvID);
    }

    /**
     * @return static
     */
    public function next()
    {
        if ($this->pvSort === null) {
            return new static($this->pName, $this->pID + 1);
        }

        return new static($this->pName, $this->pID, $this->pvSort, $this->pID + 1);
    }

    /**
     * {@inheritdoc}
     *
     * @see \JsonSerializable::jsonSerialize()
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $result = [
            'pName' => $this->pName,
            'pID' => $this->pID,
        ];
        if ($this->pvSort !== null && $this->pvID !== null) {
            $result += [
                'pvSort' => $this->pvSort,
                'pvID' => $this->pvID,
            ];
        }

        return $result;
    }
}
