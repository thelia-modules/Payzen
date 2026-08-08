<?php

namespace Payzen\EventListener;

use Payzen\Model\PayzenConfigQuery;
use Payzen\Payzen;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class ConfigListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'module.config' => [
                'onModuleConfig', 128
            ],
        ];
    }

    public function onModuleConfig(GenericEvent $event): void
    {
        $subject = $event->getSubject();

        if ($subject !== "HealthStatus") {
            throw new \RuntimeException('Event subject does not match expected value');
        }

        $configModule = PayzenConfigQuery::create()
            ->filterByName(['site_id', 'test_certificate', 'production_certificate', 'platform_url', 'mode', 'default_language', 'banking_delay', 'redirect_enabled', 'success_timeout', 'failure_timeout', 'minimum_amount', 'maximum_amount', 'three_ds_minimum_order_amount'])
            ->find();

        $moduleConfig = [];

        $moduleConfig['module'] = Payzen::getModuleCode();
        $configsCompleted = true;

        if ($configModule->count() === 0) {
            $configsCompleted = false;
        }

        foreach ($configModule as $config) {
            $moduleConfig[$config->getName()] = $config->getValue();
            if ($config->getValue() === null) {
                $configsCompleted = false;
            }
        }

        $moduleConfig['completed'] = $configsCompleted;

        $event->setArgument('payzen.module.config', $moduleConfig);

    }
}

