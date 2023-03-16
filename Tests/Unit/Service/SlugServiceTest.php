<?php
declare(strict_types=1);
namespace Sypets\Autofix\Tests\Unit\Service;

use Sypets\Autofix\Service\SlugService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class SlugServiceTest  extends UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->setupTca();
    }

    protected function setupTca()
    {
        $GLOBALS['TCA']['sys_category']['ctrl']['languageField'] = 'sys_language_uid';
        $GLOBALS['TCA']['sys_category']['columns']['slug']['config']['type'] = 'slug';
        $GLOBALS['TCA']['sys_category']['columns']['slug']['config']['eval'] = 'unique';
        $GLOBALS['TCA']['sys_category']['columns']['title']['config'] = [];
    }

    /**
     * @return \Generator<string,array<string,string>>
     */
    public function isSlugFieldDataProvider(): \Generator
    {
        yield 'sys_category.slug is slug' => [
            'table' => 'sys_category',
            'field' => 'slug',
            'result' => true,
        ];

        yield 'sys_category.title is NOT slug' => [
            'table' => 'sys_category',
            'field' => 'title',
            'result' => false,
        ];
    }

    /**
     * @test
     * @dataProvider isSlugFieldDataProvider
     */
    public function isSlugFieldReturnsCorrectValue(string $table, string $field, bool $expectedResult): void
    {
        $slugService = new SlugService();
        $actualReason = '';
        $actualResult = $slugService->isSlugField($table, $field, $actualReason);
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @return \Generator<string,array<string,string>>
     */
    public function isSlugFieldForDeduplicatingDataProvider(): \Generator
    {
        yield 'sys_category.slug is slug for deduplicating' => [
            'table' => 'sys_category',
            'field' => 'slug',
            'result' => true,
        ];

        yield 'sys_category.title is NOT slug for deduplicating' => [
            'table' => 'sys_category',
            'field' => 'title',
            'result' => false,
        ];
    }

    /**
     * @test
     * @dataProvider isSlugFieldForDeduplicatingDataProvider
     */
    public function isSlugFieldForDeduplicatingReturnsCorrectValue(string $table, string $field, bool $expectedResult): void
    {
        $slugService = new SlugService();
        $actualReason = '';
        $actualResult = $slugService->isSlugFieldForDeduplicating($table, $field, $actualReason);
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function getLanguageFieldNameReturnsCorrectValue(): void
    {
        $slugService = new SlugService();
        $result = $slugService->getLanguageFieldName('sys_category');
        $this->assertEquals('sys_language_uid', $result);
    }

    /**
     * @test
     */
    public function getUniqueTypeReturnsCorrectValue(): void
    {
        $slugService = new SlugService();
        $result = $slugService->getUniqueType('sys_category', 'slug');
        $this->assertEquals('unique', $result);
    }

}
