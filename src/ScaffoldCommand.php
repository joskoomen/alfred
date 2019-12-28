<?php

namespace JosKoomen\Alfred;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class ScaffoldCommand extends Command
{
    private $sourceRoot = SourcePaths::LARAVEL;

    public function __construct()
    {
        parent::__construct('scaffold');
    }

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('scaffold')
            ->setDescription('Scaffold your Packages')
            ->addArgument('type', InputArgument::OPTIONAL)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces to rescaffold all');
    }

    /**
     * Execute the command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $directory = getcwd();
        $output->writeln('<fg=cyan;options=bold>🤵Hello Master Wayne. Start scaffolding</>');
        $this->scaffoldConfig($input, $output, $directory)
            ->scaffoldImports($output, $directory);
    }

    protected function scaffoldConfig(InputInterface $input, OutputInterface $output, $directory)
    {
        $modulesDir = @glob($directory . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . '@ypa/{pickup-truck-*}' . DIRECTORY_SEPARATOR . 'scaffold', GLOB_BRACE);
        $filesystem = new Filesystem();

        $sourceDirectory = $directory . DIRECTORY_SEPARATOR . $this->sourceRoot;
        $configFile = $sourceDirectory . DIRECTORY_SEPARATOR . 'scss' . DIRECTORY_SEPARATOR . '_config.scss';

        if ($input->getOption('force')) {
            @file_put_contents($configFile, '@charset "UTF-8";' . "\n\n");
        }

        foreach ($modulesDir as $dir) {

            $configFiles = @glob($dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . '*.scss');

            foreach ($configFiles as $file) {

                try {
                    $fileName = explode(DIRECTORY_SEPARATOR . 'scaffold' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR, $file)[1];

                } catch (IOExceptionInterface $e) {
                    throw new \RuntimeException('Error with reading scaffold folder. $fileName is not an array (ScaffoldCommand line 68)');
                }

                $targetFile = $sourceDirectory . DIRECTORY_SEPARATOR . 'scss' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $fileName;

                if ($input->getOption('force') || !$filesystem->exists($targetFile)) {
                    $filesystem->copy($file, $targetFile);
                    $output->writeln('<fg=yellow;options=bold>🤵 Scaffold</> ' . $this->sourceRoot . '/scss/config/' . $fileName);
                    $importFile = str_replace('.scss', '', str_replace('_', '', $fileName));
                    @file_put_contents($configFile, "@import 'config/" . $importFile . "';\n", FILE_APPEND);
                }
            }
        }

        return $this;
    }

    protected function scaffoldImports(OutputInterface $output, $directory)
    {
        $modulesDir = @glob($directory . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . '@ypa/pickup-truck-*');

        $sourceDirectory = $directory . DIRECTORY_SEPARATOR . $this->sourceRoot;
        $appFile = $sourceDirectory . DIRECTORY_SEPARATOR . 'scss' . DIRECTORY_SEPARATOR . 'app.scss';
        $string = @file_get_contents($appFile);
        $newImports = [];

        foreach ($modulesDir as $dir) {
            $importFile = str_replace($directory . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR, '~', $dir);

            preg_match('/(' . @json_encode($importFile) . ')/', $string, $includes, PREG_OFFSET_CAPTURE);

            if (!isset($includes[0])) {
                $newImports[] = $importFile;
                $output->writeln('<fg=yellow;options=bold>🤵 @import </> ' . $importFile . '; added');
            }
        }

        $stringArr = explode("\n" . '@import "config";' . "\n", $string);

        $newString = $stringArr[0];
        foreach ($newImports as $import) {
            $newString .= '@import "' . $import . '"' . ";\n";
        }
        $newString .= "\n";
        $newString .= '@import "config";' . "\n";
        if (isset($stringArr[1])) {
            $newString .= $stringArr[1];
        }

        @file_put_contents($appFile, $newString);

        return $this->scaffoldIncludes($output, $appFile, $newImports);
    }

    protected function scaffoldIncludes(OutputInterface $output, $appFile, $newImports)
    {
        $count = count($newImports);
        if ($count > 0) {


            $string = @file_get_contents($appFile);

            $stringArr = explode("\n\n" . '@import "ui";', $string);

            $newString = $stringArr[0];

            if (!strpos($string, 'Helper packages have an argument $responsive (boolean)')) {
                $newString .= "\n\n" . '// Helper packages have an argument $responsive (boolean)' . "\n";
                $newString .= '// Color based helper packages have an argument $hover (boolean)' . "\n";
                $newString .= '// Both are by default false' . "\n";
                $newString .= '// YOU MAY REMOVE THIS COMMENT WHEN YOU UNDERSTAND.' . "\n";
            }
            foreach ($newImports as $import) {
                $import = str_replace('~@ypa/pickup-truck-', '', $import . '()' . ";");

                if ($import !== 'core();') {
                    if (strpos($import, 'helpers') !== false) {
                        if (strpos($import, 'helpers-spacings') !== false) {
                            $padding = str_replace("();", "-padding();", $import);
                            $margin = str_replace("();", "-margin();", $import);
                            $newString .= "\n" . '//@include ' . $padding;
                            $newString .= "\n" . '//@include ' . $margin;
                        } else {
                            $newString .= "\n" . '//@include ' . $import;
                        }
                    } else {
                        if (strpos($import, 'core') !== false) {
                            $newString .= "\n" . '//@include ypa-' . str_replace('core-', '', $import);
                        } else {
                            $newString .= "\n" . '//@include ypa-' . $import;
                        }
                    }
                }
            }
            $newString .= "\n\n";
            $newString .= '@import "ui";';
            if (isset($stringArr[1])) {
                $newString .= $stringArr[1];
            }

            @file_put_contents($appFile, $newString);
        }
        $output->writeln('<fg=yellow;options=bold>🤵 ' . $count . ' packages added in app.scss.</>');
        $output->writeln('<fg=cyan;options=bold>🤵 All done Master Wayne.</>');

        return $this;
    }

}
