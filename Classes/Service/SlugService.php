<?php
declare(strict_types=1);
namespace Sypets\Autofix\Service;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SlugService
{
    protected const UNIQUE_TYPE_UNIQUE = 'unique';
    protected const UNIQUE_TYPE_IN_SITE = 'uniqueInSite';
    protected const UNIQUE_TYPE_IN_PID = 'uniqueInPid';

    protected const EXCLUDE_TABLES = [
        'sys_history',
        'sys_log',
        'sys_refindex',
        'tx_calendarize_domain_model_index',
    ];

    /**
     * array
     *   $table
     *     $field => SlugHelper
     * @var array<string,array<string,SlugHelper>>
     */
    protected array $slugHelpers = [];

    public function generateSlugHelper(string $table, string $field): SlugHelper
    {
        if ($this->slugHelpers[$table][$field] ?? false) {
            return $this->slugHelpers[$table][$field];
        }
        $slugHelper = GeneralUtility::makeInstance(SlugHelper::class, $table, $field,
            $GLOBALS['TCA'][$table]['columns'][$field]['config']
        );
        $this->slugHelpers[$table][$field] = $slugHelper;
        return $slugHelper;
    }

    /**
     * Check if a field is a valid slug field (also checks list of excluded tables)
     *
     * @param string $table
     * @param string $field
     * @param string $reason
     * @return bool
     */
    public function isSlugField(string $table, string $field, string &$reason): bool
    {
        // check if in list of exclude tables
        if (in_array($table, self::EXCLUDE_TABLES)) {
            $reason = 'is excluded table';
            return false;
        }
        if (!isset($GLOBALS['TCA'][$table]['columns']) || ! is_array($GLOBALS['TCA'][$table]['columns'])) {
            $reason = 'TCA is not configured properly';
            return false;
        }

        // check if is of type slug
        $fieldConfiguration = $GLOBALS['TCA'][$table]['columns'][$field] ?? [];
        if (!$fieldConfiguration) {
            $reason = 'TCA is not configured properly';
            return false;
        }
        $type = $fieldConfiguration['config']['type'] ?? '';
        if ($type !== 'slug') {
            $reason = 'Is not of type slug';
            return false;
        }

        return true;
    }

    /**
     * Check if deduplicating on this slug field by language is possible
     *
     * @param string $table
     * @param string $field
     * @param string $reason
     * @return bool
     */
    public function isSlugFieldForDeduplicating(string $table, string $field, string &$reason): bool
    {
        if (!$this->isSlugField($table, $field, $reason)) {
            return false;
        }

        // does not have a configured language field
        if (!isset($GLOBALS['TCA'][$table]['ctrl']['languageField'])) {
            $reason = 'Does not have a language field';
            return false;
        }

        // check eval field
        $eval = explode(',', $GLOBALS['TCA'][$table]['columns'][$field]['config']['eval'] ?? '');
        $uniqueType = $this->getUniqueType($table, $field);
        if ($uniqueType !== self::UNIQUE_TYPE_UNIQUE
            && $uniqueType !== self::UNIQUE_TYPE_IN_PID) {
            $reason = sprintf('SKIP: table %s field %s: we do not currently support uniqueInSite',
                $table, $field);
            return false;
        }

        return true;
    }

    protected function getLanguageFieldName(string $table): string
    {
        return $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? '';
    }

    protected function getUniqueType(string $table, string $field): string
    {
        $eval = explode(',', $GLOBALS['TCA'][$table]['columns'][$field]['config']['eval'] ?? '');
        if (in_array(self::UNIQUE_TYPE_UNIQUE, $eval, true)) {
            return self::UNIQUE_TYPE_UNIQUE;
        } else if (in_array(self::UNIQUE_TYPE_IN_SITE, $eval, true)) {
            return self::UNIQUE_TYPE_IN_SITE;
        } else if (in_array(self::UNIQUE_TYPE_IN_PID, $eval, true)) {
            return self::UNIQUE_TYPE_IN_PID;
        }
        return '';
    }

    /**
     * @param string $table
     * @param string $field
     * @return mixed
     */
    public function fetchRowsWithMissingSlugsForTableFieldStatement(string $table, string $field)
    {
        $queryBuilder = $this->generateQueryBuilderForFindingRecordsWithoutSlugs($table,
            $field);
        $statement = $queryBuilder->execute();
        return $statement;
    }

    /**
     * Fetch next row with missing slug and return array with new slug
     *
     * @param $statement
     * @param string $table
     * @param string $field
     * @return array|false[]
     */
    public function getNextRowWithMissingSlugs($statement, string $table, string $field): array
    {
        $result = [
            'convert' => false
        ];

        // todo: initialize this only once?
        $slugHelper = $this->generateSlugHelper($table, $field);

        if ($row = $statement->fetchAssociative()) {
            $uid = $row['uid'] ?? false;
            // pid can be 0
            $pid = $row['pid'] ?? 0;
            $slug = $row[$field] ?? '';
            if (!$uid) {
                // missing uid, skip
                return $result;
            }

            $newSlug = $slugHelper->generate($row, $pid);
            if ($newSlug) {
                // !!! generate apparently does no duplicate check, so we must do this here!
                $newSlug = $this->getUniqueSlug($table, $field, $newSlug, $row, $uid, $pid);
                $result = [
                    'uid' => $uid,
                    'slug' => $slug,
                    'newSlug' => $newSlug,
                    'convert' => true,
                ];
                return $result;
            }
        }
        return [];
    }

    /**
     * Get the db statement to fetch individual results.
     *
     * We fetch and convert Slugs one at a time (otherwise we fetch all in an array and convert
     * some to the same duplicates), e.g.
     * 1. 'sameslug' converted to => 'sameslug-1'
     * 2. 'sameslug' converted to => 'sameslug-1'
     *
     * We must update the DB entry for the first samelug before calculating the new value for the second sameslug.
     *
     * @param string $table
     * @param string $field
     * @return \Doctrine\DBAL\Result|int
     * @throws \Doctrine\DBAL\DBALException
     */
    public function fetchDuplicateSlugsForTableFieldStatement(string $table, string $field)
    {
        $uniqueType = $this->getUniqueType($table, $field);
        $languageFieldName = $this->getLanguageFieldName($table);

        $queryBuilder = $this->generateQueryBuilderForFindingRecordsWithDuplicateSlugs($uniqueType, $table,
            $field, $languageFieldName);
        return $queryBuilder->execute();
    }

    /**
     * @param \Doctrine\DBAL\Result|int $statement
     * @return array
     */
    public function getNextRowWithDuplicateSlug($statement, string $table, string $field): array
    {
        $result = [
            'convert' => false
        ];

        if ($row = $statement->fetchAssociative()) {
            $uid = $row['uid'] ?? false;
            $pid = $row['pid'] ?? false;
            $slug = $row[$field] ?? '';
            if (!$uid) {
                // missing uid, skip
                return $result;
            }
            if (!$pid) {
                // missing pid, skip
                return $result;
            }
            // empty slug: skip
            if (!$slug) {
                // empty slug, skip
                return $result;
            }

            $newSlug = $this->getUniqueSlug($table, $field, $slug, $row, $uid, $pid);
            if ($slug === $newSlug) {
                // old slug equals new slug, should not happen!
                return $result;
            }
            $result = [
                'table' => $table,
                'field' => $field,
                'uid' => $uid,
                'slug' => $slug,
                'newSlug' => $newSlug,
                'convert' => true,
            ];
            return $result;
        }
        // will abort loop
        return [];
    }

    public function getUniqueSlug(string $table, string $field, string $slug, array $row, int $uid, int $pid): string
    {
        // todo: initialize this only once?
        $slugHelper = $this->generateSlugHelper($table, $field);
        $uniqueType = $this->getUniqueType($table, $field);
        $state = RecordStateFactory::forName($table)
            ->fromArray($row, $pid, $uid);

        switch ($uniqueType) {
            case 'unique':
                return $slugHelper->buildSlugForUniqueInTable($slug, $state);
            default:
                throw new \InvalidArgumentException(
                    sprintf(
                        'Slug type for table <%s> field <%s> is not a supported type',
                        $table, $field
                    )
                );
        }
    }

    public function updateSlug(string $table, int $uid, string $slugField, string $slugValue): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
            ->update($table)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT))
            )
            ->set($slugField, $slugValue)
            ->executeStatement();
    }

    protected function generateQueryBuilderForFindingRecordsWithDuplicateSlugs(string $uniqueType, string $table, string $slugField,
        string $languageFieldName): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->select('t2.*')
            ->from($table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder->innerJoin(
            $table,
            $table,
            't2',
            $queryBuilder->expr()->eq($table . '.' . $slugField, $queryBuilder->quoteIdentifier('t2.' . $slugField))
        )->where(
            // slug is not empty
            $queryBuilder->expr()->neq($table . '.' . $slugField, $queryBuilder->createNamedParameter('')),
            $queryBuilder->expr()->neq($table . '.uid', $queryBuilder->quoteIdentifier('t2.uid')),
            // language is the same or (at least) one of the languages is -1
            $queryBuilder->expr()->or(
                $queryBuilder->expr()->eq('t2.' . $languageFieldName, $queryBuilder->createNamedParameter(-1, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('t2.' . $languageFieldName, $queryBuilder->quoteIdentifier($table . '.' . $languageFieldName))
            )
        // DISTINCT by uid
        )->groupBy('t2.uid');
        switch ($uniqueType) {
            case self::UNIQUE_TYPE_UNIQUE:
                return $queryBuilder;
            case self::UNIQUE_TYPE_IN_PID:
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->eq($table . '.pid', $queryBuilder->quoteIdentifier('t2.pid')),
                );

        }
        return $queryBuilder;
    }

    protected function generateQueryBuilderForFindingRecordsWithoutSlugs(string $table, string $slugField): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->select('*')
            ->from($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder
            ->where(
                // slug is empty
                $queryBuilder->expr()->eq($slugField, $queryBuilder->createNamedParameter(''))
            );
        return $queryBuilder;
    }
}
