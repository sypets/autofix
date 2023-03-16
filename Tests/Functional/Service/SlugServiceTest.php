<?php
declare(strict_types=1);
namespace Sypets\Autofix\Tests\Functional\Service;

use Sypets\Autofix\Service\SlugService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class SlugServiceTest  extends FunctionalTestCase
{
    public function setUp(): void
    {
        $this->testExtensionsToLoad = [
            '../../Tests/Functional/Fixtures/Extensions/test_extension'
        ];
        parent::setUp();
    }

    /**
     * @test
     */
    public function convertDuplicates(): void
    {
        $table = 'sys_category';
        $field = 'slug';
        $slugService = new SlugService();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/duplicates/SlugServiceTest_sys_category_duplicates.csv');
        $statement = $slugService->fetchDuplicateSlugsForTableFieldStatement($table, $field);
        $count = 0;
        while ($row = $slugService->getNextRowWithDuplicateSlug($statement, $table, $field)) {
            $convert = $row['convert'] ?? false;
            if (!$convert) {
                continue;
            }
            $uid = (int) $row['uid'];
            $newSlug = $row['newSlug'];
            $slugService->updateSlug($table, $uid, $field, $newSlug);
            $count++;
        }
        $this->assertEquals(2, $count);
        $this->assertCSVDataSet( __DIR__ . '/Fixtures/duplicates/SlugServiceTest_sys_category_duplicates_expected_output.csv');
    }

    /**
     * @test
     */
    public function generateSlugs(): void
    {
        $table = 'sys_category';
        $field = 'slug';
        $slugService = new SlugService();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/generate/SlugServiceTest_sys_category_generate.csv');
        $statement = $slugService->fetchRowsWithMissingSlugsForTableFieldStatement($table, $field);
        $count = 0;
        while ($row = $slugService->getNextRowWithMissingSlugs($statement, $table, $field)) {
            $convert = $row['convert'] ?? false;
            if (!$convert) {
                continue;
            }
            $uid = (int) $row['uid'];
            $newSlug = $row['newSlug'];
            $slugService->updateSlug($table, $uid, $field, $newSlug);
            $count++;
        }
        $this->assertEquals(2, $count);
        $this->assertCSVDataSet( __DIR__ . '/Fixtures/generate/SlugServiceTest_sys_category_generate_expected_output.csv');
    }


}
