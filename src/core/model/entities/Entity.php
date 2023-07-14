<?php

namespace tots\Entities;

abstract class Entity
{
    protected ?int $id;

    public function getId(): ?int
    {
        return $this->id;
    }
}
