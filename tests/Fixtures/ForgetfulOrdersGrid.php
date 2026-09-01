<?php

namespace CrewGrid\Tests\Fixtures;

/** A grid that starts every visit fresh, whatever the config says. */
class ForgetfulOrdersGrid extends OrdersGrid
{
    public ?bool $rememberView = false;
}
