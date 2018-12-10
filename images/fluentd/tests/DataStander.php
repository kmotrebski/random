<?php declare(strict_types=1);

namespace Images\Fluentd\Tests;

interface DataStander
{
    public function isDataThere(ESHelper $helper) : bool;

    /**
     * @param ESHelper $helper
     * @return mixed
     * @throws
     */
    public function getData(ESHelper $helper);
}
