<?php

declare(strict_types=1);

/**
 * tubee
 *
 * @copyright   Copryright (c) 2017-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Tubee\Testsuite\Unit\Endpoint;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tubee\AttributeMap\AttributeMapInterface;
use Tubee\Collection\CollectionInterface;
use Tubee\Endpoint\EndpointInterface;
use Tubee\Endpoint\Exception\AttributeNotResolvable as AttributeNotResolvable;
use Tubee\Endpoint\SqlSrvUsers;
use Tubee\Endpoint\SqlSrvUsers\Exception\InvalidQuery as InvalidQuery;
use Tubee\Endpoint\SqlSrvUsers\Wrapper;
use Tubee\Endpoint\SqlSrvUsers\Wrapper as SqlSrvWrapper;
use Tubee\EndpointObject\EndpointObjectInterface;
use Tubee\Workflow\Factory as WorkflowFactory;

class SqlSrvUsersTest extends TestCase
{
    public function testSetupDefaultSettings()
    {
        $wrapper = $this->createMock(SqlSrvWrapper::class);
        $sqlSrvUsers = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $wrapper, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class));
        $sqlSrvUsers->setup();
    }

    public function testShutdown()
    {
        $wrapper = $this->createMock(SqlSrvWrapper::class);
        $sqlSrvUsers = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $wrapper, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class));
        $sqlSrvUsers->shutdown();
    }

    public function testTransformAndQuery()
    {
        $sqlSrvUsers = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $this->createMock(Wrapper::class), $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class));

        $query = [
            '$and' => [
                ['foo' => 'bar', 'foobar' => 'foobar'],
                ['bar' => 'foo', 'barf' => 'barf'],
            ],
        ];

        $efilter = '(foo= ? AND foobar= ?) AND (bar= ? AND barf= ?)';
        $evalues = ['bar', 'foobar', 'foo', 'barf'];

        list($filter, $values) = $sqlSrvUsers->transformQuery($query);
        $this->assertSame($efilter, $filter);
        $this->assertSame($evalues, $values);
    }

    public function testCountNoFilterReturnsTotal()
    {
        $mock = $this->createMock(Wrapper::class);
        $mock->method('getQueryResult')->willReturn([
            ['count' => 1],
        ]);
        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);
        $result = $ep->count();
        $this->assertEquals(1, $result);
    }

    public function testGetAllNoFilterReturnsTotal()
    {
        $mock = $this->createMock(Wrapper::class);
        $mock->method('getQueryResult')->willReturn([
            ['foo' => 'bar'],
        ]);
        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);

        $this->assertSame(['foo' => 'bar'], iterator_to_array($ep->getAll())[0]->getData());
        $this->assertCount(1, iterator_to_array($ep->getAll()));
    }

    public function testGetOne()
    {
        $mock = $this->createMock(Wrapper::class);
        $mock->method('getQueryResult')->willReturn([
            ['foo' => 'bar'],
        ]);

        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);

        $result = $ep->getOne([])->getData();
        $this->assertSame(['foo' => 'bar'], $result);
    }

    public function testCreateMechanismWindows()
    {
        $mock = $this->createMock(Wrapper::class);
        $mock->expects($this->once())->method('query')->with('CREATE LOGIN [foobar] FROM WINDOWS');
        $mock->method('getQueryResult')->willReturn([
            ['principal_id' => 1],
        ]);

        $object = [
            'mechanism' => 'windows',
            'loginName' => 'foobar',
        ];

        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);

        $result = $ep->create($this->createMock(AttributeMapInterface::class), $object);
        $this->assertEquals(1, $result);
    }

    public function testCreateMechanismWindowsWithSql()
    {
        $mock = $this->createMock(Wrapper::class);
        $mock->expects($this->at(1))->method('query')->with('CREATE LOGIN [foobar] FROM WINDOWS');
        $mock->expects($this->at(2))->method('query')->with('CREATE USER [bar] FOR LOGIN [foobar]');
        $mock->method('getQueryResult')->willReturn([
            ['principal_id' => 1],
        ]);

        $object = [
            'mechanism' => 'windows',
            'loginName' => 'foobar',
            'sqlName' => 'bar',
        ];

        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);

        $result = $ep->create($this->createMock(AttributeMapInterface::class), $object);
        $this->assertEquals(1, $result);
    }

    public function testCreateMechanismWindowsWithSqlAndRoles()
    {
        $mock = $this->createMock(Wrapper::class);
        $mock->expects($this->at(1))->method('query')->with('CREATE LOGIN [foobar] FROM WINDOWS');
        $mock->expects($this->at(2))->method('query')->with('CREATE USER [bar] FOR LOGIN [foobar]');
        $mock->expects($this->at(3))->method('query')->with('EXEC sp_addrolemember foobarrole, [bar]');
        $mock->method('getQueryResult')->willReturn([
            ['principal_id' => 1],
        ]);

        $object = [
            'mechanism' => 'windows',
            'loginName' => 'foobar',
            'sqlName' => 'bar',
            'userRoles' => ['foobarrole'],
        ];

        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);

        $result = $ep->create($this->createMock(AttributeMapInterface::class), $object);
        $this->assertEquals(1, $result);
    }

    public function testCreateMechanismWindowWithSqlInvalidQuery()
    {
        $mock = $this->createMock(Wrapper::class);
        $mock->expects($this->at(2))
            ->method('query')
            ->will($this->throwException(new InvalidQuery()));

        $object = [
            'mechanism' => 'windows',
            'loginName' => 'foobar',
            'sqlName' => 'bar',
            'userRoles' => ['foobarrole'],
        ];

        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);

        $result = $ep->create($this->createMock(AttributeMapInterface::class), $object);
        $this->assertEquals(null, $result);
    }

    public function testCreateMechanismLocalMissingPassword()
    {
        $mock = $this->createMock(Wrapper::class);
        $this->expectException(AttributeNotResolvable::class);
        $mock->method('getQueryResult')->willReturn([
            ['principal_id' => 1],
        ]);

        $object = [
            'mechanism' => 'local',
            'loginName' => 'foobar',
        ];

        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);

        $result = $ep->create($this->createMock(AttributeMapInterface::class), $object);
        $this->assertEquals(1, $result);
    }

    public function testCreateMechanismLocalInvalidQuery()
    {
        $mock = $this->createMock(Wrapper::class);
        $mock->expects($this->once())
            ->method('query')
            ->will($this->throwException(new InvalidQuery()));

        $object = [
            'mechanism' => 'local',
            'loginName' => 'foobar',
            'password' => 'P@ssword',
        ];

        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);

        $result = $ep->create($this->createMock(AttributeMapInterface::class), $object);
        $this->assertEquals(null, $result);
    }

    public function testCreateMechanismLocalChangePwd()
    {
        $mock = $this->createMock(Wrapper::class);
        $mock->expects($this->once())->method('query')->with('CREATE LOGIN [foobar] WITH PASSWORD = \'P@ssword\' MUST_CHANGE, CHECK_EXPIRATION = ON');
        $mock->method('getQueryResult')->willReturn([
            ['principal_id' => 1],
        ]);

        $object = [
            'mechanism' => 'local',
            'loginName' => 'foobar',
            'password' => 'P@ssword',
        ];

        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);

        $result = $ep->create($this->createMock(AttributeMapInterface::class), $object);
        $this->assertEquals(1, $result);
    }

    public function testCreateMechanismLocal()
    {
        $mock = $this->createMock(Wrapper::class);
        $mock->expects($this->once())->method('query')->with('CREATE LOGIN [foobar] WITH PASSWORD = \'P@ssword\'');
        $mock->method('getQueryResult')->willReturn([
            ['principal_id' => 1],
        ]);

        $object = [
            'mechanism' => 'local',
            'loginName' => 'foobar',
            'password' => 'P@ssword',
            'hasToChangePwd' => false,
        ];

        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);

        $result = $ep->create($this->createMock(AttributeMapInterface::class), $object);
        $this->assertEquals(1, $result);
    }

    public function testCreateMechanismLocalDisabled()
    {
        $mock = $this->createMock(Wrapper::class);
        $mock->expects($this->at(1))->method('query')->with('CREATE LOGIN [foobar] WITH PASSWORD = \'P@ssword\'');
        $mock->expects($this->at(2))->method('query')->with('ALTER LOGIN [foobar] DISABLE');
        $mock->method('getQueryResult')->willReturn([
            ['principal_id' => 1],
        ]);

        $object = [
            'mechanism' => 'local',
            'loginName' => 'foobar',
            'password' => 'P@ssword',
            'hasToChangePwd' => false,
            'disabled' => true,
        ];

        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);

        $result = $ep->create($this->createMock(AttributeMapInterface::class), $object);
        $this->assertEquals(1, $result);
    }

    public function testDeleteWithSql()
    {
        $ep_object_data = [
            'loginName' => 'foobar',
            'sqlName' => 'bar',
        ];

        $mock = $this->createMock(Wrapper::class);
        $mock->expects($this->at(1))->method('query')->with('DROP USER [bar]');
        $mock->expects($this->at(2))->method('query')->with('DROP LOGIN [foobar]');

        $ep_object = $this->createMock(EndpointObjectInterface::class);
        $ep_object->method('getData')->willReturn($ep_object_data);

        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);

        $result = $ep->delete($this->createMock(AttributeMapInterface::class), [], $ep_object);
        $this->assertEquals(true, $result);
    }

    public function testDeleteWithoutSql()
    {
        $ep_object_data = [
            'loginName' => 'foobar',
        ];

        $mock = $this->createMock(Wrapper::class);
        $mock->expects($this->once())->method('query')->with('DROP LOGIN [foobar]');

        $ep_object = $this->createMock(EndpointObjectInterface::class);
        $ep_object->method('getData')->willReturn($ep_object_data);

        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);

        $result = $ep->delete($this->createMock(AttributeMapInterface::class), [], $ep_object);
        $this->assertEquals(true, $result);
    }

    public function testDeleteInvalidQuery()
    {
        $ep_object_data = [
            'loginName' => 'foobar',
        ];

        $mock = $this->createMock(Wrapper::class);
        $mock->expects($this->once())
            ->method('query')
            ->will($this->throwException(new InvalidQuery()));

        $ep_object = $this->createMock(EndpointObjectInterface::class);
        $ep_object->method('getData')->willReturn($ep_object_data);

        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $mock, $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);

        $result = $ep->delete($this->createMock(AttributeMapInterface::class), [], $ep_object);
        $this->assertEquals(false, $result);
    }

    public function testGetDiffNoChange()
    {
        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $this->createMock(Wrapper::class), $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);
        $result = $ep->getDiff($this->createMock(AttributeMapInterface::class), []);
        $this->assertSame([], $result);
    }

    public function testGetDiffWithChange()
    {
        $diff = [
            'loginName' => 'foobar',
            'sqlName' => 'bar',
            'foo' => 'bar',
            'userRoles' => 'foobarroles',
            'disabled' => true,
        ];

        $ediff = [
            ['attrib' => 'loginName', 'data' => 'foobar'],
            ['attrib' => 'sqlName', 'data' => 'bar'],
            ['attrib' => 'userRoles', 'data' => 'foobarroles'],
            ['attrib' => 'disabled', 'data' => true],
        ];

        $ep = new SqlSrvUsers('foo', EndpointInterface::TYPE_DESTINATION, $this->createMock(Wrapper::class), $this->createMock(CollectionInterface::class), $this->createMock(WorkflowFactory::class), $this->createMock(LoggerInterface::class), [
            'data' => ['options' => ['filter_one' => '{"uid":"foo"}']],
        ]);
        $result = $ep->getDiff($this->createMock(AttributeMapInterface::class), $diff);
        $this->assertSame($ediff, $result);
    }
}
