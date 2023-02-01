<?php

namespace Yabx\TypeScriptBundle\Command;

use Yabx\TypeScriptBundle\Attributes\Method;
use Yabx\TypeScriptBundle\Service\TypeScript;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Routing\Annotation\Route;
use Yabx\TypeScriptBundle\Service\ClassResolver;
use Yabx\TypeScriptBundle\Attributes\Controller;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand('ts:dump', description: 'Generate TypeScript')]
class DumpTypeScriptCommand extends Command {

    protected ParameterBagInterface $params;
    private string $projectDir;
    private string $cacheDir;

    public function __construct(KernelInterface $kernel, ParameterBagInterface $params) {
        $this->projectDir = $kernel->getProjectDir();
        $this->cacheDir = $kernel->getCacheDir();
        $this->params = $params;
        parent::__construct();
    }

    protected function configure(): void {
        $this->addArgument('path', InputArgument::OPTIONAL, 'Output path', $this->projectDir . '/public/rest.ts');
    }

    public function run(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        $resolver = new ClassResolver;
        $ts = TypeScript::factory();

        $typesClass = $this->params->get('yabx.ts.types_class');

        if(class_exists($typesClass) && method_exists($typesClass, 'registerTypes')) {
            call_user_func([$typesClass, 'registerTypes'], $ts);
        } else {
            $io->error('There is no types_class configured');
            return Command::FAILURE;
        }

        $ts->registerGroups();

        $out = file_get_contents(__DIR__ . '/../../assets/rest-template.ts');
        $out .= $ts->getTypeScriptCode();
        $out .= "\n\n";

        $classes = $resolver->getReflections($this->params->get('yabx.ts.controllers_dir'));

        $api = '';

        foreach($classes as $rc) {

            if(!$resolver->getAttribute($rc, Controller::class)) continue;

            $classRoute = $resolver->getAttribute($rc, Route::class);

            $alias = str_replace('App\Controller\\', '', $rc->getName());

            $out .= "class {$alias} {\n\tprivate api: RestAPI;\n\tconstructor(api: RestAPI) {\n\t\tthis.api = api;\n\t}\n";

            $api .= "\n\t/** Get {$alias} API */\n\tget {$alias}(): {$alias} {\n\treturn (this.instances['{$alias}'] as {$alias}) ?? (this.instances['{$alias}'] = new {$alias}(this));\n\t}\n";

            foreach($rc->getMethods() as $rm) {

                $route = $resolver->getAttribute($rm, Route::class);
                /** @var Method $info */
                $info = $resolver->getAttribute($rm, Method::class);

                if(!$info || !$route) continue;

                $args = [];
                $request = null;

                preg_match_all('/\{([A-z0-9]+)\}/isU', $route->getPath(), $m, PREG_PATTERN_ORDER);
                foreach($m[1] as $a) {
                    $args[] = "{$a}: TIdentifier";
                }

                if($info->getRequest()) {
                    if(class_exists($info->getRequest())) {
                        $request = true;
                        $args[] = 'request: ' . $ts->getSlug($info->getRequest());
                    }
                }

                $args[] = 'fields?: EFieldGroup[]';

                $out .= "\n\t/** " . ($info->getTitle() ?: $rm->getName()) . " */\n\t" . $rm->getName() . " = (" . implode(', ', $args) . "): Promise<" . ($info->getResponse() ? $ts->getSlug($info->getResponse()) : 'unknown') . "> => ";
                $path = str_replace('{', '${', ($classRoute ?  $classRoute->getPath() : '') .  $route->getPath());
                $out .= "this.api." . strtolower($route->getMethods() ? $route->getMethods()[0] : 'post') . "(`" . $path . "`" . ($request ? ", request" : ', {}') . (', fields') . ");\n";

            }

            $out .= "}\n\n";

        }

        $out = str_replace('//INCLUDE', $api, $out);

        if(class_exists($typesClass) && method_exists($typesClass, 'codePostProcessor')) {
            $out = call_user_func([$typesClass, 'codePostProcessor'], $out);
        }

        $tmp = $this->cacheDir . '/rest.ts';
        file_put_contents($tmp, $out);

        exec('prettier -w ' . $tmp);

        rename($tmp, $input->getArgument('path'));


        $io->success($this->projectDir);
        return Command::SUCCESS;
    }

}
