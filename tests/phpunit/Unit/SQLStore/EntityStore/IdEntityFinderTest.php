<?php

namespace SMW\Tests\SQLStore\EntityStore;

use SMW\SQLStore\EntityStore\IdEntityFinder;
use SMW\IteratorFactory;
use SMW\DIWikiPage;
use SMW\Tests\TestEnvironment;

/**
 * @covers \SMW\SQLStore\EntityStore\IdEntityFinder
 * @group semantic-mediawiki
 *
 * @license GNU GPL v2+
 * @since   2.1
 *
 * @author mwjames
 */
class IdEntityFinderTest extends \PHPUnit_Framework_TestCase {

	private $testEnvironment;
	private $cache;
	private $iteratorFactory;
	private $store;
	private $conection;

	protected function setUp() {
		$this->testEnvironment = new TestEnvironment();

		$this->iteratorFactory = $this->getMockBuilder( '\SMW\IteratorFactory' )
			->disableOriginalConstructor()
			->getMock();

		$this->cache = $this->getMockBuilder( '\Onoi\Cache\Cache' )
			->disableOriginalConstructor()
			->getMock();

		$this->connection = $this->getMockBuilder( '\SMW\MediaWiki\Database' )
			->disableOriginalConstructor()
			->getMock();

		$this->store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->setMethods( [ 'getConnection' ] )
			->getMockForAbstractClass();

		$this->store->expects( $this->any() )
			->method( 'getConnection' )
			->will( $this->returnValue( $this->connection ) );
	}

	public function testCanConstruct() {

		$this->assertInstanceOf(
			IdEntityFinder::class,
			new IdEntityFinder( $this->store, $this->iteratorFactory, $this->cache )
		);
	}

	public function testGetDataItemForNonCachedId() {

		$row = new \stdClass;
		$row->smw_title = 'Foo';
		$row->smw_namespace = 0;
		$row->smw_iw = '';
		$row->smw_subobject ='';
		$row->smw_sortkey ='';
		$row->smw_sort ='';

		$this->cache->expects( $this->once() )
			->method( 'save' )
			->with(
				$this->equalTo( 42 ),
				$this->anything() );

		$this->cache->expects( $this->once() )
			->method( 'fetch' )
			->will( $this->returnValue( false ) );

		$this->connection->expects( $this->once() )
			->method( 'selectRow' )
			->with(
				$this->anything(),
				$this->anything(),
				$this->equalTo( array( 'smw_id' => 42 ) ) )
			->will( $this->returnValue( $row ) );

		$instance = new IdEntityFinder(
			$this->store,
			$this->iteratorFactory,
			$this->cache
		);

		$this->assertInstanceOf(
			'\SMW\DIWikiPage',
			$instance->getDataItemById( 42 )
		);
	}

	public function testGetDataItemForCachedId() {

		$this->cache->expects( $this->once() )
			->method( 'fetch' )
			->will( $this->returnValue( new DIWikiPage( 'Foo', NS_MAIN ) ) );

		$this->connection->expects( $this->never() )
			->method( 'selectRow' );

		$instance = new IdEntityFinder(
			$this->store,
			$this->iteratorFactory,
			$this->cache
		);

		$this->assertInstanceOf(
			'\SMW\DIWikiPage',
			$instance->getDataItemById( 42 )
		);
	}

	public function testPredefinedPropertyItem() {

		$dataItem = new DIWikiPage( '_MDAT', SMW_NS_PROPERTY );
		$dataItem->setId( 42 );
		$dataItem->setSortKey( 'bar' );
		$dataItem->setOption( 'sort', 'BAR' );

		$row = new \stdClass;
		$row->smw_title = '_MDAT';
		$row->smw_namespace = SMW_NS_PROPERTY;
		$row->smw_iw = '';
		$row->smw_subobject = '';
		$row->smw_sortkey = 'bar';
		$row->smw_sort = 'BAR';

		$this->cache->expects( $this->once() )
			->method( 'fetch' )
			->will( $this->returnValue( false ) );

		$this->connection->expects( $this->once() )
			->method( 'selectRow' )
			->with(
				$this->anything(),
				$this->anything(),
				$this->equalTo( array( 'smw_id' => 42 ) ) )
			->will( $this->returnValue( $row ) );

		$instance = new IdEntityFinder(
			$this->store,
			$this->iteratorFactory,
			$this->cache
		);

		$this->assertEquals(
			$dataItem,
			$instance->getDataItemById( 42 )
		);
	}

	public function testNullForUnknownId() {

		$this->cache->expects( $this->once() )
			->method( 'fetch' )
			->will( $this->returnValue( false ) );

		$this->connection->expects( $this->once() )
			->method( 'selectRow' )
			->will( $this->returnValue( false ) );

		$instance = new IdEntityFinder(
			$this->store,
			$this->iteratorFactory,
			$this->cache
		);

		$this->assertNull(
			$instance->getDataItemById( 42 )
		);
	}

	public function testGetDataItemsFromList() {

		$row = new \stdClass;
		$row->smw_title = 'Foo';
		$row->smw_namespace = 0;
		$row->smw_iw = '';
		$row->smw_subobject ='';

		$this->connection->expects( $this->once() )
			->method( 'select' )
			->with(
				$this->anything(),
				$this->anything(),
				$this->equalTo( array( 'smw_id' => array( 42 ) ) ) )
			->will( $this->returnValue( array( $row ) ) );

		$instance = new IdEntityFinder(
			$this->store,
			new IteratorFactory(),
			$this->cache
		);

		foreach ( $instance->getDataItemsFromList( array( 42 ) ) as $value ) {
			$this->assertEquals(
				'Foo#0##',
				$value
			);
		}
	}

	public function testFindDuplicates() {

		$row = new \stdClass;
		$row->count = 42;
		$row->smw_title = 'Foo';
		$row->smw_namespace = 0;
		$row->smw_iw = '';
		$row->smw_subobject ='';

		$this->connection->expects( $this->once() )
			->method( 'query' )
			->with( $this->stringContains( 'HAVING count(*) > 1' ) )
			->will( $this->returnValue( [ $row ] ) );

		$instance = new IdEntityFinder(
			$this->store,
			new IteratorFactory(),
			$this->cache
		);

		$this->assertInstanceOf(
			'\SMW\Iterators\MappingIterator',
			$instance->findDuplicates()
		);
	}

}
