<?php

namespace ValveSoftware\ArtifactDeckCode\Models;

class HeroModel
{
    protected $id;
    protected $turn;

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
     * Get Turn
     *
     * @return mixed
     */
    public function getTurn()
    {
        return $this->turn;
    }

    /**
     * Set Turn
     *
     * @param mixed $turn
     */
    public function setTurn($turn): void
    {
        $this->turn = $turn;
    }
}