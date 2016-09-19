<?php

namespace Epesi\Console\Modules;
use DB;
use ModuleManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        shell_exec('mkdir '.EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name);
        shell_exec('touch '.EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name.'/'.$module_name.'_0.php');
        $fp = fopen(EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name.'/'.$module_name.'_0.php','w');
        fwrite($fp,'<?php'.PHP_EOL);
        fwrite($fp,''.PHP_EOL);
        fwrite($fp,'defined("_VALID_ACCESS") || die(\'Direct access forbidden\');'.PHP_EOL);
        fwrite($fp,''.PHP_EOL);
        fwrite($fp,'class '.$module_type.'_'.$module_name.' extends Module'.PHP_EOL);
        fwrite($fp,'{'.PHP_EOL);
        fwrite($fp,'}'.PHP_EOL);
        shell_exec('touch '.EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name.'/'.$module_name.'Common_0.php');
        $fp = fopen(EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name.'/'.$module_name.'Common_0.php','w');
        fwrite($fp,'<?php'.PHP_EOL);
        fwrite($fp,''.PHP_EOL);
        fwrite($fp,'defined("_VALID_ACCESS") || die(\'Direct access forbidden\');'.PHP_EOL);
        fwrite($fp,''.PHP_EOL);
        fwrite($fp,'class '.$module_type.'_'.$module_name.'Common extends ModuleCommon'.PHP_EOL);
        fwrite($fp,'{'.PHP_EOL);
        fwrite($fp,'}'.PHP_EOL);
        shell_exec('touch '.EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name.'/'.$module_name.'Install.php');
        $fp = fopen(EPESI_LOCAL_DIR.'/modules/'.$module_type.'/'.$module_name.'/'.$module_name.'Install.php','w');
        fwrite($fp,'<?php'.PHP_EOL);
        fwrite($fp,''.PHP_EOL);
        fwrite($fp,'defined("_VALID_ACCESS") || die(\'Direct access forbidden\');'.PHP_EOL);
        fwrite($fp,''.PHP_EOL);
        fwrite($fp,'class '.$module_type.'_'.$module_name.'Install extends ModuleInstall'.PHP_EOL);
        fwrite($fp,'{'.PHP_EOL);
        fwrite($fp,'    public function install()'.PHP_EOL);
        fwrite($fp,'    {'.PHP_EOL);
        fwrite($fp,'        return true;'.PHP_EOL);
        fwrite($fp,'    }'.PHP_EOL);
        fwrite($fp,'    public function uninstall()'.PHP_EOL);
        fwrite($fp,'    {'.PHP_EOL);
        fwrite($fp,'        return true;'.PHP_EOL);
        fwrite($fp,'    }'.PHP_EOL);
        fwrite($fp,'    public function requires($v)'.PHP_EOL);
        fwrite($fp,'    {'.PHP_EOL);
        fwrite($fp,'        return array();'.PHP_EOL);
        fwrite($fp,'    }'.PHP_EOL);
        fwrite($fp,'}'.PHP_EOL);
        $output->writeln($module_type.'/'.$module_name.' module created');
    }
}