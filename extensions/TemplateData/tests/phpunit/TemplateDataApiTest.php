<?php

use MediaWiki\Tests\Api\ApiTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\TemplateData\Api\ApiTemplateData
 * @license GPL-2.0-or-later
 */
class TemplateDataApiTest extends ApiTestCase {

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();
	}

	public function testMissingTemplateData() {
		// Create a test template
		$testTemplateTitle = 'Template:WithoutTemplateData';
		$testTemplate = $this->getExistingTestPage( $testTemplateTitle );

		// Make a templatedata API request
		$result = $this->doApiRequestWithToken( [
			'action' => 'templatedata',
			'format' => 'json',
			'includeMissingTitles' => 1,
			'pageids' => $testTemplate->getId(),
		], null, $this->getTestUser()->getUser() );
		$data = $result[0]['pages'];

		$this->assertContains(
			$testTemplate->getId(),
			array_keys( $data ),
			'The page ID should be in the response'
		);
		$this->assertEquals(
			$testTemplateTitle,
			$data[$testTemplate->getId()]['title'],
			'The title should be the same'
		);
		$this->assertTrue(
			$data[$testTemplate->getId()]['notemplatedata'],
			'The notemplatedata flag should be set'
		);
		$this->assertEquals(
			NS_TEMPLATE,
			$data[$testTemplate->getId()]['ns'],
			'Namespace should be NS_TEMPLATE'
		);
	}

	public function testContainingTemplateData() {
		// Create a test template
		$testTemplateTitle = 'Template:WithTemplateData';
		$testTemplate = $this->getExistingTestPage( $testTemplateTitle );
		$testDescription = 'WithTemplateData description';
		$testContent = <<<EOT
			<noinclude>
			<templatedata>
			{
				"params": {},
				"description": "$testDescription"
			}
			</templatedata>
			</noinclude>
		EOT;
		$this->editPage( $testTemplate->getTitle(), $testContent );

		// Make a templatedata API request
		$result = $this->doApiRequestWithToken( [
			'action' => 'templatedata',
			'format' => 'json',
			'includeMissingTitles' => 1,
			'pageids' => $testTemplate->getId(),
		], null, $this->getTestUser()->getUser() );
		$data = $result[0]['pages'];

		$this->assertContains(
			$testTemplate->getId(),
			array_keys( $data ),
			'The page ID should be in the response'
		);
		$this->assertEquals(
			$testTemplateTitle,
			$data[$testTemplate->getId()]['title'],
			'The title should be the same'
		);
		$this->assertNotContains(
			'notemplatedata',
			$data[$testTemplate->getId()],
			'The notemplatedata flag should not be set'
		);
		$this->assertEquals(
			$testDescription,
			$data[$testTemplate->getId()]['description']['en'],
			'The description should be the same'
		);
		$this->assertEquals(
			NS_TEMPLATE,
			$data[$testTemplate->getId()]['ns'],
			'Namespace should be NS_TEMPLATE'
		);
	}

}
