<?php

declare(strict_types=1);

namespace Giiken\Entity\Community;

enum SovereigntyProfile: string
{
    case Local = 'local';
    case SelfHosted = 'self_hosted';
    case Northops = 'northops';
}
