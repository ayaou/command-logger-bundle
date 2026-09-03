<?php

declare(strict_types=1);

/*
 * This file is part of the command logger bundle.
 *
 * (c) Mohamed AYAOU <github.com/ayaou>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ayaou\CommandLoggerBundle\DependencyInjection\Compiler;

use Ayaou\CommandLoggerBundle\Attribute\CommandLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects the names (and aliases) of every "console.command" tagged service whose class
 * carries #[CommandLogger], and exposes them through the "command_logger.attributed_commands"
 * container parameter.
 *
 * This pass exists because of how Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass
 * handles invokable-style commands: a standalone class that does not extend Command but exposes
 * an __invoke() method has its service definition's class rewritten to Command::class (see
 * AddConsoleCommandPass::registerCommand(), around line 91). Once that happens, the runtime
 * Command instance handed to the console.command/console.terminate/console.error events is a
 * plain Command wrapping a Closure - reflecting on that instance can never see the original
 * class or its #[CommandLogger] attribute.
 *
 * To see the real class, this pass runs at TYPE_BEFORE_OPTIMIZATION, before AddConsoleCommandPass
 * rewrites it and before parameter placeholders are frozen into service arguments: it reads
 * each tagged service Definition's class directly out of the container and reflects on the
 * class, never instantiating anything.
 *
 * The reflection-based fallback in AbstractCommandListener::hasCommandLoggerAttribute() is kept
 * intentionally: it still covers a Command subclass that sets its name in configure() instead of
 * via #[AsCommand] (this pass has no name to read for it), and a command that is registered
 * directly on an Application without ever going through the container.
 */
class CommandLoggerPass implements CompilerPassInterface
{
    public const ATTRIBUTED_COMMANDS_PARAMETER = 'command_logger.attributed_commands';

    public function process(ContainerBuilder $container): void
    {
        $attributedCommands = [];

        foreach ($container->findTaggedServiceIds('console.command') as $id => $tags) {
            $definition = $container->getDefinition($id);
            $class = $definition->getClass();

            if (null === $class) {
                continue;
            }

            $class = $container->getParameterBag()->resolveValue($class);

            $reflection = $container->getReflectionClass($class, false);
            if (!$reflection) {
                continue;
            }

            if (!$reflection->getAttributes(CommandLogger::class)) {
                continue;
            }

            foreach ($this->getCommandNames($reflection) as $name) {
                $attributedCommands[$name] = true;
            }
        }

        $container->setParameter(self::ATTRIBUTED_COMMANDS_PARAMETER, array_keys($attributedCommands));
    }

    /**
     * Extracts the command name and its aliases from #[AsCommand]. Note that AsCommand does not
     * expose its "aliases" constructor parameter as a public property: when aliases (or the
     * "hidden" flag) are given, it merges them into the "name" property itself, pipe-separated
     * (e.g. "app:cmd|app:alias"). Splitting on "|" therefore covers both the bare name and every
     * alias, however they were declared.
     *
     * @param \ReflectionClass<object> $reflection
     *
     * @return list<string>
     */
    private function getCommandNames(\ReflectionClass $reflection): array
    {
        $attribute = $reflection->getAttributes(AsCommand::class)[0] ?? null;

        if (!$attribute) {
            return [];
        }

        /** @var AsCommand $asCommand */
        $asCommand = $attribute->newInstance();

        $names = [];
        foreach (explode('|', $asCommand->name) as $name) {
            if ('' !== $name) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
