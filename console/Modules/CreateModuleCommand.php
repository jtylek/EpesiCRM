<?php

namespace Epesi\Console\Modules;
use DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Memio\Memio\Config\Build;
use Memio\Model\File;
use Memio\Model\Object;
use Memio\Model\Method;
use Memio\Model\Argument;

class CreateModuleCommand extends Command
{
    protected function configure(){
        $this
            ->setName('module:create')
            ->setDescription('Install EPESI module')
            ->addArgument(
                'type',
                InputArgument::REQUIRED,
                'Module type'
            )
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Module name'
            )
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output) {
        $module_type = $input->getArgument('type');
        $module_name = $input->getArgument('name');

        //region Add defined("_VALID_ACCESS") to file.twig if not found
        $current = file_get_contents(EPESI_LOCAL_DIR . '/vendor/memio/twig-template-engine/templates/file.twig');
        if(!preg_match('/defined\(\"\_VALID\_ACCESS\"\)/', $current)) {
            file_put_contents(
                EPESI_LOCAL_DIR . '/vendor/memio/twig-template-engine/templates/file.twig',
                str_replace(
                    '<?php',
                    '<?php'.PHP_EOL.'defined("_VALID_ACCESS") || die(\'Direct access forbidden\');'.PHP_EOL,
                    $current
                )
            );
        }
        //endregion

        shell_exec('mkdir '.EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name);

        //region Main File
        $myFile = File::make(EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name.'/'.$module_name.'_0.php')
            ->setStructure(
                Object::make($module_type.'_'.$module_name)
                    ->extend(
                        Object::make('Module'))
                    ->addMethod(
                        Method::make('body')
                    )
            );

        $prettyPrinter = Build::prettyPrinter();
        
        file_put_contents(
            EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name.'/'.$module_name.'_0.php',
            $prettyPrinter->generateCode($myFile)
        );
        //endregion

        //region Common File
        $myFile = File::make(EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name.'/'.$module_name.'Common_0.php')
            ->setStructure(
                Object::make($module_type.'_'.$module_name.'Common')
                    ->extend(
                        Object::make('ModuleCommon')
                    )
            );

        $prettyPrinter = Build::prettyPrinter();

        file_put_contents(
            EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name.'/'.$module_name.'Common_0.php',
            $prettyPrinter->generateCode($myFile)
        );
        //endregion

        //region Install File
        $myFile = File::make(EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name.'/'.$module_name.'Install_0.php')
            ->setStructure(
                Object::make($module_type.'_'.$module_name.'Install')
                    ->extend(
                        Object::make('ModuleInstall'))
                    ->addMethod(
                        Method::make('install'))
                    ->addMethod(
                        Method::make('uninstall'))
                    ->addMethod(
                        Method::make('requires')
                        ->addArgument(
                            Argument::make('mixed', 'v')))
            );

        $prettyPrinter = Build::prettyPrinter();

        file_put_contents(
            EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name.'/'.$module_name.'Install_0.php',
            $prettyPrinter->generateCode($myFile)
        );
        //endregion

        $output->writeln(EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name.' module created');
    }
}