<?php

declare(strict_types=1);

namespace App\Entity;

interface HasCommunity
{
    public function getCommunityId(): string;
}
