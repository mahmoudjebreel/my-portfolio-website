<?php

namespace Database\Factories\Concerns;

use LogicException;

/**
 * Model factories produce fake data (including users with a known password),
 * so they may only run in the automated test environment — never from
 * `db:seed`, `migrate --seed` or tinker against a real database.
 */
trait TestingOnly
{
    public function configure()
    {
        if (! app()->environment('testing')) {
            throw new LogicException(sprintf(
                '%s is test-only: model factories may only run in the "testing" environment (current: "%s").',
                static::class,
                app()->environment(),
            ));
        }

        return parent::configure();
    }
}
