<?php

namespace SMW\Maintenance;

use SMW\ApplicationFactory;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv(
'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * @license GNU GPL v2+
 * @since 3.0
 *
 * @author mwjames
 */
class RemoveDuplicateEntities extends \Maintenance {

	/**
	 * @since 3.0
	 */
	public function __construct() {
		$this->mDescription = 'Remove duplicates entities without active references.';
		$this->addOption( 's', 'ID starting point', false, true );

		parent::__construct();
	}

	/**
	 * @see Maintenance::addDefaultParams
	 *
	 * @since 3.0
	 */
	protected function addDefaultParams() {
		parent::addDefaultParams();
	}

	/**
	 * @see Maintenance::execute
	 */
	public function execute() {

		if ( !defined( 'SMW_VERSION' ) ) {
			$this->output( "You need to have SMW enabled in order to use this maintenance script!\n\n" );
			exit;
		}

		$this->reportMessage(
			"\nThe script will only dispose of those duplicate entities that have no active\n" .
			"references. The log section 'untouched' contains IDs that have not been\n" .
			"removed and the user is asked to verify the content and manually remove\n".
			"those listed entities.\n\n"
		);

		$applicationFactory = ApplicationFactory::getInstance();
		$maintenanceFactory = $applicationFactory->newMaintenanceFactory();

		$duplicateEntitiesDisposer = $maintenanceFactory->newDuplicateEntitiesDisposer(
			$applicationFactory->getStore( 'SMW\SQLStore\SQLStore' ),
			array( $this, 'reportMessage' )
		);

		$duplicateEntityRecords = $duplicateEntitiesDisposer->findDuplicates();
		$duplicateEntitiesDisposer->verifyAndDispose( $duplicateEntityRecords );

		return true;
	}

	/**
	 * @see Maintenance::reportMessage
	 *
	 * @since 1.9
	 *
	 * @param string $message
	 */
	public function reportMessage( $message ) {
		$this->output( $message );
	}

}

$maintClass = 'SMW\Maintenance\RemoveDuplicateEntities';
require_once( RUN_MAINTENANCE_IF_MAIN );
