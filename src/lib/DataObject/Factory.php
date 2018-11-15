<?php

declare(strict_types=1);

/**
 * tubee.io
 *
 * @copyright   Copryright (c) 2017-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Tubee\DataObject;

use Generator;
use MongoDB\BSON\ObjectIdInterface;
use MongoDB\Database;
use Psr\Log\LoggerInterface;
use Tubee\DataObject;
use Tubee\DataObjectRelation\Factory as DataObjectRelationFactory;
use Tubee\DataType\DataTypeInterface;
use Tubee\Resource\Factory as ResourceFactory;

class Factory extends ResourceFactory
{
    /**
     * Data object relation factory.
     *
     * @var DataObjectRelationFactory
     */
    protected $relation_factory;

    /**
     * Initialize.
     */
    public function __construct(Database $db, DataObjectRelationFactory $relation_factory, LoggerInterface $logger)
    {
        $this->relation_factory = $relation_factory;
        parent::__construct($db, $logger);
    }

    /**
     * Has mandator.
     */
    public function has(DataTypeInterface $mandator, string $name): bool
    {
        return $this->db->datatypes->count([
            'name' => $name,
            'mandator' => $mandator->getName(),
        ]) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getObjectHistory(DataTypeInterface $datatype, ObjectIdInterface $id, ?array $filter = null, ?int $offset = null, ?int $limit = null): Generator
    {
        $pipeline = [
            ['$match' => ['_id' => $id]],
            ['$unwind' => '$history'],
        ];

        $count = $pipeline;

        if ($filter !== null) {
            $pipeline[] = ['$match' => $filter];
        }

        if ($offset !== null) {
            $pipeline[] = ['$skip' => $offset];
        }

        if ($limit !== null) {
            $pipeline[] = ['$limit' => $limit];
        }

        $current = $this->getOne($datatype, ['_id' => $id]);
        yield $current;

        foreach ($this->db->{$datatype->getCollection()}->aggregate($pipeline) as $version) {
            yield $version['version'] => $this->build(array_merge($current->toArray(), $version['history']), $datatype);
        }

        $count[] = ['$count' => 'count'];
        //return $this->db->{$datatype->getCollection()}->aggregate($count)['count'];
        return 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getOne(DataTypeInterface $datatype, array $filter, bool $include_dataset = true, int $version = 0): DataObjectInterface
    {
        $pipeline = $this->preparePipeline($filter, $include_dataset, $version);

        $this->logger->debug('find one object with pipeline [{pipeline}] from ['.$datatype->getCollection().']', [
            'category' => get_class($this),
            //'pipeline' => $pipeline,
        ]);

        $cursor = $this->db->{$datatype->getCollection()}->aggregate($pipeline, [
            'allowDiskUse' => true,
        ]);
        $objects = iterator_to_array($cursor);

        if (count($objects) === 0) {
            throw new Exception\NotFound('data object '.json_encode($filter).' not found in collection '.$datatype->getCollection());
        }
        if (count($objects) > 1) {
            throw new Exception\MultipleFound('multiple data objects found with filter '.json_encode($filter).' in collection '.$datatype->getCollection());
        }

        return $this->build(array_shift($objects), $datatype);
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(DataTypeInterface $datatype, ?array $query = null, bool $include_dataset = true, ?int $offset = null, ?int $limit = null, ?array $sort = null): Generator
    {
        return $this->getAllFrom($this->db->{$datatype->getCollection()}, $query, $offset, $limit, $sort, function (array $resource) use ($datatype) {
            return $this->build($resource, $datatype);
        });

        /*$pipeline = [];
        if ($include_dataset === true) {
            //$pipeline = $this->dataset;
            if (count($filter) > 0) {
                array_unshift($pipeline, ['$match' => $filter]);
            }
        } elseif (count($filter) > 0) {
            $pipeline = [['$match' => $filter]];
        }

        $found = 0;

        if ($offset !== null) {
            array_unshift($pipeline, ['$skip' => $offset]);
        }

        if ($limit !== null) {
            $pipeline[] = ['$limit' => $limit];
        }

        if (count($pipeline) === 0) {
            $this->logger->debug('empty pipeline given (no dataset configuration), collect all objects from ['.$datatype->getCollection().'] instead', [
                'category' => get_class($this),
            ]);
            $cursor = $this->db->{$datatype->getCollection()}->find();
        } else {
            $this->logger->debug('aggregate pipeline ['.json_encode($pipeline).'] on collection ['.$datatype->getCollection().']', [
                'category' => get_class($this),
            ]);
            $cursor = $this->db->{$datatype->getCollection()}->aggregate($pipeline, [
                'allowDiskUse' => true,
            ]);
        }

        foreach ($cursor as $object) {
            ++$found;
            yield (string) $object['_id'] => $this->build($object, $datatype);
        }

        if ($found === 0) {
            $this->logger->warning('found no data objects in collection ['.$datatype->getCollection().'] with aggregation pipeline ['.json_encode($pipeline).']', [
                'category' => get_class($this),
            ]);
        } else {
            $this->logger->info('found ['.$found.'] data objects in collection ['.$datatype->getCollection().'] with aggregation pipeline ['.json_encode($pipeline).']', [
                'category' => get_class($this),
            ]);
        }

        return $this->db->{$datatype->getCollection()}->count();*/
    }

    /**
     * {@inheritdoc}
     */
    public function create(DataTypeInterface $datatype, array $object, bool $simulate = false, ?array $endpoints = null): ObjectIdInterface
    {
        $datatype->getSchema()->validate($object);

        $object = [
            'data' => $object,
            'endpoints' => $endpoints,
        ];

        return $this->addTo($this->db->{$datatype->getCollection()}, $object, $simulate);
    }

    /**
     * {@inheritdoc}
     */
    public function update(DataTypeInterface $datatype, DataObjectInterface $object, array $data, bool $simulate = false, ?array $endpoints = null): bool
    {
        $datatype->getSchema()->validate($data);

        $data = ['data' => $data];

        if ($endpoints !== null) {
            $data['endpoints'] = $endpoints;
        }

        return $this->updateIn($this->db->{$datatype->getCollection()}, $object, $data, $simulate);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteOne(DataTypeInterface $datatype, ObjectIdInterface $id, bool $simulate = false): bool
    {
        return $this->deleteFrom($this->db->{$datatype->getCollection()}, $id, $simulate);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll(DataTypeInterface $datatype, ObjectIdInterface $id, bool $simulate = false): bool
    {
        $this->logger->info('delete object ['.$id.'] from ['.$datatype->getCollection().']', [
            'category' => get_class($this),
        ]);
    }

    /**
     * Change stream.
     */
    public function watch(DataTypeInterface $datatype, ?ObjectIdInterface $after = null, bool $existing = true, ?array $query = null, ?int $offset = null, ?int $limit = null, ?array $sort = null): Generator
    {
        return $this->watchFrom($this->db->{$datatype->getCollection()}, $after, $existing, $query, function (array $resource) use ($datatype) {
            return $this->build($datatype, $resource);
        }, $offset, $limit, $sort);
    }

    /**
     * Build.
     */
    public function build(array $resource, DataTypeInterface $datatype): DataObjectInterface
    {
        return $this->initResource(new DataObject($resource, $datatype, $this->relation_factory));
    }

    /**
     * Prepare pipeline.
     */
    protected function preparePipeline(array $filter, bool $include_dataset = true, int $version = 0): array
    {
        $pipeline = [];

        /*if ($include_dataset === true) {
            $pipeline = $this->dataset;
            array_unshift($pipeline, $filter);
        } else {
            $pipeline = [['$match' => $filter]];
        }*/
        $pipeline = [['$match' => $filter]];

        if ($version === 0) {
            $pipeline[] = [
                '$project' => ['history' => false],
            ];
        } else {
            $pipeline[] = [
                '$unwind' => ['path' => '$history'],
            ];

            $pipeline[] = [
                '$match' => ['history.version' => $version],
            ];

            $pipeline[] = [
                '$replaceRoot' => ['newRoot' => '$history'],
            ];
        }

        return $pipeline;
    }
}
