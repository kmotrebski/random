<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests;

interface DataStander
{
    public function isDataThere(ESHelper $helper) : bool;

    /**
     * @param ESHelper $helper
     * @return mixed
     * @throws
     * @deprecated all data needed to evaluate a test should be in the
     * Stander object. So this method should be removed. There should be only
     * only one bool-type method.
     */
    public function getData(ESHelper $helper);
}
