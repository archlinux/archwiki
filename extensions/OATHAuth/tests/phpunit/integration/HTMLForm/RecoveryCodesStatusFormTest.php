<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\HTMLForm;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\HTMLForm\RecoveryCodesStatusForm;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\HTMLForm\RecoveryCodesStatusForm
 * @covers \MediaWiki\Extension\OATHAuth\Notifications\Manager
 * @group Database
 */
class RecoveryCodesStatusFormTest extends MediaWikiIntegrationTestCase {

	public function testOnSubmitRegeneratesCodes(): void {
		$this->overrideConfigValue( 'OATHRecoveryCodesCount', 10 );

		$key = RecoveryCodeKeys::newFromArray( [
			'recoverycodekeys' => [ 'ABCD1234EFGH5678' ],
			'format' => 'unencrypted',
		] );

		$testUser = $this->getTestUser()->getUser();

		$mockOATHUser = $this->createMock( OATHUser::class );
		$mockOATHUser->method( 'getUser' )->willReturn( $testUser );
		$mockOATHUser->method( 'getCentralId' )->willReturn( 12345 );
		$mockOATHUser->method( 'getKeysForModule' )
			->willReturnCallback( static fn ( $moduleName ) => $moduleName === RecoveryCodes::MODULE_NAME
				? [ $key ]
				: []
			);
		$mockOATHUser->method( 'userHasNonSpecialEnabledKeys' )->willReturn( true );

		$mockUserRepository = $this->createMock( OATHUserRepository::class );
		$mockUserRepository->expects( $this->once() )->method( 'updateKey' )
			->with( $mockOATHUser, $this->isInstanceOf( RecoveryCodeKeys::class ) );

		$module = new RecoveryCodes(
			$mockUserRepository,
			$this->createMock( OATHAuthLogger::class ),
			$this->getServiceContainer()->getMainConfig()
		);

		$context = RequestContext::getMain();
		$context->setUser( $testUser );

		$form = new RecoveryCodesStatusForm(
			$mockOATHUser,
			$mockUserRepository,
			$module,
			$context,
			$this->createMock( OATHAuthModuleRegistry::class )
		);

		$this->assertTrue( $form->onSubmit( [] ) );
	}
}
