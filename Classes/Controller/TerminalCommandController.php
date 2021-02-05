<?php
declare(strict_types=1);

namespace Shel\Neos\Terminal\Controller;

/**
 * This file is part of the Shel.Neos.Terminal package.
 *
 * (c) 2021 Sebastian Helzle
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\View\JsonView;
use Neos\Flow\Reflection\ReflectionService;
use Shel\Neos\Terminal\Command\CommandInvocationResult;
use Shel\Neos\Terminal\Command\TerminalCommandControllerPluginInterface;
use Shel\Neos\Terminal\Exception as TerminalException;

/**
 * @Flow\Scope("singleton")
 */
class TerminalCommandController extends ActionController
{

    /**
     * @var array
     */
    protected $viewFormatToObjectNameMap = [
        'json' => JsonView::class,
    ];

    /**
     * @Flow\InjectConfiguration(path="frontendConfiguration", package="Neos.Neos.Ui")
     * @var array
     */
    protected array $frontendConfiguration;

    public function getCommandsAction(): void
    {
        $commands = $this->detectCommands();

        $commandDefinitions = array_reduce($commands, static function ($carry, TerminalCommandControllerPluginInterface $command) {
            // TODO: Only return if command privilege matches
            $carry[$command::getCommandName()] = [
                'name' => $command::getCommandName(),
                'description' => $command::getCommandDescription(),
                'usage' => $command::getCommandUsage(),
            ];
            return $carry;
        }, []);

        $this->view->assign('value', ['success' => true, 'result' => $commandDefinitions]);
    }

    /**
     * Detects plugins for this command controller
     *
     * @return array<TerminalCommandControllerPluginInterface>
     */
    protected function detectCommands(): array
    {
        $commandConfiguration = [];
        $classNames = $this->objectManager->get(ReflectionService::class)->getAllImplementationClassNamesForInterface(TerminalCommandControllerPluginInterface::class);
        foreach ($classNames as $className) {
            $commandConfiguration[$className] = $this->objectManager->get($this->objectManager->getObjectNameByClassName($className));
        }
        return $commandConfiguration;
    }

    public function invokeCommandAction(
        string $commandName,
        string $argument = null,
        NodeInterface $siteNode = null,
        NodeInterface $documentNode = null,
        NodeInterface $focusedNode = null
    ): void
    {
        $commands = $this->detectCommands();
        $result = null;

        $this->response->setContentType('application/json');

        foreach ($commands as $command) {
            // TODO: Only invoke if command privilege matches
            if ($command::getCommandName() === $commandName) {
                $result = $command->invokeCommand($argument, $siteNode, $documentNode, $focusedNode);
                break;
            }
        }

        if (!$result) {
            // TODO: Translate message
            $result = new CommandInvocationResult(false, 'Command "' . $commandName . '" not found');
        }

        $this->view->assign('value', $result);
    }

    /**
     * Thorws exception when terminal is disabled or the called command doesn't exist
     *
     * @throws TerminalException
     */
    protected function initializeAction()
    {
        $terminalConfiguration = $this->frontendConfiguration['Shel.Neos.Terminal:Terminal'];

        $terminalEnabled = $terminalConfiguration['enabled'] ?? false;
        if (!$terminalEnabled) {
            throw new TerminalException('Terminal commands are disabled');
        }

        parent::initializeAction();
    }
}