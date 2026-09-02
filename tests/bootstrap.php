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

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

/*
 * symfony/dotenv is not a dependency of this bundle, and a bundle has no .env: loading it
 * only makes sense when both happen to be there (a checkout used as an application).
 * Without these guards, every PHPUnit run dies with a fatal error.
 */
$envFile = dirname(__DIR__).'/.env';

if (class_exists(Dotenv::class) && is_file($envFile)) {
    (new Dotenv())->bootEnv($envFile);
}
