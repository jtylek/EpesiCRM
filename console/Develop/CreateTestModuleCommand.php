<?php

namespace Epesi\Console\Develop;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $module_name = 'Testing';
        $requires = $input->getOption('require');

        $module_type = 'Custom_'.$module_name;

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
        $code = "<?php\n"
              . "defined(\"_VALID_ACCESS\") || die('Direct access forbidden');\n\n"
              . "class {$module_type} extends Module {\n"
              . "\tpublic function body() {\n"
              . "\t}\n"
              . "}\n"
              . "?>\n";

        if (file_put_contents($file_main, $code) !== false) {
            $output->writeln("Created file: $file_main");
        }
        //endregion

        //region Common File
        $t = "\t";
        $file_common = $module_dir . '/' . $module_name . 'Common_0.php';
        $code = "<?php\n"
              . "defined(\"_VALID_ACCESS\") || die('Direct access forbidden');\n\n"
              . "class {$module_type}Common extends ModuleCommon {\n"
              . "\tpublic static function menu() {\n"
              . "\t\treturn ['_Testing' => []];\n"
              . "\t}\n"
              . "}\n"
              . "?>\n";

        if (file_put_contents($file_common, $code) !== false) {
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
        $code = "<?php\n"
              . "defined(\"_VALID_ACCESS\") || die('Direct access forbidden');\n\n"
              . "class {$module_type}Install extends ModuleInstall {\n"
              . "\tpublic function install() {\n"
              . "\t\treturn true;\n"
              . "\t}\n\n"
              . "\tpublic function uninstall() {\n"
              . "\t\treturn true;\n"
              . "\t}\n\n"
              . "\tpublic function requires(\$v) {\n"
              . "\t\treturn [\n{$required_modules_str}\n\t\t];\n"
              . "\t}\n\n"
              . "\tpublic function version() {\n"
              . "\t\treturn ['0.1'];\n"
              . "\t}\n\n"
              . "\tpublic static function simple_setup() {\n"
              . "\t\treturn ['package' => '_Test module'];\n"
              . "\t}\n"
              . "}\n"
              . "?>\n";

        if (file_put_contents($file_install, $code) !== false) {
            $output->writeln("Created file: $file_install");
        }
        //endregion

        return Command::SUCCESS;
    }
}
