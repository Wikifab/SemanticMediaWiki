<?php

namespace SMW\Deserializers\DVDescriptionDeserializer;

use Deserializers\DispatchableDeserializer;
use SMW\Query\QueryComparator;
use SMW\Query\DescriptionFactory;
use SMWDataValue as DataValue;
use SMW\ApplicationFactory;
use SMW\DataItemFactory;

/**
 * @private
 *
 * Create an Description object based on a value string that was entered
 * in a query. Turning inputs that a user enters in place of a value within
 * a query string into query conditions is often a standard procedure. The
 * processing must take comparators like "<" into account, but otherwise
 * the normal parsing function can be used. However, there can be datatypes
 * where processing is more complicated, e.g. if the input string contains
 * more than one value, each of which may have comparators, as in
 * SMWRecordValue. In this case, it makes sense to overwrite this method.
 * Another reason to do this is to add new forms of comparators or new ways
 * of entering query conditions.
 *
 * The resulting Description may or may not make use of the datavalue
 * object that this function was called on, so it must be ensured that this
 * value is not used elsewhere when calling this method. The function can
 * return ThingDescription to not impose any condition, e.g. if parsing
 * failed. Error messages of this DataValue object are propagated.
 *
 * @license GNU GPL v2+
 * @since 2.3
 *
 * @author mwjames
 */
abstract class DescriptionDeserializer implements DispatchableDeserializer {

	/**
	 * @var DescriptionFactory
	 */
	protected $descriptionFactory;

	/**
	 * @var DataItemFactory
	 */
	protected $dataItemFactory;

	/**
	 * @var array
	 */
	protected $errors = array();

	/**
	 * @var DataValue
	 */
	protected $dataValue;

	/**
	 * @since 2.5
	 *
	 * @param DescriptionFactory|null $descriptionFactory
	 * @param DataItemFactory|null $dataItemFactory
	 */
	public function __construct( DescriptionFactory $descriptionFactory = null, DescriptionFactory $dataItemFactory = null ) {
		$this->descriptionFactory = $descriptionFactory;
		$this->dataItemFactory = $dataItemFactory;

		if ( $this->descriptionFactory === null ) {
			$this->descriptionFactory = ApplicationFactory::getInstance()->getQueryFactory()->newDescriptionFactory();
		}

		if ( $this->dataItemFactory === null ) {
			$this->dataItemFactory = ApplicationFactory::getInstance()->getDataItemFactory();
		}
	}

	/**
	 * @since 2.3
	 *
	 * @param DataValue $dataValue
	 */
	public function setDataValue( DataValue $dataValue ) {
		$this->dataValue = $dataValue;
		$this->errors = array();
	}

	/**
	 * @since 2.3
	 *
	 * @param string $error
	 */
	public function addError( $error ) {

		if ( is_array( $error ) ) {
			return $this->errors = array_merge( $this->errors, $error );
		}

		$this->errors[] = $error;
	}

	/**
	 * @since 2.3
	 *
	 * @return array
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * Helper function for DescriptionDeserializer::deserialize that prepares a
	 * single value string, possibly extracting comparators. $value is changed
	 * to consist only of the remaining effective value string (without the
	 * comparator).
	 *
	 * @param string $value
	 * @param string|integer $comparator
	 */
	protected function prepareValue( &$value, &$comparator ) {
		$comparator = QueryComparator::getInstance()->extractComparatorFromString( $value );

		if ( $comparator === SMW_CMP_IN ) {
			$comparator = SMW_CMP_LIKE;

			// `in:...` is for the "busy" user to avoid adding wildcards now and
			// then to the value string
			$value = "*$value*";

			// No property and the assumption is [[in:...]] with the expected use
			// of the wide proximity as indicated by an additional `~`
			if ( $this->dataValue->getProperty() === null ) {
				$value = "~$value";
			}
		}
	}

}
