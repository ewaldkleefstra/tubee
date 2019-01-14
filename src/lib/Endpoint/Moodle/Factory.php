<?php

declare(strict_types=1);

/**
 * tubee.io
 *
 * @copyright   Copryright (c) 2017-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Tubee\Endpoint\Moodle;

use Psr\Log\LoggerInterface;
use Tubee\Collection\CollectionInterface;
use Tubee\Endpoint\EndpointInterface;
use Tubee\Endpoint\Image as ImageEndpoint;
use Tubee\Storage\Factory as StorageFactory;
use Tubee\Workflow\Factory as WorkflowFactory;

class Factory
{
    /**
     * Build instance.
     */
    public static function build(array $resource, CollectionInterface $collection, WorkflowFactory $workflow, LoggerInterface $logger): EndpointInterface
    {
        $storage = StorageFactory::build($resource['storage'], $logger);

        return new ImageEndpoint($resource['name'], $resource['type'], $resource['file'], $storage, $collection, $workflow, $logger, $resource);
    }
}
