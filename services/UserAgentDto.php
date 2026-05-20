<?php

declare(strict_types=1);

namespace app\services;

readonly class UserAgentDto
{
    public function __construct(
        public string  $os,
        public ?string $architecture,
        public string  $browser,
    )
    {
    }
}
