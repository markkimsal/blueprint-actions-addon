<?php

namespace Markkimsal\BlueprintActionsAddon;

use Blueprint\Blueprint;
use Blueprint\Contracts\Generator;
use Blueprint\Generators\ControllerGenerator;
use Blueprint\Models\Controller;
use Blueprint\Models\Statements\DispatchStatement;
use Blueprint\Models\Statements\EloquentStatement;
use Blueprint\Models\Statements\FireStatement;
use Blueprint\Models\Statements\QueryStatement;
use Blueprint\Models\Statements\RedirectStatement;
use Blueprint\Models\Statements\RenderStatement;
use Blueprint\Models\Statements\ResourceStatement;
use Blueprint\Models\Statements\RespondStatement;
use Blueprint\Models\Statements\SendStatement;
use Blueprint\Models\Statements\SessionStatement;
use Blueprint\Models\Statements\ValidateStatement;
use Blueprint\Tree;
use  Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class LaravelActionsGenerator
extends ControllerGenerator
implements Generator
{
    /** @var \Illuminate\Contracts\Filesystem\Filesystem */
    protected $filesystem;

    /** @var array */
    protected $imports = [];

    /** @var array */
    protected $tasks = [];

    public function __construct(Filesystem $files)
    {
        $this->filesystem = $files;
    }

    public function output(Tree $tree): array
    {
        $output = [];

        $stub = $this->filesystem->stub('action.class.stub');

        /** @var \Blueprint\Models\Controller $controller */
        foreach ($tree->controllers() as $controller) {
            $this->addImport($controller, 'Illuminate\\Http\\Request');
            $this->addImport($controller, 'Lorisleiva\\Actions\\Concerns\\AsAction');

            foreach ($controller->methods() as $actionMethod => $statementArray) {

                // $path = $this->getPath($controller);
                $path  = 'app/Domains/' . $controller->name() . '/Actions/' . $actionMethod . '.php';

                if (!$this->filesystem->exists(dirname($path))) {
                    $this->filesystem->makeDirectory(dirname($path), 0755, true);
                }

                $this->filesystem->put($path, $this->populateActionStub($stub, $controller, $actionMethod, $statementArray));

                $output['created'][] = $path;

            }
        }

        return $output;
    }

    protected function populateActionStub(string $stub, Controller $controller, string $actionName, array $statementList)
    {
        $fqns = "App\Domains". '\\' . $controller->name() . '\\' . 'Actions';
        $stub = str_replace('{{ namespace }}', $fqns, $stub);
        $stub = str_replace('{{ class }}', $actionName, $stub);
        $stub = str_replace('{{ body }}', $this->buildMethod($controller, $actionName, $statementList), $stub);
        $stub = str_replace('{{ imports }}', $this->buildImports($controller), $stub);

        return $stub;
    }

    protected function getPath(Controller $controller)
    {
        $path = str_replace('\\', '/', Blueprint::relativeNamespace($controller->fullyQualifiedClassName()));

        return Blueprint::appPath() . '/' . $path . '.php';
    }


    public function types(): array
    {
        return ['actions'];
    }

    protected function buildMethod(Controller $controller, string $name, array $statementList)
    {

        $methods = '';
        $method = '';

        // if (in_array($method, ['edit', 'update', 'show', 'destroy'])) {
        //     $context = Str::singular($controller->prefix());
        //     $reference = $this->fullyQualifyModelReference($controller->namespace(), Str::camel($context));
        //     $variable = '$' . Str::camel($context);

        //     // TODO: verify controller prefix references a model
        //     $search = '     * @return \\Illuminate\\Http\\Response';
        //     $method = str_replace($search, '     * @param \\' . $reference . ' ' . $variable . PHP_EOL . $search, $method);

        //     $search = '(Request $request';
        //     $method = str_replace($search, $search . ', ' . $context . ' ' . $variable, $method);
        //     $this->addImport($controller, $reference);
        // }

        $body = '';
        $using_validation = false;

        foreach ($statementList as $statement) {
            if ($statement instanceof SendStatement) {
                $body .= self::INDENT . $statement->output() . PHP_EOL;
                if ($statement->type() === SendStatement::TYPE_NOTIFICATION_WITH_FACADE) {
                    $this->addImport($controller, 'Illuminate\\Support\\Facades\\Notification');
                    $this->addImport($controller, config('blueprint.namespace') . '\\Notification\\' . $statement->mail());
                } elseif ($statement->type() === SendStatement::TYPE_MAIL) {
                    $this->addImport($controller, 'Illuminate\\Support\\Facades\\Mail');
                    $this->addImport($controller, config('blueprint.namespace') . '\\Mail\\' . $statement->mail());
                }
            } elseif ($statement instanceof ValidateStatement) {
                $using_validation = true;
                $class_name = $controller->name() . Str::studly($name) . 'Request';

                $fqcn = config('blueprint.namespace') . '\\Http\\Requests\\' . ($controller->namespace() ? $controller->namespace() . '\\' : '') . $class_name;

                $method = str_replace('\Illuminate\Http\Request $request', '\\' . $fqcn . ' $request', $method);
                $method = str_replace('(Request $request', '(' . $class_name . ' $request', $method);

                $this->addImport($controller, $fqcn);
            } elseif ($statement instanceof DispatchStatement) {
                $body .= self::INDENT . $statement->output() . PHP_EOL;
                $this->addImport($controller, config('blueprint.namespace') . '\\Jobs\\' . $statement->job());
            } elseif ($statement instanceof FireStatement) {
                $body .= self::INDENT . $statement->output() . PHP_EOL;
                if (!$statement->isNamedEvent()) {
                    $this->addImport($controller, config('blueprint.namespace') . '\\Events\\' . $statement->event());
                }
            } elseif ($statement instanceof RenderStatement) {
                $body .= self::INDENT . $statement->output() . PHP_EOL;
            } elseif ($statement instanceof ResourceStatement) {
                $fqcn = config('blueprint.namespace') . '\\Http\\Resources\\' . ($controller->namespace() ? $controller->namespace() . '\\' : '') . $statement->name();
                $method = str_replace('* @return \\Illuminate\\Http\\Response', '* @return \\' . $fqcn, $method);
                $this->addImport($controller, $fqcn);
                $body .= self::INDENT . $statement->output() . PHP_EOL;

                if ($statement->paginate()) {
                    if (! Str::contains($body, '::all();')) {
                        $queryStatement = new QueryStatement('all', [$statement->reference()]);
                        $body = implode(PHP_EOL, [
                            self::INDENT . $queryStatement->output($statement->reference()),
                            PHP_EOL . $body
                        ]);

                        $this->addImport($controller, $this->determineModel($controller, $queryStatement->model()));
                    }

                    $body = str_replace('::all();', '::paginate();', $body);
                }
            } elseif ($statement instanceof RedirectStatement) {
                $body .= self::INDENT . $statement->output() . PHP_EOL;
            } elseif ($statement instanceof RespondStatement) {
                $body .= self::INDENT . $statement->output() . PHP_EOL;
            } elseif ($statement instanceof SessionStatement) {
                $body .= self::INDENT . $statement->output() . PHP_EOL;
            } elseif ($statement instanceof EloquentStatement) {
                $body .= self::INDENT . $statement->output($controller->prefix(), $name, $using_validation) . PHP_EOL;
                $this->addImport($controller, $this->determineModel($controller, $statement->reference()));
            } elseif ($statement instanceof QueryStatement) {
                $body .= self::INDENT . $statement->output($controller->prefix()) . PHP_EOL;
                $this->addImport($controller, $this->determineModel($controller, $statement->model()));
            }

            $body .= PHP_EOL;
        }

        if (!empty($body)) {
            $method = trim($body);
        }

        // if (Blueprint::useReturnTypeHints()) {
        //     if (isset($fqcn) && $name !== 'destroy' && $controller->isApiResource()) {
        //         $method = str_replace(')' . PHP_EOL, '): \\' . $fqcn . PHP_EOL, $method);
        //     } else {
        //         $method = str_replace(')' . PHP_EOL, '): \Illuminate\Http\Response' . PHP_EOL, $method);
        //     }
        // }

        $methods .= PHP_EOL . $method;

        return trim($methods);
    }

}
