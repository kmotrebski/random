<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander;

use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\ESHelper;

class IndexTemplateStander implements DataStander
{
    /**
     * @var string $name template we are looking for
     */
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function isDataThere(ESHelper $helper): bool
    {
        return $helper->hasTemplate($this->name);
    }

    public function getData(ESHelper $helper) : array
    {
        return $helper->getTemplate2($this->name);
    }
}
