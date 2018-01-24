<?php

namespace Epesi\Console\Develop;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Memio\Memio\Config\Build;
use Memio\Model\File;
use Memio\Model\Object;
use Memio\Model\Method;
use Memio\Model\Argument;

class CreateTestModuleCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('dev:module:test')
            ->setDescription('Create EPESI Custom Test module files with menu and simple setup')
            ->addOption(
                'require', 'r',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Define required modules'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $module_name = 'Testing';
        $requires = $input->getOption('require');

        $module_type = 'Custom_'.$module_name;

        //region Add defined("_VALID_ACCESS") to file.twig if not found
        $current = file_get_contents(EPESI_LOCAL_DIR . '/vendor/memio/twig-template-engine/templates/file.twig');
        if (!preg_match('/defined\(\"\_VALID\_ACCESS\"\)/', $current)) {
            file_put_contents(
                EPESI_LOCAL_DIR . '/vendor/memio/twig-template-engine/templates/file.twig',
                str_replace(
                    '<?php',
                    '<?php' . PHP_EOL . 'defined("_VALID_ACCESS") || die(\'Direct access forbidden\');' . PHP_EOL,
                    $current
                )
            );
        }
        //endregion

        $prettyPrinter = Build::prettyPrinter();

        //region Create module dir
        $module_dir = EPESI_LOCAL_DIR . '/modules/Custom/' . $module_name;
        if (file_exists($module_dir)) {
            $msg = "File or directory: $module_dir already exists";
            $output->writeln($msg);
        } else {
            mkdir($module_dir, 0777, true);
            $output->writeln("Created module directory: $module_dir");
        }
        //endregion

        //region Main File
        $file_main = $module_dir . '/' . $module_name . '_0.php';
        $myFile = File::make($file_main)
                      ->setStructure(
                          Object::make($module_type)
                                ->extend(
                                    Object::make('Module'))
                                ->addMethod(
                                    Method::make('body')
                                )
                      );

        if (file_put_contents($file_main, $prettyPrinter->generateCode($myFile)) !== false) {
            $output->writeln("Created file: $file_main");
        }
        //endregion

        //region Common File
        $t = '    ';
        $file_common = $module_dir . '/' . $module_name . 'Common_0.php';
        $myFile = File::make($file_common)
                      ->setStructure(
                          Object::make($module_type . 'Common')
                                ->extend(
                                    Object::make('ModuleCommon')
                                )
                                ->addMethod(
                                    Method::make('menu')
                                        ->setBody("{$t}{$t}return ['_Testing' => []];"))
                      );

        if (file_put_contents($file_common, $prettyPrinter->generateCode($myFile)) !== false) {
            $output->writeln("Created file: $file_common");
        }
        //endregion

        //region Install File

        $closure = function ($m) use ($t) {
            $m = preg_replace('#^modules/#', '', $m);
            return "{$t}{$t}{$t}array('name' => '$m', 'version' => 0)";
        };
        $required_modules_str = implode(",\n", array_map($closure, $requires));
        $file_install = $module_dir . '/' . $module_name . 'Install.php';
        $myFile = File::make($file_install)
                      ->setStructure(
                          Object::make($module_type . 'Install')
                                ->extend(
                                    Object::make('ModuleInstall'))
                                ->addMethod(
                                    Method::make('install')
                                          ->setBody("{$t}{$t}return true;"))
                                ->addMethod(
                                    Method::make('uninstall')
                                          ->setBody("{$t}{$t}return true;"))
                                ->addMethod(
                                    Method::make('requires')
                                          ->addArgument(
                                              Argument::make('mixed', 'v'))
                                          ->setBody("{$t}{$t}return [\n$required_modules_str\n{$t}{$t}];"))
                                ->addMethod(
                                    Method::make('version')
                                          ->setBody("{$t}{$t}return ['0.1'];"))
                                ->addMethod(
                                    Method::make('simple_setup')
                                          ->setBody("{$t}{$t}return ['package' => '_Test module'];")
                                )
                      );

        if (file_put_contents($file_install, $prettyPrinter->generateCode($myFile)) !== false) {
            $output->writeln("Created file: $file_install");
        }
        //endregion

    }
}