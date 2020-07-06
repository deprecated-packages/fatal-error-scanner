<?php

declare(strict_types=1);

namespace Migrify\FatalErrorScanner\Command;

use Migrify\FatalErrorScanner\ErrorScanner;
use Migrify\FatalErrorScanner\ScannedErrorToRectorResolver;
use Migrify\FatalErrorScanner\Yaml\YamlPrinter;
use Rector\Core\Configuration\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symplify\PackageBuilder\Console\Command\CommandNaming;
use Symplify\PackageBuilder\Console\ShellCode;

final class ScanCommand extends Command
{
    /**
     * @var string
     */
    private const RECTOR_TYPES_YAML = 'fatal-types.yaml';

    /**
     * @var SymfonyStyle
     */
    private $symfonyStyle;

    /**
     * @var ScannedErrorToRectorResolver
     */
    private $scannedErrorToRectorResolver;

    /**
     * @var ErrorScanner
     */
    private $errorScanner;

    /**
     * @var YamlPrinter
     */
    private $yamlPrinter;

    public function __construct(
        SymfonyStyle $symfonyStyle,
        ScannedErrorToRectorResolver $scannedErrorToRectorResolver,
        ErrorScanner $errorScanner,
        YamlPrinter $yamlPrinter
    ) {
        $this->symfonyStyle = $symfonyStyle;
        $this->scannedErrorToRectorResolver = $scannedErrorToRectorResolver;
        $this->errorScanner = $errorScanner;
        $this->yamlPrinter = $yamlPrinter;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(CommandNaming::classToName(self::class));

        $this->setDescription('Scan for fatal type errors and dumps config that fixes it');

        $this->addArgument(
            Option::SOURCE,
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'Path to file/directory to process'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string[] $source */
        $source = $input->getArgument(Option::SOURCE);
        $errors = $this->errorScanner->scanSource($source);

        if ($errors === []) {
            $this->symfonyStyle->success('No fatal errors found');
            return ShellCode::SUCCESS;
        }
        $message = sprintf('Found %d Errors', count($errors));

        $this->symfonyStyle->section($message);
        foreach ($errors as $error) {
            $this->symfonyStyle->note($error);
        }

        $rectorConfiguration = $this->scannedErrorToRectorResolver->processErrors($errors);
        if ($rectorConfiguration === []) {
            $this->symfonyStyle->success('No fatal errors found');
            return ShellCode::SUCCESS;
        }

        $this->yamlPrinter->printYamlToFile($rectorConfiguration, self::RECTOR_TYPES_YAML);
        $message = sprintf('New config with types was created in "%s"', self::RECTOR_TYPES_YAML);

        $this->symfonyStyle->note($message);
        $message = sprintf(
            'Now run Rector to refactor your code:%svendor/bin/rector p %s --config %s',
            PHP_EOL . PHP_EOL,
            implode(' ', $source),
            self::RECTOR_TYPES_YAML
        );

        $this->symfonyStyle->success($message);

        return ShellCode::SUCCESS;
    }
}
