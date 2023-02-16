<?php

declare(strict_types=1);
namespace Sypets\Autofix\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Abstract Command: Initializse some variables and reuse.
 *
 * !!! Important: the inheriting class must call the parent function in
 * - constructor
 * - configure
 * - execute
 */
class AbstractCommand extends Command
{
    protected ?SymfonyStyle $io = null;
    protected ?InputInterface $input = null;
    protected ?OutputInterface $output = null;

    /**
     * @var bool
     */
    protected $dryRun;

    /** @var bool */
    protected $interactive;

    public function __construct(
        string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $this->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Dry run: do not change')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Interactive: ask before every change');
    }

    /**
     * Sets $this->dry-run and $this->interactive, as well as $this->io
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->dryRun = $input->getOption('dry-run');
        $this->interactive = $input->getOption('interactive');
        $this->io = new SymfonyStyle($input, $output);
        $this->input = $input;
        $this->output = $output;
        return 0;
    }

    /** ask question if we should proceed */
    protected function askProceedIfInteractive(InputInterface $input, OutputInterface $output, string $msg=''): bool
    {
        if ($this->interactive) {
            if ($msg === '') {
                $msg = 'Aktion durchführen?';
            }
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion($msg . ' (y für ja|n für nein)', false);

            if ($helper->ask($input, $output, $question)) {
                return true;
            }
            return false;
        }
        return true;
    }
}
