<?php

interface IUser
{
    /**
     * Constructor.
     * @param int $id user unique id
     */
    public function __construct(int $id);

    public function getId(): int;
}