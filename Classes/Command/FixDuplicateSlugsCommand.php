<?php
declare(strict_types=1);
namespace Sypets\Autofix\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Sypets\Autofix\Service\SlugService;

/**
 * Fix duplicate slugs (slugs with same language or slugs with one entry with language -1).
 * This was due to bug https://forge.typo3.org/issues/99529 which is now fixed. The core
 * bug is fixed, but records with duplicate slugs might previously have been created.
 *
 * In order for this script to work, a TYPO3 version with the fixed bug must be installed, because
 * core functionality is used!
 *
 * @todo Currently, eval type "uniqueInSite" is not supported, should support uniqueInSite as well
 */
class FixDuplicateSlugsCommand extends AbstractCommand
{
    protected ?SlugService $slugService = null;

    public function __construct(SlugService $slugService)
    {
        $this->slugService = $slugService;
        parent::__construct(null);
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Fix duplicate slugs due to previous TYPO3 bug (slugs in combination with sys_language_uid=-1)');
        $this->addArgument('table', null, 'Use only this table')
            ->addArgument('field', null, 'Use only this field (table is required as well)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $table = $input->getArgument('table') ?: '';
        $field = $input->getArgument('field') ?: '';

        $this->generateAllDuplicateSlugs($table, $field);

        return 0;
    }

    protected function convertDuplicateSlugsForTableField(string $table, string $field): void
    {
        $this->io->section(sprintf('table=%s, field=%s', $table, $field));
        $statement =  $this->slugService->fetchDuplicateSlugsForTableFieldStatement($table, $field);
        $countConverted = 0;
        while ($row = $this->slugService->getNextRowWithDuplicateSlug($statement, $table, $field)) {
            $convert = $row['convert'] ?? false;
            if (!$convert) {
                continue;
            }
            $table = $row['table'];
            $uid = $row['uid'];
            $field = $row['field'];
            $slug = $row['slug'];
            $newSlug = $row['newSlug'];

            $this->io->writeln(sprintf('table=<%s> uid=<%d> old slug=<%s> new slug=<%s>', $table, $uid, $slug, $newSlug));
            if ($this->interactive &&
                $this->askProceed('Convert now?') !== true) {
                        continue;
            }
            if (!$this->dryRun) {
                $this->slugService->updateSlug($table, $uid, $field, $newSlug);
                $countConverted++;
            }
        }
        $this->io->writeln($countConverted . ' converted');
    }

    protected function generateAllDuplicateSlugs(string $table = '', string $field = ''): bool
    {
        $reason = '';
        if ($table && $field) {
            $this->io->section(sprintf('table=%s, field=%s', $table, $field));
            if ($this->slugService->isSlugFieldForDeduplicating($table, $field, $reason)) {
                $this->convertDuplicateSlugsForTableField($table, $field);
            } else {
                $this->io->warning('No convertible slug field, reason:' . $reason);
            }

        }
        if ($table) {
            $tables = [$table];
        } else {
            $tables = array_keys($GLOBALS['TCA']);
        }
        foreach ($tables as $currentTable) {
            $reason = '';
            foreach ($GLOBALS['TCA'][$currentTable]['columns'] as $currentField => $values) {
                if (!$this->slugService->isSlugFieldForDeduplicating($currentTable, $currentField, $reason)) {
                    // no convertible slug field
                    continue;
                }
                $this->convertDuplicateSlugsForTableField($currentTable, $currentField);
            }
        }

        return false;
    }

    protected function askProceed(string $msg='Proceed?'): bool
    {
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion($msg . ' (y) for yes | (n) for no ... ', false);

        if ($helper->ask($this->input, $this->output, $question)) {
            return true;
        }
        return false;
    }
}
