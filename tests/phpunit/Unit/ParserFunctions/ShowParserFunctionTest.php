<?php

namespace SMW\Tests\ParserFunctions;

use ParserOutput;
use SMW\ApplicationFactory;
use SMW\ParserFunctions\ShowParserFunction;
use SMW\ParserFunctions\AskParserFunction;
use SMW\Tests\TestEnvironment;
use Title;

/**
 * @covers \SMW\ParserFunctions\ShowParserFunction
 * @group semantic-mediawiki
 *
 * @license GNU GPL v2+
 * @since 1.9
 *
 * @author mwjames
 */
class ShowParserFunctionTest extends \PHPUnit_Framework_TestCase {

	private $testEnvironment;
	private $semanticDataValidator;
	private $messageFormatter;
	private $circularReferenceGuard;
	private $expensiveFuncExecutionWatcher;

	protected function setUp() {
		parent::setUp();

		$this->testEnvironment = new TestEnvironment();
		$this->semanticDataValidator = $this->testEnvironment->getUtilityFactory()->newValidatorFactory()->newSemanticDataValidator();

		$this->testEnvironment->addConfiguration( 'smwgQueryProfiler', true );
		$this->testEnvironment->addConfiguration( 'smwgQueryResultCacheType', false );
		$this->testEnvironment->addConfiguration( 'smwgQFilterDuplicates', false );

		$this->messageFormatter = $this->getMockBuilder( '\SMW\MessageFormatter' )
			->disableOriginalConstructor()
			->getMock();

		$this->circularReferenceGuard = $this->getMockBuilder( '\SMW\Utils\CircularReferenceGuard' )
			->disableOriginalConstructor()
			->getMock();

		$this->expensiveFuncExecutionWatcher = $this->getMockBuilder( '\SMW\ParserFunctions\ExpensiveFuncExecutionWatcher' )
			->disableOriginalConstructor()
			->getMock();

		$this->expensiveFuncExecutionWatcher->expects( $this->any() )
			->method( 'hasReachedExpensiveLimit' )
			->will( $this->returnValue( false ) );
	}

	protected function tearDown() {
		$this->testEnvironment->tearDown();
		parent::tearDown();
	}

	public function testCanConstruct() {

		$parserData = $this->getMockBuilder( '\SMW\ParserData' )
			->disableOriginalConstructor()
			->getMock();

		$askParserFunction = new AskParserFunction(
			$parserData,
			$this->messageFormatter,
			$this->circularReferenceGuard,
			$this->expensiveFuncExecutionWatcher
		);

		$this->assertInstanceOf(
			'\SMW\ParserFunctions\ShowParserFunction',
			new ShowParserFunction( $askParserFunction )
		);
	}

	/**
	 * @dataProvider queryDataProvider
	 */
	public function testParse( array $params, array $expected ) {

		$parserData = ApplicationFactory::getInstance()->newParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$instance = new ShowParserFunction(
			new AskParserFunction(
				$parserData,
				$this->messageFormatter,
				$this->circularReferenceGuard,
				$this->expensiveFuncExecutionWatcher
			)
		);

		$result = $instance->parse( $params );

		if ( $expected['output'] === '' ) {
			$this->assertEmpty( $result );
		} else {
			$this->assertContains( $expected['output'], $result );
		}
	}

	public function testIsQueryDisabled() {

		$parserData = $this->getMockBuilder( '\SMW\ParserData' )
			->disableOriginalConstructor()
			->getMock();

		$this->messageFormatter->expects( $this->any() )
			->method( 'addFromKey' )
			->will( $this->returnSelf() );

		$this->messageFormatter->expects( $this->once() )
			->method( 'getHtml' );

		$instance = new ShowParserFunction(
			new AskParserFunction(
				$parserData,
				$this->messageFormatter,
				$this->circularReferenceGuard,
				$this->expensiveFuncExecutionWatcher
			)
		);

		$instance->isQueryDisabled();
	}

	/**
	 * @dataProvider queryDataProvider
	 */
	public function testInstantiatedQueryData( array $params, array $expected ) {

		$parserData = ApplicationFactory::getInstance()->newParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$instance = new ShowParserFunction(
			new AskParserFunction(
				$parserData,
				$this->messageFormatter,
				$this->circularReferenceGuard,
				$this->expensiveFuncExecutionWatcher
			)
		);

		$instance->parse( $params );

		foreach ( $parserData->getSemanticData()->getSubSemanticData() as $containerSemanticData ){
			$this->assertInstanceOf( 'SMWContainerSemanticData', $containerSemanticData );

			$this->semanticDataValidator->assertThatPropertiesAreSet(
				$expected,
				$containerSemanticData
			);
		}
	}

	public function testQueryWithErroneousData() {

		$parserData = ApplicationFactory::getInstance()->newParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$instance = new ShowParserFunction(
			new AskParserFunction(
				$parserData,
				$this->messageFormatter,
				$this->circularReferenceGuard,
				$this->expensiveFuncExecutionWatcher
			)
		);

		// #2 [[..]] is not acknowledged therefore displays an error message
		// {{#show: [[File:Fooo]]
		// |?Modification date
		// |default=no results
		// |format=table
		// }}
		$params = array(
			'[[File:Fooo]]',
			'?Modification date',
			'default=no results',
			'format=table'
		);

		$instance->parse( $params );

		$expected = array(
			'output' => 'class="smwtticon warning"', // lazy content check for the error
			'propertyCount'  => 4,
			'propertyKeys'   => array( '_ASKFO', '_ASKDE', '_ASKSI', '_ASKST' ),
			'propertyValues' => array( 'table', 0, 1, '[[:]]' )
		);

		$this->semanticDataValidator->assertThatPropertiesAreSet(
			$expected,
			$parserData->getSemanticData()->findSubSemanticData( '_QUERYda9bddddc9907eafb60792ca4bed3008' )
		);

		$expected = array(
			'propertyCount'  => 2,
			'propertyKeys'   => array( '_ERRP', '_ERRT' ),
		);

		$errorID = null;

		foreach ( $parserData->getSemanticData()->getSubSemanticData() as $subSemanticData ) {
			if ( strpos( $subSemanticData->getSubject()->getSubobjectName(), '_ERR' ) !== false ) {
				$errorID = $subSemanticData->getSubject()->getSubobjectName();
				break;
			}
		}

		$this->semanticDataValidator->assertThatPropertiesAreSet(
			$expected,
			$parserData->getSemanticData()->findSubSemanticData( $errorID )
		);
	}

	public function queryDataProvider() {

		$provider = array();

		// #0
		// {{#show: Foo-show
		// |?Modification date
		// }}
		$provider[] = array(
			array(
				'Foo-show',
				'?Modification date',
			),
			array(
				'output' => '',
				'propertyCount'  => 4,
				'propertyKeys'   => array( '_ASKFO', '_ASKDE', '_ASKSI', '_ASKST' ),
				'propertyValues' => array( 'list', 0, 1, '[[:Foo]]' )
			)
		);

		// #1
		// {{#show: Help:Bar
		// |?Modification date
		// |default=no results
		// }}
		$provider[] = array(
			array(
				'Help:Bar',
				'?Modification date',
				'default=no results'
			),
			array(
				'output' => 'no results',
				'propertyCount'  => 4,
				'propertyKeys'   => array( '_ASKFO', '_ASKDE', '_ASKSI', '_ASKST' ),
				'propertyValues' => array( 'list', 0, 1, '[[:Help:Bar]]' )
			)
		);

		return $provider;
	}
}
