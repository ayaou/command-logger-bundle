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

namespace Ayaou\CommandLoggerBundle;

use Ayaou\CommandLoggerBundle\DependencyInjection\Compiler\CommandLoggerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class CommandLoggerBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // The stage matters, and three constraints pin it down.
        //
        // It must run AFTER autoconfiguration, which is what puts the "console.command" tag on
        // the services this pass looks for. Autoconfiguration happens in TYPE_BEFORE_OPTIMIZATION
        // passes registered with a high priority, so the default priority (0) used here is late
        // enough within that same stage.
        //
        // It must run BEFORE Symfony's AddConsoleCommandPass (registered by ConsoleBundle at
        // TYPE_BEFORE_REMOVING), which rewrites the class of any invokable-style command service
        // - one that does not extend Command but exposes __invoke() - to Command::class. Once
        // that happens the original class, and its #[CommandLogger] attribute, are gone for good.
        //
        // It must also run BEFORE ResolveParameterPlaceHoldersPass, which runs at TYPE_OPTIMIZE
        // and freezes "%command_logger.attributed_commands%" into the listeners' arguments. A
        // pass running later still sets the parameter correctly - "debug:container --parameter"
        // shows the right value - but the listeners were already handed the empty array the
        // parameter held at that point. That failure is invisible to a unit test of the pass and
        // only shows up when the bundle runs inside a real application.
        //
        // TYPE_BEFORE_OPTIMIZATION is the only stage satisfying all three.
        $container->addCompilerPass(new CommandLoggerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }
}
