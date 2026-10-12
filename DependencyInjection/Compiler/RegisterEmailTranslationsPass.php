<?php

declare(strict_types=1);

namespace Payzen\DependencyInjection\Compiler;

use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Hands the e-mail catalogs of the module (I18n/email/<template>/<locale>.php) to the framework translator,
 * the one behind the Twig |trans filter.
 *
 * Thelia registers these files with its own translator only, which a Twig template reaches on a back-office
 * request alone: anywhere else the e-mail would be rendered with its source strings. The domain names are the
 * ones the Smarty templates used (payzen.email.<template>).
 */
final readonly class RegisterEmailTranslationsPass implements CompilerPassInterface
{
    public function __construct(
        private string $moduleDirectory,
        private string $domain,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('translator.default')) {
            return;
        }

        $emailDirectory = $this->moduleDirectory.'/I18n/email';

        if (!is_dir($emailDirectory)) {
            return;
        }

        $container->addResource(new GlobResource($emailDirectory, '/**/*.php', true));

        $translator = $container->getDefinition('translator.default');

        foreach (glob($emailDirectory.'/*/*.php') ?: [] as $file) {
            $translator->addMethodCall('addResource', [
                'php',
                $file,
                basename($file, '.php'),
                $this->domain.'.email.'.basename(\dirname($file)),
            ]);
        }
    }
}
