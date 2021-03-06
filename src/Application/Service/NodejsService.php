<?php

namespace Ph3\DockerArch\Application\Service;

use Ph3\DockerArch\Domain\Service\Model\AbstractService;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class NodejsService extends AbstractService
{
    /**
     * {@inheritdoc}
     */
    public function getOptionsResolver(): Options
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'cli_only' => false,
            'dotfiles' => false,
            'zsh' => true,
            'custom_zsh' => false,
            'powerline' => false,
            'bower' => true,
            'gulp' => true,
            'npm_packages' => [],
            'supervisor' => false,
        ]);
        $resolver->setRequired(['version']);
        $resolver->setAllowedTypes('version', 'string');
        $resolver->setAllowedValues('version', ['0', '4', '6', '7', '8']);

        return $resolver;
    }

    /**
     * {@inheritdoc}
     */
    public function allowedLinksExpression(): ?string
    {
        return '(mysql|mariadb|redis)';
    }

    /**
     * {@inheritdoc}
     */
    public function isWebService(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isVhostService(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isCli(): bool
    {
        return true;
    }
}
