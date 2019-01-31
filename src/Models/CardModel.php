<?php

namespace ValveSoftware\ArtifactDeckCode\Models;

class CardModel
{
    protected $id;
    protected $count;

    /**
     * Get ID
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set ID
     *
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * Get Count
     *
     * @return mixed
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Set Count
     *
     * @param mixed $count
     */
    public function setCount($count): void
    {
        $this->count = $count;
    }
}